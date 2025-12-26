<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;

beforeEach(function () {
    // Create currency
    $this->currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    // Create tenant
    $this->tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60
    ]);
    
    // Create business user
    $this->businessUser = BusinessUser::factory()->create();
    $this->businessUser->tenants()->attach($this->tenant);
    
    // Create valid invoice
    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'paid',
        'date_end' => now()->addMonth()
    ]);
    
    // Create court
    $this->court = Court::factory()->create(['tenant_id' => $this->tenant->id]);
    
    // Create test clients
    $this->client1 = User::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    $this->client2 = User::factory()->create(['name' => 'Jane', 'surname' => 'Smith']);
    
    // Authenticate
    Sanctum::actingAs($this->businessUser, [], 'business');
    
    $this->tenantHashId = EasyHashAction::encode($this->tenant->id, 'tenant-id');
});

test('can get current month financial report', function () {
    // Create bookings for current month
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => now()->startOfMonth()->addDays(5),
        'price' => 5000, // €50.00
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson(route('financials.current', ['tenant_id' => $this->tenantHashId]));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'year',
            'month',
            'month_name',
            'bookings' => [
                '*' => [
                    'id',
                    'date',
                    'date_formatted',
                    'user',
                    'amount',
                    'amount_formatted',
                    'status',
                    'status_label',
                ]
            ],
            'summary' => [
                'total_bookings',
                'total_revenue',
                'total_revenue_formatted',
                'paid_count',
                'pending_count',
                'cancelled_count',
            ]
        ]
    ]);
    
    expect($response->json('data.bookings'))->toHaveCount(1);
    expect($response->json('data.summary.total_bookings'))->toBe(1);
    expect($response->json('data.summary.paid_count'))->toBe(1);
});

test('monthly report returns bookings ordered by date and time', function () {
    $date1 = now()->startOfMonth()->addDays(5);
    $date2 = now()->startOfMonth()->addDays(3);
    $date3 = now()->startOfMonth()->addDays(5);

    // Create bookings in random order
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $date1,
        'start_time' => '14:00',
        'price' => 5000,
    ]);

    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client2->id,
        'currency_id' => $this->currency->id,
        'start_date' => $date2,
        'start_time' => '10:00',
        'price' => 3000,
    ]);

    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $date3,
        'start_time' => '10:00',
        'price' => 4000,
    ]);

    $response = $this->getJson(route('financials.monthly-report', [
        'tenant_id' => $this->tenantHashId,
        'year' => now()->year,
        'month' => now()->month,
    ]));

    $response->assertStatus(200);
    
    $bookings = $response->json('data.bookings');
    expect($bookings)->toHaveCount(3);
    
    // Check ordering: earliest date first, then by time
    expect($bookings[0]['user']['name'])->toBe('Jane'); // date2, 10:00
    expect($bookings[1]['user']['name'])->toBe('John'); // date3 (same as date1), 10:00
    expect($bookings[2]['user']['name'])->toBe('John'); // date1, 14:00
});

test('monthly report validates future dates', function () {
    $futureDate = now()->addMonths(2);
    
    $response = $this->getJson(route('financials.monthly-report', [
        'tenant_id' => $this->tenantHashId,
        'year' => $futureDate->year,
        'month' => $futureDate->month,
    ]));

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Cannot query future months.']);
});

test('monthly report validates invalid month', function () {
    $response = $this->getJson(route('financials.monthly-report', [
        'tenant_id' => $this->tenantHashId,
        'year' => now()->year,
        'month' => 13,
    ]));

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Mês inválido.']);
});

