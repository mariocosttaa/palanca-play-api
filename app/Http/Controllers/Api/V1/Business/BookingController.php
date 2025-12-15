<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateBookingRequest;
use App\Http\Requests\Api\V1\Business\UpdateBookingRequest;
use App\Http\Resources\Api\V1\Business\BookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->tenant;
        $query = Booking::forTenant($tenant->id)->with(['user', 'court', 'currency']);

        if ($request->has('date')) {
            $query->onDate($request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        }

        if ($request->has('court_id')) {
            $courtId = EasyHashAction::decode($request->court_id, 'court-id');
            $query->forCourt($courtId);
        }

        $bookings = $query->latest()->paginate(20);

        return $this->dataResponse(BookingResource::collection($bookings)->response()->getData(true));
    }

    public function store(CreateBookingRequest $request)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $data = $request->validated();
            
            // Handle Client
            $clientId = $data['client_id'] ?? null;
            
            if (!$clientId && isset($data['client'])) {
                // Create new client
                $clientData = $data['client'];
                
                // If email is provided, check if user exists (should be handled by validation, but double check)
                if (!empty($clientData['email'])) {
                    $existingUser = User::where('email', $clientData['email'])->first();
                    if ($existingUser) {
                        $clientId = $existingUser->id;
                    }
                }
                
                if (!$clientId) {
                    $newUser = User::create([
                        'name' => $clientData['name'],
                        'email' => $clientData['email'] ?? null,
                        'phone' => $clientData['phone'] ?? null,
                        'calling_code' => $clientData['calling_code'] ?? null,
                        'password' => Hash::make(Str::random(16)),
                        'country_id' => $tenant->country_id, // Default to tenant's country
                    ]);
                    $clientId = $newUser->id;
                }
            }

            if (!$clientId) {
                return $this->errorResponse('Cliente inválido ou não fornecido.');
            }

            // Get Court
            $court = Court::find($data['court_id']);
            if (!$court || $court->tenant_id !== $tenant->id) {
                return $this->errorResponse('Quadra inválida.');
            }

            // Prepare Booking Data
            $bookingData = [
                'tenant_id' => $tenant->id,
                'court_id' => $court->id,
                'user_id' => $clientId,
                'currency_id' => \App\Models\Manager\CurrencyModel::where('code', $tenant->currency)->first()->id ?? 1,
                'start_date' => $data['start_date'],
                'end_date' => $data['start_date'], // Single day booking for now
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'price' => $data['price'] ?? 0, // Should calculate based on court price if not provided
                'paid_at_venue' => $data['paid_at_venue'] ?? false,
                'is_paid' => $data['paid_at_venue'] ?? false,
                'is_pending' => false, // Created by business, so confirmed by default? Or pending?
                'is_cancelled' => false,
            ];

            // If paid at venue, it is paid
            if ($bookingData['paid_at_venue']) {
                $bookingData['is_paid'] = true;
            }

            // Check availability
            if (!$court->checkAvailability($data['start_date'], $data['start_time'], $data['end_time'])) {
                return $this->errorResponse('Horário indisponível.');
            }

            $booking = Booking::create($bookingData);

            // Generate QR code with hashed booking ID
            try {
                $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
                $qrCodeInfo = \App\Actions\General\QrCodeAction::create(
                    $tenant->id,
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

            $this->commitSafe();

            return $this->dataResponse(BookingResource::make($booking)->resolve());

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Erro ao criar agendamento', $e->getMessage());
        }
    }

    public function show(Request $request, $bookingId)
    {
        $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
        $booking = Booking::forTenant($request->tenant->id)->with(['user', 'court'])->find($bookingId);

        if (!$booking) {
            return $this->errorResponse('Agendamento não encontrado', status: 404);
        }

        return $this->dataResponse(BookingResource::make($booking)->resolve());
    }

    public function update(UpdateBookingRequest $request, $bookingId)
    {
        try {
            // Use the decoded ID from the request (prepared in UpdateBookingRequest)
            $decodedBookingId = $request->booking_id;
            
            // Fallback if not in request (shouldn't happen if request is used)
            if (!$decodedBookingId) {
                $decodedBookingId = EasyHashAction::decode($bookingId, 'booking-id');
            }

            $booking = Booking::forTenant($request->tenant->id)->find($decodedBookingId);

            if (!$booking) {
                return $this->errorResponse('Agendamento não encontrado', status: 404);
            }

            $data = $request->validated();
            
            // Handle paid_at_venue logic
            if (isset($data['paid_at_venue'])) {
                if ($data['paid_at_venue']) {
                    $data['is_paid'] = true;
                }
            }

            $booking->update($data);

            return $this->dataResponse(BookingResource::make($booking)->resolve());

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao atualizar agendamento', $e->getMessage());
        }
    }

    public function destroy(Request $request, $bookingId)
    {
        try {
            $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $booking = Booking::forTenant($request->tenant->id)->find($bookingId);

            if (!$booking) {
                return $this->errorResponse('Agendamento não encontrado', status: 404);
            }

            $booking->delete();

            return $this->successResponse('Agendamento removido com sucesso');

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao remover agendamento', $e->getMessage());
        }
    }
    public function confirmPresence(Request $request, $tenantId, $bookingId)
    {
        try {
            $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $booking = Booking::forTenant($request->tenant->id)->find($bookingId);

            if (!$booking) {
                return $this->errorResponse('Agendamento não encontrado', status: 404);
            }

            $request->validate([
                'present' => 'required|boolean',
            ]);

            $booking->update([
                'present' => $request->present,
            ]);

            return $this->dataResponse(BookingResource::make($booking)->resolve());

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao confirmar presença', $e->getMessage());
        }
    }
}
