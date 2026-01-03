<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\V1\Profile\UpdateEmailRequest;
use App\Http\Requests\Api\V1\Profile\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Profile\VerifyEmailUpdateRequest;
use App\Services\EmailVerificationCodeService;
use App\Enums\EmailTypeEnum;
use Illuminate\Support\Facades\Hash;

/**
 * @tags [API-BUSINESS] Profile
 */
class BusinessUserProfileController extends Controller
{
    /**
     * Update business user language preference
     * 
     * Updates the preferred language for the authenticated business user.
     * 
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

            $businessUser = $request->user('business');
            $businessUser->update([
                'locale' => $request->input('locale'),
            ]);

            return response()->json(['data' => [
                'locale' => $businessUser->locale,
                'message' => 'Idioma atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar idioma', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar idioma'], 500);
        }
    }

    /**
     * Update business user profile
     * 
     * Updates the profile information of the authenticated business user.
     * 
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'surname' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'timezone' => 'sometimes|string|max:50',
                'locale' => 'sometimes|in:en,pt,es,fr',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $businessUser = $request->user('business');
            $businessUser->update($request->only(['name', 'surname', 'phone', 'timezone', 'locale']));

            return response()->json(['data' => [
                'user' => $businessUser,
                'message' => 'Perfil atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar perfil', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar perfil'], 500);
        }
    }
    /**
     * Update business user timezone
     * 
     * Updates the timezone for the authenticated business user.
     * 
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

            $businessUser = $request->user('business');
            $timezoneId = \App\Actions\General\EasyHashAction::decode($request->timezone_id, 'timezone-id');
            
            $businessUser->update([
                'timezone_id' => $timezoneId,
            ]);

            // Reload timezone relationship to get the name
            $businessUser->load('timezone');

            return response()->json(['data' => [
                'timezone' => $businessUser->timezone?->name,
                'message' => 'Fuso horário atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar fuso horário', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar fuso horário'], 500);
        }
    }

    /**
     * Request business email update
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
            \Illuminate\Support\Facades\Log::debug('Business email update request received', [
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
            Log::error('Erro ao solicitar alteração de email (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao solicitar alteração de email'], 500);
        }
    }

    /**
     * Verify and update business email
     * 
     * Verifies the 6-digit code sent to the new email and finalizes the email update process for the business user.
     * 
     * @bodyParam email string required The new email address. Example: new-email@example.com
     * @bodyParam code string required The verification code. Example: 123456
     */
    public function verifyEmailUpdate(VerifyEmailUpdateRequest $request, EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $businessUser = $request->user('business');
            $newEmail = $request->email;
            $code = $request->code;

            if (!$emailService->verifyCode($newEmail, $code, EmailTypeEnum::CONFIRMATION_EMAIL)) {
                return response()->json(['message' => 'Código de verificação inválido ou expirado.'], 422);
            }

            // Update user email and mark as verified
            $businessUser->update([
                'email' => $newEmail,
                'email_verified_at' => now(),
            ]);

            $this->commitSafe();

            return response()->json([
                'data' => [
                    'email' => $businessUser->email,
                ],
                'message' => 'Email atualizado e verificado com sucesso.',
            ]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao verificar alteração de email (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao verificar alteração de email'], 500);
        }
    }

    /**
     * Update business user password
     * 
     * @bodyParam current_password string required The current password.
     * @bodyParam new_password string required The new password.
     * @bodyParam new_password_confirmation string required The new password confirmation.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $businessUser = $request->user('business');

            if (!Hash::check($request->current_password, $businessUser->password)) {
                return response()->json(['message' => 'A senha atual está incorreta.', 'errors' => ['current_password' => ['A senha atual está incorreta.']]], 422);
            }

            $businessUser->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json(['message' => 'Senha atualizada com sucesso.']);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar senha (Business)', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar senha'], 500);
        }
    }
}
