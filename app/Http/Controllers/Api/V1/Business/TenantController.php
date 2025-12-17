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

    public function uploadLogo(Request $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

            // Validate the uploaded file
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // 2MB max
            ]);

            // Delete old logo if exists
            if ($tenant->logo) {
                TenantFileAction::delete(
                    tenantId: $tenant->id,
                    fileUrl: $tenant->logo,
                    isPublic: true
                );
            }

            // Upload new logo
            $file = $request->file('logo');
            $fileInfo = TenantFileAction::save(
                tenantId: $tenant->id,
                file: $file,
                isPublic: true,
                path: 'logos',
                fileName: 'logo_' . time()
            );

            // Update tenant with new logo URL
            $tenant->update(['logo' => $fileInfo->url]);

            $this->commitSafe();

            return $this->dataResponse(TenantResourceGeneral::make($tenant)->resolve());
        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Erro ao fazer upload do logo', $e->getMessage());
        }
    }

    public function deleteLogo(Request $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

            // Check if logo exists
            if (!$tenant->logo) {
                $this->rollBackSafe();
                return $this->errorResponse('Nenhum logo encontrado para remover', status: 404);
            }

            // Delete logo file
            $deleted = TenantFileAction::delete(
                tenantId: $tenant->id,
                fileUrl: $tenant->logo,
                isPublic: true
            );

            if (!$deleted) {
                $this->rollBackSafe();
                return $this->errorResponse('Erro ao remover o arquivo do logo');
            }

            // Update tenant to remove logo URL
            $tenant->update(['logo' => null]);

            $this->commitSafe();

            return $this->dataResponse(TenantResourceGeneral::make($tenant)->resolve());
        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Erro ao remover o logo', $e->getMessage());
        }
    }
}
