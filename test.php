<?php

// TZ - time zone
// DBSTZ - database session timezone (таймзона сеанса работы с базой для текущего пользователя)

function writeln(string $message): void
{
    echo $message . PHP_EOL;
}

function check(string $expected, string $result, bool $negative = false): string
{
    if ($negative === true  && $expected !== $result) {
        return $result;
    }
    if ($negative === false && $expected === $result) {
        return $result;
    }
    return "expected: $expected | result: \033[31m$result \033[0m";
}

// создаем подключения к базам:
$mysql = new PDO('mysql:host=mysqlc;dbname=my_db_name', 'my_user', 'my_pwd');
$postgres = new PDO('pgsql:host=postgresc;port=5432;dbname=my_db_name;user=my_user;password=my_pwd');

function insertDateTime(PDO $pdo, string $dateTime)
{
    writeln('insertDateTime: ' . $dateTime);
    if (false === $pdo->prepare("INSERT INTO my_table (my_date_time) VALUES (:my_date_time)")->execute(['my_date_time' => $dateTime])) {
        throw new UnexpectedValueException('Insert problems');
    }
}

function dbWriteDateTime($dateTime)
{
    global $mysql, $postgres;

    $sql = "INSERT INTO my_table (my_date_time) VALUES (:my_date_time)";
    $data = ['my_date_time' => $dateTime];

    $mysql->prepare($sql)->execute($data);
    $postgres->prepare($sql)->execute($data);
}

// проверяем работу с полем не умеющим хранить тайм-зону
// сохраняем дату в бд
// читаем дату из бд
function writeDateAndRead($dateTime)
{
    dbWriteDateTime($dateTime);
    showLastDateTime($dateTime);
}

function showLastDateTime($dateTime)
{
    global $mysql, $postgres;
    $sql = 'SELECT my_date_time FROM my_table ORDER BY id DESC LIMIT 1';
    writeln('MySQL: ' . check($dateTime, $mysql->query($sql)->fetchColumn()));
    writeln('Postgres: ' . check($dateTime, $postgres->query($sql)->fetchColumn()));
    writeln('');
}
function dbShowTimeZone()
{
    global $mysql, $postgres;
    writeln('MySQL time_zone: ' . $mysql->query('SELECT @@session.time_zone')->fetchColumn()); // -- @@global.time_zone,
    writeln('Postgres timezone: ' . $postgres->query("SELECT current_setting('TIMEZONE')")->fetchColumn());
}
function mysqlUpdateTimeZone(string $timeZone)
{
    global $mysql;
    $mysql->exec("SET @@time_zone = '".$timeZone."'"); //
    writeln('Now time_zone: ' . $mysql->query('SELECT @@time_zone')->fetchColumn()); // -- @@global.time_zone,
}
function mysqlSetTimeZone(string $timeZone)
{
    global $mysql;
    $mysql->exec("SET LOCAL time_zone='".$timeZone."'"); // SET @@time_zone = '+00:00';
    writeln('Now time_zone: ' . $mysql->query('SELECT @@session.time_zone')->fetchColumn()); // -- @@global.time_zone,
}
function pgsqlSetTimeZone(string $timeZone)
{
    global $postgres;
// в pg документации написано надо так: $postgres->exec("SET TIME ZONE '".$timeZone."'"); но следующее тоже работает и
// нравится тем, что указано слово сессия, что означает что изменение будет точно для сеанса, а не глобально:
    $postgres->exec("SET SESSION timezone TO '".$timeZone."'");
    writeln('Now timezone: ' . $postgres->query("SELECT current_setting('TIMEZONE')")->fetchColumn());
}
function dbSessionTimeZone(string $timeZone)
{
    mysqlSetTimeZone($timeZone);
    pgsqlSetTimeZone($timeZone);
}
function dbGetLastDateTime(PDO $pdo): string
{
    //writeln('timestamp: ' . $pdo->query('SELECT UNIX_TIMESTAMP(my_date_time) FROM my_table ORDER BY id DESC LIMIT 1')->fetchColumn());
    return $pdo->query('SELECT my_date_time FROM my_table ORDER BY id DESC LIMIT 1')->fetchColumn();
}


// печатаем текущие значения таймзон (дефолтные) для пхп и бд
writeln('Session timezone:' . PHP_EOL . '-----------------');
writeln('PHP date_default_timezone_get: ' . date_default_timezone_get() ?? 'not specified');
writeln('PHP date.timezone: ' . ini_get('date.timezone') ?? 'not specified');
dbShowTimeZone();
// итог: PHP date.timezone не задана, поэтому по-умолчанию UTC