test('monthly stats calculates all statistics correctly', function () {
    $currentMonth = now()->startOfMonth();
    
    // Create various bookings with different statuses
    // 1. Paid booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(1),
        'price' => 5000, // €50.00
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // 2. Pending booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(2),
        'price' => 3000, // €30.00
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::PENDING,
    ]);

    // 3. Cancelled booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client2->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(3),
        'price' => 4000, // €40.00
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::CANCELLED,
    ]);

    // 4. Unpaid booking (not pending, not cancelled, not paid)
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(4),
        'price' => 2000, // €20.00
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // 5. No-show booking (present = false)
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client2->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(5),
        'price' => 6000, // €60.00
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
        'present' => false,
    ]);

    $response = $this->getJson(route('financials.monthly-stats', [
        'tenant_id' => $this->tenantHashId,
        'year' => $currentMonth->year,
        'month' => $currentMonth->month,
    ]));

    $response->assertStatus(200);
    
    $stats = $response->json('data.statistics');
    
    // Check counts
    expect($stats['total_bookings'])->toBe(5);
    expect($stats['paid_count'])->toBe(2);
    expect($stats['pending_count'])->toBe(1);
    expect($stats['cancelled_count'])->toBe(1);
    expect($stats['unpaid_count'])->toBe(1);
    expect($stats['not_present_count'])->toBe(1);
    
    // Check amounts (in cents)
    expect($stats['paid_amount'])->toBe(11000); // 5000 + 6000
    expect($stats['pending_amount'])->toBe(3000);
    expect($stats['cancelled_amount'])->toBe(4000);
    expect($stats['unpaid_amount'])->toBe(2000);
    expect($stats['total_revenue'])->toBe(11000); // Only paid bookings
    
    // Check percentages (JSON encodes whole number floats as integers)
    expect($stats['payment_rate'])->toBe(40); // 2/5 * 100 = 40.0 -> 40 in JSON
    expect($stats['pending_rate'])->toBe(20); // 1/5 * 100 = 20.0 -> 20 in JSON
    expect($stats['cancellation_rate'])->toBe(20); // 1/5 * 100 = 20.0 -> 20 in JSON
    expect($stats['no_show_rate'])->toBe(20); // 1/5 * 100 = 20.0 -> 20 in JSON
    
    // Check formatted amounts
    expect($stats['total_revenue_formatted'])->toBe('€ 110.00');
    expect($stats['paid_amount_formatted'])->toBe('€ 110.00');
});

test('monthly stats returns zero for empty month', function () {
    $response = $this->getJson(route('financials.monthly-stats', [
        'tenant_id' => $this->tenantHashId,
        'year' => now()->year,
        'month' => now()->month,
    ]));

    $response->assertStatus(200);
    
    $stats = $response->json('data.statistics');
    
    expect($stats['total_bookings'])->toBe(0);
    expect($stats['paid_count'])->toBe(0);
    expect($stats['total_revenue'])->toBe(0);
    expect($stats['cancellation_rate'])->toBe(0); // 0.0 -> 0 in JSON
    expect($stats['payment_rate'])->toBe(0); // 0.0 -> 0 in JSON
});

test('yearly stats aggregates all months correctly', function () {
    $year = now()->year;
    
    // Create bookings in different months
    // January - 2 paid bookings
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => "$year-01-15",
        'price' => 5000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client2->id,
        'currency_id' => $this->currency->id,
        'start_date' => "$year-01-20",
        'price' => 3000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // March - 1 paid, 1 cancelled
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => "$year-03-10",
        'price' => 4000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client2->id,
        'currency_id' => $this->currency->id,
        'start_date' => "$year-03-15",
        'price' => 2000,
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::CANCELLED,
    ]);

    $response = $this->getJson(route('financials.yearly-stats', [
        'tenant_id' => $this->tenantHashId,
        'year' => $year,
    ]));

    $response->assertStatus(200);
    
    $stats = $response->json('data.statistics');
    $breakdown = $response->json('data.monthly_breakdown');
    
    // Check yearly totals
    expect($stats['total_bookings'])->toBe(4);
    expect($stats['paid_count'])->toBe(3);
    expect($stats['cancelled_count'])->toBe(1);
    expect($stats['total_revenue'])->toBe(12000); // 5000 + 3000 + 4000
    
    // Check monthly breakdown
    expect($breakdown)->toHaveCount(12);
    expect($breakdown[0]['month'])->toBe(1);
    expect($breakdown[0]['total_revenue'])->toBe(8000); // January
    expect($breakdown[0]['total_bookings'])->toBe(2);
    
    expect($breakdown[2]['month'])->toBe(3);
    expect($breakdown[2]['total_revenue'])->toBe(4000); // March
    expect($breakdown[2]['total_bookings'])->toBe(2);
    
    // Check empty months
    expect($breakdown[1]['total_revenue'])->toBe(0); // February
    expect($breakdown[1]['total_bookings'])->toBe(0);
});

