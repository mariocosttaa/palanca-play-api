<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Business\BookingResource;
use App\Models\Booking;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Zxing\QrReader;

class BookingVerificationController extends Controller
{
    use ApiResponse;

    /**
     * Verify booking by scanning QR code image
     * Accepts QR code image, decodes it, and returns booking details
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'qr_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        try {
            // Get uploaded QR code image
            $image = $request->file('qr_image');
            
            // Decode QR code from image
            $qrReader = new QrReader($image->getRealPath());
            $qrData = $qrReader->text();
            
            if (!$qrData) {
                return $this->errorResponse('Não foi possível ler o QR Code', status: 400);
            }

            // Decode the hashed booking ID from QR code
            $bookingId = EasyHashAction::decode($qrData, 'booking-id');
            
            // Find the booking
            $booking = Booking::with(['court.courtType', 'court.tenant', 'user', 'currency'])
                ->findOrFail($bookingId);

            // Mark as verified
            $booking->update(['qr_code_verified' => true]);

            return $this->dataResponse([
                'booking' => BookingResource::make($booking)->resolve(),
            ]);

        } catch (\Exception $e) {
            // Handle QR decode errors
            if (str_contains($e->getMessage(), 'Invalid') || $e instanceof \Hashids\HashidsException) {
                return $this->errorResponse('QR Code inválido', status: 400);
            }
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->errorResponse('Agendamento não encontrado', status: 404);
            }
            
            return $this->errorResponse('Erro ao verificar agendamento', $e->getMessage(), 500);
        }
    }
}
