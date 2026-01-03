<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can create court availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    $availabilityData = [
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ];

    // Create availability
    $response = $this->postJson(
        route('courts.availabilities.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtIdHashId]),
        $availabilityData
    );

    // Assert the response is successful
    $response->assertStatus(201);

    // Assert availability was created in DB
    $this->assertDatabaseHas('courts_availabilities', [
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
    ]);
});

test('user can update court availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create initial availability
    $availability = $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID and availability ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');
    $availabilityIdHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    $updateData = [
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ];

    // Update availability
    $response = $this->putJson(
        route('courts.availabilities.update', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId,
            'availability_id' => $availabilityIdHashId
        ]),
        $updateData
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert DB updated
    $this->assertDatabaseHas('courts_availabilities', [
        'id' => $availability->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
    ]);
});

test('user can delete court availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create initial availability
    $availability = $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID and availability ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');
    $availabilityIdHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    // Delete availability
    $response = $this->deleteJson(
        route('courts.availabilities.destroy', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId,
            'availability_id' => $availabilityIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert DB missing
    $this->assertDatabaseMissing('courts_availabilities', [
        'id' => $availability->id,
    ]);
});

test('user can list court availabilities via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availabilities
    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // List availabilities
    $response = $this->getJson(
        route('courts.availabilities.index', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('user can get available dates without parameters (defaults to current month)', function () {
    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availability for the current day of week
    $currentDayOfWeek = now()->format('l');
    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => $currentDayOfWeek,
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Get available dates without parameters
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

test('user can get available dates with specific month and year', function () {
    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availability for all days of the week
    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Get available dates for specific month/year
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ]) . '?month=12&year=2025'
    );

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
    
    // Verify dates are in December 2025
    $dates = $response->json('data');
    if (count($dates) > 0) {
        expect($dates[0])->toStartWith('2025-12-');
    }
});

test('getDates endpoint validates month parameter', function () {
    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Test with invalid month (13)
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ]) . '?month=13&year=2025'
    );

    // Assert validation error
    $response->assertStatus(422);
});

test('getDates endpoint validates year parameter', function () {
    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Test with invalid year (1999)
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ]) . '?month=12&year=1999'
    );

    // Assert validation error
    $response->assertStatus(422);
});

test('getDates returns all dates in month when availability exists for all days', function () {
    // Set test time to before January 2026 to ensure all days in Jan are future dates
    \Illuminate\Support\Carbon::setTestNow('2025-12-01');

    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availability for ALL days of the week
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($days as $day) {
        $court->availabilities()->create([
            'tenant_id' => $tenant->id,
            'day_of_week_recurring' => $day,
            'start_time' => '08:00',
            'end_time' => '22:00',
            'is_available' => true,
        ]);
    }

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Get available dates for January 2026 (future month, has 31 days)
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ]) . '?month=1&year=2026'
    );

    // Assert the response is successful
    $response->assertStatus(200);
    
    // Verify we got 31 dates (all days in Jan)
    $dates = $response->json('data');
    expect(count($dates))->toBe(31);
    
    // Verify first and last date
    expect($dates[0])->toBe('2026-01-01');
    expect(end($dates))->toBe('2026-01-31');
});

test('getDates does not return past dates', function () {
    // Create a tenant and business user
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availability for ALL days of the week
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($days as $day) {
        $court->availabilities()->create([
            'tenant_id' => $tenant->id,
            'day_of_week_recurring' => $day,
            'start_time' => '08:00',
            'end_time' => '22:00',
            'is_available' => true,
        ]);
    }

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Get available dates for CURRENT month
    $response = $this->getJson(
        route('courts.availability.dates', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);
    
    // Verify no dates before today are returned
    $dates = $response->json('data');
    $today = now()->format('Y-m-d');
    
    foreach ($dates as $date) {
        expect($date)->toBeGreaterThanOrEqual($today);
    }
});


