<?php

namespace App\API\Controllers;

use App\API\Includes\FileLogger;
use App\API\Includes\RpcSchemaValidator;
use App\API\Services\McpTokenService;
use App\API\Services\RpcRegistry;
use App\API\Services\AiWorkflowService;
use App\API\Services\McpDataToolsService;
use App\Database\Database;
use DomainException;
use Throwable;

/** Read-only Model Context Protocol bridge for approved machine clients. */
class McpController extends BaseController
{
    private const PROTOCOL = '2024-11-05';

    /** Only low-risk, aggregate-free system metadata is exposed initially. */
    private const TOOLS = [
        'system_info' => [
            'rpc' => 'system.info',
            'scope' => 'system.info',
            'description' => 'Read application health metadata without school or learner records.',
        ],
        'system_schema' => [
            'rpc' => 'system.schema.list',
            'scope' => 'system.schema.list',
            'description' => 'Read the allowlisted RPC contract catalogue.',
        ],
        'ai_workflows_list' => [
            'scope' => 'ai.workflow.catalog',
            'description' => 'List governed staff AI workflows and their approval policies.',
        ],
        'ai_workflow_prepare' => [
            'scope' => 'ai.workflow.prepare',
            'description' => 'Prepare bounded staff work for review; this never publishes or mutates records.',
        ],
        'school_attendance_summary' => [
            'service' => 'attendanceSummary',
            'scope' => 'school.data.attendance_summary',
            'description' => 'Aggregate daily attendance status counts and present rate for the selected scope. No learner identity.',
            'filters' => ['date_from', 'date_to', 'level', 'status'],
        ],
        'school_fee_collection_summary' => [
            'service' => 'feeCollectionSummary',
            'scope' => 'school.data.fee_collection_summary',
            'description' => 'Aggregate fee collection rates per grade level. No payment references or payer details.',
            'filters' => ['term', 'level'],
        ],
        'school_academic_performance_summary' => [
            'service' => 'academicPerformanceSummary',
            'scope' => 'school.data.academic_performance_summary',
            'description' => 'Aggregate CBC performance: per-level/learning-area averages and competency band distribution. No individual grades.',
            'filters' => ['class', 'learning_area'],
        ],
        'school_enrollment_stats' => [
            'service' => 'enrollmentStats',
            'scope' => 'school.data.enrollment_stats',
            'description' => 'Aggregate active enrollment headcount by class stream and gender. No learner identity.',
            'filters' => ['class', 'status'],
        ],
    ];

    public function getMcp($id = null, $data = [], $segments = [])
    {
        return $this->handleMessage(null);
    }

    public function postMcp($id = null, $data = [], $segments = [])
    {
        return $this->handleMessage(is_array($data) ? $data : []);
    }

    private function handleMessage(?array $request): array
    {
        $client = $this->authenticateClient();
        if ($client === null) {
            return $this->rawError(null, -32001, 'MCP client authentication required.', 401);
        }

        if ($request === null) {
            return $this->rawResult(null, [
                'protocolVersion' => self::PROTOCOL,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'Kingsway MCP Bridge', 'version' => '1.0.0'],
            ]);
        }

        $id = $request['id'] ?? null;
        if (($request['jsonrpc'] ?? null) !== '2.0' || !isset($request['method'])) {
            return $this->rawError($id, -32600, 'Invalid Request.');
        }

