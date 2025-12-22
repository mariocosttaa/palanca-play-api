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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

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
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int optional Items per page. Example: 15
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
            abort(500, 'Failed to retrieve clients.');
        }
    }

    /**
     * Create a new client
     * 
     * @return UserResourceSpecific
     */
    public function store(CreateClientRequest $request, $tenantId): UserResourceSpecific
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
                'calling_code' => $request->calling_code,
                'country_id' => $request->country_id,
                'password' => Hash::make(Str::random(16)),
                'is_app_user' => false,
            ]);

            // Link user to tenant
            $client->tenants()->attach($tenant->id);

            $this->commitSafe();

            return new UserResourceSpecific($client);
        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to create client.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to create client.');
        }
    }

    /**
     * Get a specific client by ID
     * 
     * @return UserResourceSpecific
     */
    public function show(Request $request, string $tenantId, $clientId): UserResourceSpecific
    {
        try {
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::with('country')
                ->withCount('bookings')
                ->find($decodedId);

            if (!$client) {
                abort(404, 'Client not found.');
            }

            return new UserResourceSpecific($client);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve client.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve client.');
        }
    }

    /**
     * Update an existing client
     * 
     * @return UserResourceSpecific
     */
    public function update(UpdateClientRequest $request, $tenantId, $clientId): UserResourceSpecific
    {
        try {
            $this->beginTransactionSafe();

            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::find($decodedId);

            if (!$client) {
                $this->rollBackSafe();
                abort(404, 'Client not found.');
            }

            // Check permission - cannot edit app users
            if ($client->is_app_user) {
                $this->rollBackSafe();
                abort(403, 'Cannot edit clients registered via mobile app.');
            }

            $client->update([
                'name' => $request->name ?? $client->name,
                'surname' => $request->surname ?? $client->surname,
                'email' => $request->email ?? $client->email,
                'phone' => $request->phone ?? $client->phone,
                'calling_code' => $request->calling_code ?? $client->calling_code,
                'country_id' => $request->country_id ?? $client->country_id,
            ]);

            $this->commitSafe();

            return new UserResourceSpecific($client);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e; // Re-throw HTTP exceptions (abort calls)
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Failed to update client.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to update client.');
        }
    }

    /**
     * Get statistics for a specific client
     * 
     * Retrieves booking statistics for a client, including total, pending, cancelled, and not present counts.
     * 
     * @return array{data: array{total: int, pending: int, cancelled: int, not_present: int}}
     */
    public function stats(Request $request, string $tenantId, $clientId): JsonResponse
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
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve client stats.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve client stats.');
        }
    }

    /**
     * Get bookings for a specific client
     * 
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int Number of items per page. Example: 15
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function bookings(Request $request, string $tenantId, $clientId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve client bookings.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve client bookings.');
        }
    }
}
