<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\MobileCourtTypeResource;
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
     * List all active court types with optional filtering.
     * 
     * @queryParam search string optional Search in name and description. Example: Padel
     * @queryParam country_id string optional Filter by tenant's country HashID. Example: coun_abc123
     * @queryParam modality string optional Filter by court type modality (e.g., padel, tennis). Example: padel
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Api\V1\Mobile\MobileCourtTypeResource>
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'search' => 'nullable|string',
                'country_id' => 'nullable|string',
                'modality' => 'nullable|string',
            ]);

            $query = CourtType::query()
                ->with(['courts' => function ($query) {
                    $query->active()->with('images');
                }, 'availabilities', 'tenant.country'])
                ->withExists(['userLikes as is_liked' => function ($query) {
                    $query->where('user_id', auth()->id());
                }])
                ->where('status', true);

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
                $query->where('type', $request->modality);
            }

            $courtTypes = $query->paginate(20);
            
            return MobileCourtTypeResource::collection($courtTypes);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipos de quadras', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar tipos de quadras');
        }
    }

    /**
     * Get court type details
     * 
     * @urlParam court_type_id string required The HashID of the court type. Example: ct_abc123
     */
    public function show(Request $request, string $courtTypeIdHashId)
    {
        try {
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            
            $courtType = CourtType::with([
                    'courts' => function ($query) {
                        $query->active()->with('images');
                    },
                    'availabilities',
                    'tenant.country'
                ])
                ->withExists(['userLikes as is_liked' => function ($query) {
                    $query->where('user_id', auth()->id());
                }])
                ->where('status', true)
                ->findOrFail($courtTypeId);

            return new MobileCourtTypeResource($courtType);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Tipo de quadra não encontrado.');
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipo de quadra', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar tipo de quadra');
        }
    }

    /**
     * Get court type modalities
     * 
     * @unauthenticated
     * @response array{data: array<int, array{value: string, label: string}>}
     */
    public function types()
    {
        return response()->json(['data' => \App\Enums\CourtTypeEnum::options()]);
    }

    /**
     * List popular court types
     * 
     * List court types ordered by popularity (likes) with optional filtering.
     * 
     * @queryParam search string optional Search in name and description. Example: Padel
     * @queryParam country_id string optional Filter by tenant's country HashID. Example: coun_abc123
     * @queryParam modality string optional Filter by court type modality. Example: padel
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Api\V1\Mobile\MobileCourtTypeResource>
     */
    public function popular(Request $request)
    {
        try {
            $request->validate([
                'search' => 'nullable|string',
                'country_id' => 'nullable|string',
                'modality' => 'nullable|string',
            ]);

            $query = CourtType::query()
                ->with(['courts' => function ($query) {
                    $query->active()->with('images');
                }, 'availabilities', 'tenant.country'])
                ->withExists(['userLikes as is_liked' => function ($query) {
                    $query->where('user_id', auth()->id());
                }])
                ->where('status', true)
                ->orderBy('likes_count', 'desc');

            // Apply Filters (same as index)
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
                $query->where('type', $request->modality);
            }

            $courtTypes = $query->paginate(5);

            return MobileCourtTypeResource::collection($courtTypes);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar tipos de quadras populares', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar tipos de quadras populares');
        }
    }
}
