<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;

class SubscriptionService
{
    public function getPlan(Tenant $tenant)
    {
        return $tenant->subscriptionPlan;
    }

    public function updateOrCreatePlan(Tenant $tenant, array $data)
    {
        return SubscriptionPlan::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'courts' => (int) $data['courts'],
                'price' => (int) $data['price'],
            ]
        );
    }

    public function createInvoice(array $data)
    {
        return Invoice::create($data);
    }

    public function getInvoices(Tenant $tenant)
    {
        return Invoice::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateInvoice(Invoice $invoice, array $data)
    {
        return $invoice->update($data);
    }

    public function deleteInvoice(Invoice $invoice)
    {
        return $invoice->delete();
    }

    public function deletePlan(Tenant $tenant)
    {
        return $tenant->subscriptionPlan()->delete();
    }

    public function getValidInvoice(Tenant $tenant)
    {
        return Invoice::forTenant($tenant->id)
            ->valid()
            ->latest('date_end')
            ->first();
    }
}
