<?php

namespace App\Actions\General;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeAction
{
    /**
     * Generate and save a QR code for a booking.
     *
     * @param int|string $tenantId
     * @param int|string $bookingId
     * @param string $bookingIdHashed The hashed booking ID to encode in QR
     * @return object {url: string, local_path: string, size: int}
     */
    public static function create($tenantId, $bookingId, string $bookingIdHashed): object
    {
        $disk = 'public'; // Public storage as requested
        $basePath = "tenants/{$tenantId}/qr-codes";
        
        // Ensure directory exists
        Storage::disk($disk)->makeDirectory($basePath);
        
        // Generate unique filename with SVG extension
        $fileName = "booking_{$bookingId}_qr.svg";
        $fullPath = $basePath . '/' . $fileName;
        
        // Generate QR code with hashed booking ID in SVG format
        $qrCode = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($bookingIdHashed);
        
        // Save QR code to storage
        Storage::disk($disk)->put($fullPath, $qrCode);
        
        // Get file size
        $size = Storage::disk($disk)->size($fullPath);
        
        // Generate URL using TenantFileAction pattern
        $tenantIdHashed = EasyHashAction::encode($tenantId, 'tenant-id');
        $insidePath = 'qr-codes/' . $fileName;
        $routeUrl = route('tenant-file-show-public', [
            'tenantIdHashed' => $tenantIdHashed,
            'filePath' => $insidePath
        ]);
        
        // Remove host from URL
        $routeUrl = self::stripHostFromUrl($routeUrl);
        
        return (object) [
            'url' => $routeUrl,
            'local_path' => $fullPath,
            'size' => $size,
        ];
    }
    
    /**
     * Delete a QR code file.
     *
     * @param int|string $tenantId
     * @param string|null $qrCodePath The local path or URL of the QR code
     * @return bool
     */
    public static function delete($tenantId, ?string $qrCodePath = null): bool
    {
        if (!$qrCodePath) {
            return false;
        }
        
        $disk = 'public';
        $basePath = "tenants/{$tenantId}";
        
        // Determine the file path to delete
        $pathToDelete = null;
        
        // Handle relative paths starting with /file/
        if (str_starts_with($qrCodePath, '/file/')) {
            $pathSegments = explode('/', trim($qrCodePath, '/'));
            if (isset($pathSegments[2])) {
                $pathToDelete = implode('/', array_slice($pathSegments, 2));
            }
        } elseif (str_starts_with($qrCodePath, 'tenants/')) {
            // If it's a full storage path, extract the relative path
            $pathToDelete = preg_replace('/^tenants\/\d+\//', '', $qrCodePath);
        } else {
            // Use the path as-is
            $pathToDelete = ltrim($qrCodePath, '/');
        }
        
        if (!$pathToDelete) {
            \Log::warning('Could not determine QR code path to delete', [
                'tenant_id' => $tenantId,
                'qr_code_path' => $qrCodePath,
            ]);
            return false;
        }
        
        $fullPath = $basePath . '/' . $pathToDelete;
        
        // Check if file exists before trying to delete
        if (!Storage::disk($disk)->exists($fullPath)) {
            \Log::warning('QR code file does not exist for deletion', [
                'tenant_id' => $tenantId,
                'full_path' => $fullPath,
            ]);
            return false;
        }
        
        $deleted = Storage::disk($disk)->delete($fullPath);
        
        if ($deleted) {
            \Log::info('QR code deleted successfully', [
                'tenant_id' => $tenantId,
                'full_path' => $fullPath,
            ]);
        } else {
            \Log::error('Failed to delete QR code', [
                'tenant_id' => $tenantId,
                'full_path' => $fullPath,
            ]);
        }
        
        return $deleted;
    }
    
    /**
     * Remove host from URL (same as TenantFileAction).
     *
     * @param string $url
     * @return string
     */
    private static function stripHostFromUrl(string $url): string
    {
        // Remove protocol and domain
        $appUrl = config('app.url');
        if ($appUrl) {
            $url = preg_replace('#^' . preg_quote($appUrl, '#') . '#i', '', $url);
        }
        // Remove http(s)://domain(:port)?
        $url = preg_replace('#^https?://[^/]+#i', '', $url);
        // Remove localhost(:port)?
        $url = preg_replace('#^localhost(:\d+)?#i', '', $url);
        // Remove leading slashes
        $url = ltrim($url, '/');
        return $url;
    }
}
