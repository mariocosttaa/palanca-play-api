<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\V1\Profile\UpdateEmailRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Profile\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Profile\VerifyEmailUpdateRequest;
use App\Services\EmailVerificationCodeService;
use App\Enums\EmailTypeEnum;
use Illuminate\Support\Facades\Hash;

/**
 * @tags [API-MOBILE] Profile
 */
class UserProfileController extends Controller
{
    /**
     * Update user language preference
     * 
     * @bodyParam locale string required The preferred locale (en, pt, es, fr). Example: pt
     * 
     * @return array{data: array{locale: string, message: string}}
     */
    public function updateLanguage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'locale' => 'required|in:en,pt,es,fr',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $user->update([
                'locale' => $request->locale,
            ]);

            return response()->json(['data' => [
                'locale' => $user->locale,
                'message' => 'Idioma atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar idioma', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar idioma'], 500);
        }
    }

    /**
     * Update user profile
     * 
     * @bodyParam name string optional The user's first name. Example: Mario
     * @bodyParam surname string optional The user's last name. Example: Rossi
     * @bodyParam phone_code string optional The country calling code. Example: 351
     * @bodyParam phone string optional The user's phone number. Example: 912345678
     * @bodyParam country_id string optional The HashID of the country. Example: c_abc123
     * @bodyParam timezone_id string optional The HashID of the timezone. Example: tz_abc123
     * @bodyParam locale string optional The user's preferred locale. Example: pt
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $data = $request->only(['name', 'surname', 'phone', 'country_id', 'timezone_id', 'locale']);
            
            // Support both naming conventions
            $phoneCode = $request->phone_code ?? $request->calling_code;
            if ($phoneCode) {
                $data['calling_code'] = str_replace('+', '', $phoneCode);
            }

            $user->update($data);

            return response()->json(['data' => [
                'user' => $user,
                'message' => 'Perfil atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar perfil', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar perfil'], 500);
        }
    }
    /**
     * Update user timezone
     * 
     * @bodyParam timezone_id string required The HashID of the timezone. Example: tz_abc123
     * 
     * @return array{data: array{timezone: string|null, message: string}}
     */
    public function updateTimezone(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'timezone_id' => ['required', new \App\Rules\HashIdExists('timezones', 'id', 'timezone-id')],
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $timezoneId = \App\Actions\General\EasyHashAction::decode($request->timezone_id, 'timezone-id');
            
            $user->update([
                'timezone_id' => $timezoneId,
            ]);

            // Reload timezone relationship to get the name
            $user->load('timezone');

            return response()->json(['data' => [
                'timezone' => $user->timezone?->name,
                'message' => 'Fuso horário atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar fuso horário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar fuso horário'], 500);
        }
    }

    /**
     * Request email update
     * 
     * Starts the email change process by sending a verification code to the new email address.
     * The email is only updated after verification using the `verifyEmailUpdate` endpoint.
     * 
     * **Rate Limits:**
     * - Burst: 3 requests per 170 seconds.
     * - Daily: 10 requests per 24 hours.
     * 
     * @bodyParam email string required The new email address. Example: new-email@example.com
     */
    public function updateEmail(UpdateEmailRequest $request, EmailVerificationCodeService $emailService): JsonResponse
    {
        $user = $request->user();
        try {
            \Illuminate\Support\Facades\Log::debug('Email update request received', [
                'user_id' => $user->id,
                'new_email' => $request->email,
                'current_email' => $user->email,
            ]);

            $newEmail = $request->email;

            // Send verification code to the NEW email
            $emailService->sendVerificationCode($newEmail, EmailTypeEnum::CONFIRMATION_EMAIL);

            return response()->json([
                'data' => [
                    'email' => $newEmail,
                ],
                'message' => 'Código de verificação enviado para o novo email.',
            ]);

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Erro ao solicitar alteração de email', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao solicitar alteração de email'], 500);
        }
    }

    /**
     * Verify and update email
     * 
     * Verifies the 6-digit code sent to the new email and finalizes the email update process.
     * 
     * @bodyParam email string required The new email address. Example: new-email@example.com
     * @bodyParam code string required The verification code. Example: 123456
     */
    public function verifyEmailUpdate(VerifyEmailUpdateRequest $request, EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $user = $request->user();
            $newEmail = $request->email;
            $code = $request->code;

            if (!$emailService->verifyCode($newEmail, $code, EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return response()->json(['message' => 'Código de verificação inválido ou expirado.'], 422);
            }

            // Update user email and mark as verified
            $user->update([
                'email' => $newEmail,
                'email_verified_at' => now(),
            ]);

            $this->commitSafe();

            return response()->json([
                'data' => [
                    'email' => $user->email,
                ],
                'message' => 'Email atualizado e verificado com sucesso.',
            ]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao verificar alteração de email', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao verificar alteração de email'], 500);
        }
    }

    /**
     * Update user password
     * 
     * @bodyParam current_password string required The current password.
     * @bodyParam new_password string required The new password.
     * @bodyParam new_password_confirmation string required The new password confirmation.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'A senha atual está incorreta.', 'errors' => ['current_password' => ['A senha atual está incorreta.']]], 422);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json(['message' => 'Senha atualizada com sucesso.']);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar senha', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar senha'], 500);
        }
    }
}
