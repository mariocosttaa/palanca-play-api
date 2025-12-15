<?php

namespace App\Http\Controllers;

use App\Actions\General\EasyHashAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantFileController
{
    /**
     * Serve a public file for a tenant.
     *
     * @param Request $request
     * @param int|string $tenantId
     * @param string $filePath
     * @return \Illuminate\Http\Response|StreamedResponse
     */
    public function showPublic(Request $request, $tenantIdHashed, $filePath)
    {
        $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');

        $disk = 'public';
        $path = "tenants/{$tenantId}/" . ltrim($filePath, '/');
        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }
        $fullPath = Storage::disk($disk)->path($path);
        $mime = mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }


    /**
     * Serve a private file for a tenant (requires auth and tenant access).
     *
     * @param Request $request
     * @param int|string $tenantId
     * @param string $filePath
     * @return \Illuminate\Http\Response|StreamedResponse
     */
    public function showPrivate(Request $request, $tenantIdHashed, $filePath)
    {
        $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');

        $disk = 'local';
        $path = "tenants/{$tenantId}/" . ltrim($filePath, '/');
        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }
        $fullPath = Storage::disk($disk)->path($path);
        $mime = mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

}