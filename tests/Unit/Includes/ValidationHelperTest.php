<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;
use App\API\Includes\ValidationHelper;

class ValidationHelperTest extends TestCase
{
    // --- validateEmail ---

    public function testValidateEmailValid(): void
    {
        $result = ValidationHelper::validateEmail('user@example.com');
        $this->assertTrue($result['valid']);
        $this->assertSame('user@example.com', $result['value']);
    }

    public function testValidateEmailEmpty(): void
    {
        $result = ValidationHelper::validateEmail('');
        $this->assertFalse($result['valid']);
        $this->assertSame('Email is required', $result['error']);
    }

    public function testValidateEmailInvalidFormat(): void
    {
        $result = ValidationHelper::validateEmail('not-an-email');
        $this->assertFalse($result['valid']);
    }

    public function testValidateEmailMissingTld(): void
    {
        $result = ValidationHelper::validateEmail('user@domain');
        $this->assertFalse($result['valid']);
    }

    public function testValidateEmailWithSubdomain(): void
    {
        $result = ValidationHelper::validateEmail('user@sub.domain.com');
        $this->assertTrue($result['valid']);
    }

    public function testValidateEmailWithPlusSign(): void
    {
        $result = ValidationHelper::validateEmail('user+tag@example.com');
        $this->assertTrue($result['valid']);
    }

    // --- validateUsername ---

    public function testValidateUsernameValid(): void
    {
        $result = ValidationHelper::validateUsername('john_doe');
        $this->assertTrue($result['valid']);
        $this->assertSame('john_doe', $result['value']);
    }

    public function testValidateUsernameEmpty(): void
    {
        $result = ValidationHelper::validateUsername('');
        $this->assertFalse($result['valid']);
        $this->assertSame('Username is required', $result['error']);
    }

    public function testValidateUsernameTooShort(): void
    {
        $result = ValidationHelper::validateUsername('ab');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('3-30', $result['error']);
    }

    public function testValidateUsernameTooLong(): void
    {
        $result = ValidationHelper::validateUsername(str_repeat('a', 31));
        $this->assertFalse($result['valid']);
    }

    public function testValidateUsernameStartsWithNumber(): void
    {
        $result = ValidationHelper::validateUsername('1user');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('start with a letter', $result['error']);
    }

    public function testValidateUsernameWithHyphen(): void
    {
        $result = ValidationHelper::validateUsername('john-doe');
        $this->assertTrue($result['valid']);
    }

    public function testValidateUsernameWithSpaces(): void
    {
        $result = ValidationHelper::validateUsername('john doe');
        $this->assertFalse($result['valid']);
    }

    public function testValidateUsernameTrimsWhitespace(): void
    {
        $result = ValidationHelper::validateUsername('  john_doe  ');
        $this->assertTrue($result['valid']);
        $this->assertSame('john_doe', $result['value']);
    }

    // --- validatePassword ---

    public function testValidatePasswordValid(): void
    {
        $result = ValidationHelper::validatePassword('Str0ng!Pass');
        $this->assertTrue($result['valid']);
    }

    public function testValidatePasswordEmpty(): void
    {
        $result = ValidationHelper::validatePassword('');
        $this->assertFalse($result['valid']);
        $this->assertSame('Password is required', $result['error']);
    }

    public function testValidatePasswordTooShort(): void
    {
        $result = ValidationHelper::validatePassword('Aa1!');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('8 characters', $result['error']);
    }

    public function testValidatePasswordTooLong(): void
    {
        $result = ValidationHelper::validatePassword(str_repeat('Aa1!', 33));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('128', $result['error']);
    }

    public function testValidatePasswordMissingUppercase(): void
    {
        $result = ValidationHelper::validatePassword('password1!');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('uppercase', $result['error']);
    }

    public function testValidatePasswordMissingLowercase(): void
    {
        $result = ValidationHelper::validatePassword('PASSWORD1!');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('lowercase', $result['error']);
    }

    public function testValidatePasswordMissingNumber(): void
    {
        $result = ValidationHelper::validatePassword('Password!@');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('number', $result['error']);
    }

    public function testValidatePasswordMissingSpecialChar(): void
    {
        $result = ValidationHelper::validatePassword('Password123');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('special character', $result['error']);
    }

    public function testValidatePasswordCommonWeak(): void
    {
        $result = ValidationHelper::validatePassword('Password1!');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too common', $result['error']);
    }

    // --- sanitizeText ---

    public function testSanitizeTextHtmlEntities(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            ValidationHelper::sanitizeText('<script>alert("xss")</script>')
        );
    }

    public function testSanitizeTextTrims(): void
    {
        $this->assertSame('hello', ValidationHelper::sanitizeText('  hello  '));
    }

    public function testSanitizeTextSingleQuotes(): void
    {
        $result = ValidationHelper::sanitizeText("it's a test");
        // PHP 8.1 with ENT_QUOTES|ENT_HTML5 encodes single quotes as &apos;
        $this->assertTrue(
            str_contains($result, '&#039;') || str_contains($result, '&apos;'),
            "Expected single quote to be encoded as &#039; or &apos;, got: $result"
        );
    }

    // --- validateName ---

    public function testValidateNameValid(): void
    {
        $result = ValidationHelper::validateName('John');
        $this->assertTrue($result['valid']);
    }

    public function testValidateNameEmpty(): void
    {
        $result = ValidationHelper::validateName('', 'First name');
        $this->assertFalse($result['valid']);
        $this->assertSame('First name is required', $result['error']);
    }

    public function testValidateNameWithHyphen(): void
    {
        $result = ValidationHelper::validateName("O'Brien-Smith");
        $this->assertTrue($result['valid']);
    }

    public function testValidateNameWithNumbers(): void
    {
        $result = ValidationHelper::validateName('John123');
        $this->assertFalse($result['valid']);
    }

    public function testValidateNameTooLong(): void
    {
        $result = ValidationHelper::validateName(str_repeat('a', 51));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('1-50', $result['error']);
    }

    public function testValidateNameSanitizesOutput(): void
    {
        $result = ValidationHelper::validateName('John');
        $this->assertTrue($result['valid']);
        $this->assertSame('John', $result['value']);
    }

    // --- validateStatus ---

    public function testValidateStatusActive(): void
    {
        $result = ValidationHelper::validateStatus('active');
        $this->assertTrue($result['valid']);
        $this->assertSame('active', $result['value']);
    }

    public function testValidateStatusInactive(): void
    {
        $result = ValidationHelper::validateStatus('inactive');
        $this->assertTrue($result['valid']);
    }

    public function testValidateStatusSuspended(): void
    {
        $result = ValidationHelper::validateStatus('suspended');
        $this->assertTrue($result['valid']);
    }

    public function testValidateStatusPending(): void
    {
        $result = ValidationHelper::validateStatus('pending');
        $this->assertTrue($result['valid']);
    }

    public function testValidateStatusInvalid(): void
    {
        $result = ValidationHelper::validateStatus('deleted');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('active, inactive, suspended, or pending', $result['error']);
    }
}
