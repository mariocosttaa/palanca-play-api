<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\V1\Profile\UpdateEmailRequest;
use App\Services\EmailVerificationCodeService;
use App\Enums\EmailTypeEnum;

/**
 * @tags [API-MOBILE] Profile
 */
class UserProfileController extends Controller
{
    /**
     * Update user language preference
     * 
     * Updates the preferred language for the authenticated user.
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
     * Updates the profile information of the authenticated user.
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

            $user = $request->user();
            $user->update($request->only(['name', 'surname', 'phone', 'timezone', 'locale']));

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
     * Updates the timezone for the authenticated user.
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
     * Update user email
     * 
     * Updates the email address of the authenticated user and sends a verification code to the new email.
     * 
     */
    public function updateEmail(UpdateEmailRequest $request, EmailVerificationCodeService $emailService): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $user = $user = $request->user();
            $newEmail = $request->email;

            // Send verification code to the NEW email
            $emailService->sendVerificationCode($newEmail, EmailTypeEnum::CONFIRMATION_EMAIL);

            // Update user email and mark as unverified
            $user->update([
                'email' => $newEmail,
                'email_verified_at' => null,
            ]);

            $this->commitSafe();

            return response()->json(['data' => [
                'email' => $user->email,
                'message' => 'Email atualizado com sucesso. Por favor, verifique seu novo email.',
            ]]);

        } catch (\App\Exceptions\EmailRateLimitException $e) {
            $this->rollBackSafe();
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Erro ao atualizar email', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar email'], 500);
        }
    }
}
