<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtImageRequest;
use App\Models\Court;
use App\Models\CourtImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Court Images
 */
class CourtImageController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * 
     * @return \Illuminate\Http\JsonResponse
     * @response 200 {"message": "Image added successfully", "data": {...}}
     * @response 400 {"message": "Error message"}
     * @response 404 {"message": "Court not found"}
     */
    public function store(CreateCourtImageRequest $request, $tenantIdHashed, $courtId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
            $decodedCourtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::where('tenant_id', $tenantId)->findOrFail($decodedCourtId);

            $this->beginTransactionSafe();

            $fileInfo = TenantFileAction::save(
                tenantId: $tenantId,
                file: $request->file('image'),
                isPublic: true,
                path: 'courts'
            );

            $courtImage = $court->images()->create([
                'path' => $fileInfo->url,
                'is_primary' => $request->boolean('is_primary', false),
            ]);

            // If this is marked as primary, unset others
            if ($courtImage->is_primary) {
                $court->images()->where('id', '!=', $courtImage->id)->update(['is_primary' => false]);
            }

            $this->commitSafe();

            return response()->json([
                'message' => 'Imagem adicionada com sucesso',
                'data' => $courtImage,
            ], 200);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Erro ao adicionar imagem', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao adicionar imagem'], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @return \Illuminate\Http\JsonResponse
     * @response 200 {"message": "Image deleted successfully"}
     * @response 400 {"message": "Error message"}
     * @response 404 {"message": "Court or image not found"}
     */
    public function destroy($tenantIdHashed, $courtId, $imageId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
            $decodedCourtId = EasyHashAction::decode($courtId, 'court-id');
            $decodedImageId = EasyHashAction::decode($imageId, 'court-image-id');
            
            $court = Court::where('tenant_id', $tenantId)->findOrFail($decodedCourtId);
            $image = $court->images()->findOrFail($decodedImageId);

            $this->beginTransactionSafe();

            // Delete file using TenantFileAction
            TenantFileAction::delete(
                tenantId: $tenantId,
                fileUrl: $image->path,
                isPublic: true
            );

            $image->delete();

            $this->commitSafe();

            return response()->json(['message' => 'Imagem removida com sucesso']);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Erro ao remover imagem', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao remover imagem'], 400);
        }
    }
}
