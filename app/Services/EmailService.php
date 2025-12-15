<?php

namespace App\Services;

use App\Models\EmailSent;
use App\Models\Booking;
use App\Models\User;
use App\Mail\BookingCreated;
use App\Mail\BookingUpdated;
use App\Mail\BookingCancelled;
use App\Mail\PasswordResetCode as PasswordResetCodeMail;
use App\Jobs\SendEmailJob;

class EmailService
{
    /**
     * Send booking event email
     *
     * @param User $user
     * @param Booking $booking
     * @param string $action - 'created', 'updated', 'cancelled'
     */
    public function sendBookingEmail(User $user, Booking $booking, string $action): void
    {
        $booking->load(['court', 'court.tenant', 'currency']);

        $mailable = match($action) {
            'created' => new BookingCreated($booking),
            'updated' => new BookingUpdated($booking),
            'cancelled' => new BookingCancelled($booking),
            default => null,
        };

        if (!$mailable) {
            return;
        }

        // Create email record with pending status
        $emailSent = $this->createEmailRecord(
            $user->email,
            $mailable->subject,
            $this->getEmailTitle($action),
            $mailable->render()
        );

        // Dispatch job to send email
        SendEmailJob::dispatch($emailSent->id, $user->email, $mailable);
    }

    /**
     * Send password reset code email
     *
     * @param string $email
     * @param string $code
     */
    public function sendPasswordResetEmail(string $email, string $code): void
    {
        $mailable = new PasswordResetCodeMail($code);

        // Create email record with pending status
        $emailSent = $this->createEmailRecord(
            $email,
            $mailable->subject,
            'Código de Recuperação de Senha',
            $mailable->render()
        );

        // Dispatch job to send email
        SendEmailJob::dispatch($emailSent->id, $email, $mailable);
    }

    /**
     * Create email record in database with pending status
     */
    private function createEmailRecord(string $email, string $subject, string $title, string $htmlContent): EmailSent
    {
        return EmailSent::create([
            'user_email' => $email,
            'subject' => $subject,
            'title' => $title,
            'html_content' => $htmlContent,
            'status' => 'pending',
            'sent_at' => null, // Will be updated when email is sent
        ]);
    }

    /**
     * Get email title based on action
     */
    private function getEmailTitle(string $action): string
    {
        return match($action) {
            'created' => 'Reserva Confirmada',
            'updated' => 'Reserva Atualizada',
            'cancelled' => 'Reserva Cancelada',
            default => 'Notificação de Reserva',
        };
    }
}
