<?php

test('payment method is required when payment status is paid', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = \App\Models\Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = \App\Models\BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    \App\Models\Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = \App\Models\Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = \App\Models\User::factory()->create();
    
    // Add availability
    \App\Models\CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('bookings.store', ['tenant_id' => \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => \App\Actions\General\EasyHashAction::encode($court->id, 'court-id'),
        'client_id' => \App\Actions\General\EasyHashAction::encode($client->id, 'user-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'payment_status' => \App\Enums\PaymentStatusEnum::PAID->value,
        // payment_method missing
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payment_method']);
});
