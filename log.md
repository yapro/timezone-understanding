Исследование GMT на практике

- в пхп у нас таймзона: Москва И мы не указываем таймзону в подключении к PostgreSQL
- в pg  у нас таймзона: GMT-0 И любая дата (с указанием таймзоны) приводится к GMT-0

Практика PHP:

echo (new \DateTime())->format(DATE_ATOM);                         // -- 2019-12-04T12:22:08+03:00
DI::getDb()->createCommand("SELECT NOW()")->queryAll(); // -- 2019-12-04 09:22:08.78155+00

Практика PG:

docker exec -it processing_psql_1 bash
psql -h localhost -U postgres
\c processing;
show timezone;

TimeZone это и есть GMT-0, словно мы выполнили set timezone = 'UTC';

Проведем эксперемент с полем deferred_start_time timestamp(0) with time zone.

Следующий пример демонстрирует, что значение автоматически сохраняется в UTC в соответствии с текущим часовым поясом:

UPDATE "public"."manual_sms_send" SET "deferred_start_time" = '2019-11-29 22:00:00+03' WHERE "id" = '1';
SELECT deferred_start_time FROM manual_sms_send WHERE "id" = '1';              -- 2019-11-29 19:00:00+00
SELECT deferred_start_time::timestamptz FROM manual_sms_send WHERE "id" = '1'; -- 2019-11-29 19:00:00+00
SELECT deferred_start_time::timestamp FROM manual_sms_send WHERE "id" = '1';   -- 2019-11-29 19:00:00

как видим дата времени преобразуется в UTC.

Сохраним без указания тамзоны:

UPDATE "public"."manual_sms_send" SET "deferred_start_time" = '2019-11-29 22:00:00' WHERE "id" = '1';
SELECT deferred_start_time FROM manual_sms_send WHERE "id" = '1';              -- 2019-11-29 22:00:00+00
SELECT deferred_start_time::timestamptz FROM manual_sms_send WHERE "id" = '1'; -- 2019-11-29 22:00:00+00
SELECT deferred_start_time::timestamp FROM manual_sms_send WHERE "id" = '1';   -- 2019-11-29 22:00:00

Как видим дата времени не преобразуется.

Проведем эксперемент с полем datetime_local timestamp(0) not null

UPDATE transaction SET datetime_local = '2019-11-14 14:31:00+03' WHERE id = '1';
SELECT datetime_local::timestamptz FROM transaction WHERE id = 1'; -- 2019-11-14 14:31:00+00

Как видим дата времени не преобразуется.

Еще немного эксперементов:

ocker exec -it processing_psql_1 bash
docker exec -it processing_psql-clickhouse_1 bash
tail -f /var/lib/postgresql/data/pgdata/pg_log/postgres.log
psql -h localhost -U postgres
select deferred_start_time::timestamptz FROM manual_sms_send;
UPDATE "public"."manual_sms_send" SET "deferred_start_time" = '2019-11-29 22:00:00 +03:55' WHERE "id" = '1';

php -i | grep timezone
Default timezone => UTC
date.timezone => no value => no value

php timezone = UTC and Timezone Database = internal
set timezone = 'Europe/Moscow';
PHP                     PG
2019-12-06 21:00:00+00  2019-12-07 00:00:00+03
2019-11-29 18:05:00+00  2019-11-29 21:05:00+03

set timezone = 'UTC';
PHP                     PG
2019-12-06 21:00:00+00  2019-12-06 21:00:00+00
2019-11-29 18:05:00+00  2019-11-29 18:05:00+00
---

date.timezone = Europe/Moscow
show timezone; -- TimeZone

PHP                     PG
2019-12-06 21:00:00+00  2019-12-06 21:00:00+00
2019-11-29 18:05:00+00  2019-11-29 18:05:00+00
