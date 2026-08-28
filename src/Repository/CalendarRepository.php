<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\HttpException;
use PDO;
use PDOException;

final class CalendarRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, array{id: int, date: string, day: int, comment: ?string}>
     */
    public function findByYear(int $year): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, date, day, comment FROM calendar WHERE date >= :start AND date <= :end ORDER BY date'
        );
        $statement->execute([
            'start' => sprintf('%04d-01-01', $year),
            'end' => sprintf('%04d-12-31', $year),
        ]);

        return $this->mapByDate($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{id: int, date: string, day: int, comment: ?string}>
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query('SELECT id, date, day, comment FROM calendar ORDER BY date');
        $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values($this->mapByDate($rows));
    }

    /**
     * @return array{id: int, date: string, day: int, comment: ?string}|null
     */
    public function findByDate(string $date): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, date, day, comment FROM calendar WHERE date = :date'
        );
        $statement->execute(['date' => $date]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array{id: int, date: string, day: int, comment: ?string}
     */
    public function insert(string $date, int $day, ?string $comment): array
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO calendar (date, day, comment) VALUES (:date, :day, :comment)'
            );
            $statement->execute([
                'date' => $date,
                'day' => $day,
                'comment' => $comment,
            ]);
        } catch (PDOException $exception) {
            if ((int)($exception->errorInfo[1] ?? 0) === 1062) {
                throw new HttpException(409, 'Special day already exists for this date');
            }
            throw $exception;
        }

        $created = $this->findByDate($date);
        if ($created === null) {
            throw new HttpException(500, 'Failed to read created special day');
        }

        return $created;
    }

    /**
     * @return array{id: int, date: string, day: int, comment: ?string}|null
     */
    public function update(string $date, int $day, ?string $comment): ?array
    {
        $statement = $this->pdo->prepare(
            'UPDATE calendar SET day = :day, comment = :comment WHERE date = :date'
        );
        $statement->execute([
            'date' => $date,
            'day' => $day,
            'comment' => $comment,
        ]);

        return $this->findByDate($date);
    }

    public function delete(string $date): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM calendar WHERE date = :date');
        $statement->execute(['date' => $date]);

        return $statement->rowCount() > 0;
    }

    public function ping(): void
    {
        $this->pdo->query('SELECT 1');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array{id: int, date: string, day: int, comment: ?string}>
     */
    private function mapByDate(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrate($row);
            $map[$hydrated['date']] = $hydrated;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, date: string, day: int, comment: ?string}
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'date' => (string)$row['date'],
            'day' => (int)$row['day'],
            'comment' => $row['comment'] !== null ? (string)$row['comment'] : null,
        ];
    }
}
