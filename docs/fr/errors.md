# Erreurs

Toutes les erreurs de validation sont levées sous forme de `Svinx\Validator\ValidationError`, qui étend `\InvalidArgumentException`.

```php
namespace Svinx\Validator;

class ValidationError extends \InvalidArgumentException {}
```

## Attraper une erreur

```php
use Svinx\Validator\Examples\Models\User;
use Svinx\Validator\ValidationError;

try {
    $user = new User($payload);
} catch (ValidationError $e) {
    // $e->getMessage() : message lisible
}
```

Comme `ValidationError` est une `InvalidArgumentException`, vous pouvez aussi attraper le type SPL :

```php
try {
    $user = new User($payload);
} catch (\InvalidArgumentException $e) {
    // ...
}
```

## Comportement « premier échec »

La construction s'arrête à la **première** erreur rencontrée : un seul message est levé, pas une liste.

```
extraction → before → contraintes → type → after → affectation
```

L'ordre de parcours est l'ordre de déclaration des propriétés dans la classe.

!!! note "Pas encore d'agrégation multi-erreurs"
>    Pydantic v2 renvoie un `ValidationError` contenant *toutes* les erreurs. Ce n'est pas encore implémenté ici. Pour un formulaire qui doit afficher toutes les erreurs, validez champ par champ avec des `TypeAdapter` indépendants.

## Les messages, par étape

### Champ manquant

```
Le champ 'id' est requis.
```

Un champ est requis s'il n'a ni défaut ni type nullable.

### Type invalide

```
Le champ 'id' : int attendu, string donné ('42').
```

Le format est `Le champ '<nom>' : <type attendu> attendu, <type reçu> donné (<valeur>)`.

| Type attendu | Exemple de message |
| --- | --- |
| `int` | `Le champ 'count' : int attendu, string donné ('3').` |
| `EmailStr` | `Le champ 'email' : EmailStr attendu, array donné.` |
| `OrderStatus` | `Le champ 'status' : OrderStatus attendu, string donné ('refunded').` |
| union | `Le champ 'x' : aucune branche de int \| null ne correspond…` |

### Contraintes `#[Field]`

```
Le champ 'id' doit être > 0
Le champ 'age' doit être >= 18
Le champ 'name' doit avoir au moins 2 caractères
Le champ 'name' ne doit pas dépasser 50 caractères
Le champ 'slug' ne correspond pas au pattern attendu
Le champ 'role' doit être une des valeurs : admin, user, guest
Le champ 'price' doit être multiple de 0.5
```

### Types validés

Les messages viennent du type lui-même :

```
EmailStr invalide : 'pas-un-email' n'est pas une adresse email valide.
HttpUrl invalide : schéma "ftp" non autorisé (attendu : http ou https).
UUID invalide : 'x' n'est pas un UUID valide.
Datetime invalide : 'demain' ne respecte pas le format ISO 8601 (AAAA-MM-JJTHH:MM:SS).
IPAddress invalide : …
```

### Éléments de listes et de maps

```
Élément 1 de la valeur : int attendu, string donné ('deux').
Valeur de la clé 'age' de la valeur : int attendu, string donné ('abc').
```

### Erreurs de déclaration

Ces erreurs signalent un **bug de code**, pas une donnée invalide :

| Situation | Message |
| --- | --- |
| cible `#[FieldValidator]` inconnue | `Le validateur 'Classe::méthode' cible le champ inconnu 'x'.` |
| validateur non public / statique / abstrait | `Le validateur 'Classe::méthode' doit être une méthode publique, non statique et non abstraite.` |
| validateur renvoyant un type incompatible | `Le validateur du champ 'x' a retourné un type incompatible (attendu : string, array donné).` |
| exception inattendue dans un validateur | `Échec de la validation du champ 'x' (méthode) : <message>` |
| `TypeError` dans un validateur | `Le validateur 'méthode' du champ 'x' a reçu une valeur incompatible : <message>` |
| `extra: forbid` | `Champs inconnus : a, b.` |

### Erreurs de configuration

Levées à la déclaration de la configuration (`InvalidArgumentException`) :

```
Option extra inconnue : "bogus" (attendu : ignore, forbid ou allow).
alias_generator doit être callable ou null.
Options de configuration inconnues : inconnu
```

## Bonnes pratiques

### Traduire les messages côté application

Les messages sont en français et destinés aux développeurs. Pour une interface multilingue, mappez les messages ou enveloppez la construction :

```php
try {
    $user = new User($payload);
} catch (ValidationError $e) {
    $this->errors[] = $this->translator->translate($e->getMessage());
}
```

### Personnaliser le message d'un validateur

Le message d'un `#[FieldValidator]` est celui que vous écrivez :

```php
throw new ValidationError('Ce nom d\'utilisateur est déjà pris.');
```

### Ne pas avaler l'erreur

Une `ValidationError` doit remonter jusqu'à la couche qui décide (réponse HTTP 422, message console, DLQ...). Le modèle n'est **jamais** construit partiellement : il n'existe pas d'objet à moitié remplit à inspecter.

La suite : [Référence API](reference.md).
