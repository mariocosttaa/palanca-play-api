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
    /**
     * Get a list of clients with optional search
     * 
     * @queryParam search string Search by name, surname, email, or phone. Example: "John"
     * 
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<int, UserResourceGeneral>
     * @response 200 \Illuminate\Http\Resources\Json\ResourceCollection<int, UserResourceGeneral>
     * @response 500 {"message": "Server error"}
     */
    public function index(Request $request, $tenantId)
    {
        try {
            $tenant = $request->tenant;
            // List users who belong to this tenant (have bookings or were created for this tenant)
            $query = User::query()
                ->forTenant($tenant->id)
                ->with('country')
                ->withCount('bookings');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('surname', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            $clients = $query->paginate(15);

            return UserResourceGeneral::collection($clients);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve clients.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve clients.'], 500);
        }
    }

    /**
     * Create a new client
     * 
     * @return UserResourceSpecific
     * @response 200 UserResourceSpecific
     * @response 500 {"message": "Server error"}
     */
    public function store(CreateClientRequest $request, $tenantId)
    {
        try {
            $this->beginTransactionSafe();

            $tenant = $request->tenant;

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

            // Link user to tenant
            $client->tenants()->attach($tenant->id);

            $this->commitSafe();

            return new UserResourceSpecific($client);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to create client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create client.'], 500);
        }
    }

    /**
     * Get a specific client by ID
     * 
     * @return UserResourceSpecific
     * @response 200 UserResourceSpecific
     * @response 404 {"message": "Client not found"}
     * @response 500 {"message": "Server error"}
     */
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

            return new UserResourceSpecific($client);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve client.'], 500);
        }
    }

    /**
     * Update an existing client
     * 
     * @return UserResourceSpecific
     * @response 200 UserResourceSpecific
     * @response 403 {"message": "Cannot edit clients registered via mobile app"}
     * @response 404 {"message": "Client not found"}
     * @response 500 {"message": "Server error"}
     */
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

            return new UserResourceSpecific($client);

        } catch (\Exception $e) {
            \Log::error('Failed to update client.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update client.'], 500);
        }
    }

    /**
     * Get statistics for a specific client
     * 
     * @return \Illuminate\Http\JsonResponse
     * @response 200 {"data": {"total": 10, "pending": 2, "cancelled": 1, "not_present": 0}}
     * @response 404 {"message": "Client not found"}
     * @response 500 {"message": "Server error"}
     */
    public function stats(Request $request, $tenantId, $clientId)
    {
        try {
            $tenant = $request->tenant;
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::findOrFail($decodedId);

            $stats = [
                'total' => $client->bookings()->forTenant($tenant->id)->count(),
                'pending' => $client->bookings()->forTenant($tenant->id)->pending()->count(),
                'cancelled' => $client->bookings()->forTenant($tenant->id)->cancelled()->count(),
                'not_present' => $client->bookings()
                    ->forTenant($tenant->id)
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

    /**
     * Get bookings for a specific client
     * 
     * @queryParam per_page int Number of items per page. Example: 15
     * 
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<int, BookingResourceGeneral>
     * @response 200 \Illuminate\Http\Resources\Json\ResourceCollection<int, BookingResourceGeneral>
     * @response 404 {"message": "Client not found"}
     * @response 500 {"message": "Server error"}
     */
    public function bookings(Request $request, $tenantId, $clientId)
    {
        try {
            $tenant = $request->tenant;
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::findOrFail($decodedId);

            $perPage = $request->input('per_page', 15);
            $bookings = $client->bookings()
                ->forTenant($tenant->id)
                ->latest()
                ->paginate($perPage);

            return BookingResourceGeneral::collection($bookings);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve client bookings.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve client bookings.'], 500);
        }
    }
}
