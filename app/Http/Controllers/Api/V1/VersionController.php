<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-SHARED] Version
 */
class VersionController extends Controller
{
    /**
     * Get Mobile API version info
     * 
     * @unauthenticated
     * 
     * @return array{version: string, mandatoryUpdate: bool, currentVersion: string, message: string, downloadIos: string, downloadAndroid: string}
     */
    public function mobile(): JsonResponse
    {
        return response()->json([
            'version' => '1.1.0',
            'mandatoryUpdate' => false,
            'currentVersion' => '1.0.0',
            'message' => 'A new version is available with exciting features and improvements!',
            'downloadIos' => 'https://apps.apple.com/app/id123456789',
            'downloadAndroid' => 'https://play.google.com/store/apps/details?id=com.example.app'
        ]);
    }

    /**
     * Get Business API version info
     * 
     * @unauthenticated
     * 
     * @return array{version: string, app_name: string, environment: string, api: string}
     */
    public function business(): JsonResponse
    {
        return response()->json([
            'version' => '1.0.0',
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'api' => 'business'
        ]);
    }
}
