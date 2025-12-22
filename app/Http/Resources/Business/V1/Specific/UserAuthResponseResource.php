<?php

namespace App\Http\Resources\Business\V1\Specific;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $token
 * @property \App\Models\User $user
 * @property bool $verification_needed
 * @property string $message
 */
class UserAuthResponseResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'] ?? null,
            'user' => new UserResourceSpecific($this->resource['user']),
            'verification_needed' => $this->when(isset($this->resource['verification_needed']), fn() => $this->resource['verification_needed']),
            'message' => $this->when(isset($this->resource['message']), fn() => $this->resource['message']),
        ];
    }
}
