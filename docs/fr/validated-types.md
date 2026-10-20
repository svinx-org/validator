# Types validés

Un **type validé** est un objet-valeur immuable : il valide et convertit une valeur brute à sa construction, expose la valeur normalisée et sait se sérialiser.

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

## Utilisation dans un modèle

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

Et en sortie :

```php
$customer->model_dump();
// ['id' => '123e4567-…', 'email' => 'Client@Example.com', 'balance' => '19.9', ...]
```

## Utilisation hors modèle

Les types validés fonctionnent seuls :

```php
use Svinx\Validator\Validators\EmailStr;

$email = new EmailStr('client@example.com');

(string) $email;        // "client@example.com"
$email->domain();       // "example.com"
$email->equals(new EmailStr('client@example.com'));   // true
```

Les types s'emboîtent (un type validé en entrée est déballé) :

```php
use Svinx\Validator\Validators\Date;
use Svinx\Validator\Validators\Datetime;

$dt = new Datetime('2023-12-31T23:00:00-05:00');

(new Date($dt))->dump();                 // "2023-12-31"
(new Datetime(new Date('2024-06-01')))->dump();   // "2024-06-01T00:00:00+00:00"
```

## Le contrat commun

Tous les types implémentent `Svinx\Validator\Validators\TypeValidator` et étendent `AbstractType` :

| Méthode | Rôle |
| --- | --- |
| `::validate(mixed $raw): static` | Valide une valeur brute (retourne l'instance si déjà du bon type) |
| `->value()` | Valeur PHP normalisée (`string`, `\DateTimeImmutable`...) |
| `->dump()` | Valeur sérialisable utilisée par `model_dump()` |
| `->equals(mixed $other): bool` | Égalité par valeur normalisée |
| `(string) $type` | Même chose que `dump()` pour les types scalaires |

`jsonSerialize()` renvoie `dump()`, donc `json_encode($email)` fonctionne.

---

# Les types, un par un

## `EmailStr`

```php
use Svinx\Validator\Validators\EmailStr;

new EmailStr('client@example.com');   // ✔
new EmailStr('pas-un-email');          // ✘ ValidationError
new EmailStr(42);                      // ✘ "doit être une chaîne de caractères"
```

- validation via `FILTER_VALIDATE_EMAIL`, longueur maximale 254 (`EmailStr::MAX_LENGTH`) ;
- les espaces de début et de fin sont retirés, la casse est conservée ;
- `local()` → partie locale, `domain()` → domaine.

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

- seuls les schémas `http` et `https` sont acceptés (`HttpUrl::SCHEMES`) ;
- un hôte est obligatoire (`ftp://example.com` est refusé) ;
- la chaîne est conservée telle quelle (hors espaces de début/fin).

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

Formes acceptées : canonique, sans tirets, entre accolades, ou tout objet `Stringable` (ex. `Ramsey\Uuid\Uuid`).

## `Decimal`

`Decimal` évite les erreurs d'arrondi des floats : la valeur est stockée en **chaîne décimale canonique**.

```php
use Svinx\Validator\Validators\Decimal;

new Decimal('0.1')->value();      // "0.1"
new Decimal('1.50')->value();     // "1.5"      (zéros inutiles retirés)
new Decimal('1e3')->value();      // "1000"     (notation scientifique)
new Decimal('1e-3')->value();     // "0.001"
new Decimal('-0.0')->value();     // "0"
new Decimal(0.1 + 0.2)->value();  // "0.30000000000000004" (float déjà imprécis)
```

| Méthode | Résultat |
| --- | --- |
| `toFloat()` | float (perte de précision possible) |
| `toInt()` | int, ou `ValidationError` si la valeur a une partie décimale |
| `isInteger()` | bool |
| `isNegative()` | bool |
| `compare($other)` | -1, 0 ou 1 (comparaison exacte) |
| `equals($other)` | bool |

```php
(new Decimal('1.50'))->equals(new Decimal('1.5'));   // true
(new Decimal('9.99'))->compare('10');                // -1
```

!!! note "D'où vient la précision ?"
    Une entrée texte (`'0.1'`) reste exacte. Une entrée `float` a déjà été arrondie par IEEE 754 **avant** d'arriver dans `Decimal` : utilisez des chaînes pour les montants.

## `Date`

Parsing strict : `AAAA-MM-JJ` uniquement (plus prévisible que `new \DateTime()`, qui devine de nombreux formats).

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

Une instance `\DateTimeInterface` est acceptée (seule la date est conservée) :

```php
new Date(new \DateTime('2023-07-04 15:30:00'))->dump();   // "2023-07-04"
```

## `Datetime`

ISO 8601 strict, mais tolérant sur les formes courantes :

| Entrée | Résultat (`dump()`) |
| --- | --- |
| `2024-01-02T03:04:05Z` | `2024-01-02T03:04:05+00:00` |
| `2024-01-02 03:04:05Z` | `2024-01-02T03:04:05+00:00` |
| `2024-01-02T03:04:05+02:00` | `2024-01-02T03:04:05+02:00` |
| `2024-01-02T03:04Z` | `2024-01-02T03:04:00+00:00` |
| `2024-01-02Z` | `2024-01-02T00:00:00+00:00` |
| `2024-01-02T03:04:05.123456Z` | `2024-01-02T03:04:05.123456+00:00` |
| `demain` | ✘ `ValidationError` |
| `2024-01-02T25:00:00Z` | ✘ heure impossible |
| `2024-13-02T03:04:05Z` | ✘ date du calendrier invalide |

```php
use Svinx\Validator\Validators\Datetime;

$dt = new Datetime('2024-01-02T12:00:00Z');

$dt->toTimezone('Europe/Paris')->dump();   // "2024-01-02T13:00:00+01:00"
$dt->toDate()->dump();                     // "2024-01-02"
$dt->toDateTime();                         // \DateTimeImmutable
```

Sans fuseau dans la valeur, le fuseau par défaut de PHP est utilisé.

!!! warning "`Datetime` et `\DateTime` se marchent dessus"
    PHP est insensible à la casse sur les noms de classes : `use Svinx\Validator\Validators\Datetime;` masque `\DateTime` dans le fichier. Écrivez `\DateTime` pleinement qualifié, ou utilisez un alias :

    ```php
    use Svinx\Validator\Validators\Datetime as IsoDatetime;
    ```

## `SecretStr`

Une chaîne dont la valeur ne fuit jamais par accident :

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

Dans un modèle, `model_dump()` et `model_dump_json()` renvoient donc `**********` — pratique pour les logs et les réponses d'erreur d'une application.

## Tableau récapitulatif

| Type | Entrée acceptée | `dump()` | `format` JSON Schema |
| --- | --- | --- | --- |
| `EmailStr` | chaîne | chaîne | `email` |
| `HttpUrl` | chaîne | chaîne | `uri` |
| `IPv4Address` | chaîne | chaîne canonique | `ipv4` |
| `IPv6Address` | chaîne | chaîne compressée | `ipv6` |
| `UUID` | chaîne / `Stringable` | canonique minuscule | `uuid` |
| `Decimal` | int / float / chaîne / `Decimal` | chaîne décimale | `decimal` |
| `Date` | `AAAA-MM-JJ` / `\DateTimeInterface` | `AAAA-MM-JJ` | `date` |
| `Datetime` | ISO 8601 / `\DateTimeInterface` | ISO 8601 | `date-time` |
| `SecretStr` | chaîne | `**********` | `unknown` |

La suite : [validateurs personnalisés](custom-validators.md).
