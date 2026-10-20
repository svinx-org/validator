<?php

namespace Svinx\Validator\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Annotated;
use Svinx\Validator\Typing\Map;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Typing\TypeVar;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\UUID;
use Svinx\Validator\Examples\Typing\Order;
use Svinx\Validator\Examples\Typing\OrderStatus;

class TypingTest extends TestCase
{
    // ------------------------------------------------------------ Fabriques

    public function testFactoriesBuildExpectedKinds(): void
    {
        $this->assertSame(Type::INT, Type::int()->kind);
        $this->assertSame(Type::STR, Type::str()->kind);
        $this->assertSame(Type::LIST, Type::list(Type::int())->kind);
        $this->assertSame(Type::MAP, Type::map(Type::str(), Type::int())->kind);
        $this->assertSame(Type::UNION, Type::optional(Type::int())->kind);
        $this->assertSame(Type::LITERAL, Type::literal('a', 'b')->kind);
        $this->assertSame(Type::VALIDATOR, Type::of(EmailStr::class)->kind);
        $this->assertSame(Type::ENUM, Type::of(OrderStatus::class)->kind);
        $this->assertSame(Type::OBJECT, Type::of(DateTime::class)->kind);
        $this->assertSame(Type::MODEL, Type::of(Order::class)->kind);
    }

    public function testOfUnknownClassThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Type inconnu');

