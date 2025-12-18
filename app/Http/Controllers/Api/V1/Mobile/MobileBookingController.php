<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\CreateMobileBookingRequest;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Services\NotificationService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags [API-MOBILE] Bookings
 */
class MobileBookingController extends Controller
{
    protected $notificationService;
    protected $emailService;

    public function __construct(NotificationService $notificationService, EmailService $emailService)
    {
        $this->notificationService = $notificationService;
        $this->emailService = $emailService;
    }
    /**
     * List authenticated user's bookings
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.primaryImage', 'currency', 'court.tenant']);

            // Filter by status if provided
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'upcoming':
                        $query->where('start_date', '>=', now()->format('Y-m-d'))
                            ->where('is_cancelled', false);
                        break;
                    case 'past':
                        $query->where('start_date', '<', now()->format('Y-m-d'))
                            ->where('is_cancelled', false);
                        break;
                    case 'cancelled':
                        $query->where('is_cancelled', true);
                        break;
                }
            }

            $bookings = $query->latest('start_date')->paginate(20);

            return BookingResource::collection($bookings);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar agendamentos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamentos'], 500);
        }
    }

    /**
     * Create a new booking with support for multiple contiguous slots
     */
    public function store(CreateMobileBookingRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $data = $request->validated();
            
            // Get Court
            $court = Court::with('tenant')->findOrFail($data['court_id']);

            // Get slots from request
            $slots = $data['slots'];
            
            // Determine start and end times from slots
            $startTime = $slots[0]['start'];
            $endTime = $slots[count($slots) - 1]['end'];

            // Calculate price based on number of slots and court type pricing
            $pricePerInterval = $court->courtType->price_per_interval ?? 0;
            $totalPrice = $pricePerInterval * count($slots);

            // Check tenant auto-confirmation setting
            $isPending = !$court->tenant->auto_confirm_bookings;

            // Prepare Booking Data
            $bookingData = [
                'tenant_id' => $court->tenant_id,
                'court_id' => $court->id,
                'user_id' => $user->id,
                'currency_id' => \App\Models\Manager\CurrencyModel::where('code', $court->tenant->currency)->first()->id ?? 1,
                'start_date' => $data['start_date'],
                'end_date' => $data['start_date'], // Single day booking
                'start_time' => $startTime,
                'end_time' => $endTime,
                'price' => $totalPrice,
                'paid_at_venue' => false,
                'is_paid' => false,
                'is_pending' => $isPending, // Based on tenant configuration
                'is_cancelled' => false,
            ];

            $booking = Booking::create($bookingData);

            // Generate QR code with hashed booking ID
            try {
                $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
                $qrCodeInfo = \App\Actions\General\QrCodeAction::create(
                    $court->tenant_id,
                    $booking->id,
                    $bookingIdHashed
                );
                
                // Update booking with QR code path
                $booking->update(['qr_code' => $qrCodeInfo->url]);
            } catch (\Exception $qrException) {
                // Log QR generation error but don't fail the booking
                \Log::error('Failed to generate QR code for booking', [
                    'booking_id' => $booking->id,
                    'error' => $qrException->getMessage()
                ]);
            }

            // Create notification
            try {
                $this->notificationService->createBookingNotification($booking, 'created');
            } catch (\Exception $notifException) {
                \Log::error('Failed to create notification for booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'created');
            } catch (\Exception $emailException) {
                \Log::error('Failed to send booking email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            DB::commit();

            return BookingResource::make($booking->load(['court.courtType', 'court.primaryImage', 'currency']));

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao criar agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao criar agendamento'], 500);
        }
    }

    /**
     * Get booking details
     */
    public function show(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            $booking = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.images', 'court.tenant', 'currency'])
                ->findOrFail($bookingId);

            return BookingResource::make($booking);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamento'], 500);
        }
    }

    /**
     * Cancel a booking
     */
    public function destroy(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            $booking = Booking::where('user_id', $user->id)
                ->findOrFail($bookingId);

            // Check if booking can be cancelled (e.g., not in the past, not already cancelled)
            if ($booking->is_cancelled) {
                return response()->json(['message' => 'Este agendamento já foi cancelado'], 400);
            }

            if ($booking->start_date < now()->format('Y-m-d')) {
                return response()->json(['message' => 'Não é possível cancelar agendamentos passados'], 400);
            }

            // Mark as cancelled instead of deleting
            $booking->update(['is_cancelled' => true]);

            // Delete QR code if exists
            if ($booking->qr_code) {
                try {
                    \App\Actions\General\QrCodeAction::delete($booking->tenant_id, $booking->qr_code);
                } catch (\Exception $qrException) {
                    \Log::error('Failed to delete QR code for cancelled booking', [
                        'booking_id' => $booking->id,
                        'error' => $qrException->getMessage()
                    ]);
                }
            }

            // Create notification
            try {
                $this->notificationService->createBookingNotification($booking, 'cancelled');
            } catch (\Exception $notifException) {
                \Log::error('Failed to create notification for cancelled booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'cancelled');
            } catch (\Exception $emailException) {
                \Log::error('Failed to send cancellation email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return response()->json(['message' => 'Agendamento cancelado com sucesso']);

        } catch (\Exception $e) {
            \Log::error('Erro ao cancelar agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao cancelar agendamento'], 500);
        }
    }

    /**
     * Get user booking statistics only
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get booking statistics
            $totalBookings = Booking::where('user_id', $user->id)->count();
            
            $upcomingBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->where('is_cancelled', false)
                ->count();
            
            $pastBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '<', now()->format('Y-m-d'))
                ->where('is_cancelled', false)
                ->count();
            
            $cancelledBookings = Booking::where('user_id', $user->id)
                ->where('is_cancelled', true)
                ->count();
            
            $pendingBookings = Booking::where('user_id', $user->id)
                ->where('is_pending', true)
                ->where('is_cancelled', false)
                ->count();

            return response()->json([
                'data' => [
                    'total_bookings' => $totalBookings,
                    'upcoming_bookings' => $upcomingBookings,
                    'past_bookings' => $pastBookings,
                    'cancelled_bookings' => $cancelledBookings,
                    'pending_bookings' => $pendingBookings,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar estatísticas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar estatísticas'], 500);
        }
    }

    /**
     * Get user recent bookings with pagination
     */
    public function getRecentBookings(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get recent bookings with pagination (lazy load support)
            $perPage = $request->input('per_page', 10);
            $bookings = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.primaryImage', 'court.tenant', 'currency'])
                ->latest('created_at')
                ->paginate($perPage);

            return BookingResource::collection($bookings);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar agendamentos recentes', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamentos recentes'], 500);
        }
    }

    /**
     * Get next upcoming booking
     */
    public function getNextBooking(Request $request)
    {
        try {
            $user = $request->user();
            
            $nextBooking = Booking::where('user_id', $user->id)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->where('is_cancelled', false)
                ->with(['court.courtType', 'court.primaryImage', 'court.tenant', 'currency'])
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->first();

            return $nextBooking ? BookingResource::make($nextBooking) : response()->json(['data' => null]);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar próximo agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar próximo agendamento'], 500);
        }
    }
}
