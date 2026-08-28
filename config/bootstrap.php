<?php

declare(strict_types=1);

use App\Repository\CalendarRepository;
use App\Service\CalendarService;
use App\Controller\CalendarController;
use App\Controller\SpecialDayController;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

function createPdo(): PDO
{
    $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
    $database = getenv('MYSQL_DATABASE') ?: 'calendar';
    $user = getenv('MYSQL_USER') ?: 'calendar';
    $password = getenv('MYSQL_PASSWORD') ?: 'calendar';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $host, $database);

    $lastException = null;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        } catch (PDOException $exception) {
            $lastException = $exception;
            sleep(1);
        }
    }

    error_log($lastException?->getMessage() ?? 'Unable to connect to MySQL');
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database unavailable', 'code' => 503], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$pdo = createPdo();
$repository = new CalendarRepository($pdo);
$service = new CalendarService($repository);

return [
    'pdo' => $pdo,
    'repository' => $repository,
    'calendarController' => new CalendarController($service),
    'specialDayController' => new SpecialDayController($service),
];
