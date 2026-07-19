<?php

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use App\API\Router\ControllerRouter;
use ReflectionMethod;

class ControllerRouterTest extends TestCase
{
    private ControllerRouter $router;
    private ReflectionMethod $buildMethodName;
    private ReflectionMethod $normalizeUri;

    protected function setUp(): void
    {
        $this->router = new ControllerRouter();

        $this->buildMethodName = new ReflectionMethod(ControllerRouter::class, 'buildMethodName');
        $this->buildMethodName->setAccessible(true);

        $this->normalizeUri = new ReflectionMethod(ControllerRouter::class, 'normalizeUri');
        $this->normalizeUri->setAccessible(true);
    }

    // --- buildMethodName ---

    public function testBuildMethodNameGetWithNoResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', null);
        $this->assertSame('get', $result);
    }

    public function testBuildMethodNamePostWithNoResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'POST', null);
        $this->assertSame('post', $result);
    }

    public function testBuildMethodNameGetWithSimpleResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', 'terms');
        $this->assertSame('getTerms', $result);
    }

    public function testBuildMethodNamePostWithResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'POST', 'students');
        $this->assertSame('postStudents', $result);
    }

    public function testBuildMethodNameDeleteWithResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'DELETE', 'profile');
        $this->assertSame('deleteProfile', $result);
    }

    public function testBuildMethodNamePutWithNoResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'PUT', null);
        $this->assertSame('put', $result);
    }

    public function testBuildMethodNameKebabCaseResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', 'exam-schedules');
        $this->assertSame('getExamSchedules', $result);
    }

    public function testBuildMethodNameSnakeCaseResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', 'user_profile');
        $this->assertSame('getUserProfile', $result);
    }

    public function testBuildMethodNameEmptyResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', '');
        $this->assertSame('get', $result);
    }

    public function testBuildMethodNameMultiSegmentResource(): void
    {
        $result = $this->buildMethodName->invoke($this->router, 'GET', 'reports-compare-yearly-collections');
        $this->assertSame('getReportsCompareYearlyCollections', $result);
    }

    // --- normalizeUri ---

    public function testNormalizeUriStandardApi(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/api/academic/terms');
        $this->assertSame('academic/terms', $result);
    }

    public function testNormalizeUriLocalProjectPrefix(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/Kingsway/api/academic/terms');
        $this->assertSame('academic/terms', $result);
    }

    public function testNormalizeUriCaseInsensitiveProject(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/kingsway/api/finance/reports');
        $this->assertSame('finance/reports', $result);
    }

    public function testNormalizeUriStripsQueryString(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/api/students?page=1&limit=10');
        $this->assertSame('students', $result);
    }

    public function testNormalizeUriTrailingSlash(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/api/staff/');
        $this->assertSame('staff', $result);
    }

    public function testNormalizeUriNoApiPrefix(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/finance/reports');
        $this->assertSame('finance/reports', $result);
    }

    public function testNormalizeUriDeepPath(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/api/finance/reports/compare-yearly-collections');
        $this->assertSame('finance/reports/compare-yearly-collections', $result);
    }

    public function testNormalizeUriWithNumericId(): void
    {
        $result = $this->normalizeUri->invoke($this->router, '/api/students/profile/123');
        $this->assertSame('students/profile/123', $result);
    }
}
