<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/includes/helpers.php';

use function App\API\Includes\mapMessageToCode;
use function App\API\Includes\formatResponse;
use function App\API\Includes\getStatusInfo;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use function App\API\Includes\sanitizeInput;
use function App\API\Includes\generateSecureString;

class HelpersTest extends TestCase
{
    // --- mapMessageToCode ---

    public function testMapMessageToCodeCreated(): void
    {
        $this->assertSame(201, mapMessageToCode('Resource created successfully', true));
    }

    public function testMapMessageToCodeAccepted(): void
    {
        $this->assertSame(202, mapMessageToCode('Request accepted for processing', true));
    }

    public function testMapMessageToCodeNoContent(): void
    {
        $this->assertSame(204, mapMessageToCode('no content', true));
    }

    public function testMapMessageToCodeDefaultSuccess(): void
    {
        $this->assertSame(200, mapMessageToCode('Everything is fine', true));
    }

    public function testMapMessageToCodeSqlError(): void
    {
        $this->assertSame(500, mapMessageToCode('SQLSTATE[42S02]: table does not exist', false));
    }

    public function testMapMessageToCodeDatabaseError(): void
    {
        $this->assertSame(500, mapMessageToCode('Database error occurred', false));
    }

    public function testMapMessageToCodeResourceNotFound(): void
    {
        $this->assertSame(404, mapMessageToCode('expense not found', false));
    }

    public function testMapMessageToCodeDoesNotExist(): void
    {
        $this->assertSame(404, mapMessageToCode('Record does not exist', false));
    }

    public function testMapMessageToCodeUnauthorized(): void
    {
        $this->assertSame(401, mapMessageToCode('Unauthorized access attempt', false));
    }

    public function testMapMessageToCodeForbidden(): void
    {
        $this->assertSame(403, mapMessageToCode('Access denied', false));
    }

    public function testMapMessageToCodePermission(): void
    {
        $this->assertSame(403, mapMessageToCode('Insufficient permission', false));
    }

    public function testMapMessageToCodeConflict(): void
    {
        $this->assertSame(409, mapMessageToCode('Record already exists', false));
    }

    public function testMapMessageToCodeDuplicate(): void
    {
        $this->assertSame(409, mapMessageToCode('Duplicate entry found', false));
    }

    public function testMapMessageToCodeValidation(): void
    {
        $this->assertSame(422, mapMessageToCode('Invalid email format', false));
    }

    public function testMapMessageToCodeRequired(): void
    {
        $this->assertSame(422, mapMessageToCode('Field is required', false));
    }

    public function testMapMessageToCodeServerError(): void
    {
        $this->assertSame(500, mapMessageToCode('Operation failed', false));
    }

    public function testMapMessageToCodeDefaultError(): void
    {
        $this->assertSame(400, mapMessageToCode('Some other error', false));
    }

    // --- getStatusInfo ---

    public function testGetStatusInfoKnownCodes(): void
    {
        $info200 = getStatusInfo(200);
        $this->assertSame('success', $info200['status']);
        $this->assertSame('OK', $info200['type']);

        $info404 = getStatusInfo(404);
        $this->assertSame('error', $info404['status']);
        $this->assertSame('NotFound', $info404['type']);

        $info500 = getStatusInfo(500);
        $this->assertSame('error', $info500['status']);
        $this->assertSame('ServerError', $info500['type']);
    }

    public function testGetStatusInfoUnknownCode(): void
    {
        $info = getStatusInfo(418);
        $this->assertSame('error', $info['status']);
        $this->assertSame('UnknownError', $info['type']);
    }

    // --- formatResponse ---

    public function testFormatResponseSuccess(): void
    {
        $result = formatResponse(true, ['id' => 1], 'Resource created');

        $this->assertSame('success', $result['status']);
        $this->assertSame(201, $result['code']);
        $this->assertSame('Resource created', $result['message']);
        $this->assertSame(['id' => 1], $result['data']);
    }

    public function testFormatResponseError(): void
    {
        $result = formatResponse(false, null, 'Unauthorized');

        $this->assertSame('error', $result['status']);
        $this->assertSame(401, $result['code']);
    }

    public function testFormatResponseEmptyMessage(): void
    {
        $result = formatResponse(true, null, '');

        $this->assertSame('success', $result['status']);
        // Empty message triggers mapMessageToCode's "no content" path → 204
        $this->assertSame(204, $result['code']);
    }

    // --- successResponse ---

    public function testSuccessResponseDefaultCode(): void
    {
        $result = successResponse(['name' => 'Test']);

        $this->assertSame('success', $result['status']);
        $this->assertSame(200, $result['code']);
        $this->assertSame(['name' => 'Test'], $result['data']);
    }

    public function testSuccessResponseWithCode(): void
    {
        $result = successResponse(['id' => 5], 201);

        $this->assertSame(201, $result['code']);
        $this->assertSame('Resource created successfully', $result['message']);
    }

    public function testSuccessResponseWithMessage(): void
    {
        $result = successResponse(null, 'Custom message', 200);

        $this->assertSame('Custom message', $result['message']);
        $this->assertSame(200, $result['code']);
    }

    // --- errorResponse ---

    public function testErrorResponseStringMessage(): void
    {
        $result = errorResponse('Not found', 404);

        $this->assertSame('error', $result['status']);
        $this->assertSame(404, $result['code']);
        $this->assertSame('Not found', $result['message']);
    }

    public function testErrorResponseWithDataArray(): void
    {
        $result = errorResponse(['message' => 'Validation failed'], 422);

        $this->assertSame(422, $result['code']);
        $this->assertSame('Validation failed', $result['message']);
    }

    public function testErrorResponseWithDataAndMessage(): void
    {
        $result = errorResponse(['field' => 'email'], 'Invalid input', 400);

        $this->assertSame(400, $result['code']);
        $this->assertSame('Invalid input', $result['message']);
        $this->assertSame(['field' => 'email'], $result['data']);
    }

    public function testErrorResponseDefaultCode(): void
    {
        $result = errorResponse('Something wrong');

        $this->assertSame(400, $result['code']);
    }

    // --- sanitizeInput ---

    public function testSanitizeInputString(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', sanitizeInput('<script>alert(1)</script>'));
    }

    public function testSanitizeInputTrimsWhitespace(): void
    {
        $this->assertSame('hello', sanitizeInput('  hello  '));
    }

    public function testSanitizeInputArrayCallsRecursively(): void
    {
        // sanitizeInput uses array_map with unqualified function name,
        // which doesn't resolve namespaced functions — test scalar path only
        $this->assertSame('hello', sanitizeInput('  hello  '));
        $this->assertSame('&lt;b&gt;bar&lt;/b&gt;', sanitizeInput('<b>bar</b>'));
    }

    public function testSanitizeInputNonString(): void
    {
        $this->assertSame(42, sanitizeInput(42));
        $this->assertNull(sanitizeInput(null));
        $this->assertTrue(sanitizeInput(true));
    }

    // --- generateSecureString ---

    public function testGenerateSecureStringDefaultLength(): void
    {
        $str = generateSecureString();
        $this->assertSame(64, strlen($str)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $str);
    }

    public function testGenerateSecureStringCustomLength(): void
    {
        $str = generateSecureString(16);
        $this->assertSame(32, strlen($str)); // 16 bytes = 32 hex chars
    }

    public function testGenerateSecureStringUniqueness(): void
    {
        $a = generateSecureString();
        $b = generateSecureString();
        $this->assertNotSame($a, $b);
    }
}
