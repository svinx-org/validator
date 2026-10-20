---
name: svinx-validator
description: Carte du projet Svinx Validator — architecture, invariants, commandes et pièges. À charger avant de modifier le code, les tests, les exemples ou la documentation.
---

# Svinx Validator — carte du projet

Bibliothèque PHP 8.4 de validation de modèles et de schémas, inspirée de Pydantic.
Paquet Composer `svinx/validator`, namespace racine `Svinx\Validator`.

## Graphe des dépendances

```
BaseModel ──uses──> Typing\Type ──uses──> Validators\TypeValidator
    │                   │                         │
    │                   └──uses──> FieldInfo       └──uses──> ValidationError
    │
    ├──uses──> Field (extends FieldInfo)
    ├──uses──> FieldValidator (attribut de méthode)
    └──uses──> Config (model_config())

TypeAdapter ──uses──> Typing\Type
Typing\ListType, Typing\Map ──extend──> Typing\Type
Typing\Annotated ──fabrique──> Typing\Type (contraintes FieldInfo)
Typing\TypeVar ──résolu par──> Type::validate($value, $bindings)

Settings\BaseSettings ──extends──> BaseModel (buildType() -> Type::lax())
Validators\* ──extend──> Validators\AbstractType ──implements──> Validators\TypeValidator
```

Flux de validation d'un champ (`BaseModel::validateAndAssign`) :

```
extraction (alias/nom/défaut/nullable)
  -> Config::normalizeString (strip/lower/upper)
  -> #[FieldValidator] mode "before"
  -> Type::validate  (contraintes #[Field], puis type/convertion)
  -> #[FieldValidator] mode "after"
  -> affectation de la propriété typée
```

## Invariants à ne pas casser

1. **Fail-fast** : une seule erreur, `ValidationError` (extends `InvalidArgumentException`), jamais d'objet à moitié construit.
2. **Pas de coercition implicite** : un modèle `BaseModel` est strict. Seul `Type::lax()` (et `BaseSettings`) coerce.
3. **Les contraintes `#[Field]` s'appliquent à la valeur brute**, avant la conversion de type.
4. **Les exemples vivent dans `docs_src/`**, pas dans `tests/` : tests et documentation partagent les mêmes classes via `Svinx\Validator\Examples`.
5. **`model_dump()` renvoie des valeurs sérialisables** : modèles → tableau, `TypeValidator` → `dump()`, `BackedEnum` → valeur, `\DateTimeInterface` → ISO 8601, `SecretStr` → `**********`.
6. **Le français est la langue source de la documentation** ; les traductions sont générées, jamais éditées à la main.

## Arborescence

| Chemin | Contenu |
| --- | --- |
| `src/BaseModel.php` | Cœur : extraction, validation, `model_dump`, `model_schema`, `Config` |
| `src/Field.php`, `src/FieldInfo.php` | Attribut `#[Field]`, contraintes (`check()`), schéma JSON |
| `src/FieldValidator.php` | Attribut `#[FieldValidator]` (mode, cible, `always`) |
| `src/Config.php` | `extra`, `populate_by_name`, `alias_generator`, normalisation |
| `src/TypeAdapter.php` | Validation d'une valeur isolée |
| `src/Typing/` | `Type`, `ListType`, `Map`, `TypeVar`, `Annotated` |
| `src/Validators/` | `TypeValidator`, `AbstractType` + 9 types validés |
| `src/Settings/BaseSettings.php` | Environnement, `.env`, préfixes, settings imbriqués |
| `docs_src/` | Une classe d'exemple par fichier (PSR-4, namespace `Svinx\Validator\Examples`) |
| `tests/` | PHPUnit ; importe les exemples depuis `docs_src` |
| `docs/fr/` | Documentation source (cours en 14 chapitres) |
| `docs/en/` (+ `es`, `pt`, `de`, `ko`) | Traductions générées |
| `scripts/` | `translate.py`, `prompts/`, `README.md` |

## Commandes

```bash
composer install
composer test                                   # phpunit (118 tests)
php -l src/BaseModel.php                        # vérif de syntaxe

python scripts/translate.py --lang all --check  # CI : traductions manquantes
python scripts/translate.py --lang en           # traduire (LLM_API_KEY)
python scripts/translate.py --lang en --adopt   # enregistrer une traduction manuelle
python scripts/translate.py --lang es --provider copy   # amorcer sans API
```

Après ajout d'un fichier dans `docs_src/` ou `src/` : `composer dump-autoload`.

## Documentation

- 14 pages FR puis EN, mêmes noms de fichiers : `index`, `installation`, `first-steps`,
  `fields-and-constraints`, `nested-models`, `validated-types`, `custom-validators`,
  `typing`, `type-adapter`, `configuration`, `settings`, `schemas`, `errors`, `reference`.
- Toute page doit citer la classe réelle de `docs_src` dans un encadré
  `!!! example "docs_src/..."`.
- Régénérer une traduction : `docs/<lang>/.manifest.json` (SHA-256 de la source).
- CI : `.github/workflows/docs-translate.yml` (traduction au push + PR, vérif en PR).

## Pièges connus

| Piège | Détail |
| --- | --- |
| `Validators\Datetime` vs `\DateTime` | PHP est insensible à la casse : importer `Datetime` masque `\DateTime` dans le fichier. Écrire `\DateTime` ou aliaser. |
| `ReflectionProperty::isDefault()` | Vaut `true` pour toute propriété déclarée ; utiliser `hasDefaultValue()`. |
| Classe `List` | `list` est un mot réservé : la classe s'appelle `ListType` (fichier `ListType.php`). |
| Constructeur de `Type` | Copie via `copy(array $overrides)` ; ne pas appeler `copy(constraints: ...)`. |
| Code dans la doc | Les blocs ```php/```json/```bash/```dotenv doivent rester identiques à `docs_src` (le prompt de traduction l'impose). |
| `_extraFields` / champs | Seules les propriétés **publiques non statiques** sont des champs. |

## Tests : conventions

- Un fichier par brique (`BaseModelTest`, `FieldValidatorTest`, `ValidatorsTest`,
  `TypingTest`, `TypeAdapterTest`, `ConfigTest`, `SettingsTest`).
- Les classes d'exemple sont importées (`use Svinx\Validator\Examples\...`), jamais
  redéclarées dans `tests/`.
- Un test qui fige un message d'erreur doit vérifier une sous-chaîne stable.
