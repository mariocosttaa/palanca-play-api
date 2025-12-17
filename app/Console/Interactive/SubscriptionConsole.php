<?php

namespace App\Console\Interactive;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\search;

class SubscriptionConsole
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function menu()
    {
        while (true) {
            $action = select(
                label: 'Subscription & Invoice Management',
                options: [
                    'manage_sub' => 'Manage Subscription Plan',
                    'delete_sub' => 'Delete Subscription Plan',
                    'invoices' => 'Manage Invoices',
                    'back' => 'Back to Main Menu',
                ],
                default: 'manage_sub'
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
                'manage_sub' => $this->manageSubscription(),
                'delete_sub' => $this->deleteSubscription(),
                'invoices' => $this->invoiceMenu(),
            };
        } catch (\Exception $e) {
            error("An error occurred: " . $e->getMessage());
            if (confirm('Do you want to see the stack trace?')) {
                error($e->getTraceAsString());
            }
        }
    }

    protected function invoiceMenu()
    {
        while (true) {
            $action = select(
                label: 'Invoice Management',
                options: [
                    'create' => 'Create Invoice',
                    'edit' => 'Edit Invoice',
                    'delete' => 'Delete Invoice',
                    'list' => 'List Invoices',
                    'back' => 'Back',
                ],
                default: 'list'
            );

            if ($action === 'back') {
                break;
            }

            try {
                match ($action) {
                    'create' => $this->createInvoice(),
                    'edit' => $this->editInvoice(),
                    'delete' => $this->deleteInvoice(),
                    'list' => $this->listInvoices(),
                };
            } catch (\Exception $e) {
                error("An error occurred: " . $e->getMessage());
                if (confirm('Do you want to see the stack trace?')) {
                    error($e->getTraceAsString());
                }
            }
        }
    }

    protected function manageSubscription()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::with('subscriptionPlan')->find($tenantId);
        $plan = $this->subscriptionService->getPlan($tenant);

        if ($plan) {
            info("Current Plan: {$plan->courts} courts, Price: {$plan->price}");
            if (!confirm('Do you want to update this plan?')) {
                return;
            }
        } else {
            info("No subscription plan found for this tenant.");
            if (!confirm('Do you want to create a new plan?')) {
                return;
            }
        }

        $courts = text(
            label: 'Number of Courts',
            default: $plan?->courts ?? '3',
            validate: fn (string $value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive integer.'
        );

        $price = text(
            label: 'Price (in cents)',
            default: $plan?->price ?? '10000',
            validate: fn (string $value) => is_numeric($value) && $value >= 0 ? null : 'Must be a non-negative integer.'
        );

        $this->subscriptionService->updateOrCreatePlan($tenant, [
            'courts' => $courts,
            'price' => $price,
        ]);

        info("Subscription plan updated successfully.");
    }

    protected function createInvoice()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::with('subscriptionPlan')->find($tenantId);
        $defaultCourts = $tenant->subscriptionPlan?->courts ?? 3;
        $defaultPrice = $tenant->subscriptionPlan?->price ?? 10000;

        $price = text(
            label: 'Amount (in cents)',
            default: (string) $defaultPrice,
            required: true,
            validate: fn (string $value) => is_numeric($value) ? null : 'Must be a number.'
        );

        $maxCourts = text(
            label: 'Max Courts',
            default: (string) $defaultCourts,
            required: true,
            validate: fn (string $value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive integer.'
        );

        $status = select(
            label: 'Status',
            options: ['pending', 'paid', 'cancelled', 'overdue'],
            default: 'paid'
        );

        $startDate = text(
            label: 'Start Date (YYYY-MM-DD)',
            default: now()->format('Y-m-d'),
            validate: fn (string $value) => strtotime($value) ? null : 'Invalid date format.'
        );

        $duration = select(
            label: 'Subscription Duration',
            options: [
                'monthly' => 'Monthly (+1 Month)',
                'yearly' => 'Yearly (+1 Year)',
                'custom' => 'Custom Date',
            ],
            default: 'monthly'
        );

        $start = Carbon::parse($startDate);
        $defaultEndDate = match($duration) {
            'monthly' => $start->copy()->addMonth(),
            'yearly' => $start->copy()->addYear(),
            'custom' => $start->copy()->addMonth(),
        };

        $endDate = text(
            label: 'End Date (YYYY-MM-DD)',
            default: $defaultEndDate->format('Y-m-d'),
            validate: fn (string $value) => strtotime($value) ? null : 'Invalid date format.'
        );

        $period = text(
            label: 'Period Description (e.g. "01/2025")',
            default: Carbon::parse($startDate)->format('m/Y')
        );

        $this->subscriptionService->createInvoice([
            'tenant_id' => $tenantId,
            'price' => (int) $price,
            'max_courts' => (int) $maxCourts,
            'status' => $status,
            'date_start' => $startDate,
            'date_end' => $endDate,
            'period' => $period,
            'metadata' => [],
        ]);

        info("Invoice created successfully.");
    }

    protected function deleteSubscription()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::with('subscriptionPlan')->find($tenantId);
        
        if (!$tenant->subscriptionPlan) {
            error("No subscription plan found for this tenant.");
            return;
        }

        if (confirm("Are you sure you want to delete the subscription plan for '{$tenant->name}'?")) {
            $this->subscriptionService->deletePlan($tenant);
            info("Subscription plan deleted successfully.");
        }
    }

    protected function editInvoice()
    {
        $invoice = $this->selectInvoice();
        if (!$invoice) return;

        $field = select(
            label: 'Which field do you want to edit?',
            options: [
                'status' => "Status ({$invoice->status})",
                'price' => "Price ({$invoice->price})",
                'max_courts' => "Max Courts ({$invoice->max_courts})",
                'dates' => "Dates ({$invoice->date_start->format('Y-m-d')} - {$invoice->date_end->format('Y-m-d')})",
                'back' => 'Back',
            ]
        );

        if ($field === 'back') return;

        $data = [];
        switch ($field) {
            case 'status':
                $data['status'] = select(
                    label: 'New Status',
                    options: ['pending', 'paid', 'cancelled', 'overdue'],
                    default: $invoice->status
                );
                break;
            case 'price':
                $data['price'] = text(
                    label: 'New Price (in cents)',
                    default: (string) $invoice->price,
                    validate: fn (string $value) => is_numeric($value) ? null : 'Must be a number.'
                );
                break;
            case 'max_courts':
                $data['max_courts'] = text(
                    label: 'New Max Courts',
                    default: (string) $invoice->max_courts,
                    validate: fn (string $value) => is_numeric($value) && $value > 0 ? null : 'Must be a positive integer.'
                );
                break;
            case 'dates':
                $data['date_start'] = text(
                    label: 'Start Date (YYYY-MM-DD)',
                    default: $invoice->date_start->format('Y-m-d'),
                    validate: fn (string $value) => strtotime($value) ? null : 'Invalid date format.'
                );
                $data['date_end'] = text(
                    label: 'End Date (YYYY-MM-DD)',
                    default: $invoice->date_end->format('Y-m-d'),
                    validate: fn (string $value) => strtotime($value) ? null : 'Invalid date format.'
                );
                // Auto-update period description if dates change
                $data['period'] = Carbon::parse($data['date_start'])->format('m/Y');
                break;
        }

        if (!empty($data)) {
            $this->subscriptionService->updateInvoice($invoice, $data);
            info("Invoice updated successfully.");
        }
    }

    protected function deleteInvoice()
    {
        $invoice = $this->selectInvoice();
        if (!$invoice) return;

        if (confirm("Are you sure you want to delete this invoice?")) {
            $this->subscriptionService->deleteInvoice($invoice);
            info("Invoice deleted successfully.");
        }
    }

    protected function selectInvoice()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return null;

        $tenant = Tenant::find($tenantId);
        $invoices = $this->subscriptionService->getInvoices($tenant);

        if ($invoices->isEmpty()) {
            error("No invoices found for this tenant.");
            return null;
        }

        $options = $invoices->mapWithKeys(function ($invoice) {
            $label = "#{$invoice->id} - {$invoice->period} ({$invoice->status}) - {$invoice->price}";
            return [$invoice->id => $label];
        })->toArray();
        
        $options['back'] = 'Back';

        $invoiceId = select(
            label: 'Select Invoice',
            options: $options
        );

        if ($invoiceId === 'back') return null;

        return $invoices->firstWhere('id', $invoiceId);
    }

    protected function listInvoices()
    {
        $tenantId = $this->selectTenant();
        if (!$tenantId) return;

        $tenant = Tenant::find($tenantId);
        $invoices = $this->subscriptionService->getInvoices($tenant)->map(function ($invoice) {
            return [
                $invoice->id,
                $invoice->period,
                $invoice->price,
                $invoice->status,
                $invoice->date_start->format('Y-m-d'),
                $invoice->date_end->format('Y-m-d'),
            ];
        });

        if ($invoices->isEmpty()) {
            info('No invoices found for this tenant.');
            return;
        }

        table(
            ['ID', 'Period', 'Price', 'Status', 'Start', 'End'],
            $invoices->toArray()
        );
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
            // Search doesn't easily support "Back" unless we handle empty selection or special value.
            // For now, let's assume search is fine.
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
}
