<?php
namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtRequest;
use App\Http\Requests\Api\V1\Business\UpdateCourtRequest;
use App\Http\Resources\Shared\V1\General\CourtResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-BUSINESS] Courts
 */
class CourtController extends Controller
{
    /**
     * Get a list of all courts for the authenticated tenant
     * 
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int optional Items per page. Example: 15
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;
            $perPage = $request->input('per_page', 15);
            $courts = Court::forTenant($tenant->id)
                ->with(['images'])
                ->paginate($perPage);

            return CourtResourceGeneral::collection($courts);
        } catch (\Exception $e) {
            Log::error('Error fetching courts', ['error' => $e->getMessage()]);
            abort(500, __('There was an error fetching the courts.'));
        }
    }

    /**
     * Get a specific court by ID
     * 
     * @return CourtResourceGeneral
     */
    public function show(Request $request, string $tenantId, $courtId): CourtResourceGeneral
    {
        try {
            $tenant  = $request->tenant;
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court   = Court::forTenant($tenant->id)
                ->with(['images'])
                ->findOrFail($courtId);

            return new CourtResourceGeneral($court);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error fetching court', ['error' => $e->getMessage()]);
            abort(500, __('There was an error fetching the court.'));
        }
    }

    /**
     * Create a new court
     * 
     * Creates a new court with optional images and availabilities.
     */
    public function create(CreateCourtRequest $request, string $tenantId): CourtResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

            // Check subscription plan limits
            // Check subscription plan limits via valid invoice (injected by middleware)
            $validInvoice = $request->valid_invoice;

            // Fallback to subscription plan if no invoice (e.g. for free tier or if middleware didn't block - though it should have)
            // But user requested to use invoice.
            $maxCourts = $validInvoice ? $validInvoice->max_courts : ($tenant->subscriptionPlan?->courts ?? 0);

            $currentCourtsCount = $tenant->courts()->count();
            if ($currentCourtsCount >= $maxCourts) {
                $this->rollBackSafe();
                abort(403, __('Court limit reached for your subscription plan.'));
            }

            $court = new Court();

            $court->fill($request->validated());
            $court->tenant_id = $tenant->id;
            $court->save();

            // Handle Images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $fileInfo = TenantFileAction::save(
                        tenantId: $tenant->id,
                        file: $image,
                        isPublic: true,
                        path: 'courts'
                    );

                    $court->images()->create([
                        'path'       => $fileInfo->url, // Storing the URL/route path
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            // Handle Availabilities
            if ($request->has('availabilities')) {
                foreach ($request->availabilities as $availabilityData) {
                    $court->availabilities()->create([
                        'tenant_id'             => $tenant->id,
                        'day_of_week_recurring' => $availabilityData['day_of_week_recurring'] ?? null,
                        'specific_date'         => $availabilityData['specific_date'] ?? null,
                        'start_time'            => $availabilityData['start_time'],
                        'end_time'              => $availabilityData['end_time'],
                        'is_available'          => $availabilityData['is_available'] ?? true,
                    ]);
                }
            }

            $this->commitSafe();

            $court->load(['images']);

            return new CourtResourceGeneral($court);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->rollBackSafe();
            throw $e;
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error creating court', ['error' => $e->getMessage()]);
            abort(400, __('There was an error creating the court: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Update an existing court
     * 
     * @return CourtResourceGeneral
     */
    public function update(UpdateCourtRequest $request, $tenantId, $courtId): CourtResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $tenant  = $request->tenant;
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court   = Court::forTenant($tenant->id)->find($courtId);

            if (! $court) {
                $this->rollBackSafe();
                abort(404, __('Court not found.'));
            }

            $validated = $request->validated();
            
            $court->update($validated);

            $this->commitSafe();

            $court->load(['images']);

            return new CourtResourceGeneral($court);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->rollBackSafe();
            throw $e;
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error updating court', ['error' => $e->getMessage()]);
            abort(400, __('There was an error updating the court.'));
        }
    }

    /**
     * Delete a court
     * 
     * Deletes a court if it has no associated bookings.
     */
    public function destroy(Request $request, string $tenantId, $courtId): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $tenant  = $request->tenant;
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court   = Court::forTenant($tenant->id)->where('id', $courtId)->first();

            if (! $court) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court not found.')], 404);
            }

            //check if the court has any bookings associated
            if ($court->bookings()->exists()) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court cannot be deleted because it has associated bookings, please delete the bookings first.')], 400);
            }

            $court->delete();

            $this->commitSafe();

            return response()->json(['message' => __('Court deleted successfully.')]);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error deleting court', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error deleting the court.')], 400);
        }
    }
}
