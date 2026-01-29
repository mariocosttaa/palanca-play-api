<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingApiContextEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\CreateMobileBookingRequest;
use App\Http\Resources\Api\V1\Mobile\MobileBookingResource;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Services\Booking\CancelBookingService;
use App\Services\Booking\CreateBookingService;
use App\Services\Booking\UpdateBookingService;
use App\Services\EmailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @tags [API-MOBILE] Bookings
 */
class MobileBookingController extends Controller
{
    public function __construct(
        protected CreateBookingService $createBookingService,
        protected UpdateBookingService $updateBookingService,
        protected CancelBookingService $cancelBookingService,
        protected NotificationService $notificationService,
        protected EmailService $emailService,
    ) {
    }

    /**
     * List authenticated user's bookings
     * 
     * @queryParam status string Filter by status (upcoming, past, cancelled). Example: upcoming
     * @queryParam page int The page number. Example: 1
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|string|in:upcoming,past,cancelled,completed,confirmed,pending',
                'court_id' => 'nullable|string',
                'modality' => 'nullable|string',
            ]);

            $user = $request->user();
            
            $query = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.primaryImage', 'currency', 'court.tenant']);

            // Filter by status
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'upcoming':
                        $query->where('start_date', '>=', now()->format('Y-m-d'))
                            ->where('status', '!=', BookingStatusEnum::CANCELLED);
                        break;
                    case 'past':
                    case 'completed':
                        $query->where('start_date', '<', now()->format('Y-m-d'))
                            ->where('status', '!=', BookingStatusEnum::CANCELLED);
                        break;
                    case 'cancelled':
                        $query->where('status', BookingStatusEnum::CANCELLED);
                        break;
                    case 'confirmed':
                        $query->where('status', BookingStatusEnum::CONFIRMED);
                        break;
                    case 'pending':
                        $query->where('status', BookingStatusEnum::PENDING);
                        break;
                }
            }

            // Filter by Court
            if ($request->court_id) {
                $courtId = EasyHashAction::decode($request->court_id, 'court-id');
                $query->where('court_id', $courtId);
            }


            // Filter by Modality
            if ($request->modality) {
                $query->whereHas('court.courtType', function ($q) use ($request) {
                    $q->where('type', $request->modality);
                });
            }

            $bookings = $query->orderBy('start_date', 'desc')
                ->orderBy('start_time', 'desc')
                ->paginate(20);

            return MobileBookingResource::collection($bookings);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar agendamentos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamentos'], 500);
        }
    }

    /**
     * Create a new booking
     * 
     * Permite criar agendamentos com múltiplos horários. Horários não contíguos serão automaticamente divididos em agendamentos separados.
     * 
     */
    public function store(CreateMobileBookingRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            
            // Get Court
            $court = Court::with('tenant')->findOrFail($data['court_id']);

            // Get slots from request
            $slots = $data['slots'];
            
            // Prepare booking data for service
            $bookingData = [
                'court_id' => $court->id,
                'client_id' => $user->id,
                'start_date' => $data['start_date'],
                'slots' => $slots, // Pass slots for automatic splitting
                'payment_method' => PaymentMethodEnum::FROM_APP,
                'payment_status' => PaymentStatusEnum::PENDING,
                // Status will be determined by service based on tenant auto_confirm_bookings setting
            ];

            // Create booking using service
            $booking = $this->createBookingService->create(
                $court->tenant,
                $bookingData,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $this->notificationService->createBookingNotification(
                    $booking,
                    'created',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'created');
            } catch (\Exception $emailException) {
                Log::error('Failed to send booking email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return MobileBookingResource::make($booking->load(['court.courtType', 'court.primaryImage', 'currency']));

        } catch (HttpException $e) {
            Log::warning('Erro ao criar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao criar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao criar agendamento'], 500);
        }
    }

