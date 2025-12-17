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
     * Initiate registration (Step 1: Send Verification Code)
     * @unauthenticated
     */
    public function initiateRegistration(BusinessUserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            // Validate basic fields (email uniqueness is handled by Request)
            // We don't create the user yet.

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
    public function completeRegistration(BusinessUserRegisterRequest $request, \App\Services\EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            // Manual validation for code as it's not in the main request
            // Ideally should be in a separate Request class or added to BusinessUserRegisterRequest
            if (!$request->code) {
                 return $this->errorResponse('Verification code is required.', null, 422);
            }

            if (!$emailService->verifyCode($request->email, $request->code, \App\Enums\EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return $this->errorResponse('Invalid or expired verification code.', null, 422);
            }

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

            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            $this->commitSafe();

            return $this->dataResponse([
                'token' => $token,
                'user' => (new BusinessUserResourceSpecific($businessUser))->resolve(),
            ], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to register business user.', $e->getMessage(), 500);
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
                return $this->errorResponse('Invalid credentials.', null, 401);
            }

            $token = $businessUser->createToken($request->device_name ?? 'api-client')->plainTextToken;

            return $this->dataResponse([
                'token' => $token,
                'user' => (new BusinessUserResourceSpecific($businessUser))->resolve(),
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed.', $e->getMessage(), 500);
        }
    }

    /**
     * Logout business user (revoke current token)
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
     * Get authenticated business user
     */
    public function me(Request $request): JsonResponse
    {

        //get the token
        

        try {
            $businessUser = $request->user()->load('country');

            return $this->dataResponse(
                (new BusinessUserResourceSpecific($businessUser))->resolve()
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user profile.', $e->getMessage(), 500);
        }
    }
}
