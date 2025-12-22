<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Actions\General\TenantFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateCourtImageRequest;
use App\Http\Requests\Api\V1\Business\SetCourtImagePrimaryRequest;
use App\Http\Resources\Shared\V1\General\CourtImageResourceGeneral;
use App\Models\Court;
use App\Models\CourtImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Court Images
 */
class CourtImageController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCourtImageRequest $request, $tenantIdHashed, $courtId): CourtImageResourceGeneral
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

            // Reload the image with court relationship for resource
            $courtImage->load('court');

            return (new CourtImageResourceGeneral($courtImage))->additional(['message' => 'Imagem adicionada com sucesso']);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao adicionar imagem', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao adicionar imagem');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($tenantIdHashed, $courtId, $imageId): JsonResponse
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
            Log::error('Erro ao remover imagem', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao remover imagem'], 400);
        }
    }

    /**
     * Set or unset primary status for an image.
     */
    public function setPrimary(SetCourtImagePrimaryRequest $request, $tenantIdHashed, $courtId, $imageId): CourtImageResourceGeneral
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
            $decodedCourtId = EasyHashAction::decode($courtId, 'court-id');
            $decodedImageId = EasyHashAction::decode($imageId, 'court-image-id');
            
            $court = Court::where('tenant_id', $tenantId)->findOrFail($decodedCourtId);
            $image = $court->images()->findOrFail($decodedImageId);

            $this->beginTransactionSafe();

            $isPrimary = $request->boolean('is_primary');

            // If setting as primary, unset all other primary images
            if ($isPrimary) {
                $court->images()->where('id', '!=', $decodedImageId)->update(['is_primary' => false]);
            }

            $image->update(['is_primary' => $isPrimary]);

            $this->commitSafe();

            // Reload the image with court relationship for resource
            $image->load('court');

            return (new CourtImageResourceGeneral($image))->additional(['message' => 'Imagem definida como principal com sucesso']);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao definir imagem como principal', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao definir imagem como principal');
        }
    }
}
