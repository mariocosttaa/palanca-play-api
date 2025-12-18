<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\General\CourtResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Courts
 */
class MobileCourtController extends Controller
{
    /**
     * List courts for a tenant, optionally filtered by court type
     */
    public function index(Request $request, string $tenantIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            
            $query = Court::forTenant($tenantId)
                ->active()
                ->with(['courtType', 'primaryImage', 'images']);

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
     * Get detailed information about a specific court
     */
    public function show(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::forTenant($tenantId)
                ->active()
                ->with(['courtType', 'images', 'primaryImage'])
                ->findOrFail($courtId);

            return CourtResourceGeneral::make($court);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar quadra'], 500);
        }
    }
}
