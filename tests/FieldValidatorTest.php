<?php

namespace Svinx\Validator\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Examples\FieldValidators\Account;
use Svinx\Validator\Examples\FieldValidators\AlwaysAccount;
use Svinx\Validator\Examples\FieldValidators\BrokenAccount;
use Svinx\Validator\Examples\FieldValidators\CoercingAccount;
use Svinx\Validator\Examples\FieldValidators\StrictAccount;

class FieldValidatorTest extends TestCase
{
    public function testValidatorRunsAfterAndTransformsValue(): void
    {
        $account = new Account([
            'user_id' => 1,
            'username' => 'eorbah',
        ]);

        $this->assertSame('EORBAH', $account->name);
    }

    public function testValidatorRejectsInvalidValue(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("ne doit pas contenir d'espace");

        new Account(['user_id' => 1, 'username' => 'eor bah']);
    }

    public function testBeforeValidatorCoercesRawValue(): void
    {
        $account = new Account([
            'user_id' => 1,
            'username' => 'eorbah',
            'birth_date' => '1998-05-12',
        ]);

        $this->assertInstanceOf(DateTime::class, $account->birthday);
        $this->assertSame('1998-05-12', $account->birthday->format('Y-m-d'));
    }

    public function testValidatorKeyDerivedFromMethodName(): void
    {
        $account = new Account([
            'user_id' => 1,
            'username' => 'ok',
            'birth_date' => '2000-01-01',
        ]);

        $this->assertInstanceOf(DateTime::class, $account->birthday);
    }

    public function testValidatorCanTargetMultipleKeys(): void
    {
        $account = new Account([
            'user_id' => 1,
            'username' => 'ok',
            'nickname' => 'bob',
            'age' => 20,
        ]);

        $this->assertSame('BOB', $account->nickname);
        $this->assertSame(40, $account->age);
    }

    public function testWildcardValidatorRunsOnEveryField(): void
    {
        $account = new Account([
            'user_id' => 1,
            'username' => 'ok',
            'age' => 3,
        ]);

        $this->assertSame(1, $account->id);
        $this->assertSame('OK', $account->name);
    }

    public function testVoidValidatorKeepsValue(): void
    {
        $account = new StrictAccount(['code' => 'ok']);

        $this->assertSame('ok', $account->code);
    }

    public function testVoidValidatorRejectsInvalidValue(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Code invalide.');

        new StrictAccount(['code' => 'nope']);
    }

    public function testValidatorSkippedWhenValueIsNull(): void
    {
        $account = new StrictAccount([]);

        $this->assertNull($account->note);
    }

    public function testAlwaysValidatorRunsOnNull(): void
    {
        $account = new AlwaysAccount([]);

        $this->assertSame('défaut', $account->note);
    }

    public function testUnknownTargetKeyThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("champ inconnu 'champ_inexistant'");

        new BrokenAccount(['name' => 'x']);
    }

    public function testValidatorReturningWrongTypeThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('type incompatible');

        new CoercingAccount(['name' => 'x']);
    }

    public function testValidatorsAreCachedPerClass(): void
    {
        $a = new Account(['user_id' => 1, 'username' => 'ok']);
        $b = new Account(['user_id' => 2, 'username' => 'ok']);

        $this->assertSame('OK', $a->name);
        $this->assertSame('OK', $b->name);
    }
}
