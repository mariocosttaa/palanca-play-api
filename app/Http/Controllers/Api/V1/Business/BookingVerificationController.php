<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Zxing\QrReader;
use Illuminate\Http\JsonResponse;
use Hashids\HashidsException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Booking Verification
 */
class BookingVerificationController extends Controller
{

    /**
     * Verify booking via QR Code
     * 
     * Verifies a booking by processing an uploaded QR code image. Decodes the QR code to find the booking and marks it as verified.
     * 
     * @return array{booking: \App\Http\Resources\Business\V1\Specific\BookingResource}
     */
    public function verify(Request $request, string $tenantId): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $qrData = $request->input('code');
            
            Log::info('QR Verification', [
                'method' => 'direct_code',
                'qr_data_found' => (bool) $qrData,
                'qr_data' => $qrData
            ]);

            if (!$qrData) {
                abort(400, 'Código inválido ou não fornecido');
            }

            // Decode the hashed booking ID from QR code
            $bookingId = EasyHashAction::decode($qrData, 'booking-id');
            
            if (!$bookingId) {
                 abort(400, 'QR Code inválido (ID não encontrado)');
            }
            
            // Find the booking
            $booking = Booking::with(['court.courtType', 'court.tenant', 'user', 'currency'])
                ->findOrFail($bookingId);

            // Mark as verified
            $booking->update(['qr_code_verified' => true]);

            return response()->json([
                'booking' => new BookingResource($booking)
            ]);

        } catch (\Exception $e) {
            // Handle QR decode errors
            if (str_contains($e->getMessage(), 'Invalid') || $e instanceof HashidsException) {
                abort(400, 'QR Code inválido');
            }
            
            if ($e instanceof ModelNotFoundException) {
                abort(404, 'Agendamento não encontrado');
            }
            
            Log::error('Erro ao verificar agendamento', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao verificar agendamento');
        }
    }
}
