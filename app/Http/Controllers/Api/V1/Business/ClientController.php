<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Specific\UserResourceSpecific;
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
            $clients = User::query()
                ->paginate(15);

            return $this->dataResponse(
                UserResourceSpecific::collection($clients)->response()->getData(true)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve clients.', $e->getMessage(), 500);
        }
    }

    public function store(CreateClientRequest $request, $tenantId)
    {
        try {
            $this->beginTransactionSafe();

            $validated = $request->validated();

            // Create user with is_app_user = false
            $client = User::create(array_merge($validated, [
                'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                'is_app_user' => false,
            ]));

            $this->commitSafe();

            return $this->dataResponse(
                (new UserResourceSpecific($client))->resolve(),
                201
            );

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to create client.', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $tenantId, $clientId)
    {
        try {
            $decodedId = EasyHashAction::decode($clientId, 'user-id');
            $client = User::find($decodedId);

            if (!$client) {
                return $this->errorResponse('Client not found.', null, 404);
            }

            return $this->dataResponse(
                (new UserResourceSpecific($client))->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve client.', $e->getMessage(), 500);
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
                return $this->errorResponse('Client not found.', null, 404);
            }

            // Check permission - cannot edit app users
            if ($client->is_app_user) {
                $this->rollBackSafe();
                return $this->errorResponse('Cannot edit clients registered via mobile app.', null, 403);
            }

            $validated = $request->validated();

            $client->update($validated);

            $this->commitSafe();

            return $this->dataResponse(
                (new UserResourceSpecific($client))->resolve()
            );

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to update client.', $e->getMessage(), 500);
        }
    }
}
