# timezone-understanding

Понимая работу с тайм-зоной - см. тесты

Итог по PHP (информация по работе с таймзоной в рамках http/cli сеанса):
- в PHP нет возможности гарантировать неизменность таймзоны
- для изменения таймозны нужно использовать функцию date_default_timezone_set()
- если в настройках PHP не указано значение для date.timezone (по умолчанию UTC) + с момента старта сеанса не была
  вызвана функция date_default_timezone_set(), то вызов ini_set(date.timezone, NEW_VALUE) меняет UTC на указанное, 
  поэтому, нужно всегда при старте сеанса выставлять таймзону с помощью date_default_timezone_set

## Базы данных

Говорят, современные бд могут автоматом сохранять дату-времени в UTC, а при SELECT-е преобразовывать в дату-времени
согласно тайм-зоны сеанса, проверим этот факт.

Итог по MySQL:
- MySQL преобразует TIMESTAMP/DATETIME значения из текущего часового пояса в UTC для хранения (начиная с 8.0.19)
- MySQL преобразует TIMESTAMP обратно из UTC в текущий часовой (часовой пояс сеанса)
- MySQL НЕ преобразует DATETIME обратно из UTC в текущий часовой (часовой пояс сеанса) даже в версии 8.0.27

Тарантул (в планах):
- https://www.tarantool.io/ru/tdg/1.6/dev/sandbox/#sandbox-datetime
- https://github.com/tarantool/icu-date
- https://github.com/tarantool/tarantool/discussions/6244

### Build

```sh
docker-compose up
```

### Tests

```sh
docker-compose exec phpc php /app/test.php
```
