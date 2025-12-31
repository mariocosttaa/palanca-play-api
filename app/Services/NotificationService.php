<?php

namespace App\Services;

use App\Enums\BookingApiContextEnum;
use App\Models\Notification;
use App\Models\BusinessNotification;
use App\Models\Booking;
use App\Models\BusinessUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a booking event
     *
     * @param Booking $booking
     * @param string $action - 'created', 'updated', 'cancelled'
     * @param BookingApiContextEnum $apiContext The API context (mobile or business)
     * @return Notification|array Returns Notification for user, or array of notifications for business users
     */
    public function createBookingNotification(Booking $booking, string $action, BookingApiContextEnum $apiContext = BookingApiContextEnum::BUSINESS): Notification|array
    {
        $booking->load(['court', 'court.tenant', 'user']);
        
        $subject = $this->getSubject($action, $apiContext);
        $message = $this->getMessage($booking, $action, $apiContext);

        // For mobile bookings, notify both the user and business users
        if ($apiContext === BookingApiContextEnum::MOBILE) {
            // Create notification for the user (client)
            $userNotification = Notification::create([
                'tenant_id' => $booking->tenant_id,
                'user_id' => $booking->user_id,
                'subject' => $subject,
                'message' => $message,
            ]);

            // Create notifications for all business users associated with the tenant
            $businessNotifications = $this->notifyBusinessUsers($booking, $action, $subject, $message);

            return [
                'user_notification' => $userNotification,
                'business_notifications' => $businessNotifications,
            ];
        }

        // For business API, only notify the user (client)
        return Notification::create([
            'tenant_id' => $booking->tenant_id,
            'user_id' => $booking->user_id,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    /**
     * Notify all business users associated with the tenant about a booking event
     *
     * @param Booking $booking
     * @param string $action
     * @param string $subject
     * @param string $message
     * @return array Array of created notifications
     */
    protected function notifyBusinessUsers(Booking $booking, string $action, string $subject, string $message): array
    {
        $notifications = [];
        
        try {
            // Get all business users associated with the tenant
            $businessUsers = BusinessUser::whereHas('tenants', function ($query) use ($booking) {
                $query->where('tenants.id', $booking->tenant_id);
            })->get();

            // Create a business-specific message
            $businessMessage = $this->getBusinessMessage($booking, $action);

            // Create notifications for each business user
            foreach ($businessUsers as $businessUser) {
                try {
                    $notification = BusinessNotification::create([
                        'tenant_id' => $booking->tenant_id,
                        'business_user_id' => $businessUser->id,
                        'subject' => $subject,
                        'message' => $businessMessage,
                    ]);
                    
                    $notifications[] = $notification;
                } catch (\Exception $e) {
                    Log::error('Failed to create business notification', [
                        'business_user_id' => $businessUser->id,
                        'tenant_id' => $booking->tenant_id,
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify business users', [
                'booking_id' => $booking->id,
                'tenant_id' => $booking->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notifications;
    }

    /**
     * Get notification subject based on action and context
     */
    private function getSubject(string $action, BookingApiContextEnum $apiContext): string
    {
        if ($apiContext === BookingApiContextEnum::MOBILE) {
            return match($action) {
                'created' => 'Nova Reserva Criada',
                'updated' => 'Reserva Atualizada',
                'cancelled' => 'Reserva Cancelada',
                default => 'Notificação de Reserva',
            };
        }

        return match($action) {
            'created' => 'Reserva Criada',
            'updated' => 'Reserva Atualizada',
            'cancelled' => 'Reserva Cancelada',
            default => 'Notificação de Reserva',
        };
    }

    /**
     * Generate descriptive message for booking notification (for users)
     */
    private function getMessage(Booking $booking, string $action, BookingApiContextEnum $apiContext): string
    {
        $courtName = $booking->court->name ?? 'Campo';
        $date = Carbon::parse($booking->start_date)->format('d/m/Y');
        $startTime = Carbon::parse($booking->start_time)->format('H:i');
        $endTime = Carbon::parse($booking->end_time)->format('H:i');

        return match($action) {
            'created' => "Você criou uma reserva no {$courtName} no dia {$date} das {$startTime} às {$endTime}.",
            'updated' => "Sua reserva no {$courtName} foi atualizada para o dia {$date} das {$startTime} às {$endTime}.",
            'cancelled' => "Você cancelou a reserva no {$courtName} que estava agendada para o dia {$date} das {$startTime} às {$endTime}.",
            default => "Notificação sobre sua reserva no {$courtName}.",
        };
    }

    /**
     * Generate descriptive message for business users about booking events
     */
    private function getBusinessMessage(Booking $booking, string $action): string
    {
        $clientName = $booking->user->name . ($booking->user->surname ? ' ' . $booking->user->surname : '');
        $courtName = $booking->court->name ?? 'Campo';
        $date = Carbon::parse($booking->start_date)->format('d/m/Y');
        $startTime = Carbon::parse($booking->start_time)->format('H:i');
        $endTime = Carbon::parse($booking->end_time)->format('H:i');

        return match($action) {
            'created' => "Nova reserva criada por {$clientName} no {$courtName} no dia {$date} das {$startTime} às {$endTime}.",
            'updated' => "Reserva atualizada por {$clientName} no {$courtName} para o dia {$date} das {$startTime} às {$endTime}.",
            'cancelled' => "Reserva cancelada por {$clientName} no {$courtName} que estava agendada para o dia {$date} das {$startTime} às {$endTime}.",
            default => "Notificação sobre reserva no {$courtName}.",
        };
    }
}
