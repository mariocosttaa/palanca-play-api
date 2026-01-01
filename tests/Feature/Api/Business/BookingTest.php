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
use Illuminate\Support\Carbon;
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

test('business user can confirm booking presence for past booking', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create a booking that has already passed (yesterday)
    $pastDate = now()->subDay();
    $pastTime = $pastDate->copy()->setTime(10, 0, 0);

    $booking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $pastDate->format('Y-m-d'),
        'end_date'   => $pastDate->format('Y-m-d'),
        'start_time' => $pastTime->format('H:i:s'),
        'end_time'   => $pastTime->copy()->addHour()->format('H:i:s'),
        'present'    => null,
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

test('business user can confirm booking presence for same day booking with 1 hour before', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create a booking for today, 2 hours from now (more than 1 hour before)
    $futureTime = now()->addHours(2);
    $booking    = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date'   => now()->format('Y-m-d'),
        'start_time' => $futureTime->format('H:i:s'),
        'end_time'   => $futureTime->copy()->addHour()->format('H:i:s'),
        'present'    => null,
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
});

test('business user cannot confirm booking presence for future booking not on same day', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create a booking for tomorrow
    $futureDate = now()->addDay();
    $futureTime = $futureDate->copy()->setTime(10, 0, 0);

    $booking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $futureDate->format('Y-m-d'),
        'end_date'   => $futureDate->format('Y-m-d'),
        'start_time' => $futureTime->format('H:i:s'),
        'end_time'   => $futureTime->copy()->addHour()->format('H:i:s'),
        'present'    => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'present' => true,
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Não é possível marcar presença para agendamentos futuros. Apenas é permitido marcar presença no dia do agendamento (com pelo menos 1 hora de antecedência) ou após o horário do agendamento.',
    ]);
});

test('business user cannot confirm booking presence for same day booking less than 1 hour before', function () {
    // Set test time to noon UTC to avoid midnight crossing issues
    Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));

    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_start' => now()->subDay(),
        'date_end' => now()->addDay(),
    ]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create a booking for today, 30 minutes from now (less than 1 hour before)
    $futureTime = now('UTC')->addMinutes(30);
    $booking    = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => $futureTime->format('Y-m-d'),
        'end_date'   => $futureTime->format('Y-m-d'),
        'start_time' => $futureTime->format('H:i:s'),
        'end_time'   => $futureTime->copy()->addHour()->format('H:i:s'),
        'present'    => null,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.confirm-presence', [
        'tenant_id'  => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'present' => true,
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'Só é possível marcar presença com pelo menos 1 hora de antecedência do horário do agendamento.',
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

test('can get bookings filtered by presence status - pending', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking with present = null (should be included when filtering by pending)
    $pendingBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(2)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null, // Pending - not yet marked
    ]);

    // Create booking with present = true (should NOT be included when filtering by pending)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(1)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => true, // Confirmed
    ]);

    // Create booking with present = false (should NOT be included when filtering by pending)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(3)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => false, // Rejected/canceled
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'pending',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(1);

    // Should only return bookings with present = null
    foreach ($data as $booking) {
        expect($booking['present'])->toBeNull();
    }

    // Verify the correct booking is returned
    $returnedIds          = collect($data)->pluck('id')->toArray();
    $pendingBookingHashId = EasyHashAction::encode($pendingBooking->id, 'booking-id');
    expect($returnedIds)->toContain($pendingBookingHashId);
});

test('can get bookings filtered by presence status - present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking with present = true (should be included when filtering by confirmed)
    $confirmedBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(1)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => true, // Confirmed
    ]);

    // Create booking with present = null (should NOT be included when filtering by confirmed)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(2)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null, // Pending
    ]);

    // Create booking with present = false (should NOT be included when filtering by confirmed)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(3)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => false, // Rejected/canceled
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'present',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(1);

    // Should only return bookings with present = true
    foreach ($data as $booking) {
        expect($booking['present'])->toBe(true);
    }

    // Verify the correct booking is returned
    $returnedIds            = collect($data)->pluck('id')->toArray();
    $confirmedBookingHashId = EasyHashAction::encode($confirmedBooking->id, 'booking-id');
    expect($returnedIds)->toContain($confirmedBookingHashId);
});

