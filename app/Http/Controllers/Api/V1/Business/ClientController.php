<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\BookingResourceGeneral;
use App\Http\Resources\Business\V1\General\UserResourceGeneral;
use App\Http\Resources\Business\V1\Specific\UserResourceSpecific;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use App\Http\Requests\Api\V1\Business\CreateClientRequest;
use App\Http\Requests\Api\V1\Business\UpdateClientRequest;

/**
 * @tags [API-BUSINESS] Clients
 */
class ClientController extends Controller
{
    public function index(Request $request, $tenantId)
    {
        try {
            // List users with pagination
            // TODO: Filter by users who have bookings with this tenant
            // List users with pagination
            // TODO: Filter by users who have bookings with this tenant
            $clients = User::query()
                ->with('country')
                ->withCount('bookings')
                ->paginate(15);

            return UserResourceGeneral::collection($clients);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve clients.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve clients.'], 500);
        }
    }

    public function store(CreateClientRequest $request, $tenantId)
    {
        try {
            $this->beginTransactionSafe();

            // Create user with is_app_user = false
            $client = User::create([
                'name' => $request->name,
                'surname' => $request->surname,
                'email' => $request->email,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                'is_app_user' => false,
            ]);

            $this->commitSafe();

            return UserResourceSpecific::make($client);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to create client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create client.'], 500);
        }
    }

    public function show(Request $request, $tenantId, $clientId)
    {
        try {
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::with('country')
                ->withCount('bookings')
                ->find($decodedId);

            if (!$client) {
                return response()->json(['message' => 'Client not found.'], 404);
            }

            return UserResourceSpecific::make($client);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve client.'], 500);
        }
    }

    public function update(UpdateClientRequest $request, $tenantId, $clientId)
    {
        try {
            $this->beginTransactionSafe();

            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::find($decodedId);

            if (!$client) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Client not found.'], 404);
            }

            // Check permission - cannot edit app users
            if ($client->is_app_user) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Cannot edit clients registered via mobile app.'], 403);
            }

            $client->update([
                'name' => $request->name ?? $client->name,
                'surname' => $request->surname ?? $client->surname,
                'email' => $request->email ?? $client->email,
                'phone' => $request->phone ?? $client->phone,
                'country_id' => $request->country_id ?? $client->country_id,
            ]);

            $this->commitSafe();

            return UserResourceSpecific::make($client);

        } catch (\Exception $e) {
            \Log::error('Failed to update client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update client.'], 500);
        }
    }

    public function stats(Request $request, $tenantId, $clientId)
    {
        try {
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::findOrFail($decodedId);

            $stats = [
                'total' => $client->bookings()->count(),
                'pending' => $client->bookings()->pending()->count(),
                'cancelled' => $client->bookings()->cancelled()->count(),
                'not_present' => $client->bookings()
                    ->where('present', false)
                    ->where('is_cancelled', false)
                    ->where('is_pending', false)
                    ->count(),
            ];

            return response()->json(['data' => $stats]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve client stats.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve client stats.'], 500);
        }
    }

    public function bookings(Request $request, $tenantId, $clientId)
    {
        try {
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::findOrFail($decodedId);

            $perPage = $request->input('per_page', 15);
            $bookings = $client->bookings()
                ->latest()
                ->paginate($perPage);

            return BookingResourceGeneral::collection($bookings);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve client bookings.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve client bookings.'], 500);
        }
    }
}
