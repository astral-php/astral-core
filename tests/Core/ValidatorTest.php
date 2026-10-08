<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du validateur.
 * Couvre les 10 règles : required, min, max, email, integer,
 * numeric, alpha, url, confirmed, in.
 */
final class ValidatorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // required
    // -------------------------------------------------------------------------

    public function testRequiredPassesWhenValuePresent(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    public function testRequiredFailsWhenValueMissing(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->first('name'));
    }

    public function testRequiredFailsWhenValueIsEmptyString(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // min
    // -------------------------------------------------------------------------

    public function testMinPassesForLongEnoughString(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'min:3']);
        $this->assertTrue($v->passes());
    }

    public function testMinFailsForShortString(): void
    {
        $v = Validator::make(['name' => 'Al'], ['name' => 'min:3']);
        $this->assertTrue($v->fails());
    }

    public function testMinPassesForNumericAboveThreshold(): void
    {
        $v = Validator::make(['price' => '10'], ['price' => 'min:5']);
        $this->assertTrue($v->passes());
    }

    public function testMinFailsForNumericBelowThreshold(): void
    {
        $v = Validator::make(['price' => '3'], ['price' => 'min:5']);
        $this->assertTrue($v->fails());
    }

    public function testMinSkipsEmptyValue(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'min:3']);
        $this->assertTrue($v->passes());
    }

    // -------------------------------------------------------------------------
    // max
    // -------------------------------------------------------------------------

    public function testMaxPassesForShortEnoughString(): void
    {
        $v = Validator::make(['name' => 'Hi'], ['name' => 'max:10']);
        $this->assertTrue($v->passes());
    }

    public function testMaxFailsForTooLongString(): void
    {
        $v = Validator::make(['name' => 'VeryLongNameHere'], ['name' => 'max:5']);
        $this->assertTrue($v->fails());
    }

    public function testMaxPassesForNumericBelowThreshold(): void
    {
        $v = Validator::make(['qty' => '3'], ['qty' => 'max:10']);
        $this->assertTrue($v->passes());
    }

    public function testMaxFailsForNumericAboveThreshold(): void
    {
        $v = Validator::make(['qty' => '15'], ['qty' => 'max:10']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // email
    // -------------------------------------------------------------------------

    public function testEmailPassesForValidAddress(): void
    {
        $v = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    public function testEmailFailsForInvalidAddress(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    public function testEmailSkipsEmptyValue(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    // -------------------------------------------------------------------------
    // integer
    // -------------------------------------------------------------------------

    public function testIntegerPassesForValidInteger(): void
    {
        $v = Validator::make(['age' => '42'], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    public function testIntegerPassesForNegativeInteger(): void
    {
        $v = Validator::make(['temp' => '-5'], ['temp' => 'integer']);
        $this->assertTrue($v->passes());
    }

    public function testIntegerFailsForDecimal(): void
    {
        $v = Validator::make(['age' => '3.14'], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    public function testIntegerFailsForText(): void
    {
        $v = Validator::make(['age' => 'abc'], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // numeric
    // -------------------------------------------------------------------------

    public function testNumericPassesForInteger(): void
    {
        $v = Validator::make(['n' => '10'], ['n' => 'numeric']);
        $this->assertTrue($v->passes());
    }

    public function testNumericPassesForDecimal(): void
    {
        $v = Validator::make(['price' => '9.99'], ['price' => 'numeric']);
        $this->assertTrue($v->passes());
    }

    public function testNumericFailsForText(): void
    {
        $v = Validator::make(['price' => 'abc'], ['price' => 'numeric']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // alpha
    // -------------------------------------------------------------------------

    public function testAlphaPassesForLettersOnly(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'alpha']);
        $this->assertTrue($v->passes());
    }

    public function testAlphaFailsForStringWithDigits(): void
    {
        $v = Validator::make(['name' => 'Alice1'], ['name' => 'alpha']);
        $this->assertTrue($v->fails());
    }

    public function testAlphaFailsForStringWithSpaces(): void
    {
        $v = Validator::make(['name' => 'Alice Bob'], ['name' => 'alpha']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // url
    // -------------------------------------------------------------------------

    public function testUrlPassesForValidHttpUrl(): void
    {
        $v = Validator::make(['site' => 'https://example.com'], ['site' => 'url']);
        $this->assertTrue($v->passes());
    }

    public function testUrlFailsForPlainText(): void
    {
        $v = Validator::make(['site' => 'example'], ['site' => 'url']);
        $this->assertTrue($v->fails());
    }

    public function testUrlSkipsEmptyValue(): void
    {
        $v = Validator::make(['site' => ''], ['site' => 'url']);
        $this->assertTrue($v->passes());
    }

    // -------------------------------------------------------------------------
    // confirmed
    // -------------------------------------------------------------------------

    public function testConfirmedPassesWhenFieldsMatch(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->passes());
    }

    public function testConfirmedFailsWhenFieldsDiffer(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->fails());
    }

    public function testConfirmedFailsWhenConfirmationMissing(): void
    {
        $v = Validator::make(
            ['password' => 'secret'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // in
    // -------------------------------------------------------------------------

    public function testInPassesForAllowedValue(): void
    {
        $v = Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,guest']);
        $this->assertTrue($v->passes());
    }

    public function testInFailsForDisallowedValue(): void
    {
        $v = Validator::make(['role' => 'superuser'], ['role' => 'in:admin,user,guest']);
        $this->assertTrue($v->fails());
    }

    // -------------------------------------------------------------------------
    // Combinaisons de règles
    // -------------------------------------------------------------------------

    public function testCombinedRulesAllPass(): void
    {
        $v = Validator::make(
            ['email' => 'alice@example.com'],
            ['email' => 'required|email']
        );
        $this->assertTrue($v->passes());
    }

    public function testCombinedRulesPartialFail(): void
    {
        $v = Validator::make(
            ['email' => ''],
            ['email' => 'required|email']
        );
        $this->assertTrue($v->fails());
        // 'required' doit déclencher une erreur, 'email' ignore les vides
        $this->assertCount(1, $v->errors()['email']);
    }

    public function testMultipleFieldsWithErrors(): void
    {
        $v = Validator::make(
            ['name' => '', 'email' => 'bad'],
            ['name' => 'required', 'email' => 'required|email']
        );
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('email', $v->errors());
    }

    // -------------------------------------------------------------------------
    // Règles via tableau (alternative au pipe)
    // -------------------------------------------------------------------------

    public function testRulesAsArray(): void
    {
        $v = Validator::make(
            ['name' => 'Al'],
            ['name' => ['required', 'min:3']]
        );
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->first('name'));
    }

    // -------------------------------------------------------------------------
    // errors() et first()
    // -------------------------------------------------------------------------

    public function testFirstReturnsNullForValidField(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertNull($v->first('name'));
        $this->assertNull($v->first('nonexistent'));
    }

    public function testErrorsReturnsEmptyArrayWhenValid(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertSame([], $v->errors());
    }
}
