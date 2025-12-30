<?php

use App\Actions\General\EasyHashAction;
use App\Actions\General\QrCodeAction;
use App\Enums\BookingStatusEnum;
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

test('business user can create booking with new client no email', function () {
    // Create currency and country
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    Country::factory()->create(['calling_code' => '+351']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user   = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    // Valid invoice
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    // Add availability
    CourtAvailability::factory()->create([
        'tenant_id'     => $tenant->id,
        'court_id'      => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time'    => '08:00',
        'end_time'      => '20:00',
        'is_available'  => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    Sanctum::actingAs($user, [], 'business');

    // Step 1: Create the client first using the client endpoint
    $clientResponse = $this->postJson(route('clients.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'name'         => 'New Client',
        'calling_code' => '+351',
        'phone'        => '123456789',
    ]);

    $clientResponse->assertStatus(201);
    $clientId = $clientResponse->json('data.id');

    // Step 2: Create the booking with the created client
    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id'       => $courtHashId,
        'start_date'     => now()->addDay()->format('Y-m-d'),
        'start_time'     => '10:00',
        'end_time'       => '11:00',
        'client_id'      => $clientId,
        'payment_method' => PaymentMethodEnum::CASH->value,
        'payment_status' => PaymentStatusEnum::PAID->value,
    ]);

    $response->assertStatus(201);

    // Verify client created
    $this->assertDatabaseHas('users', [
        'name'  => 'New Client',
        'email' => null,
    ]);

    // Verify booking created
    $this->assertDatabaseHas('bookings', [
        'tenant_id'      => $tenant->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('business user can create booking with existing client', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    // Add availability
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
    $this->assertDatabaseHas('bookings', [
        'user_id' => $client->id,
    ]);
});

test('business user can update booking paid at venue', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => null,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'payment_method' => \App\Enums\PaymentMethodEnum::CASH->value,
        'payment_status' => \App\Enums\PaymentStatusEnum::PAID->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id'             => $booking->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('business user can confirm booking presence', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'present'   => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'present' => true,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id'      => $booking->id,
        'present' => true,
    ]);

    // Test unconfirming
    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'present' => false,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id'      => $booking->id,
        'present' => false,
    ]);
});

test('can search bookings by client name', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Court A']);

    // Create clients with specific names
    $client1 = User::factory()->create(['name' => 'Alice', 'surname' => 'Johnson']);
    $client2 = User::factory()->create(['name' => 'Bob', 'surname' => 'Smith']);

    // Create bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client1->id,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client2->id,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'search'    => 'Alice',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(1, count($data));
});

test('can search bookings by court name', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court1 = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tennis Court']);
    $court2 = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Padel Court']);

    $client = User::factory()->create();

    // Create bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court1->id,
        'user_id'   => $client->id,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court2->id,
        'user_id'   => $client->id,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'search'    => 'Tennis',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(1, count($data));
});

test('can delete booking with card payment method', function () {
    Storage::fake('public');

    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo      = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
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
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo      = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
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
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => null,
        'payment_status' => PaymentStatusEnum::PENDING,
        'present'        => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo      = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);

    // Verify QR code file is deleted
    Storage::disk('public')->assertMissing($qrCodePath);
});

test('cannot delete booking paid from app', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível excluir um agendamento que foi pago pelo aplicativo. Você pode cancelar o agendamento alterando o status para cancelado.',
    ]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
});

test('cannot delete booking if client is marked as present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => true,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível excluir um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.',
    ]);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
});

test('deleting booking also deletes associated QR code file', function () {
    Storage::fake('public');

    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    // Generate QR code for the booking
    $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
    $qrCodeInfo      = QrCodeAction::create($tenant->id, $booking->id, $bookingIdHashed);
    $booking->update(['qr_code' => $qrCodeInfo->url]);

    // Verify QR code file exists before deletion
    $qrCodePath = "tenants/{$tenant->id}/qr-codes/booking_{$booking->id}_qr.svg";
    Storage::disk('public')->assertExists($qrCodePath);

    Sanctum::actingAs($user, [], 'business');

    // Delete the booking
    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
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
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking without QR code
    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
        'qr_code'        => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    // Delete the booking - should not throw error even without QR code
    $response = $this->deleteJson(route('bookings.destroy', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]));

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Agendamento removido com sucesso']);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
});

