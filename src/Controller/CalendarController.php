<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Http\Validator;
use App\Service\CalendarService;

final class CalendarController
{
    public function __construct(private readonly CalendarService $service)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function year(Request $request, array $params): Response
    {
        $year = Validator::year($params['year']);

        return Response::json($this->service->year($year));
    }

    /**
     * @param array<string, string> $params
     */
    public function day(Request $request, array $params): Response
    {
        $year = Validator::year($params['year']);
        $date = Validator::date($params['date']);

        return Response::json($this->service->day($year, $date));
    }
}