        Type::of('Classe\\Inexistante');
    }

    public function testOptionalFoldsNullIntoUnion(): void
    {
        $type = Type::optional(Type::int());

        $this->assertTrue($type->isNullable());
        $this->assertNull($type->validate(null));
        $this->assertSame(3, $type->validate(3));
        $this->assertSame('int | null', $type->describe());
    }

    // -------------------------------------------------------- fromReflection

    public function testFromReflectionMapsPropertyTypes(): void
    {
        $meta = Order::model_schema()['properties'];

        $this->assertSame(['type' => 'integer'], $meta['count']);
        $this->assertTrue(TyTypeHelper::hasNull($meta['label']));
        $this->assertSame(['type' => 'array'], $meta['tags']);
        $this->assertSame(['type' => 'string', 'format' => 'email'], $meta['owner']);
        $this->assertTrue(TyTypeHelper::hasNull($meta['born']));
        $this->assertSame(['type' => 'string', 'title' => OrderStatus::class], $meta['status']);
    }

    public function testFromReflectionHandlesNullable(): void
    {
        $type = Order::model_schema()['properties']['born'];

        $this->assertArrayHasKey('anyOf', $type);
        $this->assertTrue(TyTypeHelper::hasNull($type));
    }

    // -------------------------------------------------------------- Scalaires

    public function testStrictScalarsRejectOtherTypes(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('int attendu');

        Type::int()->validate('42');
    }

    public function testLaxScalarsCoerce(): void
    {
        $this->assertSame(42, Type::int()->lax()->validate('42'));
        $this->assertSame(4.5, Type::float()->lax()->validate('4.5'));
        $this->assertTrue(Type::bool()->lax()->validate('yes'));
        $this->assertFalse(Type::bool()->lax()->validate('OFF'));
        $this->assertSame('12', Type::str()->lax()->validate(12));
    }

    public function testLaxIntRejectsFractionalString(): void
    {
        $this->expectException(ValidationError::class);

        Type::int()->lax()->validate('4.5');
    }

    public function testLiteral(): void
    {
        $type = Type::literal('a', 'b');

        $this->assertSame('a', $type->validate('a'));
        $this->expectException(ValidationError::class);
        $type->validate('c');
    }

    public function testEnum(): void
    {
        $type = Type::of(OrderStatus::class);

        $this->assertSame(OrderStatus::Paid, $type->validate('paid'));
        $this->assertSame('paid', $type->dump(OrderStatus::Paid));
    }

    public function testEnumRejectsUnknownValue(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('OrderStatus attendu');

        Type::of(OrderStatus::class)->validate('refunded');
    }

    // ------------------------------------------------------------- Conteneurs

    public function testListValidatesItems(): void
    {
        $type = Type::list(Type::int());

        $this->assertSame([1, 2, 3], $type->validate([1, 2, 3]));
        $this->assertSame('list[int]', $type->describe());
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $type->schema());
    }

    public function testListRejectsAssociativeArray(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('list[int] attendu');

        Type::list(Type::int())->validate(['a' => 1]);
    }

    public function testListReportsItemIndex(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Élément 1');

        Type::list(Type::int())->validate([1, 'deux']);
    }

    public function testListOfValidatedTypesDumpsScalars(): void
    {
        $type = Type::list(Type::of(EmailStr::class));
        $emails = $type->validate(['a@b.co', 'c@d.co']);

        $this->assertInstanceOf(EmailStr::class, $emails[0]);
        $this->assertSame(['a@b.co', 'c@d.co'], $type->dump($emails));
        $this->assertSame(
            ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']],
            $type->schema()
        );
    }

    public function testMapCoercesKeysAndValues(): void
    {
        $type = Map::make(Type::str(), Type::int())->lax();
        $result = $type->validate(['age' => '42', 7 => '8']);

        $this->assertSame(['age' => 42, '7' => 8], $result);
        $this->assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'integer']], $type->schema());
    }

    public function testMapRejectsInvalidValue(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Valeur de la clé');

        Map::make(Type::str(), Type::int())->validate(['age' => 'pas un int']);
    }

    // ------------------------------------------------------------- TypeVar

    public function testTypeVarIsResolvedThroughBindings(): void
    {
        $type = Type::list(TypeVar::of('T'));

        $this->assertSame([1, 2], $type->validate([1, 2], ['T' => Type::int()]));
        $this->assertSame(['a'], $type->validate(['a'], ['T' => Type::str()]));
    }

    public function testUnboundTypeVarThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Variable de type non liée');

        Type::list(TypeVar::of('T'))->validate([1]);
    }

    public function testLaxContainerPropagatesToBoundTypeVar(): void
    {
        $type = Type::map(Type::str(), TypeVar::of('T'))->lax();

        $this->assertSame(['page' => 2], $type->validate(['page' => '2'], ['T' => Type::int()]));
    }

    public function testTypeVarBoundAndDefault(): void
    {
        $bound = TypeVar::of('T', Type::int());

        $this->assertSame([5], Type::list($bound)->validate([5]));
        $this->assertSame([5], Type::list(TypeVar::of('U', null, Type::int()))->validate([5]));
    }

    // ------------------------------------------------------------ Annotated

    public function testAnnotatedAppliesConstraints(): void
    {
        $type = Annotated::of(Type::str(), new Field(min_length: 3, max_length: 5));

        $this->assertSame('abcd', $type->validate('abcd'));
        $this->assertSame(['type' => 'string', 'minLength' => 3, 'maxLength' => 5], $type->schema());
    }

    public function testAnnotatedRejectsTooShort(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('au moins 3 caractères');

        Annotated::of(Type::str(), new Field(min_length: 3))->validate('ab');
    }

    public function testAnnotatedKeepsFreeMetadata(): void
    {
        $type = Annotated::of(Type::int(), 'un entier utile', 42);

        $this->assertSame(['un entier utile', 42], $type->metadata);
    }

    // ------------------------------------------------------------ Divers

    public function testDumpHandlesNestedStructures(): void
    {
        $type = Type::list(Type::of(UUID::class));
        $uuids = $type->validate([
            '123e4567-e89b-42d3-a456-426614174000',
            '123e4567-e89b-42d3-a456-426614174001',
        ]);

        $this->assertSame(
            ['123e4567-e89b-42d3-a456-426614174000', '123e4567-e89b-42d3-a456-426614174001'],
            $type->dump($uuids)
        );
    }

    public function testDumpHandlesModels(): void
    {
        $type = Type::list(Type::of(Order::class));
        $models = $type->validate([
            ['count' => 1, 'owner' => 'a@b.co', 'status' => 'paid'],
        ]);

        $this->assertInstanceOf(Order::class, $models[0]);
        $this->assertSame(
            [['count' => 1, 'label' => null, 'tags' => [], 'owner' => 'a@b.co', 'born' => null, 'status' => 'paid']],
            $type->dump($models)
        );
    }
}

/**
 * Petits utilitaires de test pour inspecter les schémas.
 */
final class TyTypeHelper
{
    public static function typeName(array $schema): string
    {
        return $schema['anyOf'] ?? $schema['type'] ?? 'inconnu';
    }

    public static function hasNull(array $schema): bool
    {
        foreach ($schema['anyOf'] ?? [] as $member) {
            if (($member['type'] ?? null) === 'null') {
                return true;
            }
        }

        return false;
    }
}
