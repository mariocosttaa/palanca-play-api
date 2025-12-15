<?php

namespace App\Jobs;

use App\Models\EmailSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $emailSentId;
    public $recipientEmail;
    public $mailable;

    /**
     * Create a new job instance.
     */
    public function __construct(int $emailSentId, string $recipientEmail, Mailable $mailable)
    {
        $this->emailSentId = $emailSentId;
        $this->recipientEmail = $recipientEmail;
        $this->mailable = $mailable;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send the email
            Mail::to($this->recipientEmail)->send($this->mailable);

            // Update status to sent
            EmailSent::where('id', $this->emailSentId)->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            // Update status to failed with error message
            EmailSent::where('id', $this->emailSentId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        EmailSent::where('id', $this->emailSentId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
