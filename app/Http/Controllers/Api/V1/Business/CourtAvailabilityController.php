<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtAvailabilityRequest;
use App\Http\Requests\Api\V1\Business\UpdateCourtAvailabilityRequest;
use App\Http\Resources\General\CourtAvailabilityResourceGeneral;
use App\Models\CourtAvailability;
use Illuminate\Http\Request;

class CourtAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;
            $availabilities = CourtAvailability::forTenant($tenant->id)->get();

            return $this->dataResponse(
                CourtAvailabilityResourceGeneral::collection($availabilities)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar disponibilidades', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $tenantIdHashId, string $availabilityIdHashId)
    {
        try {
            $tenant = $request->tenant;
            $availabilityId = EasyHashAction::decode($availabilityIdHashId, 'court-availability-id');
            $availability = CourtAvailability::forTenant($tenant->id)->findOrFail($availabilityId);

            return $this->dataResponse(CourtAvailabilityResourceGeneral::make($availability)->resolve());

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar disponibilidade', $e->getMessage(), 500);
        }
    }

    public function create(CreateCourtAvailabilityRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $availability = new CourtAvailability();

            $availability->fill($request->validated());
            $availability->tenant_id = $tenant->id;
            $availability->save();

            $this->commitSafe();

            return $this->dataResponse(CourtAvailabilityResourceGeneral::make($availability)->resolve());

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao criar a disponibilidade', $e->getMessage());
        }
    }

    public function update(UpdateCourtAvailabilityRequest $request, string $tenantIdHashId, string $availabilityIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $availabilityId = EasyHashAction::decode($availabilityIdHashId, 'court-availability-id');
            $availability = CourtAvailability::forTenant($tenant->id)->find($availabilityId);

            if (!$availability) {
                $this->rollBackSafe();
                return $this->errorResponse(message: 'Disponibilidade não encontrada', status: 404);
            }

            $availability->update($request->validated());

            $this->commitSafe();

            return $this->dataResponse(CourtAvailabilityResourceGeneral::make($availability)->resolve());

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao actualizar a disponibilidade', $e->getMessage());
        }
    }

    public function destroy(Request $request, string $tenantIdHashId, string $availabilityIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $availabilityId = EasyHashAction::decode($availabilityIdHashId, 'court-availability-id');
            $availability = CourtAvailability::forTenant($tenant->id)->where('id', $availabilityId)->first();

            if (!$availability) {
                $this->rollBackSafe();
                return $this->errorResponse(message: 'Disponibilidade não encontrada', status: 404);
            }

            $availability->delete();

            $this->commitSafe();

            return $this->successResponse('Disponibilidade deletada com sucesso');

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao deletar a disponibilidade', $e->getMessage());
        }
    }
}
