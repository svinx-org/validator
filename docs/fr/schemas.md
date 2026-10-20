# Schémas

Un schéma décrit un modèle ou un type sans exécuter de validation. Il sert à documenter une API (OpenAPI), générer un formulaire ou vérifier un contrat.

## `model_schema()`

```php
use Svinx\Validator\Examples\Models\User;

User::model_schema();
```

```json
{
    "title": "User",
    "type": "object",
    "properties": {
        "user_id": {
            "type": "integer",
            "description": "ID unique",
            "exclusiveMinimum": 0
        },
        "full_name": {
            "type": "string",
            "minLength": 2,
            "maxLength": 50
        },
        "role": {
            "type": "string",
            "enum": ["admin", "user", "guest"]
        },
        "birth_date": {
            "anyOf": [
                { "type": "object", "title": "DateTime" },
                { "type": "null" }
            ]
        }
    },
    "required": ["user_id"]
}
```

| Clé | Contenu |
| --- | --- |
| `title` | `Config::title` si défini, sinon le nom court de la classe |
| `type` | toujours `"object"` |
| `properties` | un schéma par champ, indexé par **alias** |
| `required` | champs sans défaut et non nullables, indexés par alias |

## D'où vient le schéma d'un champ ?

Chaque propriété est convertie en `Typing\Type` par réflexion (`Type::fromReflection`), puis `Type::schema()` est appelé. Le schéma d'un champ combine donc :

1. le schéma du type (`integer`, `string` + `format`, `array` + `items`, `anyOf`...) ;
2. les contraintes `#[Field]` ;
3. les métadonnées `#[Field]` (`title`, `description`, `default`).

### Correspondance des contraintes

| `#[Field]` | JSON Schema |
| --- | --- |
| `gt` | `exclusiveMinimum` |
| `ge` | `minimum` |
| `lt` | `exclusiveMaximum` |
| `le` | `maximum` |
| `multiple_of` | `multipleOf` |
| `min_length` | `minLength` |
| `max_length` | `maxLength` |
| `pattern` | `pattern` |
| `choices` | `enum` |
| `title` / `description` / `default` | `title` / `description` / `default` |

### Correspondance des types

| Type | Schéma |
| --- | --- |
| `int` / `float` / `bool` / `string` | `"type": "integer"` / `"number"` / `"boolean"` / `"string"` |
| `array` | `"type": "array"` |
| `Type::list(T)` | `"type": "array"` + `"items"` |
| `Type::map(K, V)` | `"type": "object"` + `"additionalProperties"` |
| `Type::literal(...)` | `"enum": [...]` |
| union / `optional` | `"anyOf": [...]` |
| `null` | `"type": "null"` |
| modèle (`BaseModel`) | `"type": "object"` + `"title": "<classe>"` |
| énumération PHP | `"type": "string"` + `"title": "<énumération>"` |
| `\DateTime` et assimilés | `"type": "object"` + `"title"` |
| type validé | `"type": "string"` + `"format"` (voir ci-dessous) |

### Formats des types validés

| Type | `format` |
| --- | --- |
| `EmailStr` | `email` |
| `HttpUrl` | `uri` |
| `IPv4Address` | `ipv4` |
| `IPv6Address` | `ipv6` |
| `UUID` | `uuid` |
| `Date` | `date` |
| `Datetime` | `date-time` |
| `Decimal` | `decimal` |

!!! note "Un `format: object` pour `\DateTime`"
    Le type natif `\DateTime` n'a pas de format JSON Schema standard détecté automatiquement : son schéma est `{"type": "object", "title": "DateTime"}`. Pour obtenir `{"type": "string", "format": "date-time"}`, utilisez le type validé `Datetime` (ou `Date`).

## Schéma d'un type isolé

```php
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

Type::list(Type::of(EmailStr::class))->schema();
// [
//     'type' => 'array',
//     'items' => ['type' => 'string', 'format' => 'email'],
// ]
```

```php
Type::map(Type::str(), Type::int())->schema();
// ['type' => 'object', 'additionalProperties' => ['type' => 'integer']]
```

```php
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Annotated;

Annotated::of(Type::str(), new Field(min_length: 3, max_length: 5))->schema();
// ['type' => 'string', 'minLength' => 3, 'maxLength' => 5]
```

## Schéma d'un `TypeAdapter`

```php
use Svinx\Validator\TypeAdapter;

$adapter = TypeAdapter::of(Type::map(Type::str(), Type::of(EmailStr::class)));

$adapter->json_schema();
// [
//     'type' => 'object',
//     'additionalProperties' => ['type' => 'string', 'format' => 'email'],
// ]

$adapter->json_schema_json();   // JSON indenté
```

## Propriétés garanties

- Le schéma est **déterministe** : mêmes déclarations → même tableau.
- Les champs sont indexés par **alias** (c'est le nom exposé à l'extérieur).
- L'ordre des propriétés suit l'ordre de déclaration dans la classe.
- `required` ne contient jamais un champ nullable ou doté d'un défaut.

## Limitations actuelles

- pas de `$defs` / `$ref` : un modèle imbriqué produit `{"type": "object", "title": "User"}` sans exposer ses propres propriétés dans le schéma parent ;
- pas de génération de définition complète pour les tableaux de modèles (PHP n'a pas de génériques déclaratifs) ;
- pas de sortie OpenAPI complète (`components`, `examples`, `discriminator`...).

Ces limites sont assumées : le schéma est un **aperçu documentaire**, pas un générateur OpenAPI complet.

La suite : [Erreurs](errors.md).