test('can get bookings filtered by presence status - not-present', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking with present = false (should be included when filtering by not-present)
    $notPresentBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(3)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => false, // Not present
    ]);

    // Create booking with present = null (should NOT be included when filtering by not-present)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(2)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => null, // Pending
    ]);

    // Create booking with present = true (should NOT be included when filtering by not-present)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_date'   => now()->subDays(1)->format('Y-m-d'),
        'end_time'   => '11:00',
        'present'    => true, // Present
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'not-present',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(1);

    // Should only return bookings with present = false
    foreach ($data as $booking) {
        expect($booking['present'])->toBe(false);
    }

    // Verify the correct booking is returned
    $returnedIds             = collect($data)->pluck('id')->toArray();
    $notPresentBookingHashId = EasyHashAction::encode($notPresentBooking->id, 'booking-id');
    expect($returnedIds)->toContain($notPresentBookingHashId);
});

test('presence endpoint supports all filters', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create(['name' => 'John']);

    // Create booking matching all filters
    Booking::factory()->create([
        'tenant_id'      => $tenant->id,
        'court_id'       => $court->id,
        'user_id'        => $client->id,
        'start_date'     => now()->subDays(2)->format('Y-m-d'),
        'status'         => BookingStatusEnum::CONFIRMED,
        'payment_status' => PaymentStatusEnum::PAID,
        'present'        => null, // Pending
    ]);

    Sanctum::actingAs($user, [], 'business');

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'pending',
        'status'          => 'confirmed',
        'payment_status'  => 'paid',
        'court_id'        => $courtHashId,
        'search'          => 'John',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(1);

    // Verify all filters are applied correctly
    $booking = $data[0];
    expect($booking['present'])->toBeNull(); // presence_status = pending
    expect($booking['status'])->toBe('confirmed');
    expect($booking['payment_status'])->toBe('paid');
});

test('presence endpoint requires presence_status parameter', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        // No presence_status filter - should fail
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'O parâmetro presence_status é obrigatório. Valores aceites: all, pending, present, not-present',
    ]);
});

test('presence endpoint validates presence_status parameter value', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'invalid_value',
    ]));

    $response->assertStatus(400);
    $response->assertJson([
        'message' => 'O valor do parâmetro presence_status é inválido. Valores aceites: all, pending, present, not-present',
    ]);
});

test('presence endpoint returns all bookings when presence_status is all', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create bookings with different presence statuses
    $pendingBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'present'    => null, // Pending
    ]);

    $presentBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'present'    => true, // Present
    ]);

    $notPresentBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'present'    => false, // Not present
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'all',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(3); // Should return all three bookings

    // Verify all presence statuses are included
    $returnedIds = collect($data)->pluck('id')->toArray();
    expect($returnedIds)->toContain(EasyHashAction::encode($pendingBooking->id, 'booking-id'));
    expect($returnedIds)->toContain(EasyHashAction::encode($presentBooking->id, 'booking-id'));
    expect($returnedIds)->toContain(EasyHashAction::encode($notPresentBooking->id, 'booking-id'));

    // Verify presence values are correct
    $presenceValues = collect($data)->pluck('present')->toArray();
    expect($presenceValues)->toContain(null);
    expect($presenceValues)->toContain(true);
    expect($presenceValues)->toContain(false);
});

test('presence endpoint correctly filters by both status and presence_status', function () {
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant   = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user     = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court  = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create booking with status=confirmed and present=true (should be included)
    $confirmedBooking = Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'status'     => BookingStatusEnum::CONFIRMED,
        'present'    => true, // Confirmed presence
    ]);

    // Create booking with status=confirmed but present=null (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'status'     => BookingStatusEnum::CONFIRMED,
        'present'    => null, // Pending presence - should be excluded
    ]);

    // Create booking with status=confirmed but present=false (should NOT be included)
    Booking::factory()->create([
        'tenant_id'  => $tenant->id,
        'court_id'   => $court->id,
        'user_id'    => $client->id,
        'start_date' => now()->subDays(3)->format('Y-m-d'),
        'status'     => BookingStatusEnum::CONFIRMED,
        'present'    => false, // Rejected presence - should be excluded
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('bookings.presence', [
        'tenant_id'       => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'presence_status' => 'present',
        'status'          => 'confirmed',
    ]));

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(count($data))->toBe(1);

    // Verify only the booking with both status=confirmed AND present=true is returned
    $booking = $data[0];
    expect($booking['status'])->toBe('confirmed');
    expect($booking['present'])->toBe(true);

    // Verify the correct booking ID
    $returnedIds            = collect($data)->pluck('id')->toArray();
    $confirmedBookingHashId = EasyHashAction::encode($confirmedBooking->id, 'booking-id');
    expect($returnedIds)->toContain($confirmedBookingHashId);
});