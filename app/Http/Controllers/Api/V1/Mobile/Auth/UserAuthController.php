<?php

namespace App\Http\Controllers\Api\V1\Mobile\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UserLoginRequest;
use App\Http\Requests\Api\V1\Auth\UserRegisterRequest;
use App\Http\Resources\Business\V1\Specific\UserResourceSpecific;
use App\Http\Resources\Business\V1\Specific\UserAuthResponseResource;
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
     * Register user
     * 
     * Step 1: Create User & Send Verification Code.
     * 
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

            return (new UserAuthResponseResource([
                'token' => $token,
                'user' => $user,
                'verification_needed' => true,
                'message' => 'User registered successfully. Please verify your email.'
            ]))->response()->setStatusCode(201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            $this->rollBackSafe();
            \Log::error('Failed to register user.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to register user.');
        }
    }

    /**
     * Verify Email
     * 
     * Step 2: Verify Code.
     * 
     * @bodyParam code string required The verification code sent to the email. Example: 123456
     * 
     * @return array{message: string}
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
     * 
     * @return array{message: string}
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
     * 
     * @return array{verified: bool, email: string}
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'verified' => $user->hasVerifiedEmail(),
                'email' => $user->email,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to check status.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to check status.'], 500);
        }
    }

    /**
     * Login user
     * 
     * @unauthenticated
     * 
     * @return \App\Http\Resources\Business\V1\Specific\UserAuthResponseResource
     */
    public function login(UserLoginRequest $request): UserAuthResponseResource
    {
        try {
            $request->authenticate();

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                abort(401, 'Invalid credentials.');
            }

            $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

            return new UserAuthResponseResource([
                'token' => $token,
                'user' => $user,
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Login failed.', ['error' => $e->getMessage()]);
            abort(500, 'Login failed.');
        }
    }

    /**
     * Logout user
     * 
     * Revoke current token.
     * 
     * @return array{message: string}
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
     * Get authenticated user
     * 
     * @return \App\Http\Resources\Business\V1\Specific\UserResourceSpecific
     */
    public function me(Request $request): UserResourceSpecific
    {
        try {
            $user = $request->user()->load(['country', 'timezone']);

            return new UserResourceSpecific($user);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve user.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve user.');
        }
    }
    /**
     * Google Login
     * 
     * @unauthenticated
     * 
     * @bodyParam token string required The Google ID token. Example: eyJhbGciOiJSUzI1NiIs...
     * @bodyParam device_name string optional The device name for the token. Example: iPhone 13
     * 
     * @return \App\Http\Resources\Business\V1\Specific\UserAuthResponseResource
     */
    public function googleLogin(Request $request): UserAuthResponseResource
    {
        try {
            $request->validate(['token' => 'required|string']);

            // Verify the token with Google
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->userFromToken($request->token);

            $this->beginTransactionSafe();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'surname' => '', // Google doesn't always provide surname separately in this flow
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'google_login' => true,
                    'email_verified_at' => now(),
                    'is_app_user' => true,
                ]
            );

            // If user exists but wasn't created via Google, we might want to link it or just log them in.
            // For now, we update google_login to true if it wasn't.
            if (!$user->google_login) {
                $user->update(['google_login' => true]);
            }
            
            // Ensure email is verified if they login with Google
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $token = $user->createToken($request->device_name ?? 'google-login')->plainTextToken;

            $this->commitSafe();

            return new UserAuthResponseResource([
                'token' => $token,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Google login failed.', ['error' => $e->getMessage()]);
            abort(401, 'Google login failed.');
        }
    }

    /**
     * Link Google Account
     * 
     * @bodyParam token string required The Google ID token. Example: eyJhbGciOiJSUzI1NiIs...
     * 
     * @return array{message: string}
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
            \Log::error('Failed to unlink Google account.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to unlink Google account.'], 500);
        }
    }
}
