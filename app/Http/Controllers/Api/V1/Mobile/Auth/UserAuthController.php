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
     * Register user (Step 1: Create User & Send Verification Code)
     * @unauthenticated
     */
    public function register(UserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
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

            // Send verification code
            try {
                $emailService->sendVerificationCode($user->email, EmailTypeEnum::CONFIRMATION_EMAIL);
            } catch (\App\Exceptions\EmailRateLimitException $e) {
                // If rate limit hit during registration (unlikely for new email), we still create user but warn
                // Or we could rollback. But better to let them exist and retry sending later.
                // For now, we'll suppress and let them request resend if needed, or just let it fail if critical.
                // Actually, if we can't send code, they can't verify. But they can login and request resend.
            }

            $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

            $this->commitSafe();

            return $this->dataResponse([
                'token' => $token,
                'user' => (new UserResourceSpecific($user))->resolve(),
                'verification_needed' => true,
                'message' => 'User registered successfully. Please verify your email.'
            ], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to register user.', $e->getMessage(), 500);
        }
    }

    /**
     * Verify Email (Step 2: Verify Code)
     */
    public function verifyEmail(Request $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string',
            ]);

            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                return $this->successResponse('Email already verified.');
            }

            if (!$emailService->verifyCode($user->email, $request->code, EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return $this->errorResponse('Invalid or expired verification code.', null, 422);
            }

            $user->markEmailAsVerified();

            return $this->successResponse('Email verified successfully.');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to verify email.', $e->getMessage(), 500);
        }
    }

    /**
     * Resend Verification Code
     */
    public function resendVerificationCode(Request $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                return $this->errorResponse('Email already verified.', null, 400);
            }

            $emailService->sendVerificationCode($user->email, EmailTypeEnum::CONFIRMATION_EMAIL);

            return $this->successResponse('Verification code sent successfully.');

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            return $this->errorResponse($e->getMessage(), null, $e->getCode());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send verification code.', $e->getMessage(), 500);
        }
    }

    /**
     * Check Verification Status
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return $this->dataResponse([
                'verified' => $user->hasVerifiedEmail(),
                'email' => $user->email,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check status.', $e->getMessage(), 500);
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
