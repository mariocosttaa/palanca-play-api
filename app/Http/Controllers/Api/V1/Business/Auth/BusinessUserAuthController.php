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
use Illuminate\Validation\ValidationException;
use App\Enums\EmailTypeEnum;

/**
 * @tags [API-BUSINESS] Auth
 */
class BusinessUserAuthController extends Controller
{
    /**
     * Register business user (Step 1: Create User & Send Verification Code)
     * @unauthenticated
     */
    public function register(BusinessUserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

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

            // Send verification code
            try {
                $emailService->sendVerificationCode($businessUser->email, EmailTypeEnum::CONFIRMATION_EMAIL);
            } catch (\App\Exceptions\EmailRateLimitException $e) {
                // Suppress rate limit error on registration
            }

            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            $this->commitSafe();

            return response()->json(['data' => [
                'token' => $token,
                'user' => (new BusinessUserResourceSpecific($businessUser))->resolve(),
                'verification_needed' => true,
                'message' => 'User registered successfully. Please verify your email.'
            ]], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to register business user.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to register business user.'], 500);
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
                return response()->json(['message' => 'Email already verified.']);
            }

            if (!$emailService->verifyCode($user->email, $request->code, EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return response()->json(['message' => 'Invalid or expired verification code.'], 422);
            }

            $user->markEmailAsVerified();

            return response()->json(['message' => 'Email verified successfully.']);

        } catch (\Exception $e) {
            \Log::error('Failed to verify email.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to verify email.'], 500);
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
                return response()->json(['message' => 'Email already verified.'], 400);
            }

            $emailService->sendVerificationCode($user->email, EmailTypeEnum::CONFIRMATION_EMAIL);

            return response()->json(['message' => 'Verification code sent successfully.']);

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Failed to send verification code.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to send verification code.'], 500);
        }
    }

    /**
     * Check Verification Status
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json(['data' => [
                'verified' => $user->hasVerifiedEmail(),
                'email' => $user->email,
            ]]);

        } catch (\Exception $e) {
            \Log::error('Failed to check status.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to check status.'], 500);
        }
    }

    /**
     * Login business user
     * @unauthenticated
     */
    public function login(BusinessUserLoginRequest $request): JsonResponse
    {
        try {
            $request->authenticate();

            $businessUser = BusinessUser::where('email', $request->email)->first();
            
            if (!$businessUser) {
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }

            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            return response()->json(['data' => [
                'token' => $token,
                'user' => (new BusinessUserResourceSpecific($businessUser))->resolve(),
            ]]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Login failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Login failed.'], 500);
        }
    }

    /**
     * Logout business user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Logged out successfully.']);

        } catch (\Exception $e) {
            \Log::error('Logout failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Logout failed.'], 500);
        }
    }

    /**
     * Get authenticated business user
     */
    public function me(Request $request): JsonResponse
    {

        //get the token
        

        try {
            $businessUser = $request->user()->load('country');

            return BusinessUserResourceSpecific::make($businessUser)->response();

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve user profile.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve user profile.'], 500);
        }
    }
    /**
     * Google Login
     * @unauthenticated
     */
    public function googleLogin(Request $request): JsonResponse
    {
        try {
            $request->validate(['token' => 'required|string']);

            // Verify the token with Google
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->userFromToken($request->token);

            $this->beginTransactionSafe();

            $businessUser = BusinessUser::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'surname' => '', // Google doesn't always provide surname separately
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'google_login' => true,
                    'email_verified_at' => now(),
                ]
            );

            // If user exists but wasn't created via Google
            if (!$businessUser->google_login) {
                $businessUser->update(['google_login' => true]);
            }
            
            // Ensure email is verified
            if (!$businessUser->hasVerifiedEmail()) {
                $businessUser->markEmailAsVerified();
            }

            $token = $businessUser->createToken($request->device_name ?? 'google-login')->plainTextToken;

            $this->commitSafe();

            return response()->json(['data' => [
                'token' => $token,
                'user' => (new BusinessUserResourceSpecific($businessUser))->resolve(),
            ]]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            $this->rollBackSafe();
            \Log::error('Google login failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Google login failed.'], 401);
        }
    }

    /**
     * Link Google Account
     */
    public function linkGoogle(Request $request): JsonResponse
    {
        try {
            $request->validate(['token' => 'required|string']);

            $user = $request->user();

            // Verify the token with Google
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->userFromToken($request->token);

            // Enforce email matching
            if ($googleUser->getEmail() !== $user->email) {
                return response()->json(['message' => 'Google email does not match your account email.'], 422);
            }

            $user->update(['google_login' => true]);

            return response()->json(['message' => 'Google account linked successfully.']);

        } catch (\Exception $e) {
            \Log::error('Failed to link Google account.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to link Google account.'], 500);
        }
    }

    /**
     * Unlink Google Account
     */
    public function unlinkGoogle(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->google_login) {
                return response()->json(['message' => 'Google account is not linked.'], 400);
            }

            $user->update(['google_login' => false]);

            return response()->json(['message' => 'Google account unlinked successfully.']);

        } catch (\Exception $e) {
            \Log::error('Failed to unlink Google account.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to unlink Google account.'], 500);
        }
    }
}
