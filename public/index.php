<?php

declare(strict_types=1);

use App\Exception\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$container = require dirname(__DIR__) . '/config/bootstrap.php';

$request = Request::fromGlobals();
$router = new Router();

$calendarController = $container['calendarController'];
$specialDayController = $container['specialDayController'];
$repository = $container['repository'];

$router->add('GET', '/', static function (): Response {
    return Response::json([
        'name' => 'Production Calendar API',
        'version' => '1.0.0',
        'docs' => '/docs',
        'openapi' => '/openapi.yaml',
        'health' => '/health',
    ]);
});

$router->add('GET', '/health', static function () use ($repository): Response {
    $repository->ping();
    return Response::json(['status' => 'ok']);
});

$router->add('GET', '/api/v1/calendar/{year}', [$calendarController, 'year']);
$router->add('GET', '/api/v1/calendar/{year}/{date}', [$calendarController, 'day']);
$router->add('GET', '/api/v1/special-days', [$specialDayController, 'index']);
$router->add('POST', '/api/v1/special-days', [$specialDayController, 'create']);
$router->add('PUT', '/api/v1/special-days/{date}', [$specialDayController, 'update']);
$router->add('DELETE', '/api/v1/special-days/{date}', [$specialDayController, 'delete']);

try {
    $response = $router->dispatch($request);
    if ($response->status === 204) {
        http_response_code(204);
        return;
    }
    $response->send();
} catch (HttpException $exception) {
    Response::json([
        'error' => $exception->getMessage(),
        'code' => $exception->status,
    ], $exception->status)->send();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Response::json([
        'error' => 'Internal server error',
        'code' => 500,
    ], 500)->send();
}
