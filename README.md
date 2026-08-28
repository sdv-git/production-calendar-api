# Производственный календарь API

Микросервис на чистом PHP 8.4: REST API производственного календаря РФ.
Авторизация не нужна, календарь один на систему.

В MySQL хранятся только **особые дни**:

- праздничные (`day = 9`)
- сокращённые рабочие (`day = 8`)
- переносы (`day = 1..7` — эффективный день недели)

Обычные дни в таблицу не пишутся и вычисляются при запросе года.

Официальная основа seed-данных: [производственный календарь 2024](https://www.consultant.ru/law/ref/calendar/proizvodstvennye/2024/).

## Запуск

Нужны Docker и Docker Compose.

```bash
docker compose up --build
```

Сервис: [http://127.0.0.1:8472](http://127.0.0.1:8472)

- Документация и сетка календаря: [http://127.0.0.1:8472/docs](http://127.0.0.1:8472/docs)
- OpenAPI: [http://127.0.0.1:8472/openapi.yaml](http://127.0.0.1:8472/openapi.yaml)
- Health: [http://127.0.0.1:8472/health](http://127.0.0.1:8472/health)

Остановка: `docker compose down`. Данные MySQL живут в volume `mysql_data`.

## Кодировка поля `day`

| Значение | Смысл |
| --- | --- |
| 1 | воскресенье |
| 2 | понедельник |
| 3 | вторник |
| 4 | среда |
| 5 | четверг |
| 6 | пятница |
| 7 | суббота |
| 8 | сокращённый рабочий день |
| 9 | праздничный день |

Пример переноса: `2024-12-28` — суббота, но `day = 2` (понедельник), то есть рабочий день.

В ответах API дополнительно есть:

- `actual_day` — фактический день недели 1–7
- `type` — `holiday` / `shortened` / `transfer` / `regular`
- `is_working` — `false` для вс (`1`), сб (`7`) и праздника (`9`)

## Примеры

Календарь за 2024 год:

```bash
curl http://127.0.0.1:8472/api/v1/calendar/2024
```

Один день:

```bash
curl http://127.0.0.1:8472/api/v1/calendar/2024/2024-12-28
```

Добавить особый день:

```bash
curl -X POST http://127.0.0.1:8472/api/v1/special-days \
  -H 'Content-Type: application/json' \
  -d '{"date":"2025-01-01","day":9,"comment":"Новый год"}'
```

Изменить:

```bash
curl -X PUT http://127.0.0.1:8472/api/v1/special-days/2025-01-01 \
  -H 'Content-Type: application/json' \
  -d '{"day":9,"comment":"Новогодние каникулы"}'
```

Удалить:

```bash
curl -X DELETE http://127.0.0.1:8472/api/v1/special-days/2025-01-01
```

## Тесты

HTTP-проверки без PHPUnit, против уже запущенного стека:

```bash
docker compose exec app php tests/run.php
```

С хоста:

```bash
API_BASE=http://127.0.0.1:8472 php tests/run.php
```

## Стек

- PHP 8.4 FPM, без фреймворков и Composer
- nginx в том же контейнере, что и PHP
- MySQL 8
- Docker Compose, порт 8472
