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

/**
 * @tags [API-BUSINESS] Court Types
 */
class CourtTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;
            $courtTypes = CourtType::forTenant($tenant->id)->get();

            return CourtTypeResourceGeneral::collection($courtTypes);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipos de quadras', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar tipos de quadras'], 500);
        }
    }

    public function show(Request $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::with('courts')->forTenant($tenant->id)->findOrFail($courtTypeId);

            return CourtTypeResourceGeneral::make($courtType);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipo de quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar tipo de quadra'], 500);
        }
    }

    public function update(UpdateCourtTypeRequest $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Tipo de quadra não encontrado'], 404);
            }

            $courtType->update($request->validated());

            $this->commitSafe();

            return CourtTypeResourceGeneral::make($courtType);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao actualizar o tipo de Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao actualizar o tipo de Quadra'], 400);
        }
    }

    public function create(CreateCourtTypeRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtType = new CourtType();

            $courtType->fill($request->validated());
            $courtType->tenant_id = $tenant->id;
            $courtType->save();

            // Create availabilities for this court type (template for courts of this type)
            if ($request->has('availabilities')) {
                foreach ($request->availabilities as $availabilityData) {
                    $courtType->availabilities()->create([
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

            return CourtTypeResourceGeneral::make($courtType);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao criar o tipo de quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao criar o tipo de quadra'], 400);
        }
    }


    public function destroy(Request $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->where('id', $courtTypeId)->first();

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Tipo de quadra não encontrado'], 404);
            }

            //check if the court type has any courts associated
            if ($courtType->courts()->exists()) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Tipo de quadra não pode ser deletado porque tem quadras associadas, apague as quadras primeiro'], 400);
            }

            $courtType->delete();

            $this->commitSafe();

            return response()->json(['message' => 'Tipo de quadra deletado com sucesso']);

        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Houve um erro ao deletar o tipo de Quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Houve um erro ao deletar o tipo de Quadra'], 400);
        }
    }
    public function types()
    {
        return response()->json(['data' => \App\Enums\CourtTypeEnum::values()]);
    }
}
