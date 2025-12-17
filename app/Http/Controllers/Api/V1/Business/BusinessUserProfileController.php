<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @tags [API-BUSINESS] Profile
 */
class BusinessUserProfileController extends Controller
{
    /**
     * Update business user language preference
     */
    public function updateLanguage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'language' => 'required|in:en,pt,es,fr',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Dados inválidos', $validator->errors(), 422);
            }

            $businessUser = $request->user('business');
            $businessUser->update([
                'language' => $request->language,
            ]);

            return $this->dataResponse([
                'language' => $businessUser->language,
                'message' => 'Idioma atualizado com sucesso',
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao atualizar idioma', $e->getMessage(), 500);
        }
    }

    /**
     * Update business user profile
     */
    public function updateProfile(Request $request)
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
                return $this->errorResponse('Dados inválidos', $validator->errors(), 422);
            }

            $businessUser = $request->user('business');
            $businessUser->update($request->only(['name', 'surname', 'phone', 'timezone', 'language']));

            return $this->dataResponse([
                'user' => $businessUser,
                'message' => 'Perfil atualizado com sucesso',
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao atualizar perfil', $e->getMessage(), 500);
        }
    }
}
