<?php

namespace App\Http\Controllers\Api\V1\Business\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\BusinessUserLoginRequest;
use App\Http\Requests\Api\V1\Auth\BusinessUserRegisterRequest;
use App\Http\Resources\Specific\BusinessUserResourceSpecific;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BusinessUserAuthController extends Controller
{
    /**
     * Register a new business user
     */
    public function register(BusinessUserRegisterRequest $request): JsonResponse
    {
        $businessUser = BusinessUser::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'country_id' => $request->country_id,
            'calling_code' => $request->calling_code,
            'phone' => $request->phone,
            'timezone' => $request->timezone,
        ]);

        $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new BusinessUserResourceSpecific($businessUser),
            ],
        ], 201);
    }

    /**
     * Login business user
     */
    public function login(BusinessUserLoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $businessUser = BusinessUser::where('email', $request->email)->first();
        $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new BusinessUserResourceSpecific($businessUser),
            ],
        ]);
    }

    /**
     * Logout business user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * Get authenticated business user
     */
    public function me(Request $request): JsonResponse
    {
        $businessUser = $request->user()->load('country');

        return response()->json([
            'data' => new BusinessUserResourceSpecific($businessUser),
        ]);
    }
}

