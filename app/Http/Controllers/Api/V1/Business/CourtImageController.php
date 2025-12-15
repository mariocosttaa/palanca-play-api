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

class CourtImageController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCourtImageRequest $request, $tenantIdHashed, $courtId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
            $court = Court::where('tenant_id', $tenantId)->findOrFail($courtId);

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
            return $this->errorResponse('Erro ao adicionar imagem', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($tenantIdHashed, $courtId, $imageId)
    {
        try {
            $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
            $court = Court::where('tenant_id', $tenantId)->findOrFail($courtId);
            $image = $court->images()->findOrFail($imageId);

            $this->beginTransactionSafe();

            // Delete file using TenantFileAction
            TenantFileAction::delete(
                tenantId: $tenantId,
                fileUrl: $image->path,
                isPublic: true
            );

            $image->delete();

            $this->commitSafe();

            return $this->successResponse('Imagem removida com sucesso');
        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Erro ao remover imagem', $e->getMessage());
        }
    }
}
