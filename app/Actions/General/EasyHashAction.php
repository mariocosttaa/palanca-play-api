<?php

namespace App\Actions\General;

use Hashids\Hashids;
use Illuminate\Support\Facades\Crypt;

class EasyHashAction
{
    public static function encode(string|int|null $valueToBeEncode, string $type = '', int $minReturnEncode = 21)
    {
        if (is_null($valueToBeEncode)) {
            return null;
        }

        $hashids = new Hashids($type, $minReturnEncode);

        return $hashids->encode($valueToBeEncode);
    }

    public static function decode(string $valueEncoded, string $type = '', int $minReturnEncode = 21): int|string|null
    {
        try {
            $hashids = new Hashids($type, $minReturnEncode);
            $decodedArray = $hashids->decode($valueEncoded);

            if (empty($decodedArray)) {
                return null;
            }

            $decoded = $decodedArray[0] ?? null;
            return is_numeric($decoded) ? (int) $decoded : $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Securely encrypt an array payload as a string using Laravel Crypt.
     * Intended for sensitive data (e.g., OAuth tokens). Returns an opaque string.
     */
    public static function encryptArray(array $data): string
    {
        return Crypt::encryptString(json_encode($data));
    }

    /**
     * Decrypt a string produced by encryptArray back into an array.
     * Returns an empty array on failure.
     */
    public static function decryptArray(?string $encrypted): array
    {
        if (empty($encrypted)) {
            return [];
        }
        try {
            $json = Crypt::decryptString($encrypted);
            $arr = json_decode($json, true);
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Encrypt a single scalar/string value.
     */
    public static function encryptString(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a single encrypted string. Returns null on failure.
     */
    public static function decryptString(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
