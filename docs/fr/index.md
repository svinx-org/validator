# Svinx Validator

**Validation de modèles et de schémas à la Pydantic, en PHP pur.**

`Svinx\Validator` apporte en PHP 8.4 les idées qui rendent Pydantic agréable à utiliser :

- vous déclarez **des propriétés typées** ;
- la bibliothèque **valide, convertit et sérialise** les données brutes (tableaux `json_decode`, variables d'environnement, formulaires...) ;
- vous ajoutez vos **règles métier** avec des méthodes, pas avec des tableaux de configuration imbriqués.

```php
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class User extends BaseModel
{
    #[Field(alias: 'user_id', gt: 0)]
    public int $id;

    #[Field(alias: 'full_name', min_length: 2)]
    public string $name = 'Anonyme';
}

$user = new User(['user_id' => 42, 'full_name' => 'Jean']);

$user->id;                    // int(42)
$user->name;                  // "Jean"
$user->model_dump();          // ['user_id' => 42, 'full_name' => 'Jean']
```

Une valeur invalide ne produit pas un modèle à moitié rempli : une `ValidationError` est levée **avant** que l'objet ne soit construit.

```php
new User(['user_id' => 0]);   // Svinx\Validator\ValidationError : Le champ 'id' doit être > 0
```

---

## Ce que couvre la bibliothèque

| Brique | Rôle | Chapitre |
| --- | --- | --- |
| `BaseModel` | Construit un modèle validé depuis un tableau | [Premiers pas](first-steps.md) |
| `Field` | Contraintes déclaratives (`gt`, `min_length`, `choices`, alias...) | [Champs et contraintes](fields-and-constraints.md) |
| Modèles imbriqués | Modèles dans des modèles, tableaux de modèles | [Modèles imbriqués](nested-models.md) |
| Types validés | `EmailStr`, `HttpUrl`, `UUID`, `Decimal`, `Date`... | [Types validés](validated-types.md) |
| `FieldValidator` | Validateurs personnalisés (l'équivalent de `@field_validator`) | [Validateurs personnalisés](custom-validators.md) |
| `Typing\Type` | Descripteurs de types (`list[int]`, `Optional`, `Literal`, `TypeVar`...) | [Types et annotations](typing.md) |
| `TypeAdapter` | Valider une valeur isolée, sans modèle | [TypeAdapter](type-adapter.md) |
| `Config` | Options de modèle (`extra`, alias automatiques, normalisation) | [Configuration](configuration.md) |
| `BaseSettings` | Configuration typée depuis l'environnement et `.env` | [Settings](settings.md) |
| Schémas | `model_schema()`, JSON Schema, formats OpenAPI | [Schémas](schemas.md) |
| `ValidationError` | Comprendre et gérer les erreurs | [Erreurs](errors.md) |

---

## Le cours

1. [Installation](installation.md)
2. [Premiers pas](first-steps.md)
3. [Champs et contraintes](fields-and-constraints.md)
4. [Modèles imbriqués](nested-models.md)
5. [Types validés](validated-types.md)
6. [Validateurs personnalisés](custom-validators.md)
7. [Types et annotations](typing.md)
8. [TypeAdapter](type-adapter.md)
9. [Configuration](configuration.md)
10. [Settings](settings.md)
11. [Schémas](schemas.md)
12. [Erreurs](errors.md)
13. [Référence API](reference.md)

---

## À qui s'adresse ce cours

À toute personne qui écrit du PHP côté serveur et qui manipule des données entrantes non fiables : API HTTP, lignes de commande, consommateurs de files de messages, fichiers de configuration.

Le cours suppose que vous connaissez les bases de PHP (classes, types, attributs). Aucune connaissance de Pydantic n'est nécessaire : les analogies sont signalées quand elles existent, jamais présumées.

!!! note "Différences assumées avec Pydantic"
    PHP n'a pas de génériques, d'opérateurs `|` de types paramétrés ni de décorateurs. Les types composés sont donc décrits par des objets (`Type::list(Type::int())`) et les validateurs par des attributs PHP 8 (`#[Field(...)]`, `#[FieldValidator(...)]`). Toutes les différences sont documentées au fil du cours.
