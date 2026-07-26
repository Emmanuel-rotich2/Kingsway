# Complete Staff Management Access Control Summary

## Updated Role-Based Access Control for Staff Routes

### Complete Access Summary Table:

| Route | Director | School Admin | Headteacher | Deputy Academic | Deputy Discipline | Generic Staff |
|-------|----------|--------------|-------------|-----------------|-------------------|----------------|
| manage_staff | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| staff_onboarding | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| staff_lifecycle | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| staff_appointments | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| import_existing_staff | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| all_teachers | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| staff_performance | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| teacher_workload | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ |
| staff_id_cards | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| staff_role_assignments | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| teacher_performance_reviews | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ |
| staff_attendance | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| staff_leave | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |

### Detailed Route Descriptions:

#### Core Staff Management Routes:

**manage_staff** - All Staff Directory
- **Director**: Full access to view, approve appointments, strategic oversight
- **School Admin**: Full HR operations, create, edit, delete staff records
- **Headteacher**: View all staff, participate in interviews, approve leave
- **Deputy Discipline**: View staff for disciplinary matters, access staff information
- **Deputy Academic**: No access (focus on teacher-specific routes)
- **Generic Staff**: No access

**staff_onboarding** - Staff Onboarding Workflow
- **Director**: Approve onboarding, strategic hiring decisions
- **School Admin**: Full onboarding management, document collection, probation tracking
- **Headteacher**: Participate in onboarding interviews, review probation progress
- **Deputy Discipline**: Monitor staff onboarding for disciplinary considerations
- **Deputy Academic**: No access
- **Generic Staff**: No access

**staff_lifecycle** - Staff Lifecycle Management
- **Director**: Approve promotions, demotions, transfers, terminations
- **School Admin**: Initiate lifecycle actions, track career progression
- **Headteacher**: Recommend promotions, handle personnel changes
- **Deputy Discipline**: Handle disciplinary-related lifecycle actions (suspensions, terminations)
- **Deputy Academic**: No access
- **Generic Staff**: No access

**staff_appointments** - Staff Appointments
- **Director**: Approve new appointments, salary changes
- **School Admin**: Create appointment records, manage contract terms
- **Headteacher**: Participate in appointment interviews, recommend positions
- **Deputy Discipline**: View appointments for disciplinary context
- **Deputy Academic**: No access
- **Generic Staff**: No access

**import_existing_staff** - Import Existing Staff
- **Director**: Strategic oversight of staff migration
- **School Admin**: Execute bulk staff import operations
- **Headteacher**: No access
- **Deputy Discipline**: No access
- **Deputy Academic**: No access
- **Generic Staff**: No access

#### Teacher-Specific Routes:

**all_teachers** - Teachers Directory
- **Director**: View teaching workforce, strategic teacher management
- **School Admin**: Full teacher management, onboarding, security passes
- **Headteacher**: Teacher oversight, performance management
- **Deputy Academic**: Primary teacher management authority
- **Deputy Discipline**: View teachers for disciplinary matters
- **Generic Staff**: No access

**teacher_workload** - Teacher Workload Management
- **Director**: Ensure equitable workload distribution
- **School Admin**: No access (focus on HR operations)
- **Headteacher**: Review and approve teacher workload assignments
- **Deputy Academic**: Primary workload management authority
- **Deputy Discipline**: No access
- **Generic Staff**: No access

**teacher_performance_reviews** - Teacher Performance Reviews
- **Director**: Strategic oversight of teacher performance
- **School Admin**: No access (focus on HR operations)
- **Headteacher**: Conduct teacher performance reviews
- **Deputy Academic**: Primary performance review authority
- **Deputy Discipline**: Performance reviews with disciplinary context
- **Generic Staff**: No access

#### Performance and Monitoring Routes:

