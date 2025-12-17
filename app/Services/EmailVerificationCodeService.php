<?php

namespace App\Services;

use App\Enums\EmailTypeEnum;
use App\Models\EmailSent;
use Illuminate\Support\Str;

class EmailVerificationCodeService
{
    /**
     * Send a verification code to the given email.
     *
     * @param string $email
     * @param EmailTypeEnum $type
     * @return string The generated code
     */
    public function sendVerificationCode(string $email, EmailTypeEnum $type): string
    {
        // Check for max limit (10 emails in 24 hours)
        $dailyCount = EmailSent::where('user_email', $email)
            ->where('type', $type)
            ->where('sent_at', '>=', now()->subHours(24))
            ->count();

        if ($dailyCount >= 10) {
            throw new \App\Exceptions\EmailRateLimitException(
                'You have reached the maximum number of verification emails. Please contact support for assistance.',
                429
            );
        }

        // Check for cooldown (1 email every 2.5 minutes / 150 seconds)
        $lastSent = EmailSent::where('user_email', $email)
            ->where('type', $type)
            ->latest('sent_at')
            ->first();

        if ($lastSent && $lastSent->sent_at->diffInSeconds(now()) < 150) {
            $secondsRemaining = ceil(150 - $lastSent->sent_at->diffInSeconds(now()));
            throw new \App\Exceptions\EmailRateLimitException(
                "Please wait {$secondsRemaining} seconds before requesting a new verification email.",
                429
            );
        }

        // Generate a 6-digit code
        $code = (string) random_int(100000, 999999);

        // Send the email
        \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\EmailVerificationCode($code));

        EmailSent::create([
            'user_email' => $email,
            'code' => $code,
            'type' => $type,
            'subject' => $this->getSubjectForType($type),
            'title' => 'Verification Code',
            'html_content' => view('emails.verification-code', ['code' => $code])->render(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $code;
    }

    /**
     * Verify the code for the given email and type.
     *
     * @param string $email
     * @param string $code
     * @param EmailTypeEnum $type
     * @return bool
     */
    public function verifyCode(string $email, string $code, EmailTypeEnum $type): bool
    {
        $verification = EmailSent::where('user_email', $email)
            ->where('type', $type)
            ->where('code', $code)
            ->where('sent_at', '>=', now()->subMinutes(15)) // Code expires in 15 minutes
            ->latest('sent_at')
            ->first();

        return $verification !== null;
    }

    protected function getSubjectForType(EmailTypeEnum $type): string
    {
        return match ($type) {
            EmailTypeEnum::CONFIRMATION_EMAIL => 'Confirm your email address',
            EmailTypeEnum::PASSWORD_CHANGE => 'Password Change Verification',
            EmailTypeEnum::BOOKING => 'Booking Confirmation',
            EmailTypeEnum::BOOKING_CANCELLED => 'Booking Cancelled',
            EmailTypeEnum::BOOKING_EDITED => 'Booking Updated',
            default => 'Notification',
        };
    }
}
