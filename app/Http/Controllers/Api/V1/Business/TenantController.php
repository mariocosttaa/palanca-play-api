<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\General\TenantResourceGeneral;
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
    /**
     * Get a list of all tenants for the authenticated business user
     * 
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<int, TenantResourceGeneral>
     * @response 200 \Illuminate\Http\Resources\Json\ResourceCollection<int, TenantResourceGeneral>
     * @response 500 {"message": "Server error"}
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $tenants = Tenant::forBusinessUser($request->user()->id)->paginate($perPage);
            return TenantResourceGeneral::collection($tenants);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar grupos ou empresas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar grupos ou empresas'], 500);
        }
    }

    /**
     * Get a specific tenant by ID
     * 
     * @return TenantResourceGeneral
     * @response 200 TenantResourceGeneral
     * @response 500 {"message": "Server error"}
     */
    public function show(Request $request, string $tenantIdHashId)
    {
        return new TenantResourceGeneral($request->tenant);
    }

    /**
     * Update a tenant
     * 
     * @return TenantResourceGeneral
     * @response 200 TenantResourceGeneral
     * @response 400 {"message": "Error message"}
     * @response 500 {"message": "Server error"}
     */
    public function update(UpdateTenantRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $businessUserId = $request->user()->id;
            $tenant = Tenant::with('businessUsers')->where('id', $request->tenant_id)->first();
            if (!$tenant) {
                $this->rollBackSafe();
                return response()->json(['message' => 'O grupo ou empresa indicado não existe'], 400);
            }

            if (!$tenant->businessUsers->contains($businessUserId)) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Você não tem permissão para atualizar este grupo ou empresa'], 500);
            }

            $data = $request->validated();


            $tenant->update($data);

            $this->commitSafe();

            return new TenantResourceGeneral($tenant);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Erro ao atualizar o grupo ou empresa', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar o grupo ou empresa'], 400);
        }
    }

    /**
     * Upload a logo for the tenant
     * 
     * @return TenantResourceGeneral
     * @response 200 TenantResourceGeneral
     * @response 400 {"message": "Error message"}
     * @response 500 {"message": "Server error"}
     */
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

            return new TenantResourceGeneral($tenant);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Erro ao fazer upload do logo', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao fazer upload do logo'], 400);
        }
    }

    /**
     * Delete the tenant logo
     * 
     * @return TenantResourceGeneral
     * @response 200 TenantResourceGeneral
     * @response 400 {"message": "Error message"}
     * @response 404 {"message": "Logo not found"}
     * @response 500 {"message": "Server error"}
     */
    public function deleteLogo(Request $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

            // Check if logo exists
            if (!$tenant->logo) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Nenhum logo encontrado para remover'], 404);
            }

            // Delete logo file
            $deleted = TenantFileAction::delete(
                tenantId: $tenant->id,
                fileUrl: $tenant->logo,
                isPublic: true
            );

            if (!$deleted) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Erro ao remover o arquivo do logo'], 400);
            }

            // Update tenant to remove logo URL
            $tenant->update(['logo' => null]);

            $this->commitSafe();

            return new TenantResourceGeneral($tenant);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Erro ao remover o logo', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao remover o logo'], 400);
        }
    }

}
