<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral;
use App\Models\CourtType;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Court Types
 */
class MobileCourtTypeController extends Controller
{
    /**
     * List court types
     * 
     * List all active court types for a tenant.
     * 
     * @unauthenticated
     * 
     * @urlParam tenant_id string required The HashID of the tenant. Example: ten_abc123
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral>
     */
    public function index(Request $request, string $tenantIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            
            $courtTypes = CourtType::forTenant($tenantId)
                ->with(['courts' => function ($query) {
                    $query->active()->with('images');
                }, 'availabilities'])
                ->where('status', true)
                ->get();

            return CourtTypeResourceGeneral::collection($courtTypes);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipos de quadras', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar tipos de quadras'], 500);
        }
    }

    /**
     * Get court type details
     * 
     * @unauthenticated
     * 
     * @urlParam tenant_id string required The HashID of the tenant. Example: ten_abc123
     * @urlParam court_type_id string required The HashID of the court type. Example: ct_abc123
     * 
     * @return \App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral
     */
    public function show(Request $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            
            $courtType = CourtType::forTenant($tenantId)
                ->with([
                    'courts' => function ($query) {
                        $query->active()->with('images');
                    },
                    'availabilities'
                ])
                ->where('status', true)
                ->findOrFail($courtTypeId);

            return new CourtTypeResourceGeneral($courtType);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipo de quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar tipo de quadra'], 500);
        }
    }
    /**
     * Get court type modalities
     * 
     * @unauthenticated
     * 
     * @return array{data: string[]}
     */
    public function types()
    {
        return response()->json(['data' => \App\Enums\CourtTypeEnum::values()]);
    }
}
