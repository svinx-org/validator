# Validated types

A **validated type** is an immutable value object: it validates and converts a raw value when it is constructed, exposes the normalized value, and knows how to serialize itself.

!!! example "docs_src/Validators/Customer.php"
    ```php
    <?php

    namespace Svinx\Validator\Examples\Validators;

    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Field;
    use Svinx\Validator\Validators\Date;
    use Svinx\Validator\Validators\Datetime;
    use Svinx\Validator\Validators\Decimal;
    use Svinx\Validator\Validators\EmailStr;
    use Svinx\Validator\Validators\HttpUrl;
    use Svinx\Validator\Validators\IPv4Address;
    use Svinx\Validator\Validators\IPv6Address;
    use Svinx\Validator\Validators\UUID;

    class Customer extends BaseModel
    {
        #[Field(alias: 'id')]
        public UUID $id;

        #[Field(alias: 'email')]
        public EmailStr $email;

        #[Field(alias: 'site')]
        public HttpUrl $site;

        public Decimal $balance;

        public Date $birthday;

        public Datetime $created_at;

        public ?IPv4Address $last_ip = null;

        public IPv6Address $ipv6;
    }
    ```

## Using them in a model

```php
use Svinx\Validator\Examples\Validators\Customer;

$customer = new Customer([
    'id'         => '123E4567-E89B-42D3-A456-426614174000',
    'email'      => '  Client@Example.com  ',
    'site'       => 'https://example.com/path?x=1',
    'balance'    => '19.90',
    'birthday'   => '1990-05-12',
    'created_at' => '2024-01-02T03:04:05Z',
    'ipv6'       => '2001:0DB8:0000:0000:0000:0000:0000:0001',
]);

$customer->email->value();      // "Client@Example.com"  (espaces retirés)
$customer->id->value();         // "123e4567-e89b-42d3-a456-426614174000" (normalisé)
$customer->balance->value();    // "19.9"   (précision exacte, sans float)
$customer->birthday->dump();    // "1990-05-12"
$customer->ipv6->value();       // "2001:db8::1"  (compressé)
```

And on output:

```php
$customer->model_dump();
// ['id' => '123e4567-…', 'email' => 'Client@Example.com', 'balance' => '19.9', ...]
```

## Using them outside a model

Validated types work on their own:

```php
use Svinx\Validator\Validators\EmailStr;

$email = new EmailStr('client@example.com');

(string) $email;        // "client@example.com"
$email->domain();       // "example.com"
$email->equals(new EmailStr('client@example.com'));   // true
```

The types nest (a validated type passed in as input is unwrapped):

```php
use Svinx\Validator\Validators\Date;
use Svinx\Validator\Validators\Datetime;

$dt = new Datetime('2023-12-31T23:00:00-05:00');

(new Date($dt))->dump();                 // "2023-12-31"
(new Datetime(new Date('2024-06-01')))->dump();   // "2024-06-01T00:00:00+00:00"
```

## The common contract

All the types implement `Svinx\Validator\Validators\TypeValidator` and extend `AbstractType`:

| Method | Role |
| --- | --- |
| `::validate(mixed $raw): static` | Validates a raw value (returns the instance if it is already of the right type) |
| `->value()` | Normalized PHP value (`string`, `\DateTimeImmutable`...) |
| `->dump()` | Serializable value used by `model_dump()` |
| `->equals(mixed $other): bool` | Equality by normalized value |
| `(string) $type` | Same as `dump()` for scalar types |

`jsonSerialize()` returns `dump()`, so `json_encode($email)` works.

---

# The types, one by one

## `EmailStr`

```php
use Svinx\Validator\Validators\EmailStr;

new EmailStr('client@example.com');   // ✔
new EmailStr('pas-un-email');          // ✘ ValidationError
new EmailStr(42);                      // ✘ "doit être une chaîne de caractères"
```

- validation through `FILTER_VALIDATE_EMAIL`, maximum length 254 (`EmailStr::MAX_LENGTH`);
- leading and trailing whitespace is stripped, the case is preserved;
- `local()` → local part, `domain()` → domain.

```php
$email = new EmailStr('eor.bah@example.com');
$email->local();    // "eor.bah"
$email->domain();   // "example.com"
```

## `HttpUrl`

```php
use Svinx\Validator\Validators\HttpUrl;

$url = new HttpUrl('https://example.com:8443/path?x=1#top');

$url->scheme();     // "https"
$url->host();       // "example.com"
$url->port();       // 8443
$url->path();       // "/path"
$url->query();      // "x=1"
$url->fragment();   // "top"
```

- only the `http` and `https` schemes are accepted (`HttpUrl::SCHEMES`);
- a host is mandatory (`ftp://example.com` is rejected);
- the string is kept as is (apart from leading and trailing whitespace).

## `IPv4Address`

```php
use Svinx\Validator\Validators\IPv4Address;

$ip = new IPv4Address('192.168.1.10');

$ip->value();        // "192.168.1.10"
$ip->toInt();        // 3232235786
$ip->toBinary();     // 4 octets
new IPv4Address('::1');   // ✘
```

## `IPv6Address`

```php
use Svinx\Validator\Validators\IPv6Address;

$ip = new IPv6Address('2001:0DB8:0000:0000:0000:0000:0000:0001');

$ip->value();        // "2001:db8::1"   (notation compressée)
$ip->toBinary();     // 16 octets
$ip->toIPv4();       // null, sauf pour les adresses ::ffff:a.b.c.d
```

## `UUID`

