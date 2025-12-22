<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\NotificationResourceGeneral;
use App\Models\Notification;
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
            
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->limit(5)
                ->get();

            return NotificationResourceGeneral::collection($notifications);

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
            $notifications = Notification::forUser($user->id)
                ->latest()
                ->paginate($perPage);

            return NotificationResourceGeneral::collection($notifications);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar notificações', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar notificações');
        }
    }

    /**
     * Mark notification as read
     * 
     * @return \App\Http\Resources\Shared\V1\General\NotificationResourceGeneral
     */
    public function markAsRead(Request $request, $notificationId): \App\Http\Resources\Shared\V1\General\NotificationResourceGeneral
    {
        try {
            $user = $request->user();
            $notificationId = EasyHashAction::decode($notificationId, 'notification-id');
            
            $notification = Notification::forUser($user->id)
                ->findOrFail($notificationId);

            $notification->markAsRead();

            return new NotificationResourceGeneral($notification);

        } catch (\Exception $e) {
            Log::error('Erro ao marcar notificação como lida', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao marcar notificação como lida');
        }
    }
}
