<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\General\CourtTypeResourceGeneral;
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
                    $query->active()->with(['primaryImage']);
                }])
                ->where('status', true)
                ->get();

            return $this->dataResponse(
                CourtTypeResourceGeneral::collection($courtTypes)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar tipos de quadras', $e->getMessage(), 500);
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
                ->with(['courts' => function ($query) {
                    $query->active()->with(['images', 'primaryImage']);
                }])
                ->where('status', true)
                ->findOrFail($courtTypeId);

            return $this->dataResponse(
                CourtTypeResourceGeneral::make($courtType)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar tipo de quadra', $e->getMessage(), 500);
        }
    }
}
