<?php

namespace App\Http\Controllers\Api\V1\Mobile\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UserLoginRequest;
use App\Http\Requests\Api\V1\Auth\UserRegisterRequest;
use App\Http\Resources\Specific\UserResourceSpecific;
use App\Models\User;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Validation\ValidationException;
use App\Enums\EmailTypeEnum;

/**
 * @tags [API-MOBILE] Auth
 */
class UserAuthController extends Controller
{
    /**
     * Initiate registration (Step 1: Send Verification Code)
     * @unauthenticated
     */
    public function initiateRegistration(UserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            // Validate basic fields (email uniqueness is handled by Request)
            
            $emailService->sendVerificationCode($request->email, EmailTypeEnum::CONFIRMATION_EMAIL);

            return $this->dataResponse([
                'email' => $request->email,
                'expires_in' => 900 // 15 minutes
            ], 200);

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            return $this->errorResponse($e->getMessage(), null, $e->getCode());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send verification code.', $e->getMessage(), 500);
        }
    }

    /**
     * Complete registration (Step 2: Verify Code and Create User)
     * @unauthenticated
     */
    public function completeRegistration(UserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            if (!$request->code) {
                 return $this->errorResponse('Verification code is required.', null, 422);
            }

            if (!$emailService->verifyCode($request->email, $request->code, \App\Enums\EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return $this->errorResponse('Invalid or expired verification code.', null, 422);
            }

            $this->beginTransactionSafe();

            $callingCode = null;
            if ($request->country_id) {
                $country = Country::find($request->country_id);
                $callingCode = $country?->calling_code;
            }

            $user = User::create([
                'name' => $request->name,
                'surname' => $request->surname,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'country_id' => $request->country_id,
                'calling_code' => $callingCode,
                'phone' => $request->phone,
                'timezone' => $request->timezone,
                'is_app_user' => true,
            ]);

            $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

            $this->commitSafe();

            return $this->dataResponse([
                'token' => $token,
                'user' => (new UserResourceSpecific($user))->resolve(),
            ], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to register user.', $e->getMessage(), 500);
        }
    }

    /**
     * Login user
     * @unauthenticated
     */
    public function login(UserLoginRequest $request): JsonResponse
    {
        try {
            $request->authenticate();

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return $this->errorResponse('Invalid credentials.', null, 401);
            }

            $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

            return $this->dataResponse([
                'token' => $token,
                'user' => (new UserResourceSpecific($user))->resolve(),
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed.', $e->getMessage(), 500);
        }
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse('Logged out successfully.');

        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed.', $e->getMessage(), 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load('country');

            return $this->dataResponse(
                (new UserResourceSpecific($user))->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user.', $e->getMessage(), 500);
        }
    }
}
