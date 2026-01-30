<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\MobileCourtResource;
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
     * List all active courts with optional filtering.
     * 
     * @queryParam search string optional Search in court name and description. Example: Padel
     * @queryParam country_id string optional Filter by tenant's country HashID. Example: coun_abc123
     * @queryParam modality string optional Filter by court type modality (e.g., padel, tennis). Example: padel
     * @queryParam tenant_id string optional Filter by specific tenant HashID. Example: ten_abc123
     * @queryParam court_type_id string optional Filter by specific court type HashID. Example: ct_abc123
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Api\V1\Mobile\MobileCourtResource>
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $request->validate([
                'search' => 'nullable|string',
                'country_id' => 'nullable|string',
                'modality' => 'nullable|string',
                'tenant_id' => 'nullable|string',
                'court_type_id' => 'nullable|string',
            ]);

            $query = Court::active()
                ->with(['images', 'courtType', 'tenant.country']);

            // Apply Filters
            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->country_id) {
                $countryId = EasyHashAction::decode($request->country_id, 'country-id');
                $query->whereHas('tenant', function ($q) use ($countryId) {
                    $q->where('country_id', $countryId);
                });
            }

            if ($request->modality) {
                $query->whereHas('courtType', function ($q) use ($request) {
                    $q->where('type', $request->modality);
                });
            }

            if ($request->tenant_id) {
                $tenantId = EasyHashAction::decode($request->tenant_id, 'tenant-id');
                $query->where('tenant_id', $tenantId);
            }

            if ($request->court_type_id) {
                $courtTypeId = EasyHashAction::decode($request->court_type_id, 'court-type-id');
                $query->where('court_type_id', $courtTypeId);
            }

            $courts = $query->paginate(20);

            return MobileCourtResource::collection($courts);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar quadras', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar quadras');
        }
    }

    /**
     * Get court details
     * 
     * @urlParam court_id string required The HashID of the court. Example: crt_abc123
     */
    public function show(Request $request, string $courtIdHashId): MobileCourtResource
    {
        try {
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::active()
                ->with(['images', 'courtType', 'tenant.country'])
                ->findOrFail($courtId);

            return new MobileCourtResource($court);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Quadra não encontrada.');
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar quadra', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar quadra');
        }
    }
}
