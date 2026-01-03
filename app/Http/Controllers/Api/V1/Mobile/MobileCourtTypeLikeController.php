<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\CourtType;
use App\Models\CourtTypeUserLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-MOBILE] Court Types
 */
class MobileCourtTypeLikeController extends Controller
{
    /**
     * Toggle like for a court type
     * 
     * Liking or unliking a court type for the authenticated user.
     * 
     * @urlParam court_type_id string required The HashID of the court type. Example: ct_abc123
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(Request $request, string $courtTypeIdHashId)
    {
        try {
            $courtTypeId = EasyHashAction::decode($courtTypeIdHashId, 'court-type-id');
            
            $courtType = CourtType::findOrFail($courtTypeId);
            $user = auth()->user();

            $this->beginTransactionSafe();

            if ($user->likedCourtTypes()->where('court_type_id', $courtType->id)->exists()) {
                CourtTypeUserLike::where('user_id', $user->id)
                    ->where('court_type_id', $courtType->id)
                    ->delete();
                $courtType->decrement('likes_count');
                $liked = false;
            } else {
                CourtTypeUserLike::create([
                    'user_id' => $user->id,
                    'court_type_id' => $courtType->id,
                ]);
                $courtType->increment('likes_count');
                $liked = true;
            }

            $this->commitSafe();

            return response()->json([
                'message' => $liked ? 'Quadra favoritada com sucesso.' : 'Quadra removida dos favoritos.',
                'is_liked' => $liked,
                'likes_count' => $courtType->likes_count
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Tipo de quadra não encontrado.'], 404);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao favoritar quadra', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao processar sua solicitação.'], 500);
        }
    }
}
