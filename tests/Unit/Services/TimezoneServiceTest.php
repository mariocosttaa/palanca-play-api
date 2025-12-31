<?php

use App\Services\TimezoneService;
use Carbon\Carbon;

test('it defaults to UTC if no context is set', function () {
    $service = new TimezoneService();
    expect($service->getContextTimezone())->toBe('UTC');
});

test('it can set context timezone', function () {
    $service = new TimezoneService();
    $service->setContextTimezone('Europe/London');
    expect($service->getContextTimezone())->toBe('Europe/London');
});

test('it converts user time to utc', function () {
    $service = new TimezoneService();
    $service->setContextTimezone('America/New_York'); // UTC-5

    // User input: 12:00 NY
    $input = '2025-01-01 12:00:00';
    
    $utc = $service->toUTC($input);

    // Should be 17:00 UTC
    expect($utc->format('Y-m-d H:i:s'))->toBe('2025-01-01 17:00:00')
        ->and($utc->timezoneName)->toBe('UTC');
});

test('it converts utc to user time', function () {
    $service = new TimezoneService();
    $service->setContextTimezone('America/New_York'); // UTC-5

    // DB value: 17:00 UTC
    $utc = Carbon::parse('2025-01-01 17:00:00', 'UTC');

    $userTime = $service->toUserTime($utc);

    // Should be 12:00 NY
    expect($userTime)->toContain('2025-01-01T12:00:00-05:00');
});

test('it handles null values gracefully', function () {
    $service = new TimezoneService();
    expect($service->toUTC(null))->toBeNull()
        ->and($service->toUserTime(null))->toBeNull();
});
