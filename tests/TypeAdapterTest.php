<?php

namespace Svinx\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Map;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Typing\TypeVar;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\UUID;
use Svinx\Validator\Examples\TypeAdapters\Customer;

class TypeAdapterTest extends TestCase
{
    public function testValidateAndDumpSimpleType(): void
    {
        $adapter = TypeAdapter::of(EmailStr::class);

        $email = $adapter->validate('client@example.com');

        $this->assertInstanceOf(EmailStr::class, $email);
        $this->assertSame('client@example.com', $adapter->dump($email));
    }

    public function testValidateFromStringAlreadyTyped(): void
    {
        $adapter = TypeAdapter::of(UUID::class);
        $uuid = $adapter->validate('123e4567-e89b-42d3-a456-426614174000');

        $this->assertInstanceOf(UUID::class, $uuid);
        $this->assertSame($uuid, $adapter->validate($uuid));
    }

    public function testValidateJson(): void
    {
        $adapter = TypeAdapter::of(Type::list(EmailStr::class));
        $emails = $adapter->validate_json('["a@b.co","c@d.co"]');

        $this->assertCount(2, $emails);
        $this->assertSame('["a@b.co","c@d.co"]', $adapter->dump_json($emails));
    }

    public function testValidateJsonRejectsMalformedJson(): void
    {
        $this->expectException(\JsonException::class);

        TypeAdapter::of(Type::int())->validate_json('{invalide');
    }

    public function testJsonSchema(): void
    {
        $adapter = TypeAdapter::of(Map::make(Type::str(), Type::of(EmailStr::class)));

        $this->assertSame(
            ['type' => 'object', 'additionalProperties' => ['type' => 'string', 'format' => 'email']],
            $adapter->json_schema()
        );
        $this->assertJson($adapter->json_schema_json());
    }

    public function testTypeVarBindings(): void
    {
        $adapter = TypeAdapter::of(Type::list(TypeVar::of('T')), ['T' => Type::int()]);

        $this->assertSame([1, 2], $adapter->validate([1, 2]));

        $this->expectException(ValidationError::class);
        $adapter->validate(['a']);
    }

    public function testModelAdapter(): void
    {
        $adapter = TypeAdapter::model(Customer::class);
        $customer = $adapter->validate(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame(
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            $adapter->dump($customer)
        );
        $this->assertSame(['type' => 'object', 'title' => Customer::class], $adapter->json_schema());
    }

    public function testModelAdapterRejectsNonModel(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('n\'est pas un modèle');

        TypeAdapter::model(\stdClass::class);
    }

    public function testAdapterOfModelTypeValidatesArrays(): void
    {
        $adapter = TypeAdapter::of(Type::of(Customer::class));

        $this->assertInstanceOf(
            Customer::class,
            $adapter->validate(['name' => 'Bob', 'email' => 'bob@example.com'])
        );
    }
}
