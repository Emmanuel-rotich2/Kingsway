<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/services/PolicyEngine.php';

use App\Services\PolicyEngine;
use ReflectionMethod;
use ReflectionClass;

class PolicyEngineTest extends TestCase
{
    private ReflectionMethod $compareValues;
    private ReflectionMethod $resolveFieldValue;
    private ReflectionMethod $evaluateRuleExpression;
    private ReflectionMethod $policyApplies;
    private PolicyEngine $engine;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(PolicyEngine::class);

        // Create instance without DB constructor via reflection
        $this->engine = $ref->newInstanceWithoutConstructor();

        $this->compareValues = $ref->getMethod('compareValues');
        $this->compareValues->setAccessible(true);

        $this->resolveFieldValue = $ref->getMethod('resolveFieldValue');
        $this->resolveFieldValue->setAccessible(true);

        $this->evaluateRuleExpression = $ref->getMethod('evaluateRuleExpression');
        $this->evaluateRuleExpression->setAccessible(true);

        $this->policyApplies = $ref->getMethod('policyApplies');
        $this->policyApplies->setAccessible(true);
    }

    // --- compareValues ---

    public function testCompareValuesEquals(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, '=', 5));
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, '==', '5'));
        $this->assertTrue($this->compareValues->invoke($this->engine, 'foo', 'EQ', 'foo'));
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, 'EQUALS', 10));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, '=', 6));
    }

    public function testCompareValuesStrictEquals(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, '===', 5));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, '===', '5'));
        $this->assertTrue($this->compareValues->invoke($this->engine, 'bar', 'STRICT_EQUALS', 'bar'));
    }

    public function testCompareValuesNotEquals(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, '!=', 6));
        $this->assertTrue($this->compareValues->invoke($this->engine, 'a', '<>', 'b'));
        $this->assertTrue($this->compareValues->invoke($this->engine, 1, 'NEQ', 2));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, '!=', 5));
    }

    public function testCompareValuesGreaterThan(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, '>', 5));
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, 'GT', 5));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, '>', 5));
    }

    public function testCompareValuesGreaterThanOrEqual(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, '>=', 10));
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, 'GTE', 5));
        $this->assertFalse($this->compareValues->invoke($this->engine, 4, '>=', 5));
    }

    public function testCompareValuesLessThan(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 3, '<', 5));
        $this->assertTrue($this->compareValues->invoke($this->engine, 3, 'LT', 5));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, '<', 5));
    }

    public function testCompareValuesLessThanOrEqual(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, '<=', 5));
        $this->assertTrue($this->compareValues->invoke($this->engine, 3, 'LTE', 5));
        $this->assertFalse($this->compareValues->invoke($this->engine, 6, '<=', 5));
    }

    public function testCompareValuesIn(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 2, 'IN', [1, 2, 3]));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, 'IN', [1, 2, 3]));
    }

    public function testCompareValuesNotIn(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, 'NOT_IN', [1, 2, 3]));
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, 'NOT IN', [1, 2, 3]));
        $this->assertFalse($this->compareValues->invoke($this->engine, 2, 'NOT_IN', [1, 2, 3]));
    }

    public function testCompareValuesContains(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'hello world', 'CONTAINS', 'world'));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'hello', 'CONTAINS', 'world'));
    }

    public function testCompareValuesNotContains(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'hello', 'NOT_CONTAINS', 'world'));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'hello world', 'NOT_CONTAINS', 'world'));
    }

    public function testCompareValuesStartsWith(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'finance.view', 'STARTS_WITH', 'finance'));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'staff.view', 'STARTS', 'finance'));
    }

    public function testCompareValuesEndsWith(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'finance.view', 'ENDS_WITH', '.view'));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'finance.edit', 'ENDS', '.view'));
    }

    public function testCompareValuesRegex(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'test123', 'REGEX', '/^test\d+$/'));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'abc', 'MATCHES', '/^test\d+$/'));
    }

    public function testCompareValuesIsNull(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, null, 'IS_NULL', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, null, 'NULL', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, 0, 'IS_NULL', null));
    }

    public function testCompareValuesIsNotNull(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'value', 'IS_NOT_NULL', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, null, 'NOT_NULL', null));
    }

    public function testCompareValuesIsEmpty(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, '', 'IS_EMPTY', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, [], 'EMPTY', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, null, 'EMPTY', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, 'text', 'IS_EMPTY', null));
    }

    public function testCompareValuesIsNotEmpty(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 'text', 'IS_NOT_EMPTY', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, '', 'NOT_EMPTY', null));
    }

    public function testCompareValuesIsTrue(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, true, 'IS_TRUE', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, 1, 'TRUE', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, '1', 'IS_TRUE', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, false, 'IS_TRUE', null));
    }

    public function testCompareValuesIsFalse(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, false, 'IS_FALSE', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, 0, 'FALSE', null));
        $this->assertTrue($this->compareValues->invoke($this->engine, '0', 'IS_FALSE', null));
        $this->assertFalse($this->compareValues->invoke($this->engine, true, 'IS_FALSE', null));
    }

    public function testCompareValuesBetween(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 5, 'BETWEEN', [1, 10]));
        $this->assertTrue($this->compareValues->invoke($this->engine, 1, 'BETWEEN', [1, 10]));
        $this->assertTrue($this->compareValues->invoke($this->engine, 10, 'BETWEEN', [1, 10]));
        $this->assertFalse($this->compareValues->invoke($this->engine, 11, 'BETWEEN', [1, 10]));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, 'BETWEEN', [1]));
    }

    public function testCompareValuesNotBetween(): void
    {
        $this->assertTrue($this->compareValues->invoke($this->engine, 11, 'NOT_BETWEEN', [1, 10]));
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, 'NOT BETWEEN', [1, 10]));
    }

    public function testCompareValuesUnknownOperator(): void
    {
        $this->assertFalse($this->compareValues->invoke($this->engine, 5, 'UNKNOWN', 5));
    }

    // --- resolveFieldValue ---

    public function testResolveFieldValueSimple(): void
    {
        $context = ['user_id' => 42, 'role_id' => 5];

        $this->assertSame(42, $this->resolveFieldValue->invoke($this->engine, 'user_id', $context));
        $this->assertSame(5, $this->resolveFieldValue->invoke($this->engine, 'role_id', $context));
    }

    public function testResolveFieldValueNested(): void
    {
        $context = [
            'route' => ['domain' => 'SCHOOL', 'id' => 10],
        ];

        $this->assertSame('SCHOOL', $this->resolveFieldValue->invoke($this->engine, 'route.domain', $context));
        $this->assertSame(10, $this->resolveFieldValue->invoke($this->engine, 'route.id', $context));
    }

    public function testResolveFieldValueDeepNested(): void
    {
        $context = [
            'a' => ['b' => ['c' => 'deep']],
        ];

        $this->assertSame('deep', $this->resolveFieldValue->invoke($this->engine, 'a.b.c', $context));
    }

    public function testResolveFieldValueMissingKey(): void
    {
        $context = ['user_id' => 1];

        $this->assertNull($this->resolveFieldValue->invoke($this->engine, 'missing_key', $context));
    }

    public function testResolveFieldValueMissingNestedKey(): void
    {
        $context = ['route' => ['domain' => 'SCHOOL']];

        $this->assertNull($this->resolveFieldValue->invoke($this->engine, 'route.nonexistent', $context));
    }

    // --- evaluateRuleExpression ---

    public function testEvaluateLeafRule(): void
    {
        $expression = ['field' => 'role_id', 'operator' => '=', 'value' => 2];
        $context = ['role_id' => 2];

        $this->assertTrue($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateLeafRuleFails(): void
    {
        $expression = ['field' => 'role_id', 'operator' => '=', 'value' => 2];
        $context = ['role_id' => 5];

        $this->assertFalse($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateAndConditionAllTrue(): void
    {
        $expression = [
            'condition' => 'AND',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                ['field' => 'route.domain', 'operator' => '=', 'value' => 'SCHOOL'],
            ],
        ];
        $context = ['role_id' => 2, 'route' => ['domain' => 'SCHOOL']];

        $this->assertTrue($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateAndConditionOneFalse(): void
    {
        $expression = [
            'condition' => 'AND',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                ['field' => 'route.domain', 'operator' => '=', 'value' => 'SYSTEM'],
            ],
        ];
        $context = ['role_id' => 2, 'route' => ['domain' => 'SCHOOL']];

        $this->assertFalse($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateOrConditionOneTrue(): void
    {
        $expression = [
            'condition' => 'OR',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                ['field' => 'role_id', 'operator' => '=', 'value' => 5],
            ],
        ];
        $context = ['role_id' => 5];

        $this->assertTrue($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateOrConditionAllFalse(): void
    {
        $expression = [
            'condition' => 'OR',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                ['field' => 'role_id', 'operator' => '=', 'value' => 3],
            ],
        ];
        $context = ['role_id' => 5];

        $this->assertFalse($this->evaluateRuleExpression->invoke($this->engine, $expression, $context));
    }

    public function testEvaluateNotCondition(): void
    {
        $expression = [
            'condition' => 'NOT',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
            ],
        ];

        $this->assertTrue($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['role_id' => 5]
        ));
        $this->assertFalse($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['role_id' => 2]
        ));
    }

    public function testEvaluateXorCondition(): void
    {
        $expression = [
            'condition' => 'XOR',
            'rules' => [
                ['field' => 'a', 'operator' => '=', 'value' => 1],
                ['field' => 'b', 'operator' => '=', 'value' => 1],
            ],
        ];

        // Exactly one true
        $this->assertTrue($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['a' => 1, 'b' => 0]
        ));

        // Both true
        $this->assertFalse($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['a' => 1, 'b' => 1]
        ));

        // Both false
        $this->assertFalse($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['a' => 0, 'b' => 0]
        ));
    }

    public function testEvaluateNestedExpression(): void
    {
        $expression = [
            'condition' => 'OR',
            'rules' => [
                [
                    'condition' => 'AND',
                    'rules' => [
                        ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                        ['field' => 'route.domain', 'operator' => '=', 'value' => 'SYSTEM'],
                    ],
                ],
                ['field' => 'user_id', 'operator' => '=', 'value' => 1],
            ],
        ];

        // Matches second branch
        $this->assertTrue($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['role_id' => 5, 'route' => ['domain' => 'SCHOOL'], 'user_id' => 1]
        ));

        // Matches first branch
        $this->assertTrue($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['role_id' => 2, 'route' => ['domain' => 'SYSTEM'], 'user_id' => 99]
        ));

        // Neither matches
        $this->assertFalse($this->evaluateRuleExpression->invoke(
            $this->engine,
            $expression,
            ['role_id' => 5, 'route' => ['domain' => 'SCHOOL'], 'user_id' => 99]
        ));
    }

    public function testEvaluateEmptyRulesReturnsFalse(): void
    {
        $expression = ['condition' => 'AND', 'rules' => []];

        $this->assertFalse($this->evaluateRuleExpression->invoke($this->engine, $expression, []));
    }

    public function testEvaluateMissingConditionAndFieldReturnsFalse(): void
    {
        $expression = ['something' => 'else'];

        $this->assertFalse($this->evaluateRuleExpression->invoke($this->engine, $expression, []));
    }

    // --- policyApplies ---

    public function testPolicyAppliesGlobal(): void
    {
        $policy = ['applies_to' => 'global', 'target_ids' => null];
        $context = ['user_id' => 1, 'role_id' => 2];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesRoleMatch(): void
    {
        $policy = ['applies_to' => 'role', 'target_ids' => json_encode([2, 5])];
        $context = ['role_id' => 2];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesRoleNoMatch(): void
    {
        $policy = ['applies_to' => 'role', 'target_ids' => json_encode([2, 5])];
        $context = ['role_id' => 10];

        $this->assertFalse($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesRoleEmptyTargets(): void
    {
        $policy = ['applies_to' => 'role', 'target_ids' => json_encode([])];
        $context = ['role_id' => 10];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesUserMatch(): void
    {
        $policy = ['applies_to' => 'user', 'target_ids' => json_encode([42])];
        $context = ['user_id' => 42];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesRouteMatch(): void
    {
        $policy = ['applies_to' => 'route', 'target_ids' => json_encode([10, 20])];
        $context = ['route' => ['id' => 10]];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesDomainMatch(): void
    {
        $policy = ['applies_to' => 'domain', 'target_ids' => json_encode(['SYSTEM'])];
        $context = ['route' => ['domain' => 'SYSTEM']];

        $this->assertTrue($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    public function testPolicyAppliesUnknownType(): void
    {
        $policy = ['applies_to' => 'unknown', 'target_ids' => null];
        $context = [];

        $this->assertFalse($this->policyApplies->invoke($this->engine, $policy, $context));
    }

    // --- createPreset ---

    public function testCreatePresetSystemAdminNoSchool(): void
    {
        $preset = PolicyEngine::createPreset('system_admin_no_school');

        $this->assertSame('system_admin_no_school_access', $preset['name']);
        $this->assertSame('deny', $preset['rule_type']);
        $this->assertSame(100, $preset['priority']);
        $this->assertSame('role', $preset['applies_to']);
        $this->assertSame([2], $preset['target_ids']);
    }

    public function testCreatePresetSchoolNoSystem(): void
    {
        $preset = PolicyEngine::createPreset('school_no_system');

        $this->assertSame('school_roles_no_system_access', $preset['name']);
        $this->assertSame('deny', $preset['rule_type']);
    }

    public function testCreatePresetDelegatedReadonly(): void
    {
        $preset = PolicyEngine::createPreset('delegated_readonly');

        $this->assertSame('restrict', $preset['rule_type']);
        $this->assertSame('global', $preset['applies_to']);
    }

    public function testCreatePresetMaintenanceDenyAll(): void
    {
        $preset = PolicyEngine::createPreset('maintenance_deny_all');

        $this->assertSame('deny', $preset['rule_type']);
        $this->assertSame(200, $preset['priority']);
        $this->assertFalse($preset['is_active']);
    }

    public function testCreatePresetUnknownReturnsEmpty(): void
    {
        $preset = PolicyEngine::createPreset('nonexistent');

        $this->assertSame([], $preset);
    }

    // --- validateRuleExpression ---

    public function testValidateRuleExpressionValidLeaf(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'field' => 'role_id',
            'operator' => '=',
            'value' => 2,
        ]);

        $this->assertSame([], $errors);
    }

    public function testValidateRuleExpressionEmptyField(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'field' => '',
            'operator' => '=',
        ]);

        $this->assertContains('Rule field cannot be empty', $errors);
    }

    public function testValidateRuleExpressionEmptyOperator(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'field' => 'role_id',
            'operator' => '',
        ]);

        $this->assertContains('Rule operator cannot be empty', $errors);
    }

    public function testValidateRuleExpressionValidCompound(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'condition' => 'AND',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testValidateRuleExpressionInvalidCondition(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'condition' => 'NAND',
            'rules' => [
                ['field' => 'role_id', 'operator' => '=', 'value' => 2],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('NAND', $errors[0]);
    }

    public function testValidateRuleExpressionMissingRulesArray(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'condition' => 'AND',
        ]);

        $this->assertContains('Compound rule must have a rules array', $errors);
    }

    public function testValidateRuleExpressionNestedErrors(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'condition' => 'AND',
            'rules' => [
                ['field' => '', 'operator' => '='],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Rule[0]', $errors[0]);
    }

    public function testValidateRuleExpressionNoFieldOrCondition(): void
    {
        $errors = $this->engine->validateRuleExpression([
            'something' => 'else',
        ]);

        $this->assertContains('Rule must have either a field or a condition', $errors);
    }
}
