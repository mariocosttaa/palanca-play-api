<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\General\TenantResourceGeneral;
use App\Models\Tenant;
use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Requests\Api\V1\Business\UpdateTenantRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Tenants
 */
class TenantController extends Controller
{
    /**
     * Get a list of all tenants for the authenticated business user
     * 
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int optional Items per page. Example: 15
     *
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $perPage = $request->input('per_page', 15);
            $tenants = Tenant::forBusinessUser($request->user()->id)->paginate($perPage);
            return TenantResourceGeneral::collection($tenants);
        } catch (\Exception $e) {
            Log::error('Erro ao listar grupos ou empresas', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao listar grupos ou empresas');
        }
    }

    /**
     * Get a specific tenant by ID
     * 
     */
    public function show(Request $request, string $tenantId): TenantResourceGeneral
    {
        return new TenantResourceGeneral($request->tenant);
    }

    /**
     * Update a tenant
     * 
     */
    public function update(UpdateTenantRequest $request, $tenantId): TenantResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $businessUserId = $request->user()->id;
            $tenant = Tenant::with('businessUsers')->where('id', $request->tenant_id)->first();
            if (!$tenant) {
                $this->rollBackSafe();
                abort(400, 'O grupo ou empresa indicado não existe');
            }

            if (!$tenant->businessUsers->contains($businessUserId)) {
                $this->rollBackSafe();
                abort(403, 'Você não tem permissão para atualizar este grupo ou empresa');
            }

            $data = $request->validated();

            if (isset($data['timezone_id'])) {
                $timezone = \App\Models\Timezone::find($data['timezone_id']);
                if ($timezone) {
                    $data['timezone'] = $timezone->name;
                }
            }

            $tenant->update($data);

            $this->commitSafe();

            return new TenantResourceGeneral($tenant);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao atualizar o grupo ou empresa', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao atualizar o grupo ou empresa');
        }
    }

    /**
     * Upload a logo for the tenant
     * 
     */
    public function uploadLogo(Request $request, string $tenantId): TenantResourceGeneral
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
            Log::error('Erro ao fazer upload do logo', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao fazer upload do logo');
        }
    }

    /**
     * Delete the tenant logo
     * 
     */
    public function deleteLogo(Request $request, string $tenantId): TenantResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

            // Check if logo exists
            if (!$tenant->logo) {
                $this->rollBackSafe();
                abort(404, 'Nenhum logo encontrado para remover');
            }

            // Delete logo file
            $deleted = TenantFileAction::delete(
                tenantId: $tenant->id,
                fileUrl: $tenant->logo,
                isPublic: true
            );

            if (!$deleted) {
                $this->rollBackSafe();
                abort(400, 'Erro ao remover o arquivo do logo');
            }

            // Update tenant to remove logo URL
            $tenant->update(['logo' => null]);

            $this->commitSafe();

            return new TenantResourceGeneral($tenant);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao remover o logo', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao remover o logo');
        }
    }

}
