<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Validation\ValidationException;
use Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testPassesReturnsTrueWhenNoErrors(): void
    {
        $result = $this->validator->validate(['name' => 'Alice'], ['name' => 'required|string']);

        self::assertTrue($result->passes());
        self::assertFalse($result->fails());
    }

    public function testFailsReturnsTrueWhenErrorsExist(): void
    {
        $result = $this->validator->validate([], ['name' => 'required']);

        self::assertTrue($result->fails());
        self::assertFalse($result->passes());
    }

    public function testErrorsReturnsMapOfFieldToMessages(): void
    {
        $result = $this->validator->validate([], ['name' => 'required']);

        self::assertArrayHasKey('name', $result->errors());
        self::assertCount(1, $result->errors()['name']);
    }

    public function testFirstErrorReturnsFirstMessageForField(): void
    {
        $result = $this->validator->validate(['age' => 'abc'], ['age' => 'integer|min:18']);

        self::assertNotNull($result->firstError('age'));
    }

    public function testFirstErrorReturnsNullWhenFieldHasNoError(): void
    {
        $result = $this->validator->validate(['name' => 'Alice'], ['name' => 'required']);

        self::assertNull($result->firstError('name'));
    }

    public function testRequiredRuleFailsWhenFieldMissing(): void
    {
        $result = $this->validator->validate([], ['name' => 'required']);

        self::assertTrue($result->fails());
    }

    public function testRequiredRuleFailsWhenValueIsEmptyString(): void
    {
        $result = $this->validator->validate(['name' => ''], ['name' => 'required']);

        self::assertTrue($result->fails());
    }

    public function testRequiredRuleFailsWhenValueIsNull(): void
    {
        $result = $this->validator->validate(['name' => null], ['name' => 'required']);

        self::assertTrue($result->fails());
    }

    public function testRequiredRulePassesWithZeroValue(): void
    {
        $result = $this->validator->validate(['count' => 0], ['count' => 'required']);

        self::assertTrue($result->passes());
    }

    public function testNullableSkipsRemainingRulesWhenValueIsNull(): void
    {
        $result = $this->validator->validate(['nickname' => null], ['nickname' => 'nullable|email']);

        self::assertTrue($result->passes());
    }

    public function testFieldNotPresentAndNotRequiredIsSkipped(): void
    {
        $result = $this->validator->validate([], ['nickname' => 'email|min:3']);

        self::assertTrue($result->passes());
    }

    public function testStringRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'hello'], ['v' => 'string'])->passes());
        self::assertTrue($this->validator->validate(['v' => 123], ['v' => 'string'])->fails());
    }

    public function testIntegerRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 5], ['v' => 'integer'])->passes());
        self::assertTrue($this->validator->validate(['v' => '5'], ['v' => 'integer'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'abc'], ['v' => 'integer'])->fails());
    }

    public function testNumericRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => '3.14'], ['v' => 'numeric'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'abc'], ['v' => 'numeric'])->fails());
    }

    public function testBooleanRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => true], ['v' => 'boolean'])->passes());
        self::assertTrue($this->validator->validate(['v' => '1'], ['v' => 'boolean'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'yes'], ['v' => 'boolean'])->fails());
    }

    public function testArrayRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => [1, 2]], ['v' => 'array'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'not-array'], ['v' => 'array'])->fails());
    }

    public function testEmailRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'a@b.com'], ['v' => 'email'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'not-an-email'], ['v' => 'email'])->fails());
    }

    public function testMinRuleForStringLength(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'hello'], ['v' => 'min:3'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'hi'], ['v' => 'min:3'])->fails());
    }

    public function testMinRuleForNumericValue(): void
    {
        self::assertTrue($this->validator->validate(['v' => 10], ['v' => 'numeric|min:5'])->passes());
        self::assertTrue($this->validator->validate(['v' => 2], ['v' => 'numeric|min:5'])->fails());
    }

    public function testMaxRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'hi'], ['v' => 'max:5'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'too long value'], ['v' => 'max:5'])->fails());
    }

    public function testBetweenRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 5], ['v' => 'numeric|between:1,10'])->passes());
        self::assertTrue($this->validator->validate(['v' => 50], ['v' => 'numeric|between:1,10'])->fails());
    }

    public function testInRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'draft'], ['v' => 'in:draft,published'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'archived'], ['v' => 'in:draft,published'])->fails());
    }

    public function testRegexRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => 'abc123'], ['v' => 'regex:/^[a-z0-9]+$/'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'ABC!!!'], ['v' => 'regex:/^[a-z0-9]+$/'])->fails());
    }

    public function testDateRule(): void
    {
        self::assertTrue($this->validator->validate(['v' => '2026-01-01'], ['v' => 'date'])->passes());
        self::assertTrue($this->validator->validate(['v' => 'not-a-date'], ['v' => 'date'])->fails());
    }

    public function testConfirmedRulePassesWhenMatches(): void
    {
        $result = $this->validator->validate(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );

        self::assertTrue($result->passes());
    }

    public function testConfirmedRuleFailsWhenMismatch(): void
    {
        $result = $this->validator->validate(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'confirmed']
        );

        self::assertTrue($result->fails());
    }

    public function testCustomRuleViaExtend(): void
    {
        $this->validator->extend('even', static fn (mixed $value): bool => ((int) $value) % 2 === 0);

        self::assertTrue($this->validator->validate(['v' => 4], ['v' => 'even'])->passes());
        self::assertTrue($this->validator->validate(['v' => 3], ['v' => 'even'])->fails());
    }

    public function testCustomRuleCanOverrideBuiltIn(): void
    {
        $this->validator->extend('email', static fn (mixed $value): bool => $value === 'always-valid');

        $result = $this->validator->validate(['v' => 'not-a-real-email'], ['v' => 'email']);

        self::assertTrue($result->fails());

        $result2 = $this->validator->validate(['v' => 'always-valid'], ['v' => 'email']);

        self::assertTrue($result2->passes());
    }

    public function testCustomMessageOverridesDefault(): void
    {
        $result = $this->validator->validate(
            [],
            ['name' => 'required'],
            ['name.required' => 'Ten khong duoc de trong.']
        );

        self::assertSame('Ten khong duoc de trong.', $result->firstError('name'));
    }

    public function testUnknownRuleThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['v' => 'x'], ['v' => 'not_a_real_rule']);
    }

    public function testMultipleFailingRulesOnSameFieldAccumulateAllErrors(): void
    {
        $result = $this->validator->validate(['v' => 'xyz'], ['v' => 'integer|email|in:a,b,c']);

        self::assertCount(3, $result->errors()['v']);
    }
}
