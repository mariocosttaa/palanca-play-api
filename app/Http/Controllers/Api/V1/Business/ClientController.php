<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request, $tenantId)
    {
        // For now, list all users. In future, might filter by tenant bookings.
        // Or maybe we should filter by users who have bookings with this tenant?
        // The user request was "create noe client to see client details".
        // Let's just return all users for now, or maybe paginate.
        
        $clients = User::query()
            ->paginate(15);

        return $this->dataResponse($clients);
    }

    public function store(Request $request, $tenantId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        // Create user with is_app_user = false
        $client = User::create(array_merge($validated, [
            'password' => Hash::make(\Illuminate\Support\Str::random(16)), // Random password for business-created users?
            'is_app_user' => false,
        ]));

        return $this->dataResponse($client, status: 201);
    }

    public function show(Request $request, $tenantId, $clientId)
    {
        $clientId = EasyHashAction::decode($clientId, 'user-id'); // Assuming user IDs are hashed? Or maybe not? 
        // User model uses HasHashid trait, so likely hashed in routes if we use binding, but here we might need manual decoding if passed as ID.
        // Let's check if we use route model binding or manual.
        // Given previous controllers, we use manual decoding often.
        // But User might be different. Let's assume manual decode for consistency with other business controllers.
        // Wait, User ID hashing usually uses 'user-id' scope? Let's check User model.
        // User model uses HasHashid.
        
        // If we use route param {client_id}, it's a string.
        
        $client = User::find($clientId);
        
        // If not found by direct ID, try decoding
        if (!$client) {
             $decodedId = EasyHashAction::decode($clientId, 'user-id');
             if ($decodedId) {
                 $client = User::find($decodedId);
             }
        }

        if (!$client) {
            return $this->errorResponse('Cliente não encontrado', status: 404);
        }

        return $this->dataResponse($client);
    }

    public function update(Request $request, $tenantId, $clientId)
    {
        $clientId = EasyHashAction::decode($clientId, 'user-id');
        $client = User::find($clientId);

        if (!$client) {
            return $this->errorResponse('Cliente não encontrado', status: 404);
        }

        // Check permission
        if ($client->is_app_user) {
            return $this->errorResponse('Não é possível editar clientes registrados pelo aplicativo.', status: 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($client->id)],
            'phone' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        $client->update($validated);

        return $this->dataResponse($client);
    }
}