**staff_performance** - Staff Performance Overview
- **Director**: Organization-wide performance metrics
- **School Admin**: HR performance management
- **Headteacher**: No access (focus on teacher-specific reviews)
- **Deputy Academic**: No access
- **Deputy Discipline**: Staff performance for disciplinary matters
- **Generic Staff**: No access

**staff_attendance** - Staff Attendance
- **Director**: Monitor staff attendance patterns
- **School Admin**: Manage daily staff attendance
- **Headteacher**: Monitor teaching staff attendance
- **Deputy Academic**: No access
- **Deputy Discipline**: Monitor attendance for disciplinary action (absenteeism)
- **Generic Staff**: No access

**staff_leave** - Staff Leave Management
- **Director**: Approve extended leave, strategic staffing
- **School Admin**: Process leave requests, manage leave balances
- **Headteacher**: Approve teaching staff leave
- **Deputy Academic**: No access
- **Deputy Discipline**: Consider disciplinary context for leave decisions
- **Generic Staff**: No access

#### Administrative Routes:

**staff_id_cards** - Staff Security Passes
- **Director**: No access (operational function)
- **School Admin**: Generate, preview, print and issue staff security passes
- **Headteacher**: No access
- **Deputy Academic**: No access
- **Deputy Discipline**: No access
- **Generic Staff**: No access

**staff_role_assignments** - Staff Role Assignments
- **Director**: No access (operational function)
- **School Admin**: Assign system roles and permissions
- **Headteacher**: No access
- **Deputy Academic**: No access
- **Deputy Discipline**: No access
- **Generic Staff**: No access

### Role-Specific Capabilities:

#### Director (Role 3) - Strategic Authority
- Full oversight of entire staff management system
- Approve appointments, promotions, terminations
- Strategic hiring and workforce planning
- Review organization-wide performance metrics
- Approve major staffing changes and salary adjustments

#### School Administrator (Role 4) - Operations Backbone
- Complete HR operational control
- Staff onboarding, lifecycle management
- Import existing staff records
- Generate staff security passes and assign system roles
- Process leave requests and manage attendance
- Create payroll and handle disciplinary procedures

#### Headteacher (Role 5) - Academic Leadership
- View all staff for academic planning
- Participate in staff interviews and hiring
- Approve teaching staff leave
- Conduct teacher performance reviews
- Manage teacher workload distribution
- Access staff onboarding and lifecycle information

#### Deputy Head Academic (Role 6) - Teacher Management
- Primary authority over teacher-specific routes
- Assign teachers to classes and subjects
- Manage teacher workload balancing
- Conduct teacher performance reviews
- No access to general staff management (delegated to School Admin)

#### Deputy Head Discipline (Role 63) - Disciplinary Leadership
- **Staff disciplinary oversight authority**
- Access staff directories for disciplinary investigations
- Monitor staff performance and attendance for disciplinary matters
- Handle staff lifecycle actions related to discipline (suspensions, terminations)
- Review staff onboarding for disciplinary considerations
- Access staff leave records for disciplinary context
- **Key role**: Head of disciplinary committee dealing with both staff and student cases

#### Generic Staff (Role 64) - Basic Access
- Personal information only (no staff management access)
- View own timetable, attendance, payslip
- Read announcements and messages
- No access to any staff management routes

### Permission Mapping:

The system uses database permissions checked by backend middleware:

```javascript
// Staff management permissions
staff.directory.view        // View staff directories
staff.directory.manage      // Manage staff records
staff.onboarding.view       // View onboarding records
staff.onboarding.manage     // Manage onboarding process
staff.lifecycle.view        // View lifecycle records
staff.lifecycle.manage      // Manage lifecycle actions
staff.appointments.view     // View appointments
staff.appointments.manage   // Manage appointments
staff.import.manage         // Import existing staff
staff.performance.view      // View performance data
staff.roles.manage          // Manage staff roles
```

Each role's permissions are stored in the database and enforced by the RBAC middleware to ensure proper access control.
