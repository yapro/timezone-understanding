# timezone-understanding

Понимая работу с тайм-зоной - см. тесты

Итог для php-разработчика:
- во всей инфраструктуре должна быть одинаковая TZ (по-умолчанию UTC, для простоты понимания проблем стоит ее придерживаться)
- в PHP сеансе нужно сразу вызывать date_default_timezone_set
- в date_default_timezone_set должна быть указана TZ используемая на сервере/в инфраструктуре (чтобы удобнее сверять логи)
- пользователь сеанса имеет TZ, ее не нужно устанавливать в date_default_timezone_set, ее нужно устанавливать для сеанса работы с бд
- ниже тесты показали, что правильная работа с TZ в сеансе, реализована только в PostgresQL 

## Языки

PHP (информация по работе с таймзоной в рамках http/cli сеанса):
- в PHP нет возможности гарантировать неизменность таймзоны
- для изменения таймозны нужно использовать функцию date_default_timezone_set()
- если в настройках PHP не указано значение для date.timezone (по умолчанию UTC) + с момента старта сеанса не была
  вызвана функция date_default_timezone_set(), то вызов ini_set(date.timezone, NEW_VALUE) меняет UTC на указанное, 
  поэтому, нужно всегда при старте сеанса выставлять таймзону с помощью date_default_timezone_set

## Базы данных

Говорят, современные бд могут автоматом сохранять дату-времени в UTC, а при SELECT-е преобразовывать в дату-времени
согласно тайм-зоны сеанса, проверим этот факт.

MySQL:
- MySQL преобразует TIMESTAMP/DATETIME значения из текущего часового пояса в UTC для хранения (начиная с 8.0.19)
- MySQL преобразует TIMESTAMP обратно из UTC в текущий часовой (часовой пояс сеанса)
- MySQL НЕ преобразует DATETIME обратно из UTC в текущий часовой (часовой пояс сеанса) даже в версии 8.0.27
- итог: нативную поддержку TZ сеанса не использовать (придется писать реализацию конвертации из UTC в TZ пользователя на клиенте)
- p.s. в рамках доктрины придется использовать https://www.doctrine-project.org/projects/doctrine-orm/en/2.10/cookbook/working-with-datetime.html
  или что-то вроде https://github.com/timostamm/doctrine-fixed-timezone

PgSQL:
- если вставлять дату времени в TZ+1, а читать в TZ-0, то из даты времени вычитается час + будет указана TZ-0 (все верно)
- если вставлять дату времени в TZ+1, а читать в TZ-1, то дата времени будет в TZ+1, противоречит первому, но:
- если читать в TZ-1 в виде названия Africa/Tunis, то к дате времени прибавится час + будет указана TZ-1 (все верно)
- итог: можно использовать нативную поддержку TZ, но только если в сеансах с бд использовать названия TZ, а не часовые отступы
- плюс: постгрес поддерживает историю изменения таймзон, это круто https://postgrespro.ru/docs/postgresql/14/datatype-datetime#DATATYPE-TIMEZONES

Список аббревиатур: SELECT * FROM pg_timezone_abbrevs;

Тарантул (еще не тестировался):
- https://www.tarantool.io/ru/tdg/1.6/dev/sandbox/#sandbox-datetime
- https://github.com/tarantool/icu-date
- https://github.com/tarantool/tarantool/discussions/6244

## Тестирование

### Build

```sh
docker-compose up
```

### Tests

```sh
docker-compose exec phpc php /app/test.php
```

Материалы:
- https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
- https://habr.com/ru/post/129319/
