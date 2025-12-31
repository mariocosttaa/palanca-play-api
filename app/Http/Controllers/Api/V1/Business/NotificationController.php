<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\BusinessNotificationResource;
use App\Models\BusinessNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Actions\General\EasyHashAction;

/**
 * @tags [API-BUSINESS] Notifications
 */
class NotificationController extends Controller
{
    /**
     * Get recent notifications (last 5)
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function recent(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $user = $request->user();
            
            $notifications = BusinessNotification::forBusinessUser($user->id)
                ->latest()
                ->limit(5)
                ->get();

            return BusinessNotificationResource::collection($notifications);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar notificações recentes', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar notificações recentes');
        }
    }

    /**
     * Get all notifications with pagination
     * 
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int Number of items per page. Example: 20
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $user = $request->user();
            
            $perPage = $request->input('per_page', 20);
            $notifications = BusinessNotification::forBusinessUser($user->id)
                ->latest()
                ->paginate($perPage);

            return BusinessNotificationResource::collection($notifications);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar notificações', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar notificações');
        }
    }

    /**
     * Mark notification as read
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BusinessNotificationResource|\Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $notificationId): \App\Http\Resources\Business\V1\Specific\BusinessNotificationResource|\Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $notificationId = EasyHashAction::decode($notificationId, 'notification-id');
            
            $notification = BusinessNotification::forBusinessUser($user->id)
                ->findOrFail($notificationId);

            $notification->markAsRead();

            return new BusinessNotificationResource($notification);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Notificação não encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao marcar notificação como lida', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao marcar notificação como lida');
        }
    }
}