// убедимся, что изменение date.timezone изменяет дефолтную таймзону
$expected = 'Canada/Newfoundland';
writeln('Test ini_set(date.timezone, '.$expected.'):' . PHP_EOL . '-----------------');
ini_set('date.timezone', $expected);
writeln('ini_get(date.timezone): ' . check($expected, ini_get('date.timezone')));
writeln('date_default_timezone_get: ' . check($expected, date_default_timezone_get()));
writeln('DateTime object timezone: ' . check($expected, (new DateTime())->format('e')));
writeln('');
// итог: вызов ini_set(date.timezone, VALUE) перебил значение, которое возвращает date_default_timezone_get(), но только
// потому, что мы еще ни разу не вызывали date_default_timezone_set (после ее вызова, уже нельзя будет изменить
// сессионную таймзону с помощью вызова ini_set(date.timezone, NEW_VALUE)


// убедимся, что изменение date_default_timezone_set изменяет таймзону установленную через date.timezone
$previousTimezone = $expected;
$expected = 'UTC';
writeln('Test date_default_timezone_set('.$expected.'):' . PHP_EOL . '-----------------');
date_default_timezone_set($expected);
writeln('ini_get(date.timezone): ' . check($previousTimezone, ini_get('date.timezone')));
writeln('date_default_timezone_get: ' . check($expected, date_default_timezone_get()));
writeln('DateTime object timezone: ' . check($expected, (new DateTime())->format('e')));
writeln('');
// итог: вызов date_default_timezone_set(VALUE) изменил сессионное значение, но не изменил значение установленное
// вызовом ini_set(date.timezone, SOME_VALUE), но это не важно, важно что DateTime-объекты имеют ожидаемую таймзону


// убедимся, что изменение date.timezone не изменяет сессионную таймзону установленную через date_default_timezone_set
$previousTimezone = $expected;
$expected = 'Asia/Kolkata';
writeln('Test ini_set(date.timezone, '.$expected.'):' . PHP_EOL . '-----------------');
ini_set('date.timezone', $expected);
writeln('ini_get(date.timezone): ' . check($expected, ini_get('date.timezone')));
writeln('date_default_timezone_get: ' . check($previousTimezone, date_default_timezone_get()));
writeln('DateTime object timezone: ' . check($previousTimezone, (new DateTime())->format('e')));
writeln('');
// ИТОГ: вызов ini_set(date.timezone, SOME_VALUE) не меняет сессионное значение таймзоны, а лишь изменяет значение
// установленное в date.timezone, таким образом DateTime-объекты имеют таймзону указанную с помощью date_default_timezone_set




// исследуем влияние измнения таймзоны на DateTime-объекты
writeln('Test to change PHP timezone for DateTime-object:' . PHP_EOL . '-----------------');

// убедимся, что изменение PHP timezone не влияет на изменение таймзоны DateTime-объектов созданных до изменения PHP timezone
date_default_timezone_set('Canada/Newfoundland');
$dateTime = new DateTimeImmutable();
$before = $dateTime->format(DATE_ATOM);
date_default_timezone_set('Asia/Kolkata');
$after = $dateTime->format(DATE_ATOM);
writeln('change timezone after create object: ' . check($before, $after));

// убедимся, что изменение PHP date.timezone влияет на изменение таймзоны DateTime-объектов созданных после изменения PHP date.timezone
writeln('change timezone and two objects:');
date_default_timezone_set('Canada/Newfoundland');
$before = new DateTimeImmutable();
date_default_timezone_set('Africa/Algiers');
$after = new DateTimeImmutable();
// напечатаем результаты чтобы было понятней:
writeln('before: ' . $before->format(DATE_ATOM));
writeln('after:  ' . $after->format(DATE_ATOM));
// убедимся, что появляется разница в таймзоне:
writeln('DateTime objects are different timezones: ' . check($before->format('e'), $after->format('e'), true));
// убедимся, что изменение PHP timezone влияет не только на таймзону, но и на дату времени:
writeln('DateTime objects are different Y-m-d H:i:s: ' . check($before->format('Y-m-d H:i:s'), $after->format('Y-m-d H:i:s'), true));
writeln('');
// итог: DateTime-объект создается с датой и временем которое сейчас в указанной таймзоне, а значение таймзоны в DateTime-объекте говорит только о том, где сейчас эта дата и время


//----------------------------------------------------------------------------------------------------------------------