test('yearly stats validates future year', function () {
    $futureYear = now()->addYears(2)->year;
    
    $response = $this->getJson(route('financials.yearly-stats', [
        'tenant_id' => $this->tenantHashId,
        'year' => $futureYear,
    ]));

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Cannot query future years.']);
});

test('yearly stats validates invalid year', function () {
    $response = $this->getJson(route('financials.yearly-stats', [
        'tenant_id' => $this->tenantHashId,
        'year' => 1999,
    ]));

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Invalid year.']);
});

test('financial endpoints enforce tenant isolation', function () {
    // Create another tenant
    $otherTenant = Tenant::factory()->create(['currency' => 'eur']);
    Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
        'status' => 'paid',
        'date_end' => now()->addMonth()
    ]);
    
    $otherCourt = Court::factory()->create(['tenant_id' => $otherTenant->id]);
    
    // Create booking for other tenant
    Booking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'court_id' => $otherCourt->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => now()->startOfMonth()->addDays(5),
        'price' => 5000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    // Create booking for our tenant
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => now()->startOfMonth()->addDays(5),
        'price' => 3000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Request current month for our tenant
    $response = $this->getJson(route('financials.current', ['tenant_id' => $this->tenantHashId]));

    $response->assertStatus(200);
    
    // Should only see our tenant's booking
    expect($response->json('data.bookings'))->toHaveCount(1);
    expect($response->json('data.summary.total_revenue'))->toBe(3000);
});

test('financial resource formats client name correctly', function () {
    // Create booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => now()->startOfMonth()->addDays(5),
        'price' => 5000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson(route('financials.current', ['tenant_id' => $this->tenantHashId]));

    $response->assertStatus(200);
    
    $booking = $response->json('data.bookings.0');
    expect($booking['user']['name'])->toBe('John');
    expect($booking['user']['surname'])->toBe('Doe');
    expect($booking['status'])->toBe('paid');
    expect($booking['status_label'])->toBe('Pago');
});

test('financial resource handles different booking statuses', function () {
    $currentMonth = now()->startOfMonth();
    
    // Paid
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(1),
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Pending
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(2),
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::PENDING,
    ]);

    // Cancelled
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'user_id' => $this->client1->id,
        'currency_id' => $this->currency->id,
        'start_date' => $currentMonth->copy()->addDays(3),
        'payment_status' => PaymentStatusEnum::PENDING,
        'status' => BookingStatusEnum::CANCELLED,
    ]);

    $response = $this->getJson(route('financials.current', ['tenant_id' => $this->tenantHashId]));

    $response->assertStatus(200);
    
    $bookings = $response->json('data.bookings');
    
    // Should have 3 bookings
    expect($bookings)->toHaveCount(3);
    
    // Check that we have one of each status (order may vary)
    $statuses = collect($bookings)->pluck('status')->sort()->values()->all();
    expect($statuses)->toContain('paid');
    expect($statuses)->toContain('pending');
    expect($statuses)->toContain('cancelled');
    
    // Check status labels exist
    $statusLabels = collect($bookings)->pluck('status_label')->sort()->values()->all();
    expect($statusLabels)->toContain('Pago');
    expect($statusLabels)->toContain('Pendente');
    expect($statusLabels)->toContain('Cancelado');
});
