<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Booking;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Create a notification for a booking event
     *
     * @param Booking $booking
     * @param string $action - 'created', 'updated', 'cancelled'
     * @return Notification
     */
    public function createBookingNotification(Booking $booking, string $action): Notification
    {
        $booking->load(['court', 'court.tenant']);
        
        $subject = $this->getSubject($action);
        $message = $this->getMessage($booking, $action);

        return Notification::create([
            'tenant_id' => $booking->tenant_id,
            'user_id' => $booking->user_id,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    /**
     * Get notification subject based on action
     */
    private function getSubject(string $action): string
    {
        return match($action) {
            'created' => 'Reserva Criada',
            'updated' => 'Reserva Atualizada',
            'cancelled' => 'Reserva Cancelada',
            default => 'Notificação de Reserva',
        };
    }

    /**
     * Generate descriptive message for booking notification
     */
    private function getMessage(Booking $booking, string $action): string
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
}
