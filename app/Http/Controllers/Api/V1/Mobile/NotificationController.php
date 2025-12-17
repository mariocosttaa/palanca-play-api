<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Notifications
 */
class NotificationController extends Controller
{
    /**
     * Get recent notifications (last 8)
     */
    public function recent(Request $request)
    {
        try {
            $user = $request->user();
            
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->limit(8)
                ->get();

            return $this->dataResponse(
                NotificationResource::collection($notifications)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar notificações recentes', $e->getMessage(), 500);
        }
    }

    /**
     * Get all notifications with pagination
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $perPage = $request->input('per_page', 20);
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->paginate($perPage);

            return $this->dataResponse(
                NotificationResource::collection($notifications)->response()->getData(true)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar notificações', $e->getMessage(), 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $notificationHashId)
    {
        try {
            $user = $request->user();
            $notificationId = \App\Actions\General\EasyHashAction::decode($notificationHashId, 'notification-id');
            
            $notification = Notification::forUser($user->id)
                ->findOrFail($notificationId);

            $notification->markAsRead();

            return $this->dataResponse(
                NotificationResource::make($notification)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao marcar notificação como lida', $e->getMessage(), 500);
        }
    }
}
