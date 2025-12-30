<?php

use App\Actions\General\EasyHashAction;
use App\Actions\General\QrCodeAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use App\Models\Manager\CurrencyModel;
use App\Models\Country;
use App\Models\CourtAvailability;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;

uses(RefreshDatabase::class);

test('business user can create booking with new client no email', function () {
    // Create currency and country
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    Country::factory()->create(['calling_code' => '+351']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Valid invoice
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Add availability
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    Sanctum::actingAs($user, [], 'business');

    // Step 1: Create the client first using the client endpoint
    $clientResponse = $this->postJson(route('clients.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'name' => 'New Client',
        'calling_code' => '+351',
        'phone' => '123456789',
    ]);
    
    $clientResponse->assertStatus(201);
    $clientId = $clientResponse->json('data.id');

    // Step 2: Create the booking with the created client
    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => $courtHashId,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'client_id' => $clientId,
        'payment_method' => PaymentMethodEnum::CASH->value,
        'payment_status' => PaymentStatusEnum::PAID->value,
    ]);

    $response->assertStatus(201);
    
    // Verify client created
    $this->assertDatabaseHas('users', [
        'name' => 'New Client',
        'email' => null,
    ]);

    // Verify booking created
    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $tenant->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('business user can create booking with existing client', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Add availability
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'client_id' => EasyHashAction::encode($client->id, 'user-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('bookings', [
        'user_id' => $client->id,
    ]);
});

test('business user can update booking paid at venue', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => null,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'payment_method' => \App\Enums\PaymentMethodEnum::CASH->value,
        'payment_status' => \App\Enums\PaymentStatusEnum::PAID->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('business user can confirm booking presence', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'present' => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'present' => true,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'present' => true,
    ]);
    
    // Test unconfirming
    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'present' => false,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'present' => false,
    ]);
});

test('can search bookings by client name', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Court A']);
    
    // Create clients with specific names
    $client1 = User::factory()->create(['name' => 'Alice', 'surname' => 'Johnson']);
    $client2 = User::factory()->create(['name' => 'Bob', 'surname' => 'Smith']);
    
    // Create bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client1->id,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client2->id,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'search' => 'Alice'
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(1, count($data));
});

test('can search bookings by court name', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court1 = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tennis Court']);
    $court2 = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Padel Court']);
    
    $client = User::factory()->create();
    
    // Create bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court1->id,
        'user_id' => $client->id,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court2->id,
        'user_id' => $client->id,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'search' => 'Tennis'
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(1, count($data));
});

test('can delete booking with card payment method', function () {
    Storage::fake('public');
    
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    
    // Verify QR code file is deleted
    Storage::disk('public')->assertMissing($qrCodePath);
});

test('can delete booking with cash payment method', function () {
    Storage::fake('public');
    
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    
    // Verify QR code file is deleted
    Storage::disk('public')->assertMissing($qrCodePath);
});

test('can delete booking with null payment method', function () {
    Storage::fake('public');
    
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => null,
        'payment_status' => PaymentStatusEnum::PENDING,
        'present' => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    
    // Verify QR code file is deleted
    Storage::disk('public')->assertMissing($qrCodePath);
});

test('cannot delete booking paid from app', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível excluir um agendamento que foi pago pelo aplicativo. Você pode cancelar o agendamento alterando o status para cancelado.'
    ]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
});

test('cannot delete booking if client is marked as present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => true,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível excluir um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.'
    ]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
});

test('deleting booking also deletes associated QR code file', function () {
    Storage::fake('public');
    
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists before deletion
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    // Delete the booking
    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(200);
    
    // Verify booking is deleted
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    
    // Verify QR code file is also deleted
    Storage::disk('public')->assertMissing($qrCodePath);
});

test('deleting booking without QR code does not cause errors', function () {
    Storage::fake('public');
    
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    // Create booking without QR code
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
        'qr_code' => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    // Delete the booking - should not throw error even without QR code
    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
});

test('can update payment status from paid to pending for booking paid with card', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);
});

test('can update payment status from paid to pending for booking paid with cash', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);
});

test('cannot update payment status for booking paid from app', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payment_status']);
    $response->assertJson([
        'errors' => [
            'payment_status' => [
                'Não é possível alterar o status de pagamento de um agendamento pago pelo aplicativo.'
            ]
        ]
    ]);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('cannot update booking if client is marked as present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present' => true,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'price' => 2000,
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.'
    ]);
});

