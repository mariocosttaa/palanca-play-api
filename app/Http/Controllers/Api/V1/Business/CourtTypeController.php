<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\UpdateCourtTypeRequest;
use App\Http\Requests\Api\V1\Business\CreateCourtTypeRequest;
use App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Court Types
 */
class CourtTypeController extends Controller
{
    /**
     * Get a list of all court types for the authenticated tenant
     * 
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<int, CourtTypeResourceGeneral>
     * @response 200 \Illuminate\Http\Resources\Json\ResourceCollection<int, CourtTypeResourceGeneral>
     * @response 500 {"message": "Server error"}
     */
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;
            $perPage = $request->input('per_page', 15);
            $courtTypes = CourtType::with('availabilities')->forTenant($tenant->id)->paginate($perPage);

            return CourtTypeResourceGeneral::collection($courtTypes);
        } catch (\Exception $e) {
            \Log::error('Error fetching court types', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error fetching the court types.')], 500);
        }
    }

    /**
     * Get a specific court type by ID
     * 
     * @return CourtTypeResourceGeneral
     * @response 200 CourtTypeResourceGeneral
     * @response 404 {"message": "Court type not found"}
     * @response 500 {"message": "Server error"}
     */
    public function show(Request $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::with([
                'courts' => function ($query) {
                    $query->with('images');
                },
                'availabilities'
            ])->forTenant($tenant->id)->findOrFail($courtTypeId);

            return new CourtTypeResourceGeneral($courtType);

        } catch (\Exception $e) {
            \Log::error('Error fetching court type', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error fetching the court type.')], 500);
        }
    }

    /**
     * Update an existing court type
     * 
     * @return CourtTypeResourceGeneral
     * @response 200 CourtTypeResourceGeneral
     * @response 400 {"message": "Error message"}
     * @response 404 {"message": "Court type not found"}
     * @response 500 {"message": "Server error"}
     */
    public function update(UpdateCourtTypeRequest $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type not found.')], 404);
            }

            $courtType->update($request->validated());

            $this->commitSafe();

            $courtType->load(['availabilities', 'courts' => function ($query) {
                $query->with('images');
            }]);

            return new CourtTypeResourceGeneral($courtType);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Error updating court type', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error updating the court type.')], 400);
        }
    }

    /**
     * Create a new court type
     * 
     * @return \Illuminate\Http\JsonResponse
     * @response 201 CourtTypeResourceGeneral
     * @response 400 {"message": "Error message"}
     * @response 500 {"message": "Server error"}
     */
    public function create(CreateCourtTypeRequest $request, string $tenantIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtType = new CourtType();

            $courtType->fill($request->validated());
            $courtType->tenant_id = $tenant->id;
            $courtType->save();

            $this->commitSafe();

            $courtType->load(['availabilities', 'courts' => function ($query) {
                $query->with('images');
            }]);

            return (new CourtTypeResourceGeneral($courtType))->response()->setStatusCode(201);
        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Error creating court type', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error creating the court type.')], 400);
        }
    }

    /**
     * Delete a court type
     * 
     * @return \Illuminate\Http\JsonResponse
     * @response 200 {"message": "Court type deleted successfully"}
     * @response 400 {"message": "Court type cannot be deleted because it has associated courts"}
     * @response 404 {"message": "Court type not found"}
     * @response 500 {"message": "Server error"}
     */
    public function destroy(Request $request, string $tenantIdHashId, string $courtTypeIdHashId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            $courtType = CourtType::forTenant($tenant->id)->where('id', $courtTypeId)->first();

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type not found.')], 404);
            }

            //check if the court type has any courts associated
            if ($courtType->courts()->exists()) {
                $this->rollBackSafe();
                return response()->json(['message' => __('Court type cannot be deleted because it has associated courts, please delete the courts first.')], 400);
            }

            $courtType->delete();

            $this->commitSafe();

            return response()->json(['message' => __('Court type deleted successfully.')]);

        }
        catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Error deleting court type', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('There was an error deleting the court type.')], 400);
        }
    }
    /**
     * Get available court type options with translated labels
     * 
     * @response array{data: array<int, array{value: string, label: string}>}
     */
    public function types(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => \App\Enums\CourtTypeEnum::options()]);
    }
}