        try {
            $method = (string) $request['method'];
            $params = isset($request['params']) && is_array($request['params']) ? $request['params'] : [];
            if ($method === 'notifications/initialized') {
                return ['mcp_raw' => true, 'notification' => true];
            }
            if ($method === 'tools/list') {
                return $this->rawResult($id, ['tools' => $this->toolList($client)]);
            }
            if ($method === 'tools/call') {
                return $this->callTool($id, $params, $client);
            }
            return $this->rawError($id, -32601, 'Method not found.');
        } catch (DomainException $e) {
            return $this->rawError($id, -32602, $e->getMessage());
        } catch (Throwable $e) {
            FileLogger::write('mcp', [
                'type' => 'error',
                'client_id' => $client['client_id'],
                'error' => $e->getMessage(),
            ], 'error');
            return $this->rawError($id, -32603, 'Internal error.');
        }
    }

    private function toolList(array $client): array
    {
        $tools = [];
        foreach (self::TOOLS as $name => $tool) {
            if (!McpTokenService::hasScope($client, $tool['scope'])) {
                continue;
            }
            $tools[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $this->inputSchema($name),
            ];
        }
        return $tools;
    }

    private function inputSchema(string $name): array
    {
        $tool = self::TOOLS[$name] ?? [];
        if ($name === 'ai_workflow_prepare') {
            return [
                'type' => 'object',
                'required' => ['workflow_id', 'input'],
                'properties' => [
                    'workflow_id' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 100],
                    'input' => ['type' => 'object'],
                ],
                'additionalProperties' => false,
            ];
        }
        $properties = [];
        foreach ((array) ($tool['filters'] ?? []) as $filter) {
            $properties[$filter] = ['type' => ['string', 'array']];
        }
        return [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];
    }

    private function callTool($id, array $params, array $client): array
    {
        $name = (string) ($params['name'] ?? '');
        if (!isset(self::TOOLS[$name])) {
            return $this->rawError($id, -32602, 'Unknown tool.');
        }
        $tool = self::TOOLS[$name];
        if (!McpTokenService::hasScope($client, $tool['scope'])) {
            return $this->rawError($id, -32003, 'Tool scope is not granted.', 403);
        }
        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            return $this->rawError($id, -32602, 'Tool arguments must be an object.');
        }
        if (in_array($name, ['system_info', 'system_schema', 'ai_workflows_list'], true) && $arguments !== []) {
            return $this->rawError($id, -32602, 'This tool does not accept arguments.');
        }

        if ($name === 'ai_workflows_list') {
            $workflowService = $this->contract(AiWorkflowService::class);
            $visible = array_values(array_filter(
                $workflowService->describeForContext($client['scopes'], '', 'dashboard', 'staff'),
                static function (array $workflow) use ($client): bool {
                    $required = (string) ($workflow['permission'] ?? '');
                    return $required === '' || McpTokenService::hasScope($client, $required);
                }
            ));
            return $this->rawResult($id, ['content' => [['type' => 'text', 'text' => json_encode($visible)]]]);
        }
        if ($name === 'ai_workflow_prepare') {
            $workflowId = (string) ($arguments['workflow_id'] ?? '');
            $input = $arguments['input'] ?? null;
            if ($workflowId === '' || !is_array($input) || strlen(json_encode($input)) > 30000) {
                return $this->rawError($id, -32602, 'workflow_id and bounded object input are required.');
            }
            $workflowService = $this->contract(AiWorkflowService::class);
            $result = $workflowService->prepare($workflowId, [
                'user_id' => 0,
                'permissions' => $client['scopes'],
                'effective_permissions' => $client['scopes'],
                'audience' => 'staff',
            ], $input);
            FileLogger::write('mcp', [
                'type' => 'tools.call',
                'client_id' => $client['client_id'],
                'tool' => $name,
                'workflow_id' => $workflowId,
                'params_hash' => hash('sha256', json_encode($arguments)),
            ]);
            return $this->rawResult($id, ['content' => [['type' => 'text', 'text' => json_encode($result)]]]);
        }
        if (isset($tool['service'])) {
            $serviceMethod = (string) $tool['service'];
            $dataTools = $this->contract(McpDataToolsService::class);
            if (!method_exists($dataTools, $serviceMethod)) {
                return $this->rawError($id, -32603, 'Tool implementation unavailable.');
            }
            $allowed = (array) ($tool['filters'] ?? []);
            $unknown = array_diff(array_keys($arguments), $allowed);
            if ($unknown !== []) {
                return $this->rawError($id, -32602, 'Tool contains unsupported filter arguments.');
            }
            $result = $dataTools->{$serviceMethod}($arguments);
            FileLogger::write('mcp', [
                'type' => 'tools.call',
                'client_id' => $client['client_id'],
                'tool' => $name,
                'params_hash' => hash('sha256', json_encode($arguments)),
                'row_count' => is_countable($result) ? count($result) : null,
            ]);
            return $this->rawResult($id, ['content' => [['type' => 'text', 'text' => json_encode($result)]]]);
        }

        $definition = RpcRegistry::resolve($tool['rpc']);
        if ($definition === null || !is_callable($definition['handler'] ?? null)) {
            return $this->rawError($id, -32603, 'Tool implementation unavailable.');
        }
        RpcSchemaValidator::validate([], (array) ($definition['_schema'] ?? []));
        $result = call_user_func($definition['handler'], [], [
            'user_id' => 0,
            'roles' => [],
            'request_id' => $_SERVER['REQUEST_ID'] ?? '',
            'db' => Database::getInstance()->getConnection(),
            'mcp_client_id' => $client['client_id'],
        ]);
        FileLogger::write('mcp', [
            'type' => 'tools.call',
            'client_id' => $client['client_id'],
            'tool' => $name,
            'params_hash' => hash('sha256', json_encode($arguments)),
            'row_count' => is_countable($result) ? count($result) : null,
        ]);
        return $this->rawResult($id, ['content' => [['type' => 'text', 'text' => json_encode($result)]]]);
    }

    private function authenticateClient(): ?array
    {
        $header = '';
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = (string) $value;
                    break;
                }
            }
        }
        $token = preg_match('/^Bearer\s+(mcp_[A-Za-z0-9]+)$/i', $header, $m)
            ? $m[1]
            : (string) ($_SERVER['HTTP_X_MCP_TOKEN'] ?? '');
        return McpTokenService::authenticate(
            Database::getInstance()->getConnection(),
            $token,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }

    private function rawResult($id, array $result): array
    {
        return ['mcp_raw' => true, 'jsonrpc' => '2.0', 'result' => $result, 'id' => $id];
    }

    private function rawError($id, int $code, string $message, int $http = 200): array
    {
        if ($http >= 400) {
            http_response_code($http);
        }
        return ['mcp_raw' => true, 'jsonrpc' => '2.0', 'error' => ['code' => $code, 'message' => $message], 'id' => $id];
    }
}
