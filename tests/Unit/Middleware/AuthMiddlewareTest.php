<?php

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\API\Middleware\AuthMiddleware;
use ReflectionMethod;

class AuthMiddlewareTest extends TestCase
{
    private ReflectionMethod $normalizeDecodedUser;

    protected function setUp(): void
    {
        $this->normalizeDecodedUser = new ReflectionMethod(AuthMiddleware::class, 'normalizeDecodedUser');
        $this->normalizeDecodedUser->setAccessible(true);
    }

    public function testNormalizeDecodedUserWithArrayRoles(): void
    {
        $user = [
            'id' => 1,
            'username' => 'admin',
            'roles' => [
                ['id' => 2, 'name' => 'System Admin'],
                ['id' => 5, 'name' => 'Teacher'],
            ],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([2, 5], $result['role_ids']);
        $this->assertSame(['system admin', 'teacher'], $result['role_names']);
    }

    public function testNormalizeDecodedUserWithRoleIdKey(): void
    {
        $user = [
            'roles' => [
                ['role_id' => 10, 'name' => 'Accountant'],
            ],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([10], $result['role_ids']);
        $this->assertSame(['accountant'], $result['role_names']);
    }

    public function testNormalizeDecodedUserWithObjectRoles(): void
    {
        $role = new \stdClass();
        $role->id = 3;
        $role->name = 'Director';

        $user = [
            'roles' => [$role],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([3], $result['role_ids']);
        $this->assertSame(['director'], $result['role_names']);
    }

    public function testNormalizeDecodedUserWithObjectRoleId(): void
    {
        $role = new \stdClass();
        $role->role_id = 7;
        $role->name = 'Class Teacher';

        $user = [
            'roles' => [$role],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([7], $result['role_ids']);
    }

    public function testNormalizeDecodedUserWithNumericRoles(): void
    {
        $user = [
            'roles' => [1, 3, 5],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([1, 3, 5], $result['role_ids']);
        $this->assertSame([], $result['role_names']);
    }

    public function testNormalizeDecodedUserWithStringRoles(): void
    {
        $user = [
            'roles' => ['admin', 'teacher'],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([], $result['role_ids']);
        $this->assertSame(['admin', 'teacher'], $result['role_names']);
    }

    public function testNormalizeDecodedUserDeduplicates(): void
    {
        $user = [
            'roles' => [
                ['id' => 2, 'name' => 'Admin'],
                ['id' => 2, 'name' => 'Admin'],
            ],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([2], $result['role_ids']);
        $this->assertSame(['admin'], $result['role_names']);
    }

    public function testNormalizeDecodedUserEmptyRoles(): void
    {
        $user = [
            'id' => 1,
            'roles' => [],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([], $result['role_ids']);
        $this->assertSame([], $result['role_names']);
    }

    public function testNormalizeDecodedUserNoRolesKey(): void
    {
        $user = [
            'id' => 1,
            'username' => 'test',
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame([], $result['role_ids']);
        $this->assertSame([], $result['role_names']);
    }

    public function testNormalizeDecodedUserPreservesOriginalData(): void
    {
        $user = [
            'id' => 42,
            'username' => 'john',
            'email' => 'john@example.com',
            'roles' => [['id' => 5, 'name' => 'Teacher']],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertSame(42, $result['id']);
        $this->assertSame('john', $result['username']);
        $this->assertSame('john@example.com', $result['email']);
    }

    public function testNormalizeDecodedUserMixedRoleTypes(): void
    {
        $objRole = new \stdClass();
        $objRole->id = 8;
        $objRole->name = 'Librarian';

        $user = [
            'roles' => [
                ['id' => 2, 'name' => 'Admin'],
                $objRole,
                5,
                'nurse',
            ],
        ];

        $result = $this->normalizeDecodedUser->invoke(null, $user);

        $this->assertContains(2, $result['role_ids']);
        $this->assertContains(8, $result['role_ids']);
        $this->assertContains(5, $result['role_ids']);
        $this->assertContains('admin', $result['role_names']);
        $this->assertContains('librarian', $result['role_names']);
        $this->assertContains('nurse', $result['role_names']);
    }
}
