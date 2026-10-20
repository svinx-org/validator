# Référence API

Référence exhaustive des classes, méthodes et attributs exposés.

## `Svinx\Validator\BaseModel`

Classe abstraite dont héritent tous les modèles.

### Cycle de vie

| Méthode | Description |
| --- | --- |
| `__construct(array $data = [])` | Valide et affecte les champs |
| `model_validate(array $data): static` | Construit le modèle (raccourci explicite) |
| `model_validate_json(string $json): static` | Construit depuis du JSON (`\JsonException` si invalide) |

### Sérialisation

| Méthode | Description |
| --- | --- |
| `model_dump(?array $include = null, ?array $exclude = null): array` | Tableau sérialisé, indexé par alias |
| `model_dump_json(int $flags = 0): string` | JSON |
| `extra(): array` | Clés inconnues conservées (`extra: allow`) |

### Configuration et schéma

| Méthode | Description |
| --- | --- |
| `model_config(): Config` | Configuration du modèle (surchargeable) |
| `model_schema(): array` | Schéma JSON du modèle |

### Champs

Une propriété est un champ si elle est **publique** et **non statique**. Les propriétés privées et protégées sont ignorées.

| Type PHP | Conversion à l'entrée | Sortie `model_dump()` |
| --- | --- | --- |
| `int`, `float`, `bool`, `string`, `array` | vérification stricte | inchangé |
| `?T` | accepte `null` | inchangé |
| sous-classe `BaseModel` | `array` → instance | tableau récursif |
| sous-classe `TypeValidator` | brut → instance validée | `->dump()` |
| énumération `BackedEnum` | valeur scalaire → cas | valeur scalaire |
| `\DateTime` / `\DateTimeImmutable` | chaîne → instance | chaîne ISO 8601 |
| `mixed` / non typé | accepté tel quel | inchangé |

## `Svinx\Validator\Field`

Attribut ciblant une propriété (`Attribute::TARGET_PROPERTY`).

```php
#[Field(
    alias: null,            // string|null
    title: null,            // string|null
    description: null,      // string|null
    gt: null, ge: null,     // float|int|null
    lt: null, le: null,     // float|int|null
    multiple_of: null,      // float|int|null
    min_length: null,       // int|null
    max_length: null,       // int|null
    pattern: null,          // string|null
    choices: null,          // array|null
    default: null,          // mixed
)]
```

`Field` étend `FieldInfo`, qui expose aussi :

| Membre | Description |
| --- | --- |
| `check(mixed $value, string $name = 'La valeur'): void` | Vérifie les contraintes, lève `ValidationError` |
| `jsonSchema(): array` | Contraintes au format JSON Schema |
| `isRequired(): bool` | Vrai sans défaut ni factory |
| `getDefault(): mixed` | Défaut (ou résultat de la factory) |
| `FieldInfo::make(...)` | Fabrique équivalente au constructeur |

Les combinaisons incohérentes lèvent `InvalidArgumentException` à la déclaration : `gt` + `ge`, `lt` + `le`, `gt >= lt`, `ge > le`, `min_length > max_length`.

## `Svinx\Validator\FieldValidator`

Attribut ciblant une méthode (`TARGET_METHOD`, répétable).

```php
#[FieldValidator(
    key: null,                      // string|array|null : nom, alias, liste, '*' ou null
    mode: FieldValidator::AFTER,    // 'after' | 'before'
    always: false,                  // bool : exécuter même si la valeur est null
)]
```

| Constante | Valeur |
| --- | --- |
| `FieldValidator::BEFORE` | `'before'` |
| `FieldValidator::AFTER` | `'after'` |
| `FieldValidator::ANY` | `'*'` |

Signature de la méthode : `public function nom(string $cls, mixed $value): mixed`.

## Types validés (`Svinx\Validator\Validators`)

### Interface `TypeValidator`

```php
interface TypeValidator
{
    public static function validate(mixed $raw): static;
    public function value(): mixed;
    public function dump(): mixed;
}
```

### `AbstractType`

Base commune : `parse()` (abstrait), `value()`, `dump()`, `validate()`, `equals()`, `__toString()`, `jsonSerialize()`.

### Types

| Classe | Méthodes spécifiques |
| --- | --- |
| `EmailStr` | `local()`, `domain()`, constante `MAX_LENGTH` |
| `HttpUrl` | `scheme()`, `host()`, `port()`, `path()`, `query()`, `fragment()`, constante `SCHEMES` |
| `IPv4Address` | `toBinary()`, `toInt()` |
| `IPv6Address` | `toBinary()`, `toIPv4()` |
| `UUID` | `version()`, `variant()`, `toBinary()`, `toCompact()` |
| `Decimal` | `toFloat()`, `toInt()`, `isInteger()`, `isNegative()`, `compare()`, `equals()` |
| `Date` | `dump()`, `toDateTime()`, `year()`, `month()`, `day()` |
| `Datetime` | `dump()`, `toDateTime()`, `toTimezone(string)`, `toDate()` |
| `SecretStr` | `getSecretValue()`, `isEmpty()`, constante `MASK` |

## `Svinx\Validator\Typing`

### `Type`

