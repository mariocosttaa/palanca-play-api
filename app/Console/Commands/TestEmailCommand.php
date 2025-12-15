<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Booking;
use App\Services\EmailService;
use App\Models\PasswordResetCode;
use Illuminate\Console\Command;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {type=all : Type of email to test (booking-created, booking-cancelled, password-reset, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email functionality with Mailhog';

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');

        $this->info('🧪 Testing Email Functionality with Mailhog');
        $this->info('📧 Mailhog Web UI: http://localhost:8025');
        $this->newLine();

        try {
            switch ($type) {
                case 'booking-created':
                    $this->testBookingCreated();
                    break;
                case 'booking-cancelled':
                    $this->testBookingCancelled();
                    break;
                case 'password-reset':
                    $this->testPasswordReset();
                    break;
                case 'all':
                    $this->testBookingCreated();
                    $this->testBookingCancelled();
                    $this->testPasswordReset();
                    break;
                default:
                    $this->error('Invalid type. Use: booking-created, booking-cancelled, password-reset, or all');
                    return 1;
            }

            $this->newLine();
            $this->info('✅ Email test completed!');
            $this->info('🌐 Check Mailhog at: http://localhost:8025');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function testBookingCreated()
    {
        $this->info('📨 Testing Booking Created Email...');

        // Get or create a test user
        $user = User::first();
        if (!$user) {
            $this->warn('No users found. Creating test user...');
            $user = User::factory()->create([
                'email' => 'test@palancaplay.com',
                'name' => 'Test',
                'surname' => 'User',
            ]);
        }

        // Get or create a test booking
        $booking = Booking::with(['court', 'court.tenant', 'currency'])->first();
        if (!$booking) {
            $this->warn('No bookings found. Please create a booking first.');
            return;
        }

        $this->emailService->sendBookingEmail($user, $booking, 'created');
        $this->line('  ✓ Booking Created email sent to: ' . $user->email);
    }

    private function testBookingCancelled()
    {
        $this->info('📨 Testing Booking Cancelled Email...');

        $user = User::first();
        if (!$user) {
            $this->warn('No users found. Skipping...');
            return;
        }

        $booking = Booking::with(['court', 'court.tenant', 'currency'])->first();
        if (!$booking) {
            $this->warn('No bookings found. Skipping...');
            return;
        }

        $this->emailService->sendBookingEmail($user, $booking, 'cancelled');
        $this->line('  ✓ Booking Cancelled email sent to: ' . $user->email);
    }

    private function testPasswordReset()
    {
        $this->info('📨 Testing Password Reset Email...');

        $user = User::first();
        if (!$user) {
            $this->warn('No users found. Creating test user...');
            $user = User::factory()->create([
                'email' => 'test@palancaplay.com',
                'name' => 'Test',
                'surname' => 'User',
            ]);
        }

        $code = PasswordResetCode::generateCode();
        $this->emailService->sendPasswordResetEmail($user->email, $code);
        $this->line('  ✓ Password Reset email sent to: ' . $user->email);
        $this->line('  ℹ Code: ' . $code);
    }
}
