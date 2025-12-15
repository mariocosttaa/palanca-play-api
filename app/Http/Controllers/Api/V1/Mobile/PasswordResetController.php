<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Request password reset code
     */
    public function requestCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Dados inválidos', $validator->errors(), 422);
            }

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

            return $this->successResponse('Código de recuperação enviado para seu email');

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao enviar código de recuperação', $e->getMessage(), 500);
        }
    }

    /**
     * Verify code and reset password
     */
    public function verifyCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Dados inválidos', $validator->errors(), 422);
            }

            // Find valid code
            $resetCode = PasswordResetCode::forEmail($request->email)
                ->where('code', $request->code)
                ->valid()
                ->first();

            if (!$resetCode) {
                return $this->errorResponse('Código inválido ou expirado', status: 400);
            }

            // Update user password
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Mark code as used
            $resetCode->markAsUsed();

            return $this->successResponse('Senha redefinida com sucesso');

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao redefinir senha', $e->getMessage(), 500);
        }
    }

    /**
     * Check if code is valid (without using it)
     */
    public function checkCode(Request $request, string $code)
    {
        try {
            $validator = Validator::make(['code' => $code], [
                'code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Código inválido', $validator->errors(), 422);
            }

            $resetCode = PasswordResetCode::where('code', $code)
                ->valid()
                ->first();

            if (!$resetCode) {
                return $this->dataResponse([
                    'valid' => false,
                    'message' => 'Código inválido ou expirado',
                ]);
            }

            return $this->dataResponse([
                'valid' => true,
                'email' => $resetCode->email,
                'expires_at' => $resetCode->expires_at->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao verificar código', $e->getMessage(), 500);
        }
    }
}