    /**
     * Update a booking
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return \App\Http\Resources\Api\V1\Mobile\MobileBookingResource|\Illuminate\Http\JsonResponse
     */
    public function update(\App\Http\Requests\Api\V1\Mobile\UpdateMobileBookingRequest $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            // Find booking and verify ownership
            $booking = Booking::where('user_id', $user->id)
                ->with('tenant')
                ->findOrFail($bookingId);

            $data = $request->validated();

            // Handle slots: convert to start/end time and calculate price
            if (isset($data['slots']) && is_array($data['slots']) && count($data['slots']) > 0) {
                // 1. Set start and end times
                $data['start_time'] = $data['slots'][0]['start'];
                $data['end_time'] = $data['slots'][count($data['slots']) - 1]['end'];

                // 2. Calculate price
                 // We need the court to calculate price. Use new court_id if provided, else use existing booking's court.
                $courtId = $data['court_id'] ?? null;
                
                if ($courtId) {
                    $court = Court::with('courtType')->find($courtId);
                } else {
                     // Fetch existing booking with court if needed
                     if(!$booking->relationLoaded('court')) {
                        $booking->load('court.courtType');
                     }
                     $court = $booking->court;
                }

                if ($court && $court->courtType) {
                    $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                    $data['price'] = $pricePerInterval * count($data['slots']);
                }

                // 3. Keep slots in data for service to handle splitting logic
                // unset($data['slots']); 
            }

            // Update booking using service
            $booking = $this->updateBookingService->update(
                $booking->tenant,
                $bookingId,
                $data,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $this->notificationService->createBookingNotification(
                    $booking,
                    'updated',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for updated booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'updated');
            } catch (\Exception $emailException) {
                Log::error('Failed to send update email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return MobileBookingResource::make($booking->load(['court.courtType', 'court.primaryImage', 'currency']));

        } catch (HttpException $e) {
            Log::warning('Erro ao atualizar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao atualizar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao atualizar agendamento'], 500);
        }
    }

    /**
     * Get booking details
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return \App\Http\Resources\Api\V1\Mobile\MobileBookingResource|\Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            $booking = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.images', 'court.tenant', 'currency'])
                ->findOrFail($bookingId);

            return MobileBookingResource::make($booking);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamento'], 500);
        }
    }

    /**
     * Cancel a booking
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            // Find booking and verify ownership
            $booking = Booking::where('user_id', $user->id)
                ->findOrFail($bookingId);

            // Cancel booking using service
            $booking = $this->cancelBookingService->cancel(
                $booking->tenant,
                $bookingId,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $this->notificationService->createBookingNotification(
                    $booking,
                    'cancelled',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for cancelled booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'cancelled');
            } catch (\Exception $emailException) {
                Log::error('Failed to send cancellation email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return response()->json(['message' => 'Agendamento cancelado com sucesso']);

        } catch (HttpException $e) {
            Log::warning('Erro ao cancelar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao cancelar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao cancelar agendamento'], 500);
        }
    }

    /**
     * Get user booking statistics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get booking statistics
            $totalBookings = Booking::where('user_id', $user->id)->count();
            
            $upcomingBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->count();
            
            $pastBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '<', now()->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->count();
            
            $cancelledBookings = Booking::where('user_id', $user->id)
                ->where('status', BookingStatusEnum::CANCELLED)
                ->count();
            
            $pendingBookings = Booking::where('user_id', $user->id)
                ->where('status', BookingStatusEnum::PENDING)
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
            Log::error('Erro ao buscar estatísticas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar estatísticas'], 500);
        }
    }

    /**
     * Get next upcoming booking
     * 
     * @return \App\Http\Resources\Api\V1\Mobile\MobileBookingResource|\Illuminate\Http\JsonResponse
     */
    public function getNextBooking(Request $request)
    {
        try {
            $user = $request->user();
            $now = now();
            
            $nextBooking = Booking::where('user_id', $user->id)
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->where(function ($query) use ($now) {
                    $query->whereDate('start_date', '>', $now->format('Y-m-d'))
                        ->orWhere(function ($q) use ($now) {
                            $q->whereDate('start_date', $now->format('Y-m-d'))
                                ->whereRaw("time(start_time) >= ?", [$now->format('H:i:s')]);
                        });
                })
                ->with(['court.courtType', 'court.primaryImage', 'court.tenant', 'currency'])
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->first();

            return $nextBooking ? MobileBookingResource::make($nextBooking) : response()->json(['data' => null]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar próximo agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar próximo agendamento'], 500);
        }
    }
}
