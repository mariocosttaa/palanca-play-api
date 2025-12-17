<?php

namespace App\Console\Interactive;

use App\Models\BusinessUser;
use App\Models\Country;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\search;

class BusinessUserConsole
{
    public function menu()
    {
        while (true) {
            $action = select(
                label: 'Business User Management',
                options: [
                    'list' => 'List Users',
                    'create' => 'Create User',
                    'edit' => 'Edit User',
                    'delete' => 'Delete User',
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
                'list' => $this->listUsers(),
                'create' => $this->createUser(),
                'edit' => $this->editUser(),
                'delete' => $this->deleteUser(),
            };
        } catch (\Exception $e) {
            error("An error occurred: " . $e->getMessage());
            if (confirm('Do you want to see the stack trace?')) {
                error($e->getTraceAsString());
            }
        }
    }

    protected function listUsers()
    {
        $users = BusinessUser::latest()->take(50)->get()->map(function ($user) {
            return [
                $user->id,
                $user->name,
                $user->surname,
                $user->email,
                $user->created_at->format('Y-m-d'),
            ];
        });

        if ($users->isEmpty()) {
            info('No users found.');
            return;
        }

        table(
            ['ID', 'Name', 'Surname', 'Email', 'Created At'],
            $users->toArray()
        );
    }

    protected function createUser()
    {
        info('Creating a new business user...');

        $name = text(
            label: 'Name',
            required: true
        );

        $surname = text(
            label: 'Surname',
            required: true
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email format.'
        );

        if (BusinessUser::where('email', $email)->exists()) {
            error('User with this email already exists.');
            return;
        }

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => strlen($value) >= 8 ? null : 'Password must be at least 8 characters.'
        );

        $countries = Country::all()->pluck('name', 'id')->toArray();
        $countryId = null;
        
        if (!empty($countries)) {
             $countryId = search(
                label: 'Select Country (Optional)',
                options: fn (string $value) => strlen($value) > 0
                    ? Country::where('name', 'like', "%{$value}%")->pluck('name', 'id')->toArray()
                    : $countries,
                scroll: 10
            );
        }

        $callingCode = null;
        if ($countryId) {
            $country = Country::find($countryId);
            $callingCode = $country->calling_code;
        }

        $phone = text(
            label: 'Phone (Optional)',
        );

        $user = BusinessUser::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make($password),
            'calling_code' => $callingCode,
            'phone' => $phone,
        ]);

        info("User '{$user->name} {$user->surname}' created successfully with ID: {$user->id}");
    }

    protected function editUser()
    {
        $userId = $this->selectUser();
        if (!$userId) return;

        $user = BusinessUser::find($userId);

        $field = select(
            label: 'Which field do you want to edit?',
            options: [
                'name' => "Name ({$user->name})",
                'surname' => "Surname ({$user->surname})",
                'email' => "Email ({$user->email})",
                'password' => "Password (********)",
                'phone' => "Phone ({$user->phone})",
            ]
        );

        $data = [];
        switch ($field) {
            case 'name':
                $data['name'] = text('New Name', default: $user->name);
                break;
            case 'surname':
                $data['surname'] = text('New Surname', default: $user->surname);
                break;
            case 'email':
                $email = text('New Email', default: $user->email);
                if ($email !== $user->email && BusinessUser::where('email', $email)->exists()) {
                    error('Email already taken.');
                    return;
                }
                $data['email'] = $email;
                break;
            case 'password':
                $password = password('New Password');
                if (strlen($password) < 8) {
                    error('Password must be at least 8 characters.');
                    return;
                }
                $data['password'] = Hash::make($password);
                break;
            case 'phone':
                $data['phone'] = text('New Phone', default: $user->phone);
                break;
        }

        if (!empty($data)) {
            $user->update($data);
            info("User updated successfully.");
        }
    }

    protected function deleteUser()
    {
        $userId = $this->selectUser();
        if (!$userId) return;

        $user = BusinessUser::find($userId);

        if (confirm("Are you sure you want to delete user '{$user->name} {$user->surname}'? This action cannot be undone.")) {
            $user->delete();
            info("User deleted successfully.");
        }
    }

    protected function selectUser()
    {
        $users = BusinessUser::all();
        if ($users->isEmpty()) {
            error("No users available.");
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

        $options = $users->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])->toArray();
        $options['back'] = 'Back';

        $selected = select(
            label: 'Select User',
            options: $options
        );

        if ($selected === 'back') return null;

        return $selected;
    }
}
