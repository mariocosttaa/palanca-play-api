<?php

namespace App\Http\Controllers\Api\V1\Business\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\BusinessUserLoginRequest;
use App\Http\Requests\Api\V1\Auth\BusinessUserRegisterRequest;
use App\Http\Resources\Business\V1\Specific\BusinessUserResourceSpecific;
use App\Models\BusinessUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Enums\EmailTypeEnum;
use App\Services\EmailVerificationCodeService;
use App\Http\Resources\Business\V1\Specific\AuthResponseResource;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Auth
 */
class BusinessUserAuthController extends Controller
{
    /**
     * Register business user
     * 
     * Registers a new business user and sends a verification email.
     * 
     * @unauthenticated
     */
    public function register(BusinessUserRegisterRequest $request, EmailVerificationCodeService $emailService): JsonResponse
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

            $emailService->sendVerificationCode($businessUser->email, EmailTypeEnum::CONFIRMATION_EMAIL);

            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            $this->commitSafe();

            return (new AuthResponseResource([
                'token' => $token,
                'user' => $businessUser,
                'verification_needed' => true,
                'message' => 'User registered successfully. Please verify your email.'
            ]))->response()->setStatusCode(201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Failed to register business user.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to register business user.');
        }
    }

    /**
     * Verify email address
     * 
     * Verifies the business user's email address using the 6-digit code sent during registration or via the resend endpoint.
     * 
     * @bodyParam code string required The 6-digit verification code sent to the email. Example: 123456
     * 
     * @return array{message: string}
     */
    public function verifyEmail(Request $request, EmailVerificationCodeService $emailService): JsonResponse
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
            Log::error('Failed to verify email.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to verify email.'], 500);
        }
    }

    /**
     * Resend verification code
     * 
     * Resends the 6-digit verification code to the business user's email address.
     * 
     * **Rate Limits:**
     * - Burst: 3 requests per 170 seconds.
     * - Daily: 10 requests per 24 hours.
     * 
     * @return array{message: string}
     */
    public function resendVerificationCode(Request $request, EmailVerificationCodeService $emailService): JsonResponse
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
            Log::error('Failed to send verification code.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to send verification code.'], 500);
        }
    }

    /**
     * Check verification status
     * 
     * Returns whether the authenticated business user has verified their email address.
     * 
     * @return array{data: array{verified: bool, email: string}}
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
            Log::error('Failed to check status.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to check status.'], 500);
        }
    }

    /**
     * Login business user
     * 
     * Authenticates a business user and returns an access token.
     * 
     * @unauthenticated
     */
    public function login(BusinessUserLoginRequest $request): AuthResponseResource
    {
        try {
            $request->authenticate();

            $businessUser = BusinessUser::where('email', $request->email)->first();
            
            if (!$businessUser || !Hash::check($request->password, $businessUser->password)) {
                 abort(401, 'Invalid credentials.');
            }


            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            return new AuthResponseResource([
                'token' => $token,
                'user' => $businessUser,
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Login failed.', ['error' => $e->getMessage()]);
            abort(500, 'Login failed.');
        }
    }

    /**
     * Logout business user
     * 
     * Revokes the current access token for the authenticated user.
     * 
     * @return array{message: string}
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Logged out successfully.']);

        } catch (\Exception $e) {
            Log::error('Logout failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Logout failed.'], 500);
        }
    }

    /**
     * Get authenticated business user
     * 
     * Retrieves the profile information of the currently authenticated business user.
     */
    public function me(Request $request): BusinessUserResourceSpecific
    {
        try {
            $businessUser = $request->user()->load(['country', 'timezone']);

            return new BusinessUserResourceSpecific($businessUser);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user profile.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve user profile.');
        }
    }
    /**
     * Google Login
     * 
     * Authenticates or registers a user using a Google OAuth token.
     * 
     * @unauthenticated
     */
    public function googleLogin(Request $request): AuthResponseResource
    {
        try {
            $request->validate(['token' => 'required|string']);

            // Verify the token with Google
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);

            $this->beginTransactionSafe();

            $businessUser = BusinessUser::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'surname' => '', // Google doesn't always provide surname separately
                    'password' => Hash::make(Str::random(24)),
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

            return new AuthResponseResource([
                'token' => $token,
                'user' => $businessUser,
            ]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Google login failed.', ['error' => $e->getMessage()]);
            abort(401, 'Google login failed.');
        }
    }

    /**
     * Link Google Account
     * 
     * Links a Google account to the authenticated user's account.
     * 
     * @return array{message: string}
     */
    public function linkGoogle(Request $request): JsonResponse
    {
        try {
            $request->validate(['token' => 'required|string']);

            $user = $request->user();

            // Verify the token with Google
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);

            // Enforce email matching
            if ($googleUser->getEmail() !== $user->email) {
                return response()->json(['message' => 'Google email does not match your account email.'], 422);
            }

            $user->update(['google_login' => true]);

            return response()->json(['message' => 'Google account linked successfully.']);

        } catch (\Exception $e) {
            Log::error('Failed to link Google account.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to link Google account.'], 500);
        }
    }

    /**
     * Unlink Google Account
     * 
     * Unlinks the Google account from the authenticated user's account.
     * 
     * @return array{message: string}
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
            Log::error('Failed to unlink Google account.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to unlink Google account.'], 500);
        }
    }
}
