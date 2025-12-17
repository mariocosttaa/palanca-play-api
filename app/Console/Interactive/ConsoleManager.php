<?php

namespace App\Console\Interactive;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;

class ConsoleManager
{
    public function __construct(
        protected TenantConsole $tenantConsole,
        protected SubscriptionConsole $subscriptionConsole
    ) {}

    public function run()
    {
        intro('Welcome to Palanca Play Admin Console');

        while (true) {
            $section = select(
                label: 'What would you like to manage?',
                options: [
                    'tenants' => 'Tenants & Users',
                    'subscriptions' => 'Subscriptions & Invoices',
                    'exit' => 'Exit',
                ],
                default: 'tenants'
            );

            if ($section === 'exit') {
                break;
            }

            match ($section) {
                'tenants' => $this->tenantConsole->menu(),
                'subscriptions' => $this->subscriptionConsole->menu(),
            };
        }

        outro('Goodbye!');
    }
}
