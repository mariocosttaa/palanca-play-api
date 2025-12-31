<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CourtResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Courts
 */
class MobileCourtController extends Controller
{
    /**
     * List courts
     * 
     * List courts for a tenant, optionally filtered by court type.
     * 
     * @unauthenticated
     * 
     * @urlParam tenant_id string required The HashID of the tenant. Example: ten_abc123
     * @queryParam court_type_id string optional Filter by court type HashID. Example: ct_abc123
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Shared\V1\General\CourtResourceGeneral>
     */
    public function index(Request $request, string $tenantIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            
            $query = Court::forTenant($tenantId)
                ->active()
                ->with(['images']);

            // Filter by court type if provided
            if ($request->has('court_type_id')) {
                $courtTypeId = EasyHashAction::decode($request->court_type_id, 'court-type-id');
                $query->forCourtType($courtTypeId);
            }

            $courts = $query->get();

            return CourtResourceGeneral::collection($courts);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar quadras', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar quadras'], 500);
        }
    }

    /**
     * Get court details
     * 
     * @unauthenticated
     * 
     * @urlParam tenant_id string required The HashID of the tenant. Example: ten_abc123
     * @urlParam court_id string required The HashID of the court. Example: crt_abc123
     * 
     * @return \App\Http\Resources\Shared\V1\General\CourtResourceGeneral
     */
    public function show(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::forTenant($tenantId)
                ->active()
                ->with(['images'])
                ->findOrFail($courtId);

            return new CourtResourceGeneral($court);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar quadra'], 500);
        }
    }
}
