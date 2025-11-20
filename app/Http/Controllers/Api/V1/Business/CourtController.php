<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtRequest;
use App\Http\Requests\Api\V1\Business\UpdateCourtRequest;
use App\Http\Resources\General\CourtResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;
            $courts = Court::forTenant($tenant->id)->get();

            return $this->dataResponse(CourtResourceGeneral::collection($courts)->resolve());

        } catch (\Exception $e) {
            return $this->errorResponse('Houve um erro ao buscar as Quadras', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $tenant = $request->tenant;
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            $court = Court::forTenant($tenant->id)->findOrFail($courtId);

            return $this->dataResponse(CourtResourceGeneral::make($court)->resolve());
        }
        catch (\Exception $e) {
            return $this->errorResponse('Houve um erro ao buscar a Quadra', $e->getMessage(), 500);
        }
    }
    public function create(CreateCourtRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $court = new Court();

            $court->fill($request->validated());
            $court->tenant_id = $tenant->id;
            $court->save();

            $this->commitSafe();

            return $this->dataResponse(CourtResourceGeneral::make($court)->resolve());
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao criar a Quadra', $e->getMessage());
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
                return $this->errorResponse(message: 'Quadra não encontrada', status: 404);
            }

            $court->update($request->validated());

            $this->commitSafe();

            return $this->dataResponse(CourtResourceGeneral::make($court)->resolve());
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao actualizar a Quadra', $e->getMessage());
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
                return $this->errorResponse(message: 'Quadra não encontrada', status: 404);
            }

            //check if the court has any bookings associated
            if ($court->bookings()->exists()) {
                $this->rollBackSafe();
                return $this->errorResponse(message: 'Quadra não pode ser deletada porque tem reservas associadas, apague as reservas primeiro', status: 400);
            }

            $court->delete();

            $this->commitSafe();

            return $this->successResponse('Quadra deletada com sucesso');
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Houve um erro ao deletar a Quadra', $e->getMessage());
        }
    }
}

