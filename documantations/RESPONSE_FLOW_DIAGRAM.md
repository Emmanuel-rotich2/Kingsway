# Response Flow Visual Diagram

## Complete Request-Response Cycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                         1. CLIENT REQUEST                           │
│                                                                     │
│                    GET /api/academic/1                             │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│              2. API ENTRY POINT (api/index.php)                     │
│                                                                     │
│  • Load Composer autoloader                                        │
│  • Load config.php                                                 │
│  • Load helpers.php                                                │
│  • Set Content-Type: application/json                              │
│  • Create Router instance                                          │
│  • Call router->handle()                                           │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│              3. MAIN ROUTER (api/router/Router.php)                 │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │ MIDDLEWARE PIPELINE (executed in order):                 │      │
│  │                                                          │      │
│  │  1. CORSMiddleware::handle()                             │      │
│  │     ✓ Check origin                                       │      │
│  │     ✓ Handle preflight requests                          │      │
│  │                                                          │      │
│  │  2. RateLimitMiddleware::handle()                        │      │
│  │     ✓ Prevent brute force attacks                        │      │
│  │                                                          │      │
│  │  3. AuthMiddleware::handle()                             │      │
│  │     ✓ Validate JWT token                                 │      │
│  │     ✓ Place user in $_REQUEST['user']                    │      │
│  │                                                          │      │
│  │  4. RBACMiddleware::handle()                             │      │
│  │     ✓ Load user permissions                              │      │
│  │                                                          │      │
│  │  5. DeviceMiddleware::handle()                           │      │
│  │     ✓ Log device fingerprint                             │      │
│  │     ✓ Check device blacklist                             │      │
│  └──────────────────────────────────────────────────────────┘      │
│                             │                                      │
│                             ▼                                      │
│             Delegate to ControllerRouter::route()                  │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│         4. CONTROLLER ROUTER (api/router/ControllerRouter.php)      │
│                                                                     │
│  • Parse URI: /academic/1                                          │
│    ├─ Controller: "academic"                                       │
│    ├─ ID: "1"                                                      │
│    ├─ Resource: null                                               │
│    └─ Segments: []                                                 │
│                                                                     │
│  • Load controller class: AcademicController                       │
│                                                                     │
│  • Build method name: "get" (from HTTP method)                     │
│                                                                     │
│  • Check method exists: AcademicController::get() ✓                │
│                                                                     │
│  • Get request body: {} (empty for GET)                            │
│                                                                     │
│  • Call: $controller->get(1, {}, [])                               │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│        5. CONTROLLER (api/controllers/AcademicController.php)       │
│                                                                     │
│  public function get($id = 1, $data = {}, $segments = [])          │
│  {                                                                  │
│      try {                                                          │
│          // Step 1: Call Module API                                │
│          $result = $this->api->getLearningArea(1);                 │
│                                                                     │
│          // Receives:                                              │
│          // [                                                      │
│          //     'status' => 'success',                             │
│          //     'message' => '...',                                │
│          //     'data' => [...]                                    │
│          // ]                                                      │
│                                                                     │
│          // Step 2: Check status                                   │
│          if ($result['status'] === 'success') {                    │
│              // Step 3: Route to BaseController method             │
│              return $this->success(                                │
│                  $result['data'],                                  │
│                  $result['message']                                │
│              );                                                    │
│          }                                                          │
│                                                                     │
│          // Error case                                             │
│          return $this->notFound($result['message']);               │
│                                                                     │
│      } catch (Exception $e) {                                      │
│          return $this->serverError($e->getMessage());              │
│      }                                                              │
│  }                                                                  │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│      6. MODULE API (api/modules/academic/AcademicAPI.php)           │
│                                                                     │
│  public function getLearningArea($id)                              │
│  {                                                                  │
│      $sql = "SELECT * FROM learning_areas WHERE id = ?";           │
│      $stmt = $this->db->prepare($sql);                             │
│      $stmt->execute([$id]);                                        │
│      $area = $stmt->fetch(PDO::FETCH_ASSOC);                       │
│                                                                     │
│      if (!$area) {                                                 │
│          // Return RAW ARRAY (not JSON string)                     │
│          return errorResponse('Learning area not found');          │
│          // Returns: ['status'=>'error', 'message'=>'...', ...]    │
│      }                                                              │
│                                                                     │
│      // Return RAW ARRAY (not JSON string)                         │
│      return successResponse($area, 'Area retrieved');              │
│      // Returns: ['status'=>'success', 'message'=>'...', 'data'=>..]
│  }                                                                  │
│                                                                     │
│  ✓ Database query executed                                         │
│  ✓ Data retrieved/error determined                                 │
│  ✓ Raw array returned (NO JSON encoding here)                      │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                    Returns RAW ARRAY
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│ FLOW CONTINUES BACK UP THE STACK:                                   │
│                                                                     │
│  Module API Returns Array                                          │
│           │                                                        │
│           ▼                                                        │
│  Controller receives array                                         │
│           │                                                        │
│           ▼                                                        │
│  Controller calls BaseController.success()                         │
│           │                                                        │
│           ▼                                                        │
│  BaseController.formatResponse() adds metadata                     │
│           │                                                        │
│           ▼                                                        │
│  json_encode() converts to JSON string                             │
│           │                                                        │
│           ▼                                                        │
│  ControllerRouter receives JSON string                             │
│           │                                                        │
│           ▼                                                        │
│  Router receives JSON string                                       │
│           │                                                        │
│           ▼                                                        │
│  Entry point receives JSON string                                  │
│           │                                                        │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│    7. BASE CONTROLLER (api/controllers/BaseController.php)          │
│                                                                     │
│  protected function success($data = null, $message = 'Success')    │
│  {                                                                  │
│      return $this->formatResponse(                                 │
│          'success',                                                │
│          $data,                                                    │
│          $message,                                                 │
│          200  ← HTTP Status Code                                   │
│      );                                                            │
│  }                                                                  │
│                                                                     │
│  private function formatResponse($status, $data, $message, $code) │
│  {                                                                  │
│      // Set HTTP response code                                     │
│      http_response_code($code);  // Sets header: 200               │
│                                                                     │
│      // CRITICAL: JSON ENCODING HAPPENS HERE                       │
│      return json_encode([                                          │
│          'status' => $status,           // 'success'               │
│          'message' => $message,         // 'Area retrieved'        │
│          'data' => $data,               // [...array...]           │
│          'code' => $code,               // 200                     │
│          'timestamp' => date('c'),      // '2024-11-14T10:30:00Z'  │
│          'request_id' => $this->request_id  // 'req_6547a8f9c5d2e' │
│      ]);                                                            │
│      // ↓                                                           │
│      // Returns JSON STRING (not array)                            │
│  }                                                                  │
│                                                                     │
│  ✓ HTTP status code set via http_response_code()                   │
│  ✓ All metadata added                                              │
│  ✓ json_encode() called to create JSON string                      │
│  ✓ JSON string returned to controller                              │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                    Returns JSON STRING
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│         8. API ENTRY POINT (api/index.php) FINAL STEP               │
│                                                                     │
│  $router = new Router();                                           │
│  $response = $router->handle();  // Already JSON string            │
│                                                                     │
│  // FINAL OUTPUT TO CLIENT                                        │
│  echo json_encode($response);  // Echoes to client                 │
│                                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   9. HTTP RESPONSE TO CLIENT                        │
│                                                                     │
│  HTTP/1.1 200 OK                                                   │
│  Content-Type: application/json; charset=utf-8                     │
│  X-Request-ID: req_6547a8f9c5d2e                                   │
│                                                                     │
│  {                                                                  │
│    "status": "success",                                            │
│    "message": "Learning area retrieved",                           │
│    "data": {                                                       │
│      "id": 1,                                                      │
│      "name": "Mathematics",                                        │
│      "code": "MATH"                                                │
│    },                                                              │
│    "code": 200,                                                    │
│    "timestamp": "2024-11-14T10:30:00+00:00",                      │
│    "request_id": "req_6547a8f9c5d2e"                               │
│  }                                                                  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│              10. FRONTEND (JavaScript/React)                        │
│                                                                     │
│  fetch('/api/academic/1')                                          │
│    .then(res => res.json())                                        │
│    .then(json => {                                                 │
│        if (json.status === 'success') {                            │
│            console.log(json.data.name);  // 'Mathematics'          │
│        }                                                            │
│    })                                                               │
│    .catch(err => console.error(err));                              │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Type Evolution Through Layers

