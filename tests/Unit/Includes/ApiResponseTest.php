<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;
use App\API\Includes\ApiResponse;

class ApiResponseTest extends TestCase
{
    public function testSuccessReturnsCorrectStructure(): void
    {
        $result = ApiResponse::success(['id' => 1], 'Created', 201);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(['id' => 1], $result['data']);
        $this->assertSame('Created', $result['message']);
        $this->assertSame(201, $result['code']);
        $this->assertSame([], $result['errors']);
    }

    public function testSuccessDefaultValues(): void
    {
        $result = ApiResponse::success();

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertNull($result['data']);
        $this->assertSame('OK', $result['message']);
        $this->assertSame(200, $result['code']);
    }

    public function testErrorReturnsCorrectStructure(): void
    {
        $result = ApiResponse::error('Not found', 404, ['field' => 'id']);

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['status']);
        $this->assertNull($result['data']);
        $this->assertSame('Not found', $result['message']);
        $this->assertSame(404, $result['code']);
        $this->assertSame(['field' => 'id'], $result['errors']);
    }

    public function testErrorDefaultValues(): void
    {
        $result = ApiResponse::error('Bad request');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['code']);
        $this->assertSame([], $result['errors']);
    }

    public function testNormalizeWithExplicitSuccess(): void
    {
        $result = ApiResponse::normalize([
            'success' => true,
            'data' => ['foo' => 'bar'],
            'message' => 'All good',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(200, $result['code']);
        $this->assertSame('All good', $result['message']);
    }

    public function testNormalizeWithExplicitError(): void
    {
        $result = ApiResponse::normalize([
            'success' => false,
            'message' => 'Something went wrong',
            'code' => 500,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['status']);
        $this->assertSame(500, $result['code']);
    }

    public function testNormalizeInfersSuccessFromStatusField(): void
    {
        $result = ApiResponse::normalize(['status' => 'success']);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
    }

    public function testNormalizeInfersSuccessFromCodeBelow400(): void
    {
        $result = ApiResponse::normalize(['code' => 201]);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(201, $result['code']);
    }

    public function testNormalizeInfersErrorFromCode400OrAbove(): void
    {
        $result = ApiResponse::normalize(['code' => 422]);

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['status']);
        $this->assertSame(422, $result['code']);
    }

    public function testNormalizeUsesDefaultCodeWhenCodeBelowMinimum(): void
    {
        $result = ApiResponse::normalize(['code' => 50], 200);

        $this->assertSame(200, $result['code']);
    }

    public function testNormalizePreservesExtraKeys(): void
    {
        $result = ApiResponse::normalize([
            'status' => 'success',
            'pagination' => ['page' => 1, 'total' => 100],
        ]);

        $this->assertSame(['page' => 1, 'total' => 100], $result['pagination']);
    }

    public function testNormalizeDefaultMessage(): void
    {
        $successResult = ApiResponse::normalize(['success' => true]);
        $this->assertSame('OK', $successResult['message']);

        $errorResult = ApiResponse::normalize(['success' => false]);
        $this->assertSame('Request failed', $errorResult['message']);
    }

    public function testNormalizeUsesStatusCodeAlias(): void
    {
        $result = ApiResponse::normalize(['status_code' => 201]);

        $this->assertSame(201, $result['code']);
    }
}
