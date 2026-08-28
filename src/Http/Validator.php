<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;

final class Validator
{
    public static function year(string $raw): int
    {
        if (!preg_match('/^\d{4}$/', $raw)) {
            throw new HttpException(400, 'Invalid year: expected a 4-digit year');
        }

        $year = (int)$raw;
        if ($year < 1970 || $year > 2100) {
            throw new HttpException(400, 'Year out of range: expected 1970-2100');
        }

        return $year;
    }

    public static function date(string $raw): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new HttpException(400, 'Invalid date: expected Y-m-d');
        }

        $year = (int)$date->format('Y');
        if ($year < 1970 || $year > 2100) {
            throw new HttpException(400, 'Date year out of range: expected 1970-2100');
        }

        return $raw;
    }

    public static function day(mixed $value): int
    {
        if (is_int($value)) {
            $day = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $day = (int)$value;
        } else {
            throw new HttpException(400, 'Invalid day: expected integer 1-9');
        }

        if ($day < 1 || $day > 9) {
            throw new HttpException(400, 'Invalid day: expected 1-9');
        }

        return $day;
    }

    public static function comment(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new HttpException(400, 'Invalid comment: expected string');
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > 255) {
            throw new HttpException(400, 'Invalid comment: max length is 255');
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