```
DATABASE LAYER
├─ SQL Query Result
│  └─ ['id' => 1, 'name' => 'Math', 'code' => 'MATH']  ← Raw array from PDO
│
↓
MODULE API LAYER
├─ Raw PHP Array
│  └─ [                                   ← RAW ARRAY (not JSON)
│      'status' => 'success',
│      'message' => 'Area retrieved',
│      'data' => ['id' => 1, ...]
│    ]
│
↓
CONTROLLER LAYER
├─ Check Status
├─ Call BaseController method
└─ Passes to BaseController
│
↓
BASE CONTROLLER LAYER
├─ Receives: Array with data/message
├─ formatResponse() called
├─ Metadata added: code, timestamp, request_id
├─ json_encode() called  ← JSON ENCODING HAPPENS HERE
└─ Returns: JSON STRING (not array)
│  └─ "{\"status\":\"success\",\"message\":\"Area retrieved\",...}"
│
↓
ROUTER LAYER
├─ Receives JSON string from Controller
└─ Returns to Entry Point
│
↓
ENTRY POINT LAYER
├─ Receives: JSON string
├─ echo json_encode($response)  ← Echo to client
└─ Final output to client
│
↓
FRONTEND LAYER
├─ Receives: Valid JSON
├─ res.json() parses it
└─ Usable JavaScript object
```

