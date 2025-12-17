<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\TenantResourceGeneral;
use App\Models\Tenant;
use App\Actions\EasyHashAction;
use App\Actions\General\EasyHashAction as GeneralEasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Requests\Api\V1\Business\UpdateTenantRequest;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Tenants
 */
class TenantController extends Controller
{

    public function index(Request $request)
    {
        try {
            $tenants = Tenant::forBusinessUser($request->user()->id)->get();
            return $this->dataResponse(TenantResourceGeneral::collection($tenants)->resolve());
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar grupos ou empresas', $e->getMessage());
        }
    }

    public function show(Request $request, string $tenantIdHashId)
    {
        return $this->dataResponse(TenantResourceGeneral::make($request->tenant)->resolve());
    }

    public function update(UpdateTenantRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $businessUserId = $request->user()->id;
            $tenant = Tenant::with('businessUsers')->where('id', $request->tenant_id)->first();
            if (!$tenant) {
                $this->rollBackSafe();
                return $this->errorResponse('O grupo ou empresa indicado não existe');
            }

            if (!$tenant->businessUsers->contains($businessUserId)) {
                $this->rollBackSafe();
                return $this->errorResponse(message: 'Você não tem permissão para atualizar este grupo ou empresa', status: 500);
            }

            $data = $request->validated();

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $fileInfo = TenantFileAction::save(
                    tenantId: $tenant->id,
                    file: $file,
                    isPublic: true,
                    path: 'logos',
                    fileName: 'logo_' . time()
                );
                $data['logo'] = $fileInfo->url;
            }

            $tenant->update($data);

            $this->commitSafe();

            return $this->dataResponse(TenantResourceGeneral::make($tenant)->resolve());
        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Erro ao atualizar o grupo ou empresa', $e->getMessage());
        }
    }
}