writeln('MySQL:');
writeln('Insert DateTime without timezone: ' . PHP_EOL . '-----------------');
// Изменение таймзоны в пхп (но не в бд) не влияет на сохранение даты времени в бд:
date_default_timezone_set('Europe/Moscow');
// Если DateTime вставлена без таймзоны, то MySQL НЕ запоминает таймзону (сохраняется оригинальное значение DateTime):
mysqlUpdateTimeZone('+00:00');
$dateTime = '2020-01-01 10:10:10';
insertDateTime($mysql, $dateTime);
writeln('getDateTime: ' . check($dateTime, dbGetLastDateTime($mysql)));
// Если DateTime вставлена без таймзоны, то даже если менять DBSTZ, то MySQL ничего не делает с DateTime при SELECT:
mysqlUpdateTimeZone('+01:00');
writeln('getDateTime: ' . check($dateTime, dbGetLastDateTime($mysql)));
writeln('');

writeln('Insert DateTime (+01:00): ' . PHP_EOL . '-----------------');
// Если DateTime вставлена с таймзоной, то MySQL приводит DateTime к таймзоне UTC:
mysqlUpdateTimeZone('+00:00');
insertDateTime($mysql, '2020-01-01 10:10:10+01:00');
// Мы видим, что MySQL привел дату времени к UTC (это хорошо):
writeln('getDateTime: ' . check('2020-01-01 09:10:10', dbGetLastDateTime($mysql)));
mysqlUpdateTimeZone('+01:00');
// Указание DBSTZ никак не влияет на DateTime при SELECT (DateTime возвратился для UTC - это плохо):
writeln('getDateTime: ' . check('2020-01-01 09:10:10', dbGetLastDateTime($mysql)));
writeln('');
// сначала я подумал, что что-то делаю не так, но перепроверил и убедился, что не я один удивлен https://medium.com/@kenny_7143/time-zone-in-mysql-e7b73c70fd4e

writeln('Insert DateTime (+00:00) with DBSTZ (+01:00): ' . PHP_EOL . '-----------------');
// - не важно, совпадает DateTime таймзона с DBSTZ или нет, MySQL приводит DateTime к таймзоне UTC
// - при SELECT возвращается DateTime согласно DBSTZ
mysqlUpdateTimeZone('+01:00');
insertDateTime($mysql, '2020-01-01 10:10:10+00:00');
// Мы видим, что MySQL привел дату времени к UTC (это хорошо):
writeln('getDateTime: ' . check('2020-01-01 11:10:10', dbGetLastDateTime($mysql)));
mysqlUpdateTimeZone('+02:00');
// Указание DBSTZ никак не влияет на DateTime при SELECT (DateTime возвратился для UTC - это плохо):
writeln('getDateTime: ' . check('2020-01-01 11:10:10', dbGetLastDateTime($mysql)));
writeln('');
// MySQL итог: разочарование (возможно в силу недопонимания)
// Детали:
// - https://dev.mysql.com/doc/refman/8.0/en/date-and-time-literals.html
// - https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html#time-zone-installation


//----------------------------------------------------------------------------------------------------------------------

writeln('PostgreSQL:');
writeln('Insert DateTime without timezone: ' . PHP_EOL . '-----------------');
// Изменение таймзоны в пхп (но не в бд) не влияет на сохранение даты времени в бд:
date_default_timezone_set('Europe/Moscow');
// Если DBSTZ:-1 и DateTime без таймзоны, то PostgreSQL считает, что DateTime c таймзоной -1:
pgsqlSetTimeZone('-01:00');
insertDateTime($postgres, '2020-01-01 10:10:10');
pgsqlSetTimeZone('+00:00');
writeln('getDateTime: ' . check('2020-01-01 09:10:10+00', dbGetLastDateTime($postgres)));
writeln('');
// Если DBSTZ:+1 и DateTime без таймзоны, то PostgreSQL считает, что DateTime c таймзоной +1:
pgsqlSetTimeZone('+01:00');
insertDateTime($postgres, '2020-01-01 10:10:10');
pgsqlSetTimeZone('+00:00');
writeln('getDateTime: ' . check('2020-01-01 11:10:10+00', dbGetLastDateTime($postgres)));
writeln('');
// Если DBSTZ:0 и DateTime без таймзоны, то PostgreSQL считает, что DateTime c таймзоной 0:
pgsqlSetTimeZone('+00:00');
insertDateTime($postgres, '2020-01-01 10:10:10');
writeln('getDateTime: ' . check('2020-01-01 10:10:10+00', dbGetLastDateTime($postgres)));
writeln('');
// Итог: PostgreSQL сохраняет в UTC(+00)