---

## HTTP Status Code Flow

```
Module API Returns Status Field
        │
        ├─ 'success' → Controller calls $this->success()
        │              ↓
        │              BaseController::formatResponse('success', ..., 200)
        │              ↓
        │              HTTP 200 OK ✓
        │
        └─ 'error'   → Controller calls $this->notFound() / badRequest() / etc.
                       ↓
                       BaseController::formatResponse('error', ..., 40X/50X)
                       ↓
                       HTTP 40X/50X Error ✓
```

---

## Complete Transformation Summary

| Layer | Input Type | Output Type | What Changes |
|-------|-----------|------------|--------------|
| Database | SQL result | PHP array | Raw data from DB |
| Module API | PHP array | PHP array | Adds status & message wrapper |
| Controller | PHP array | JSON string | Checks status, routes response |
| BaseController | Data + message | JSON string | **ADDS METADATA** + **JSON ENCODES** |
| Router | JSON string | JSON string | Passes through unchanged |
| Entry Point | JSON string | (echoed) | Outputs to client |
| Frontend | JSON string | JS object | Parses JSON for use |

---

## Key Transformation Point

```
                    BaseController is the ONLY place
                    where JSON encoding happens
                              ▼
┌─────────────────────────────────────────────────┐
│  json_encode([                                  │
│    'status' => $status,                         │
│    'message' => $message,                       │
│    'data' => $data,              ← Module data  │
│    'code' => $code,              ← HTTP code    │
│    'timestamp' => date('c'),     ← Metadata     │
│    'request_id' => $this->request_id  ← Metadata│
│  ])                                             │
│                                                 │
│  Output: Complete JSON response                 │
└─────────────────────────────────────────────────┘
```

---

## Everything is Connected ✅

```txt
✓ Module returns array → Controller receives array
✓ Controller checks status → Routes to BaseController
✓ BaseController adds metadata → Encodes JSON
✓ Router handles JSON → Entry point echoes
✓ Frontend receives complete response ✓
```


```
Frontend Request
    ↓
api/index.php (entry)
    ↓
Router::handle()
    ↓
CORSMiddleware → RateLimitMiddleware → AuthMiddleware → RBACMiddleware → DeviceMiddleware
    ↓
ControllerRouter::route()
    ↓
Parse URI: 'academic/exams/start-workflow'
Extract: controller='academic', resource='exams', segments=['start-workflow']
Build method: 'postExams'
Extract data: JSON body → $data array
    ↓
new AcademicController()
    ↓
$controller->postExams(null, $data, ['start-workflow'])
    ↓
routeNestedPost() builds 'postExamsStartWorkflow'
    ↓
$controller->postExamsStartWorkflow(null, $data, [])
    ↓
$this->api->startExaminationWorkflow(123, 5, 'mid-term', $data)
    ↓
Database operations (stored procedures, SQL queries)
Transaction management (begin → execute → commit/rollback)
Logging (BaseAPI::logAction, logError)
    ↓
Return ['success' => true, 'data' => [...]]
    ↓
handleResponse() formats result
    ↓
BaseController::success() or badRequest()
    ↓
formatResponse() creates JSON
    ↓
Response sent to frontend with HTTP status code
```