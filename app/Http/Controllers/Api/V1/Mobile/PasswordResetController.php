<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UserForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\UserVerifyPasswordResetRequest;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-MOBILE] Password Reset
 */
class PasswordResetController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Request password reset code
     * 
     * Sends a 6-digit recovery code to the provided email address if it exists in the users table.
     * 
     * @unauthenticated
     */
    public function requestCode(UserForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $email = $request->email;

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

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao enviar código de recuperação', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao enviar código de recuperação'], 500);
        }
    }

    /**
     * Verify code and reset password
     * 
     * Verifies the recovery code and updates the user's password.
     * 
     * @unauthenticated
     */
    public function verifyCode(UserVerifyPasswordResetRequest $request): JsonResponse
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

            // Update user password
            $user = User::where('email', $email)->first();
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
            Log::error('Erro ao redefinir senha', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao redefinir senha'], 500);
        }
    }

    /**
     * Check if code is valid
     * 
     * Checks if a recovery code is valid without using it.
     * 
     * @unauthenticated
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
            Log::error('Erro ao verificar código', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao verificar código'], 500);
        }
    }
}
