<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\UpdateCourtTypeRequest;
use App\Http\Requests\Api\V1\Business\CreateCourtTypeRequest;
use App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Enums\CourtTypeEnum;

/**
 * @tags [API-BUSINESS] Court Types
 */
class CourtTypeController extends Controller
{
    /**
     * Get a list of all court types for the authenticated tenant
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
            $courtTypes = CourtType::with('availabilities')->forTenant($tenant->id)->paginate($perPage);

            return CourtTypeResourceGeneral::collection($courtTypes);
        } catch (\Exception $e) {
            Log::error('Error fetching court types', ['error' => $e->getMessage()]);
            abort(500, __('There was an error fetching the court types.'));
        }
    }

    /**
     * Get a specific court type by ID
     * 
     * @return CourtTypeResourceGeneral
     */
    public function show(Request $request, string $tenantId, $courtTypeId): CourtTypeResourceGeneral
    {
        try {
            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::with([
                'courts' => function ($query) {
                    $query->with('images');
                },
                'availabilities'
            ])->forTenant($tenant->id)->findOrFail($courtTypeId);

            return new CourtTypeResourceGeneral($courtType);

        } catch (\Exception $e) {
            Log::error('Error fetching court type', ['error' => $e->getMessage()]);
            abort(500, __('There was an error fetching the court type.'));
        }
    }

    /**
     * Update an existing court type
     * 
     * @return CourtTypeResourceGeneral
     */
    public function update(UpdateCourtTypeRequest $request, $tenantId, $courtTypeId): CourtTypeResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type not found.')], 404);
            }

            $courtType->update($request->validated());

            $this->commitSafe();

            $courtType->load(['availabilities', 'courts' => function ($query) {
                $query->with('images');
            }]);

            return new CourtTypeResourceGeneral($courtType);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error updating court type', ['error' => $e->getMessage()]);
            abort(400, __('There was an error updating the court type.'));
        }
    }

    /**
     * Create a new court type
     * 
     * Creates a new court type definition.
     */
    public function create(CreateCourtTypeRequest $request, $tenantId): CourtTypeResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtType = new CourtType();

            $courtType->fill($request->validated());
            $courtType->tenant_id = $tenant->id;
            $courtType->save();

            $this->commitSafe();

            $courtType->load(['availabilities', 'courts' => function ($query) {
                $query->with('images');
            }]);

            return new CourtTypeResourceGeneral($courtType);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error creating court type', ['error' => $e->getMessage()]);
            abort(400, __('There was an error creating the court type.'));
        }
    }

    /**
     * Delete a court type
     * 
     * Deletes a court type if it has no associated courts.
     */
    public function destroy(Request $request, string $tenantId, $courtTypeId): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->where('id', $courtTypeId)->first();

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type not found.')], 404);
            }

            //check if the court type has any courts associated
            if ($courtType->courts()->exists()) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type cannot be deleted because it has associated courts, please delete the courts first.')], 400);
            }

            $courtType->delete();

            $this->commitSafe();

            return response()->json(['message' => __('Court type deleted successfully.')]);

        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Error deleting court type', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error deleting the court type.')], 400);
        }
    }
    /**
     * Get available court type options with translated labels
     * 
     * @response array{data: array<int, array{value: string, label: string}>}
     */
    public function types(): JsonResponse
    {
        return response()->json(['data' => CourtTypeEnum::options()]);
    }
}
