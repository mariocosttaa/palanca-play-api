<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtRequest;
use App\Http\Requests\Api\V1\Business\UpdateCourtRequest;
use App\Http\Resources\General\CourtResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Courts
 */
class CourtController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;
            $courts = Court::forTenant($tenant->id)->get();

            return CourtResourceGeneral::collection($courts);

        } catch (\Exception $e) {
            \Log::error('Houve um erro ao buscar as Quadras', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao buscar as Quadras'], 500);
        }
    }

    public function show(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $tenant = $request->tenant;
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            $court = Court::forTenant($tenant->id)->findOrFail($courtId);

            return CourtResourceGeneral::make($court);
        }
        catch (\Exception $e) {
            \Log::error('Houve um erro ao buscar a Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao buscar a Quadra'], 500);
        }
    }
    public function create(CreateCourtRequest $request, string $tenantIdHashId)
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
                return response()->json(['message' => 'Limite de quadras atingido para o seu plano de subscrição.'], 403);
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
                        'path' => $fileInfo->url, // Storing the URL/route path
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            // Handle Availabilities
            if ($request->has('availabilities')) {
                foreach ($request->availabilities as $availabilityData) {
                    $court->availabilities()->create([
                        'tenant_id' => $tenant->id,
                        'day_of_week_recurring' => $availabilityData['day_of_week_recurring'] ?? null,
                        'specific_date' => $availabilityData['specific_date'] ?? null,
                        'start_time' => $availabilityData['start_time'],
                        'end_time' => $availabilityData['end_time'],
                        'is_available' => $availabilityData['is_available'] ?? true,
                    ]);
                }
            }

            $this->commitSafe();

            return CourtResourceGeneral::make($court);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao criar a Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao criar a Quadra: ' . $e->getMessage()], 400);
        }
    }

    public function update(UpdateCourtRequest $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            $court = Court::forTenant($tenant->id)->find($courtId);

            if (!$court) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Quadra não encontrada'], 404);
            }

            $court->update($request->validated());

            $this->commitSafe();

            return CourtResourceGeneral::make($court);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao actualizar a Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao actualizar a Quadra'], 400);
        }
    }

    public function destroy(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            $court = Court::forTenant($tenant->id)->where('id', $courtId)->first();

            if (!$court) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Quadra não encontrada'], 404);
            }

            //check if the court has any bookings associated
            if ($court->bookings()->exists()) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Quadra não pode ser deletada porque tem reservas associadas, apague as reservas primeiro'], 400);
            }

            $court->delete();

            $this->commitSafe();

            return response()->json(['message' => 'Quadra deletada com sucesso']);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao deletar a Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao deletar a Quadra'], 400);
        }
    }
}

