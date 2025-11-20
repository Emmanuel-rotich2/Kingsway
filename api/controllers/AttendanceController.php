class AttendanceController extends BaseController
{

// =============================
// SECTION 3: Advanced Attendance APIs
// =============================

// GET /api/attendance/student-history/{studentId}
public function getStudentHistory($studentId = null, $data = [], $segments = []) {
$result = $this->api->getStudentAttendanceHistory($studentId ?? ($data['studentId'] ?? null));
return $this->handleResponse($result);
}

// GET /api/attendance/student-summary/{studentId}
public function getStudentSummary($studentId = null, $data = [], $segments = []) {
$result = $this->api->getStudentAttendanceSummary($studentId ?? ($data['studentId'] ?? null));
return $this->handleResponse($result);
}

// GET /api/attendance/class-attendance/{classId}/{termId}/{yearId}
public function getClassAttendance($classId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$result = $this->api->getClassAttendance($classId, $termId, $yearId);
return $this->handleResponse($result);
}

// GET /api/attendance/student-percentage/{studentId}/{termId}/{yearId}
public function getStudentPercentage($studentId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$result = $this->api->getStudentAttendancePercentage($studentId, $termId, $yearId);
return $this->handleResponse($result);
}

// GET /api/attendance/chronic-student-absentees/{classId}/{termId}/{yearId}/{threshold?}
public function getChronicStudentAbsentees($classId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$threshold = $segments[2] ?? $data['threshold'] ?? 0.2;
$result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
return $this->handleResponse($result);
}

// GET /api/attendance/staff-history/{staffId}
public function getStaffHistory($staffId = null, $data = [], $segments = []) {
$result = $this->api->getStaffAttendanceHistory($staffId ?? ($data['staffId'] ?? null));
return $this->handleResponse($result);
}

// GET /api/attendance/staff-summary/{staffId}
public function getStaffSummary($staffId = null, $data = [], $segments = []) {
$result = $this->api->getStaffAttendanceSummary($staffId ?? ($data['staffId'] ?? null));
return $this->handleResponse($result);
}

// GET /api/attendance/department-attendance/{departmentId}/{termId}/{yearId}
public function getDepartmentAttendance($departmentId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$result = $this->api->getDepartmentAttendance($departmentId, $termId, $yearId);
return $this->handleResponse($result);
}

// GET /api/attendance/staff-percentage/{staffId}/{termId}/{yearId}
public function getStaffPercentage($staffId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
return $this->handleResponse($result);
}

// GET /api/attendance/chronic-staff-absentees/{departmentId}/{termId}/{yearId}/{threshold?}
public function getChronicStaffAbsentees($departmentId = null, $data = [], $segments = []) {
$termId = $segments[0] ?? $data['termId'] ?? null;
$yearId = $segments[1] ?? $data['yearId'] ?? null;
$threshold = $segments[2] ?? $data['threshold'] ?? 0.2;
$result = $this->api->getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold);
return $this->handleResponse($result);
}

// POST /api/attendance/start-workflow
public function postStartWorkflow($id = null, $data = [], $segments = []) {
$result = $this->api->startAttendanceWorkflow($data);
return $this->handleResponse($result);
}

// POST /api/attendance/advance-workflow/{workflowInstanceId}/{action}
public function postAdvanceWorkflow($workflowInstanceId = null, $data = [], $segments = []) {
$action = $segments[0] ?? $data['action'] ?? null;
$result = $this->api->advanceAttendanceWorkflow($workflowInstanceId, $action, $data);
return $this->handleResponse($result);
}

