<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Service\CalendarService;

final class SpecialDayController
{
    public function __construct(private readonly CalendarService $service)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        $yearParam = $request->query('year');
        $year = $yearParam === null || $yearParam === '' ? null : Validator::year($yearParam);

        return Response::json($this->service->specialDays($year));
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): Response
    {
        $payload = $request->json();
        if (!array_key_exists('date', $payload) || !array_key_exists('day', $payload)) {
            throw new HttpException(400, 'Fields date and day are required');
        }

        $date = Validator::date((string)$payload['date']);
        $day = Validator::day($payload['day']);
        $comment = Validator::comment($payload['comment'] ?? null);
        $created = $this->service->create($date, $day, $comment);

        return Response::json($created, 201, [
            'Location' => '/api/v1/special-days/' . $date,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $date = Validator::date($params['date']);
        $payload = $request->json();
        if (!array_key_exists('day', $payload)) {
            throw new HttpException(400, 'Field day is required');
        }

        $day = Validator::day($payload['day']);
        $comment = Validator::comment($payload['comment'] ?? null);

        return Response::json($this->service->update($date, $day, $comment));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $date = Validator::date($params['date']);
        $this->service->delete($date);

        return Response::json(null, 204);
    }
}
