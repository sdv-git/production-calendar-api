<?php

declare(strict_types=1);

$base = rtrim(getenv('API_BASE') ?: 'http://127.0.0.1', '/');
$passed = 0;
$failed = 0;

function request(string $method, string $path, ?array $body = null): array
{
    global $base;

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ];
    if ($body !== null) {
        $options['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $raw = @file_get_contents($base . $path, false, stream_context_create($options));
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\b/', $http_response_header[0], $matches)) {
        $status = (int)$matches[1];
    }

    $json = null;
    if (is_string($raw) && $raw !== '') {
        $json = json_decode($raw, true);
    }

    return [
        'status' => $status,
        'json' => $json,
        'raw' => $raw,
    ];
}

function fail(string $message): void
{
    global $failed;
    $failed++;
    fwrite(STDERR, "FAIL  {$message}\n");
}

function pass(string $message): void
{
    global $passed;
    $passed++;
    fwrite(STDOUT, "ok    {$message}\n");
}

function assert_status(array $response, int $expected, string $label): void
{
    if ($response['status'] !== $expected) {
        fail("{$label}: expected HTTP {$expected}, got {$response['status']} {$response['raw']}");
        return;
    }
    pass($label);
}

function assert_true(bool $condition, string $label): void
{
    if (!$condition) {
        fail($label);
        return;
    }
    pass($label);
}

function day_by_date(array $days, string $date): ?array
{
    foreach ($days as $day) {
        if (($day['date'] ?? null) === $date) {
            return $day;
        }
    }
    return null;
}

$health = request('GET', '/health');
assert_status($health, 200, 'GET /health');
assert_true(($health['json']['status'] ?? null) === 'ok', 'health status is ok');

$calendar = request('GET', '/api/v1/calendar/2024');
assert_status($calendar, 200, 'GET /api/v1/calendar/2024');
$days = $calendar['json']['days'] ?? [];
assert_true(is_array($days) && count($days) === 366, '2024 is a leap year with 366 days');

$newYear = day_by_date($days, '2024-01-01');
assert_true(
    $newYear !== null && $newYear['day'] === 9 && $newYear['type'] === 'holiday' && $newYear['is_working'] === false,
    '2024-01-01 is a holiday'
);

$christmas = day_by_date($days, '2024-01-07');
assert_true(
    $christmas !== null && $christmas['day'] === 9 && $christmas['comment'] === 'Рождество Христово',
    '2024-01-07 is Christmas'
);

$shortened = day_by_date($days, '2024-02-22');
assert_true(
    $shortened !== null && $shortened['day'] === 8 && $shortened['type'] === 'shortened' && $shortened['is_working'] === true,
    '2024-02-22 is a shortened working day'
);

$transferWork = day_by_date($days, '2024-12-28');
assert_true(
    $transferWork !== null
    && $transferWork['day'] === 2
    && $transferWork['actual_day'] === 7
    && $transferWork['type'] === 'transfer'
    && $transferWork['is_working'] === true,
    '2024-12-28 Saturday is a transferred working Monday'
);

$transferOff = day_by_date($days, '2024-12-31');
assert_true(
    $transferOff !== null
    && $transferOff['day'] === 1
    && $transferOff['type'] === 'transfer'
    && $transferOff['is_working'] === false,
    '2024-12-31 is a transferred day off'
);

$workingSaturday = day_by_date($days, '2024-04-27');
assert_true(
    $workingSaturday !== null && $workingSaturday['day'] === 2 && $workingSaturday['is_working'] === true,
    '2024-04-27 Saturday is a working day'
);

$regularWednesday = day_by_date($days, '2024-01-10');
assert_true(
    $regularWednesday !== null
    && $regularWednesday['day'] === 4
    && $regularWednesday['type'] === 'regular'
    && $regularWednesday['is_working'] === true,
    '2024-01-10 regular Wednesday'
);

$single = request('GET', '/api/v1/calendar/2024/2024-05-09');
assert_status($single, 200, 'GET one day');
assert_true(($single['json']['type'] ?? null) === 'holiday', '2024-05-09 Victory Day');

$badYear = request('GET', '/api/v1/calendar/abcd');
assert_status($badYear, 400, 'invalid year is 400');

$mismatch = request('GET', '/api/v1/calendar/2024/2025-01-01');
assert_status($mismatch, 400, 'date/year mismatch is 400');

$special = request('GET', '/api/v1/special-days?year=2024');
assert_status($special, 200, 'GET special days for 2024');
assert_true(is_array($special['json']['days'] ?? null) && count($special['json']['days']) === 26, '2024 has 26 special days');

$customDate = '2025-06-15';
request('DELETE', '/api/v1/special-days/' . $customDate);

$created = request('POST', '/api/v1/special-days', [
    'date' => $customDate,
    'day' => 9,
    'comment' => 'Тестовый праздник',
]);
assert_status($created, 201, 'POST special day');
assert_true(($created['json']['type'] ?? null) === 'holiday', 'created day is a holiday');

$duplicate = request('POST', '/api/v1/special-days', [
    'date' => $customDate,
    'day' => 8,
]);
assert_status($duplicate, 409, 'duplicate special day is 409');

$updated = request('PUT', '/api/v1/special-days/' . $customDate, [
    'day' => 8,
    'comment' => 'Сокращённый',
]);
assert_status($updated, 200, 'PUT special day');
assert_true(($updated['json']['day'] ?? null) === 8 && ($updated['json']['type'] ?? null) === 'shortened', 'updated to shortened');

$invalidDay = request('POST', '/api/v1/special-days', [
    'date' => '2025-06-16',
    'day' => 10,
]);
assert_status($invalidDay, 400, 'day=10 is 400');

$deleted = request('DELETE', '/api/v1/special-days/' . $customDate);
assert_status($deleted, 204, 'DELETE special day');

$deletedAgain = request('DELETE', '/api/v1/special-days/' . $customDate);
assert_status($deletedAgain, 404, 'DELETE missing special day is 404');

$unknown = request('GET', '/api/v1/unknown');
assert_status($unknown, 404, 'unknown path is 404');

fwrite(STDOUT, "\n{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