// GET /api/attendance/workflow-status/{workflowInstanceId}
public function getWorkflowStatus($workflowInstanceId = null, $data = [], $segments = []) {
$result = $this->api->getAttendanceWorkflowStatus($workflowInstanceId);
return $this->handleResponse($result);
}

    // GET /api/attendance/list-workflows
    public function getListWorkflows($id = null, $data = [], $segments = []) {
        $result = $this->api->listAttendanceWorkflows($data);
        return $this->handleResponse($result);
    }

    private AttendanceAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new AttendanceAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/attendance - List all attendance records
     * GET /api/attendance/{id} - Get single attendance record
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/attendance - Create new attendance record
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/attendance/{id} - Update attendance record
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        $result = $this->api->update($data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/attendance/{id} - Delete attendance record
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // =============================
    // SECTION 2: Nested Resource Routing
    // =============================

    private function routeNestedGet($resource, $id, $data, $segments)
    {
        // ...existing code...
    }

    private function routeNestedPost($resource, $id, $data, $segments)
    {
        // ...existing code...
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
        }

        // GET /api/attendance/staff-percentage/{staffId}/{termId}/{yearId}
        public function getStaffPercentage($staffId = null, $data = [], $segments = []) {
            $termId = $segments[0] ?? $data['termId'] ?? null;
            $yearId = $segments[1] ?? $data['yearId'] ?? null;
            $result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
            return $this->handleResponse($result);
        }

        // GET /api/attendance/chronic-staff-absentees/{departmentId}/{termId}/{yearId}/{threshold?}
        public function getChronicStaffAbsentees($departmentId = null, $data = [], $segments = []) {
            $termId = $segments[0] ?? $data['termId'] ?? null;
            $yearId = $segments[1] ?? $data['yearId'] ?? null;
            $threshold = $segments[2] ?? $data['threshold'] ?? 0.2;
            $result = $this->api->getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold);
            return $this->handleResponse($result);
        }

        // POST /api/attendance/start-workflow
        public function postStartWorkflow($id = null, $data = [], $segments = []) {
            $result = $this->api->startAttendanceWorkflow($data);
            return $this->handleResponse($result);
        }

        // POST /api/attendance/advance-workflow/{workflowInstanceId}/{action}
        public function postAdvanceWorkflow($workflowInstanceId = null, $data = [], $segments = []) {
            $action = $segments[0] ?? $data['action'] ?? null;
            $result = $this->api->advanceAttendanceWorkflow($workflowInstanceId, $action, $data);
            return $this->handleResponse($result);
        }

        // GET /api/attendance/workflow-status/{workflowInstanceId}
        public function getWorkflowStatus($workflowInstanceId = null, $data = [], $segments = []) {
            $result = $this->api->getAttendanceWorkflowStatus($workflowInstanceId);
            return $this->handleResponse($result);
        }

        // GET /api/attendance/list-workflows
        public function getListWorkflows($id = null, $data = [], $segments = []) {
            $result = $this->api->listAttendanceWorkflows($data);
            return $this->handleResponse($result);
        }

        private AttendanceAPI $api;

        public function __construct() {
            parent::__construct();
            $this->api = new AttendanceAPI();
        }

        // ========================================
        // SECTION 1: Base CRUD Operations
        // ========================================

        /**
         * GET /api/attendance - List all attendance records
         * GET /api/attendance/{id} - Get single attendance record
         */
        public function get($id = null, $data = [], $segments = [])
        {
            if ($id !== null && empty($segments)) {
                $result = $this->api->get($id);
                return $this->handleResponse($result);
            }
        
            if (!empty($segments)) {
                $resource = array_shift($segments);
                return $this->routeNestedGet($resource, $id, $data, $segments);
            }
        
            $result = $this->api->list($data);
            return $this->handleResponse($result);
        }

        /**
         * POST /api/attendance - Create new attendance record
         */
        public function post($id = null, $data = [], $segments = [])
        {
            if ($id !== null) {
                $data['id'] = $id;
            }
        
            if (!empty($segments)) {
                $resource = array_shift($segments);
                return $this->routeNestedPost($resource, $id, $data, $segments);
            }
        
            $result = $this->api->create($data);
            return $this->handleResponse($result);
        }

        /**
         * PUT /api/attendance/{id} - Update attendance record
         */
        public function put($id = null, $data = [], $segments = [])
        {
            if ($id !== null) {
                $data['id'] = $id;
            }
            $result = $this->api->update($data);
            return $this->handleResponse($result);
        }

        /**
         * DELETE /api/attendance/{id} - Delete attendance record
         */
        public function delete($id = null, $data = [], $segments = [])
        {
            $result = $this->api->delete($id);
            return $this->handleResponse($result);
        }

        // =============================
        // SECTION 2: Nested Resource Routing
        // =============================

        private function routeNestedGet($resource, $id, $data, $segments)
        {
            // ...existing code...
        }

        private function routeNestedPost($resource, $id, $data, $segments)
        {
            // ...existing code...
        }

        /**
         * Get current authenticated user ID
         */
        private function getCurrentUserId()
        {
            return $this->user['id'] ?? null;
        }
    }
