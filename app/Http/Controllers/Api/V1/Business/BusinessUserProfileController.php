<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

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
     * @return array{data: array{language: string, message: string}}
     * @response 422 {"message": "Dados inválidos", "errors": []}
     */
    public function updateLanguage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'language' => 'required|in:en,pt,es,fr',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $businessUser = $request->user('business');
            $businessUser->update([
                'language' => $request->language,
            ]);

            return response()->json(['data' => [
                'language' => $businessUser->language,
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
     * @return array{data: array{user: \App\Models\BusinessUser, message: string}}
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
                'language' => 'sometimes|in:en,pt,es,fr',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
            }

            $businessUser = $request->user('business');
            $businessUser->update($request->only(['name', 'surname', 'phone', 'timezone', 'language']));

            return response()->json(['data' => [
                'user' => $businessUser,
                'message' => 'Perfil atualizado com sucesso',
            ]]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar perfil', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao atualizar perfil'], 500);
        }
    }
}
