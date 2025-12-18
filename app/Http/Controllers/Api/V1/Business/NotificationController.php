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

            return NotificationResourceGeneral::collection($notifications);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações recentes', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar notificações recentes'], 500);
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

            return NotificationResourceGeneral::collection($notifications);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar notificações', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar notificações'], 500);
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

            return NotificationResourceGeneral::make($notification);

        } catch (\Exception $e) {
            \Log::error('Erro ao marcar notificação como lida', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao marcar notificação como lida'], 500);
        }
    }
}
