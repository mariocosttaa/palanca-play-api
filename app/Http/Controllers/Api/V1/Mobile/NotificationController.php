<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\Specific\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Notifications
 */
class NotificationController extends Controller
{
    /**
     * Get recent notifications
     * 
     * Get the last 8 notifications for the authenticated user.
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Mobile\V1\Specific\NotificationResource>
     */
    public function recent(Request $request)
    {
        try {
            $user = $request->user();
            
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->limit(8)
                ->get();

            return NotificationResource::collection($notifications);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações recentes', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar notificações recentes'], 500);
        }
    }

    /**
     * List notifications
     * 
     * Get all notifications for the authenticated user with pagination.
     * 
     * @queryParam page int The page number. Example: 1
     * @queryParam per_page int The number of items per page. Example: 20
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Mobile\V1\Specific\NotificationResource>
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $perPage = $request->input('per_page', 20);
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->paginate($perPage);

            return NotificationResource::collection($notifications);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar notificações'], 500);
        }
    }

    /**
     * Mark notification as read
     * 
     * @urlParam notification_id string required The HashID of the notification. Example: not_abc123
     * 
     * @return \App\Http\Resources\Mobile\V1\Specific\NotificationResource
     */
    public function markAsRead(Request $request, string $notificationHashId)
    {
        try {
            $user = $request->user();
            $notificationId = \App\Actions\General\EasyHashAction::decode($notificationHashId, 'notification-id');
            
            $notification = Notification::forUser($user->id)
                ->findOrFail($notificationId);

            $notification->markAsRead();

            return NotificationResource::make($notification);

        } catch (\Exception $e) {
            \Log::error('Erro ao marcar notificação como lida', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao marcar notificação como lida'], 500);
        }
    }
}
