<?php

namespace Svinx\Validator;

use Attribute;
use InvalidArgumentException;

/**
 * Déclare une méthode comme validateur personnalisé d'un ou plusieurs champs.
 *
 * Équivalent PHP du décorateur `@field_validator` de Pydantic.
 *
 * La méthode doit être publique, non statique, et recevoir la valeur à valider :
 *
 *     #[FieldValidator('username')]
 *     public function validate_username(string $cls, string $value): string
 *     {
 *         if (str_contains($value, ' ')) {
 *             throw new ValidationError("Le username ne doit pas contenir d'espace.");
 *         }
 *
 *         return $value;
 *     }
 *
 * Si la méthode retourne null, la valeur courante est conservée.
 * Si la méthode est déclarée `void`, sa valeur de retour est ignorée.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class FieldValidator
{
    /** Exécution après le contrôle du type et des contraintes (défaut). */
    public const BEFORE = 'before';

    /** Exécution avant tout contrôle, sur la valeur brute. */
    public const AFTER = 'after';

    /** Cible spéciale : tous les champs du modèle. */
    public const ANY = '*';

    /**
     * @param string|array|null $key Champ ciblé : nom de propriété, alias, tableau de cibles,
     *                              ou self::ANY pour tous les champs.
     *                              Si null, la cible est déduite du nom de la méthode
     *                              (`validate_username` => `username`).
     * @param string            $mode self::AFTER (défaut) ou self::BEFORE.
     * @param bool              $always Exécute le validateur même lorsque la valeur est null.
     */
    public function __construct(
        public string|array|null $key = null,
        public string $mode = self::AFTER,
        public bool $always = false,
    ) {
        if (!in_array($mode, [self::BEFORE, self::AFTER], true)) {
            throw new InvalidArgumentException(
                "Mode de validation inconnu : '$mode' (attendu : 'before' ou 'after')."
            );
        }
    }
}
