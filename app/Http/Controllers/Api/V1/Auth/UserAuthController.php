<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UserLoginRequest;
use App\Http\Requests\Api\V1\Auth\UserRegisterRequest;
use App\Http\Resources\Specific\UserResourceSpecific;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(UserRegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'country_id' => $request->country_id,
            'calling_code' => $request->calling_code,
            'phone' => $request->phone,
            'timezone' => $request->timezone,
        ]);

        $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResourceSpecific($user),
            ],
        ], 201);
    }

    /**
     * Login user
     */
    public function login(UserLoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResourceSpecific($user),
            ],
        ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('country');

        return response()->json([
            'data' => new UserResourceSpecific($user),
        ]);
    }
}