test('can update payment status from paid to pending for booking paid with card', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id'             => $booking->id,
        'payment_method' => PaymentMethodEnum::CARD,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);
});

test('can update payment status from paid to pending for booking paid with cash', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id'             => $booking->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);
});

test('cannot update payment status for booking paid from app', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'payment_status' => PaymentStatusEnum::PENDING->value,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payment_status']);
    $response->assertJson([
        'errors' => [
            'payment_status' => [
                'Não é possível alterar o status de pagamento de um agendamento pago pelo aplicativo.',
            ],
        ],
    ]);
    $this->assertDatabaseHas('bookings', [
        'id'             => $booking->id,
        'payment_method' => PaymentMethodEnum::FROM_APP,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);
});

test('cannot update booking if client is marked as present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $booking = Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_method' => PaymentMethodEnum::CASH,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => true,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'price' => 2000,
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.',
    ]);
});

// ==================== Filter Tests ====================

test('can filter bookings by status confirmed', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create bookings with different statuses
    $confirmedBooking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::CONFIRMED,
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::PENDING,
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::CANCELLED,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'status'    => 'confirmed',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    // Should only return confirmed bookings
    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['status'])->toBe('confirmed');
    }
});

test('can filter bookings by status pending', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::PENDING,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'status'    => 'pending',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['status'])->toBe('pending');
    }
});

test('can filter bookings by status cancelled', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::CANCELLED,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'status'    => 'cancelled',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['status'])->toBe('cancelled');
    }
});

test('can filter bookings by payment_status paid', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create paid booking
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);

    // Create pending payment booking
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id'      => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'payment_status' => 'paid',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['payment_status'])->toBe('paid');
    }
});

test('can filter bookings by payment_status pending', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'payment_status' => PaymentStatusEnum::PENDING,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id'      => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'payment_status' => 'pending',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['payment_status'])->toBe('pending');
    }
});

test('can filter bookings by date', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $targetDate = \Carbon\Carbon::parse(now()->addDays(5))->format('Y-m-d');

    // Create booking on target date - use Carbon to ensure proper date handling
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => \Carbon\Carbon::parse($targetDate),
    ]);

    // Create booking on different date
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => \Carbon\Carbon::parse(now()->addDays(10)),
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'date'      => $targetDate,
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();

    // Verify at least one booking matches the target date
    $found = false;
    foreach ($data as $booking) {
        if ($booking['start_date'] === $targetDate) {
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

test('can filter bookings by date range', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $startDate = now()->addDays(5)->format('Y-m-d');
    $endDate   = now()->addDays(7)->format('Y-m-d');

    // Create bookings within range
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->addDays(6)->format('Y-m-d'),
    ]);

    // Create booking outside range
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'start_date' => $startDate,
        'end_date'   => $endDate,
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        $bookingDate = $booking['start_date'];
        expect($bookingDate)->toBeGreaterThanOrEqual($startDate);
        expect($bookingDate)->toBeLessThanOrEqual($endDate);
    }
});

test('can filter bookings by court_id', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court1 = Court::factory()->create(['tenant_id' => $tenant->id]);
    $court2 = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking for court1
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court1->id,
        'user_id'   => $client->id,
    ]);

    // Create booking for court2
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court2->id,
        'user_id'   => $client->id,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $court1HashId = EasyHashAction::encode($court1->id, 'court-id');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'court_id'  => $court1HashId,
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    foreach ($data as $booking) {
        expect($booking['court_id'])->toBe($court1HashId);
    }
});

