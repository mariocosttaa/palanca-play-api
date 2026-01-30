<?php

use App\Actions\General\EasyHashAction;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Country;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\Invoice;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->currency = CurrencyModel::factory()->create(['code' => 'eur']);
    Country::factory()->create(['calling_code' => '+351']);
});

test('business api: creating a booking generates a qr code', function () {
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user   = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    CourtAvailability::factory()->create([
        'tenant_id'     => $tenant->id,
        'court_id'      => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time'    => '08:00',
        'end_time'      => '20:00',
        'is_available'  => true,
    ]);

    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id'   => EasyHashAction::encode($court->id, 'court-id'),
        'client_id'  => EasyHashAction::encode($client->id, 'user-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time'   => '11:00',
    ]);

    $response->assertStatus(201);
    
    $booking = Booking::first();
    $this->assertNotNull($booking->qr_code);
    
    // Verify file exists in storage
    // URL format: tenants/{id}/qr-codes/booking_{id}_qr.svg (from QrCodeAction)
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);
});

test('mobile api: creating a booking generates a qr code', function () {
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    CourtAvailability::factory()->create([
        'tenant_id'     => $tenant->id,
        'court_id'      => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time'    => '08:00',
        'end_time'      => '20:00',
        'is_available'  => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'web');

    $response = $this->postJson('/api/v1/bookings', [
        'court_id'   => $courtHashId,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'slots' => [
            ['start' => '10:00', 'end' => '11:00']
        ]
    ]);

    $response->assertStatus(201);
    
    $booking = Booking::first();
    $this->assertNotNull($booking->qr_code);
    
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);
});

test('mobile api: updating a booking to split generates qr codes for both', function () {
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $pricePerInterval = 1000;
    $courtType = \App\Models\CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => $pricePerInterval,
        'interval_time_minutes' => 60
    ]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id
    ]);

    $testDate = now()->addDays(5)->format('Y-m-d');
    
    Invoice::factory()->create([
        'tenant_id' => $tenant->id, 
        'status' => 'paid', 
        'date_start' => now()->subMonth(),
        'date_end' => now()->addMonth()
    ]);

    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $testDate,
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $user = User::factory()->create();

    // Create initial booking
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => $testDate,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');

    Sanctum::actingAs($user, [], 'web');

    // Update with non-contiguous slots: 10:00-11:00 AND 13:00-14:00
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '13:00', 'end' => '14:00']
    ];

    $url = '/api/v1/bookings/' . $bookingIdHashed;

    $response = $this->putJson($url, [
        'slots' => $slots,
    ]);

    $response->assertStatus(200);

    // Verify both bookings have QR codes
    $bookings = Booking::all();
    $this->assertCount(2, $bookings);

    foreach ($bookings as $b) {
        $this->assertNotNull($b->qr_code, "Booking ID {$b->id} is missing a QR code");
        $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$b->id}_qr.svg";
        Storage::disk('public')->assertExists($qrCodePath);
    }
});

test('business api: updating a booking ensures qr code exists', function () {
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user   = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking WITHOUT QR code initially
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'qr_code' => null
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'payment_status' => PaymentStatusEnum::PAID->value,
    ]);

    $response->assertStatus(200);
    
    $booking->refresh();
    $this->assertNotNull($booking->qr_code);
    
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);
});