```php
use Svinx\Validator\Validators\UUID;

$uuid = new UUID('{123E4567-E89B-42D3-A456-426614174000}');

$uuid->value();      // "123e4567-e89b-42d3-a456-426614174000"
$uuid->version();    // 4
$uuid->variant();    // "RFC 4122"
$uuid->toCompact();  // "123e4567e89b42d3a456426614174000"
$uuid->toBinary();   // 16 octets
```

Accepted forms: canonical, without hyphens, wrapped in braces, or any `Stringable` object (e.g. `Ramsey\Uuid\Uuid`).

## `Decimal`

`Decimal` avoids the rounding errors of floats: the value is stored as a **canonical decimal string**.

```php
use Svinx\Validator\Validators\Decimal;

new Decimal('0.1')->value();      // "0.1"
new Decimal('1.50')->value();     // "1.5"      (zéros inutiles retirés)
new Decimal('1e3')->value();      // "1000"     (notation scientifique)
new Decimal('1e-3')->value();     // "0.001"
new Decimal('-0.0')->value();     // "0"
new Decimal(0.1 + 0.2)->value();  // "0.30000000000000004" (float déjà imprécis)
```

| Method | Result |
| --- | --- |
| `toFloat()` | float (precision may be lost) |
| `toInt()` | int, or `ValidationError` if the value has a fractional part |
| `isInteger()` | bool |
| `isNegative()` | bool |
| `compare($other)` | -1, 0 or 1 (exact comparison) |
| `equals($other)` | bool |

```php
(new Decimal('1.50'))->equals(new Decimal('1.5'));   // true
(new Decimal('9.99'))->compare('10');                // -1
```

!!! note "Where does the precision come from?"
    Text input (`'0.1'`) stays exact. A `float` input has already been rounded by IEEE 754 **before** it reaches `Decimal`: use strings for amounts.

## `Date`

Strict parsing: `YYYY-MM-DD` only (more predictable than `new \DateTime()`, which guesses a great many formats).

```php
use Svinx\Validator\Validators\Date;

$date = new Date('2024-02-29');

$date->value();        // \DateTimeImmutable à minuit
$date->dump();         // "2024-02-29"
$date->year();         // 2024
$date->month();        // 2
$date->day();          // 29
$date->toDateTime();   // \DateTimeImmutable

new Date('2024-02-30');   // ✘ pas une date du calendrier
new Date('12/25/2023');   // ✘ format refusé
new Date('2024-1-5');     // ✘ forme non zéro-padded refusée
```

A `\DateTimeInterface` instance is accepted (only the date is kept):

```php
new Date(new \DateTime('2023-07-04 15:30:00'))->dump();   // "2023-07-04"
```

## `Datetime`

Strict ISO 8601, but tolerant of the common shapes:

| Input | Result (`dump()`) |
| --- | --- |
| `2024-01-02T03:04:05Z` | `2024-01-02T03:04:05+00:00` |
| `2024-01-02 03:04:05Z` | `2024-01-02T03:04:05+00:00` |
| `2024-01-02T03:04:05+02:00` | `2024-01-02T03:04:05+02:00` |
| `2024-01-02T03:04Z` | `2024-01-02T03:04:00+00:00` |
| `2024-01-02Z` | `2024-01-02T00:00:00+00:00` |
| `2024-01-02T03:04:05.123456Z` | `2024-01-02T03:04:05.123456+00:00` |
| `demain` | ✘ `ValidationError` |
| `2024-01-02T25:00:00Z` | ✘ impossible hour |
| `2024-13-02T03:04:05Z` | ✘ invalid calendar date |

```php
use Svinx\Validator\Validators\Datetime;

$dt = new Datetime('2024-01-02T12:00:00Z');

$dt->toTimezone('Europe/Paris')->dump();   // "2024-01-02T13:00:00+01:00"
$dt->toDate()->dump();                     // "2024-01-02"
$dt->toDateTime();                         // \DateTimeImmutable
```

When the value carries no timezone, PHP's default timezone is used.

!!! warning "`Datetime` and `\DateTime` shadow each other"
    PHP is case-insensitive about class names: `use Svinx\Validator\Validators\Datetime;` hides `\DateTime` in the file. Write `\DateTime` fully qualified, or use an alias:

    ```php
    use Svinx\Validator\Validators\Datetime as IsoDatetime;
    ```

## `SecretStr`

A string whose value never leaks by accident:

```php
use Svinx\Validator\Validators\SecretStr;

$secret = new SecretStr('super-secret');

echo $secret;              // "**********"
(string) $secret;          // "**********"
$secret->dump();           // "**********"
json_encode($secret);      // "**********"
$secret->getSecretValue(); // "super-secret"  (accès explicite)
$secret->isEmpty();        // false
```

In a model, `model_dump()` and `model_dump_json()` therefore return `**********` — handy for logs and for an application's error responses.

## Summary table

| Type | Accepted input | `dump()` | JSON Schema `format` |
| --- | --- | --- | --- |
| `EmailStr` | string | string | `email` |
| `HttpUrl` | string | string | `uri` |
| `IPv4Address` | string | canonical string | `ipv4` |
| `IPv6Address` | string | compressed string | `ipv6` |
| `UUID` | string / `Stringable` | lowercase canonical | `uuid` |
| `Decimal` | int / float / string / `Decimal` | decimal string | `decimal` |
| `Date` | `YYYY-MM-DD` / `\DateTimeInterface` | `YYYY-MM-DD` | `date` |
| `Datetime` | ISO 8601 / `\DateTimeInterface` | ISO 8601 | `date-time` |
| `SecretStr` | string | `**********` | `unknown` |

Next: [custom validators](custom-validators.md).
