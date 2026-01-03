<?php

namespace App\Http\Controllers\Api\V1\Business\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\BusinessForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\BusinessVerifyPasswordResetRequest;
use App\Models\PasswordResetCode;
use App\Models\BusinessUser;
use App\Services\EmailService;
use App\Services\EmailVerificationCodeService;
use App\Enums\EmailTypeEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Password Reset
 */
class BusinessPasswordResetController extends Controller
{
    protected $emailService;
    protected $verificationService;

    public function __construct(EmailService $emailService, EmailVerificationCodeService $verificationService)
    {
        $this->emailService = $emailService;
        $this->verificationService = $verificationService;
    }

    /**
     * Request password reset code
     * 
     * Sends a 6-digit recovery code to the provided email address if it exists in the business users table.
     * 
     * **Rate Limits:**
     * - Burst: 3 requests per 170 seconds.
     * - Daily: 10 requests per 24 hours.
     * 
     * @unauthenticated
     * 
     * @bodyParam email string required The business user's email address. Example: admin@example.com
     * 
     * @return array{message: string}
     */
    public function requestCode(BusinessForgotPasswordRequest $request): JsonResponse
    {
        try {
            $email = $request->email;

            // Check rate limit
            $this->verificationService->checkRateLimit($email, EmailTypeEnum::PASSWORD_CHANGE);

            $this->beginTransactionSafe();

            // Invalidate any existing codes for this email
            PasswordResetCode::forEmail($email)->delete();

            // Generate new code
            $code = PasswordResetCode::generateCode();
            
            // Create password reset code (expires in 15 minutes)
            PasswordResetCode::create([
                'email' => $email,
                'code' => $code,
                'expires_at' => now()->addMinutes(15),
            ]);

            // Send email with code
            $this->emailService->sendPasswordResetEmail($email, $code);

            $this->commitSafe();

            return response()->json(['message' => 'Código de recuperação enviado para seu email']);

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao enviar código de recuperação (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao enviar código de recuperação'], 500);
        }
    }

    /**
     * Verify code and reset password
     * 
     * Verifies the recovery code and updates the business user's password.
     * 
     * @unauthenticated
     * 
     * @bodyParam email string required The business user's email address. Example: admin@example.com
     * @bodyParam code string required The 6-digit code. Example: 123456
     * @bodyParam password string required The new password. Example: new-password-123
     * @bodyParam password_confirmation string required The password confirmation. Example: new-password-123
     * 
     * @return array{message: string}
     */
    public function verifyCode(BusinessVerifyPasswordResetRequest $request): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $email = $request->email;
            $code = $request->code;
            $password = $request->password;

            // Find valid code
            $resetCode = PasswordResetCode::forEmail($email)
                ->where('code', $code)
                ->valid()
                ->first();

            if (!$resetCode) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Código inválido ou expirado'], 400);
            }

            // Update business user password
            $user = BusinessUser::where('email', $email)->first();
            $user->update([
                'password' => Hash::make($password),
            ]);

            // Auto-verify user if not already verified
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            // Mark code as used
            $resetCode->markAsUsed();

            $this->commitSafe();

            return response()->json(['message' => 'Senha redefinida com sucesso']);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao redefinir senha (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao redefinir senha'], 500);
        }
    }

    /**
     * Check if code is valid
     * 
     * Checks if a recovery code is valid without using it.
     * 
     * @unauthenticated
     * 
     * @urlParam code string required The 6-digit code. Example: 123456
     * 
     * @return array{data: array{valid: bool, email?: string, expires_at?: string, message?: string}}
     */
    public function checkCode(string $code): JsonResponse
    {
        try {
            $resetCode = PasswordResetCode::where('code', $code)
                ->valid()
                ->first();

            if (!$resetCode) {
                return response()->json([
                    'data' => [
                        'valid' => false,
                        'message' => 'Código inválido ou expirado',
                    ]
                ]);
            }

            return response()->json([
                'data' => [
                    'valid' => true,
                    'email' => $resetCode->email,
                    'expires_at' => $resetCode->expires_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar código (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao verificar código'], 500);
        }
    }
}