test('can combine multiple filters simultaneously', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tennis Court']);
    $client = User::factory()->create(['name' => 'John', 'surname' => 'Doe']);

    $targetDate = \Carbon\Carbon::parse(now()->addDays(5))->format('Y-m-d');

    // Create booking matching all filters
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'start_date'     => \Carbon\Carbon::parse($targetDate),
        'status'         => BookingStatusEnum::CONFIRMED,
        'payment_status' => PaymentStatusEnum::PAID,
    ]);

    // Create bookings that don't match all filters
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'start_date'     => \Carbon\Carbon::parse($targetDate),
        'status'         => BookingStatusEnum::PENDING, // Different status
        'payment_status' => PaymentStatusEnum::PAID,
    ]);

    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'start_date'     => \Carbon\Carbon::parse($targetDate),
        'status'         => BookingStatusEnum::CONFIRMED,
        'payment_status' => PaymentStatusEnum::PENDING, // Different payment status
    ]);

    Sanctum::actingAs($user, [], 'business');

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id'      => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'date'           => $targetDate,
        'status'         => 'confirmed',
        'payment_status' => 'paid',
        'court_id'       => $courtHashId,
        'search'         => 'John',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    // Should only return the booking that matches ALL filters
    expect($data)->not->toBeEmpty();

    // Verify all returned bookings match all filters
    foreach ($data as $booking) {
        expect($booking['start_date'])->toBe($targetDate);
        // Status and payment_status are returned as enum values (strings)
        expect($booking['status'])->toBe('confirmed');
        expect($booking['payment_status'])->toBe('paid');
        expect($booking['court_id'])->toBe($courtHashId);
    }
});

test('pagination works with filters', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create 25 confirmed bookings (more than default pagination of 20)
    Booking::factory()->count(25)->create([
        'tenant_id' => $tenant->id,
        'court_id'  => $court->id,
        'user_id'   => $client->id,
        'status'    => BookingStatusEnum::CONFIRMED,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.index', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'status'    => 'confirmed',
    ]));

    $response->assertStatus(200);

    // Check pagination structure
    $response->assertJsonStructure([
        'data',
        'links',
        'meta' => [
            'current_page',
            'per_page',
            'total',
            'last_page',
        ],
    ]);

    $meta = $response->json('meta');
    expect($meta['total'])->toBe(25);
    expect($meta['per_page'])->toBe(20);
    expect($meta['last_page'])->toBe(2);
    expect(count($response->json('data')))->toBe(20); // First page should have 20 items
});

test('can get pending presence bookings (past or started but not marked as present)', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create past booking not marked as present (should be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(2)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null, // Not marked as present
    ]);

    // Create past booking marked as present (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(1)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => true, // Already marked as present
    ]);

    // Create past booking marked as not present (should be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(3)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => false, // Marked as not present
    ]);

    // Create future booking (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->addDays(5)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->addDays(5)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.pending-presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();

    // Should only return bookings that are past/started and not marked as present
    foreach ($data as $booking) {
        $bookingDate = \Carbon\Carbon::parse($booking['start_date']);
        $isPast      = $bookingDate->isPast() || ($bookingDate->isToday() && \Carbon\Carbon::parse($booking['start_time'])->isPast());
        expect($isPast)->toBeTrue();
        expect($booking['present'])->not->toBe(true);
    }
});

