<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\NotificationResourceGeneral;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Notifications
 */
class NotificationController extends Controller
{
    /**
     * Get recent notifications (last 5)
     */
    public function recent(Request $request)
    {
        try {
            $user = $request->user();
            
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->limit(5)
                ->get();

            return $this->dataResponse(
                NotificationResourceGeneral::collection($notifications)->resolve()
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
                NotificationResourceGeneral::collection($notifications)->response()->getData(true)
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
                NotificationResourceGeneral::make($notification)->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao marcar notificação como lida', $e->getMessage(), 500);
        }
    }
}
