<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\HttpException;
use App\Repository\CalendarRepository;
use DateTimeImmutable;

final class CalendarService
{
    public const SUNDAY = 1;
    public const SATURDAY = 7;
    public const SHORTENED = 8;
    public const HOLIDAY = 9;

    public function __construct(private readonly CalendarRepository $repository)
    {
    }

    /**
     * @return array{year: int, days: list<array<string, mixed>>}
     */
    public function year(int $year): array
    {
        $special = $this->repository->findByYear($year);
        $days = [];
        $cursor = new DateTimeImmutable(sprintf('%04d-01-01', $year));
        $end = new DateTimeImmutable(sprintf('%04d-12-31', $year));

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $days[] = $this->resolve($cursor, $special[$date] ?? null);
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'year' => $year,
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function day(int $year, string $date): array
    {
        $parsed = new DateTimeImmutable($date);
        if ((int)$parsed->format('Y') !== $year) {
            throw new HttpException(400, 'Date does not belong to the requested year');
        }

        return $this->resolve($parsed, $this->repository->findByDate($date));
    }

    /**
     * @return array{days: list<array<string, mixed>>}
     */
    public function specialDays(?int $year): array
    {
        $rows = $year === null
            ? $this->repository->findAll()
            : array_values($this->repository->findByYear($year));

        $days = [];
        foreach ($rows as $row) {
            $days[] = $this->presentStored($row);
        }

        return ['days' => $days];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $date, int $day, ?string $comment): array
    {
        return $this->presentStored($this->repository->insert($date, $day, $comment));
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $date, int $day, ?string $comment): array
    {
        $updated = $this->repository->update($date, $day, $comment);
        if ($updated === null) {
            throw new HttpException(404, 'Special day not found');
        }

        return $this->presentStored($updated);
    }

    public function delete(string $date): void
    {
        if (!$this->repository->delete($date)) {
            throw new HttpException(404, 'Special day not found');
        }
    }

    /**
     * @param array{id: int, date: string, day: int, comment: ?string}|null $row
     * @return array<string, mixed>
     */
    private function resolve(DateTimeImmutable $date, ?array $row): array
    {
        $actualDay = (int)$date->format('w') + 1;
        $day = $row !== null ? $row['day'] : $actualDay;

        $payload = [
            'date' => $date->format('Y-m-d'),
            'day' => $day,
            'actual_day' => $actualDay,
            'type' => $this->type($day, $actualDay, $row !== null),
            'is_working' => $this->isWorking($day),
            'comment' => $row['comment'] ?? null,
        ];

        if ($row !== null) {
            $payload['id'] = $row['id'];
        }

        return $payload;
    }

    /**
     * @param array{id: int, date: string, day: int, comment: ?string} $row
     * @return array<string, mixed>
     */
    private function presentStored(array $row): array
    {
        return $this->resolve(new DateTimeImmutable($row['date']), $row);
    }

    private function type(int $day, int $actualDay, bool $stored): string
    {
        if ($day === self::HOLIDAY) {
            return 'holiday';
        }
        if ($day === self::SHORTENED) {
            return 'shortened';
        }
        if ($stored && $day !== $actualDay) {
            return 'transfer';
        }

        return 'regular';
    }

    private function isWorking(int $day): bool
    {
        return !in_array($day, [self::SUNDAY, self::SATURDAY, self::HOLIDAY], true);
    }
}
