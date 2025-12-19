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
     * List all active court types for a tenant
     * Used in the mobile booking page to show available court types
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
     * Get detailed information about a specific court type
     * Used when user clicks on a court type to see details
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
    public function types()
    {
        return response()->json(['data' => \App\Enums\CourtTypeEnum::values()]);
    }
}