// Если DBSTZ отличное от 00, то возвращается DateTime с TZ противоположной DBSTZ, но временем согласно DBSTZ (это правильно)
pgsqlSetTimeZone('+01:00');
writeln('getDateTime: ' . check('2020-01-01 09:10:10-01', dbGetLastDateTime($postgres)));
pgsqlSetTimeZone('-01:00');
writeln('getDateTime: ' . check('2020-01-01 11:10:10+01', dbGetLastDateTime($postgres)));
writeln('');
// Итог: время правильное (согласно DBSTZ), а на таймзону просто не нужно смотреть (не понимаю, почему противоположно DBSTZ)

writeln('Insert DateTime with timezone: ' . PHP_EOL . '-----------------');
// Если DateTime вставлена с таймзоной, то MySQL приводит DateTime к таймзоне UTC:
pgsqlSetTimeZone('+00:00');
insertDateTime($postgres, '2020-01-01 10:10:10+01:00');
// Мы видим, что PostgreSQL привел дату времени к UTC (это хорошо):
writeln('getDateTime: ' . check('2020-01-01 09:10:10+00', dbGetLastDateTime($postgres)));
writeln('');

writeln('Different +01:00 and Africa/Tunis (+1)'.PHP_EOL.'--:');
// выше мы вставили DateTime с TZ+1, сейчас читаем с DBSTZ+1, но получаем -2 часа времени (что за бред):
pgsqlSetTimeZone('+01:00');
writeln('getDateTime: ' . check('2020-01-01 08:10:10-01', dbGetLastDateTime($postgres)));
// однако, если DBSTZ+01:00 задать названием, то дата возвращается правильно:
pgsqlSetTimeZone('Africa/Tunis'); // это +01:00
writeln('getDateTime: ' . check('2020-01-01 10:10:10+01', dbGetLastDateTime($postgres)));
// в дополнение PgSQL подсказывает таймзону, которую мы сейчас используем
writeln('');


// Тест-кейс реальной жизни:
writeln('Real life case 1'.PHP_EOL.'--:');
// пользователь 1 (TZ:+1) создает запись
pgsqlSetTimeZone('+01:00');
insertDateTime($postgres, '2020-01-01 10:10:10+00');
// пользователь 2 (TZ: 0) читает запись (dateTime должен показывать время TZ: 0)
pgsqlSetTimeZone('+00:00');
// тест успешен т.к. при вставке pgsql учел dateTime TZ
writeln('getDateTime: ' . check('2020-01-01 10:10:10+00', dbGetLastDateTime($postgres)));
// пользователь 3 (TZ:-1) читает запись (dateTime должен показывать время для TZ: -1)
pgsqlSetTimeZone('-01:00');
writeln('getDateTime: ' . check('2020-01-01 09:10:10-01', dbGetLastDateTime($postgres)));
// итог: тест провален (т.к. getDateTime: 2020-01-01 11:10:10+01) - нужно использовать названия таймзон, а не часовые отступы
writeln('');

// может быть при вставке в dateTime должна содержаться TZ аналогичная DBSTZ, проверим:
writeln('Real life case 2'.PHP_EOL.'--:');
// пользователь 1 (TZ:+1) создает запись
pgsqlSetTimeZone('+01:00');
insertDateTime($postgres, '2020-01-01 10:10:10+01');
// пользователь 2 (TZ: 0) читает запись (dateTime должен показывать время TZ: 0)
pgsqlSetTimeZone('+00:00');
writeln('getDateTime: ' . check('2020-01-01 09:10:10+00', dbGetLastDateTime($postgres)));
// пользователь 3 (TZ:-1) читает запись (dateTime должен показывать время TZ: 0)
pgsqlSetTimeZone('-01:00');
// итог: тест провален (т.к. getDateTime: 2020-01-01 10:10:10+01)
writeln('getDateTime: ' . check('2020-01-01 08:10:10+00', dbGetLastDateTime($postgres)));
// однако, если DBSTZ-01:00 задать названием, то дата возвращается правильно:
pgsqlSetTimeZone('Atlantic/Azores'); // это -01:00
writeln('getDateTime: ' . check('2020-01-01 08:10:10-01', dbGetLastDateTime($postgres)));
// в дополнение PgSQL подсказывает таймзону, которую мы сейчас используем
writeln('');



// todo нужен тест доктрины
// Asia/Kabul
// Canada/Newfoundland