test('pending presence includes bookings that have ended (end_date and end_time check)', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $now = now();

    // Create booking that started yesterday (should be included via start_date < today check)
    $booking1 = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->copy()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => $now->copy()->subDays(1)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null,
    ]);

    // Create booking that started today with past start_time (should be included via start_date = today AND start_time < now)
    $pastStartTime = $now->copy()->subHours(1)->format('H:i');
    $futureEndTime = $now->copy()->addHours(1)->format('H:i');
    $booking2      = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->format('Y-m-d'),
        'start_time' => $pastStartTime, // Started 1 hour ago
        'end_date'   => $now->format('Y-m-d'),
        'end_time'   => $futureEndTime, // Will end in 1 hour
        'present'    => null,
    ]);

    // Create booking that ended today with past end_time (should be included via end_date = today AND end_time < now)
    $pastEndTime = $now->copy()->subHours(2)->format('H:i');
    $booking3    = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->copy()->subDays(1)->format('Y-m-d'),
        'start_time' => '14:00',
        'end_date'   => $now->format('Y-m-d'),
        'end_time'   => $pastEndTime, // Ended 2 hours ago
        'present'    => null,
    ]);

    // Create booking that hasn't started yet (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->format('Y-m-d'),
        'start_time' => $now->copy()->addHours(2)->format('H:i'), // Starts in 2 hours
        'end_date'   => $now->format('Y-m-d'),
        'end_time'   => $now->copy()->addHours(3)->format('H:i'),
        'present'    => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.pending-presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();

    // Should include at least the bookings that have started or ended
    // Note: booking1 (started yesterday) and booking2 (started today) should be included
    // booking3 (ended today) should also be included if end_time check works
    expect(count($data))->toBeGreaterThanOrEqual(2);

    // Collect booking IDs to verify which ones are included
    $returnedIds = collect($data)->pluck('id')->toArray();

    // Verify all returned bookings have started or ended
    foreach ($data as $booking) {
        expect($booking['present'])->not->toBe(true);

        // Verify booking has started or ended
        $startDate     = \Carbon\Carbon::parse($booking['start_date']);
        $startTime     = \Carbon\Carbon::parse($booking['start_time']);
        $startDateTime = $startDate->copy()->setTimeFromTimeString($startTime->format('H:i:s'));

        $endDate     = \Carbon\Carbon::parse($booking['end_date']);
        $endTime     = \Carbon\Carbon::parse($booking['end_time']);
        $endDateTime = $endDate->copy()->setTimeFromTimeString($endTime->format('H:i:s'));

        $hasStarted = $startDateTime->isPast();
        $hasEnded   = $endDateTime->isPast();

        expect($hasStarted || $hasEnded)->toBeTrue('Booking should have started or ended');
    }

    // Verify that bookings that have ended today are included (end_date/end_time check works)
    // booking3 should be included because it ended today (end_date = today AND end_time < now)
    // even though it started yesterday
    $hasEndedTodayBooking = false;
    foreach ($data as $booking) {
        $endDate     = \Carbon\Carbon::parse($booking['end_date']);
        $endTime     = \Carbon\Carbon::parse($booking['end_time']);
        $endDateTime = $endDate->copy()->setTimeFromTimeString($endTime->format('H:i:s'));

        // Check if booking ended today (end_date is today AND end_time is in the past)
        if ($endDate->isToday() && $endDateTime->isPast()) {
            $hasEndedTodayBooking = true;
            break;
        }
    }

    // booking3 should be included via the end_date/end_time check
    // This verifies that the end_date/end_time check is working correctly
    expect($hasEndedTodayBooking)->toBeTrue('Should include bookings that have ended today (end_date = today AND end_time < now)');
});

test('pending presence excludes bookings that have not started yet', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    $now = now();

    // Create booking that hasn't started yet (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->format('Y-m-d'),
        'start_time' => $now->copy()->addHours(2)->format('H:i'), // Starts in 2 hours
        'end_date'   => $now->format('Y-m-d'),
        'end_time'   => $now->copy()->addHours(3)->format('H:i'),
        'present'    => null,
    ]);

    // Create booking for tomorrow (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $now->copy()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => $now->copy()->addDay()->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.pending-presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    // Should not include future bookings
    foreach ($data as $booking) {
        $startDate     = \Carbon\Carbon::parse($booking['start_date']);
        $startTime     = \Carbon\Carbon::parse($booking['start_time']);
        $startDateTime = $startDate->copy()->setTimeFromTimeString($startTime->format('H:i:s'));

        // Booking should have started or ended
        $hasStarted  = $startDateTime->isPast();
        $endDate     = \Carbon\Carbon::parse($booking['end_date']);
        $endTime     = \Carbon\Carbon::parse($booking['end_time']);
        $endDateTime = $endDate->copy()->setTimeFromTimeString($endTime->format('H:i:s'));
        $hasEnded    = $endDateTime->isPast();

        expect($hasStarted || $hasEnded)->toBeTrue();
    }
});

test('pending presence endpoint supports filters', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create(['name' => 'John']);

    // Create past booking matching filters
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'start_date'     => now()->subDays(2)->format('Y-m-d'),
        'status'         => BookingStatusEnum::CONFIRMED,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    $response = $this->getJson(route('bookings.pending-presence', [
        'tenant_id'      => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'status'         => 'confirmed',
        'payment_status' => 'paid',
        'court_id'       => $courtHashId,
        'search'         => 'John',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
});