| Membre | Description |
| --- | --- |
| `__construct(string $kind, ...)` | Rarement utilisé directement : préférez les fabriques |
| `::any/null/bool/int/float/str/array()` | Types simples |
| `::list($item)` → `ListType` | Liste homogène |
| `::map($key, $value)` → `Map` | Dictionnaire |
| `::union(...$types)`, `::optional($type)` | Unions |
| `::literal(...$values)` | Valeurs fermées |
| `::model/validator/enum/object(string)` | Par classe |
| `::of(string $classe)` | Détection automatique |
| `::from(Type|string|TypeVar)` | Conversion |
| `::fromReflection(?ReflectionType)` | Depuis une déclaration PHP |
| `::typevar(TypeVar)` | Variable de type |
| `->validate($value, array $bindings = [], ?string $label = null)` | Validation/convertion |
| `->dump($value, array $bindings = [])` | Sérialisation |
| `->schema(): array` | Schéma JSON |
| `->matches($value): bool` | Test léger |
| `->describe(): string` | Nom lisible |
| `->isNullable(): bool` | Accepte `null` |
| `->withConstraints(FieldInfo ...)`, `->withMetadata(...)` | Copies enrichies |
| `->lax()` | Coercition des scalaires |

Constantes : `Type::ANY`, `NULL`, `BOOL`, `INT`, `FLOAT`, `STR`, `ARRAY`, `LIST`, `MAP`, `MODEL`, `VALIDATOR`, `OBJECT`, `ENUM`, `LITERAL`, `UNION`, `TYPEVAR`.

### `ListType`

`ListType::make(Type|string|TypeVar $item)`, `item(): Type`.

### `Map`

`Map::make($key, $value)`, `keyType(): Type`, `valueType(): Type`.

### `TypeVar`

`TypeVar::of(string $name, Type|string|null $bound = null, Type|string|null $default = null)`, `describe()`.

### `Annotated`

`Annotated::of(Type|string|TypeVar $type, mixed ...$metadata): Type` — les `FieldInfo` deviennent des contraintes.

## `Svinx\Validator\TypeAdapter`

Classe finale.

| Membre | Description |
| --- | --- |
| `::of(Type|string|TypeVar $type, array $bindings = [])` | Construit l'adaptateur |
| `::model(string $class)` | Adaptateur d'un modèle |
| `->lax()` | Variante laxiste |
| `->validate($value)` | Valide et convertit |
| `->validate_json(string $json)` | Depuis du JSON |
| `->dump($value)` | Sérialise |
| `->dump_json($value, int $flags = 0)` | JSON |
| `->json_schema()` | Schéma JSON |
| `->json_schema_json(int $flags = JSON_PRETTY_PRINT)` | Schéma JSON encodé |
| `->type`, `->bindings` | Propriétés en lecture seule |

## `Svinx\Validator\Config`

| Membre | Description |
| --- | --- |
| `__construct(extra, populate_by_name, alias_generator, str_strip_whitespace, str_to_lower, str_to_upper, title)` | Options |
| `::make(array $options)` | Fabrique avec surcharge partielle |
| `->with(array $changes)` | Copie modifiée |
| `->toArray()` | Toutes les options |
| `->aliasFor(string $property, ?string $alias)` | Alias effectif |
| `->normalizeString(mixed $value)` | Normalisation d'une chaîne |

Constantes : `Config::EXTRA_IGNORE`, `Config::EXTRA_FORBID`, `Config::EXTRA_ALLOW`.

## `Svinx\Validator\Settings\BaseSettings`

Classe abstraite étendant `BaseModel`.

| Membre | Description |
| --- | --- |
| `__construct(?array $data = null, ?array $env = null)` | Données explicites + environnement simulé |
| `::load(?array $env = null): static` | Chargement depuis l'environnement |
| `::env_prefix(): string` | Préfixe des variables |
| `::env_file(): ?string` | Chemin d'un fichier `.env` |
| `::env_nested_delimiter(): ?string` | Séparateur des settings imbriqués |
| `::case_sensitive(): bool` | Respect de la casse des noms |
| `::readEnvironment(?string $file = null): array` | `.env` + variables réelles |
| `::parseEnvFile(string $file): array` | Analyse d'un fichier `.env` |

Les types des champs sont validés en mode laxiste.

## `Svinx\Validator\ValidationError`

Étend `\InvalidArgumentException`. Toutes les erreurs de validation de données (et de déclaration) passent par cette exception.

## Arborescence du paquet

```
src/
├── BaseModel.php
├── Config.php
├── Field.php
├── FieldInfo.php
├── FieldValidator.php
├── ModelValidator.php
├── TypeAdapter.php
├── ValidationError.php
├── Settings/
│   └── BaseSettings.php
├── Typing/
│   ├── Annotated.php
│   ├── ListType.php
│   ├── Map.php
│   ├── Type.php
│   └── TypeVar.php
└── Validators/
    ├── AbstractType.php
    ├── TypeValidator.php
    ├── Date.php
    ├── Datetime.php
    ├── Decimal.php
    ├── EmailStr.php
    ├── HttpUrl.php
    ├── IPv4Address.php
    ├── IPv6Address.php
    ├── SecretStr.php
    └── UUID.php
```

Les exemples documentés vivent dans `docs_src/` (namespace `Svinx\Validator\Examples`), les tests dans `tests/`.
