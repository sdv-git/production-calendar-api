<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;

final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $path = rtrim($path, '/') ?: '/';

        return new self(
            method: strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            path: $path,
            query: $_GET,
            body: file_get_contents('php://input') ?: '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->body === '') {
            throw new HttpException(400, 'JSON body is required');
        }

        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Invalid JSON body');
        }

        return $data;
    }

    public function query(string $key): ?string
    {
        $value = $this->query[$key] ?? null;
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string)$value : null;
    }
}
