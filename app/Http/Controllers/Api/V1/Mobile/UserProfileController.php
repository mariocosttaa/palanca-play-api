<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-MOBILE] Profile
 */
class UserProfileController extends Controller
{
    /**
     * Update user language preference
     * 
     * Updates the preferred language for the authenticated user.
     * 
     * @return array{data: array{locale: string, message: string}}
     * @response 422 {"message": "Dados inválidos", "errors": []}
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
     * 
     * @return array{data: array{user: \App\Models\User, message: string}}
     * @response 422 {"message": "Dados inválidos", "errors": []}
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
     * @return array{data: array{timezone: string, message: string}}
     */
    public function updateTimezone(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'timezone_id' => ['required', new \App\Rules\HashIdExists('timezones', 'id')],
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $timezoneId = \App\Actions\General\EasyHashAction::decode($request->timezone_id);
            
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
}
