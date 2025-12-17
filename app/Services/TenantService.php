<?php

namespace App\Services;

use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantService
{
    public function listTenants()
    {
        return Tenant::with('country')->get();
    }

    public function createTenant(array $data)
    {
        return DB::transaction(function () use ($data) {
            return Tenant::create([
                'name' => $data['name'],
                'country_id' => $data['country_id'],
                'currency' => strtolower($data['currency']),
                'timezone' => $data['timezone'],
                'auto_confirm_bookings' => $data['auto_confirm_bookings'] ?? false,
                'booking_interval_minutes' => $data['booking_interval_minutes'] ?? 60,
                'buffer_between_bookings_minutes' => $data['buffer_between_bookings_minutes'] ?? 0,
            ]);
        });
    }

    public function updateTenant(Tenant $tenant, array $data)
    {
        return $tenant->update($data);
    }

    public function deleteTenant(Tenant $tenant)
    {
        return $tenant->delete();
    }

    public function searchUsers(string $query)
    {
        return BusinessUser::where('email', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->take(10)
            ->get();
    }

    public function assignUserToTenant(Tenant $tenant, int $userId)
    {
        if ($tenant->businessUsers()->where('business_user_id', $userId)->exists()) {
            throw new \Exception("User is already assigned to this tenant.");
        }

        $tenant->businessUsers()->attach($userId);
    }

    public function unassignUserFromTenant(Tenant $tenant, int $userId)
    {
        if (!$tenant->businessUsers()->where('business_user_id', $userId)->exists()) {
            throw new \Exception("User is not assigned to this tenant.");
        }

        $tenant->businessUsers()->detach($userId);
    }
}
