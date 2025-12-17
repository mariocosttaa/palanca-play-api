<?php

namespace App\Console\Interactive;

use App\Models\Country;
use App\Models\BusinessUser;
use App\Models\Tenant;
use App\Services\TenantService;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\search;

class TenantConsole
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    public function menu()
    {
        while (true) {
            $action = select(
                label: 'Tenant Management',
                options: [
                    'list' => 'List Tenants',
                    'create' => 'Create Tenant',
                    'edit' => 'Edit Tenant',
                    'delete' => 'Delete Tenant',
                    'assign_user' => 'Assign Business User to Tenant',
                    'unassign_user' => 'Unassign Business User from Tenant',
                    'back' => 'Back to Main Menu',
                ],
                default: 'list'
            );

            if ($action === 'back') {
                break;
            }

            $this->handleAction($action);
        }
    }

    protected function handleAction(string $action)
    {
        try {
            match ($action) {
                'list' => $this->listTenants(),
                'create' => $this->createTenant(),
                'edit' => $this->editTenant(),
                'delete' => $this->deleteTenant(),
                'assign_user' => $this->assignUser(),
                'unassign_user' => $this->unassignUser(),
            };
        } catch (\Exception $e) {
            error("An error occurred: " . $e->getMessage());
            if (confirm('Do you want to see the stack trace?')) {
                error($e->getTraceAsString());
            }
        }
    }

    protected function listTenants()
    {
        $tenants = $this->tenantService->listTenants()->map(function ($tenant) {
            return [
                $tenant->id,
                $tenant->name,
                $tenant->country->name ?? 'N/A',
                $tenant->created_at->format('Y-m-d'),
            ];
        });

        if ($tenants->isEmpty()) {
            info('No tenants found.');
            return;
        }

        table(
            ['ID', 'Name', 'Country', 'Created At'],
            $tenants->toArray()
        );
    }

    protected function createTenant()
    {
        info('Creating a new tenant...');

        $name = text(
            label: 'Tenant Name',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 3 => 'The name must be at least 3 characters.',
                default => null
            }
        );

        $countries = Country::all()->pluck('name', 'id')->toArray();
        if (empty($countries)) {
            error('No countries found in database. Please seed countries first.');
            return;
        }

        $countryId = search(
            label: 'Select Country',
            options: fn (string $value) => strlen($value) > 0
                ? Country::where('name', 'like', "%{$value}%")->pluck('name', 'id')->toArray()
                : $countries,
            scroll: 10
        );

        $currency = text(
            label: 'Currency Code (e.g. EUR, USD)',
            default: 'EUR',
            required: true,
            validate: fn (string $value) => strlen($value) !== 3 ? 'Currency must be 3 characters.' : null
        );

        $timezone = text(
            label: 'Timezone',
            default: 'UTC',
            required: true
        );

        $tenant = $this->tenantService->createTenant([
            'name' => $name,
            'country_id' => $countryId,
            'currency' => $currency,
            'timezone' => $timezone,
        ]);

        info("Tenant '{$tenant->name}' created successfully with ID: {$tenant->id}");
    }

    protected function editTenant()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::find($tenantId);

        $field = select(
            label: 'Which field do you want to edit?',
            options: [
                'name' => "Name ({$tenant->name})",
                'currency' => "Currency ({$tenant->currency})",
                'timezone' => "Timezone ({$tenant->timezone})",
                'auto_confirm_bookings' => "Auto Confirm Bookings (" . ($tenant->auto_confirm_bookings ? 'Yes' : 'No') . ")",
            ]
        );

        $data = [];
        switch ($field) {
            case 'name':
                $data['name'] = text('New Name', default: $tenant->name);
                break;
            case 'currency':
                $data['currency'] = text('New Currency', default: $tenant->currency);
                break;
            case 'timezone':
                $data['timezone'] = text('New Timezone', default: $tenant->timezone);
                break;
            case 'auto_confirm_bookings':
                $data['auto_confirm_bookings'] = confirm('Enable Auto Confirm Bookings?', default: $tenant->auto_confirm_bookings);
                break;
        }

        if (!empty($data)) {
            $this->tenantService->updateTenant($tenant, $data);
            info("Tenant updated successfully.");
        }
    }

    protected function deleteTenant()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::find($tenantId);

        if (confirm("Are you sure you want to delete tenant '{$tenant->name}'? This action cannot be undone.")) {
            $this->tenantService->deleteTenant($tenant);
            info("Tenant deleted successfully.");
        }
    }

    protected function assignUser()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::find($tenantId);

        $search = text('Search user by email or name');
        
        $users = $this->tenantService->searchUsers($search);

        if ($users->isEmpty()) {
            error("No users found.");
            return;
        }

        $options = $users->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])->toArray();

        $userId = select(
            label: 'Select User to Assign',
            options: $options
        );

        try {
            $this->tenantService->assignUserToTenant($tenant, $userId);
            info("User assigned successfully.");
        } catch (\Exception $e) {
            error($e->getMessage());
        }
    }

    protected function unassignUser()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::with('businessUsers')->find($tenantId);
        $users = $tenant->businessUsers;

        if ($users->isEmpty()) {
            error("No users assigned to this tenant.");
            return;
        }

        $options = $users->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])->toArray();

        $userId = select(
            label: 'Select User to Unassign',
            options: $options
        );

        if (confirm("Are you sure you want to unassign this user?")) {
            try {
                $this->tenantService->unassignUserFromTenant($tenant, $userId);
                info("User unassigned successfully.");
            } catch (\Exception $e) {
                error($e->getMessage());
            }
        }
    }

    protected function selectTenant()
    {
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            error("No tenants available.");
            return null;
        }

        if ($tenants->count() > 10) {
             $id = search(
                label: 'Search Tenant',
                options: fn (string $value) => strlen($value) > 0
                    ? Tenant::where('name', 'like', "%{$value}%")->pluck('name', 'id')->toArray()
                    : Tenant::limit(10)->pluck('name', 'id')->toArray()
            );
            return $id;
        }

        $options = $tenants->pluck('name', 'id')->toArray();
        $options['back'] = 'Back';

        $selected = select(
            label: 'Select Tenant',
            options: $options
        );

        if ($selected === 'back') return null;

        return $selected;
    }

    protected function selectUser()
    {
        $users = BusinessUser::all();
        if ($users->isEmpty()) {
            error("No business users available.");
            return null;
        }

        if ($users->count() > 10) {
            $id = search(
                label: 'Search User',
                options: fn (string $value) => strlen($value) > 0
                    ? BusinessUser::where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->pluck('name', 'id')->toArray()
                    : BusinessUser::limit(10)->pluck('name', 'id')->toArray()
            );
            return $id;
        }

        $options = $users->pluck('name', 'id')->toArray();
        $options['back'] = 'Back';

        $selected = select(
            label: 'Select User',
            options: $options
        );

        if ($selected === 'back') return null;

        return $selected;
    }
}
