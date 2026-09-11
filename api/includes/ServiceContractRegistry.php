<?php

namespace App\API\Includes;

use LogicException;

/**
 * ServiceContractRegistry — the governed service contract catalogue.
 *
 * Roadmap §4.2 "service/manager-level contract layer":
 *
 *   Frontend (js/api.js) -> HTTP Controller -> ServiceContractBroker
 *   (gRPC-style service contract layer) -> REAL Business Logic (Footnote:
 *   AcademicAPI / FinanceAPI / inline service classes) -> Database
 *
 * Every business-logic class reachable from controllers is listed in the
 * governed-service inventory below. Controllers MUST obtain governed
 * services through ServiceContractBroker::contract() (wired as the
 * BaseController::contract() accessor) — never through bare "new X(...)".
 * The scripts/contract_coverage.php lint fails loudly in development when a
 * controller bypasses the accessor, so the "MUST apply to all controllers"
 * rule is enforced mechanically, not just documented.
 *
 * The catalogue also exposes remote-facing contract ids ('id' entries)
 * that the ServiceContractBroker::call() path uses for JSON-RPC / MCP /
 * background-queue execution — dual access to the SAME business logic with
 * zero duplication.
 */
class ServiceContractRegistry
{
    /** @var array<string, array>|null contract id -> contract definition */
    private static $registry = null;

    /** @var array<string, array>|null FQCN -> governed-service definition */
    private static $governed = null;

    public static function boot(): void
    {
        if (self::$registry !== null) {
            return;
        }
        self::$registry = [];
        self::$governed = [];

        // Core governed services (extended as controllers are wired).
        self::govern('App\\API\\Modules\\academic\\AcademicAPI', 'academic', 'academic.api');
        self::govern('App\\API\\Modules\\finance\\FinanceAPI', 'finance', 'finance.api');
        self::govern('App\\API\\Modules\\attendance\\AttendanceAPI', 'attendance', 'attendance.api');
        self::govern('App\\API\\Modules\\reports\\ReportsAPI', 'reports', 'reports.api');
        self::govern('App\\API\\Modules\\system\\SystemAPI', 'system', 'system.api');

        // Full governed inventory: every business-logic class controllers may

        // instantiate (controller-facing governed set). Controllers MUST obtain

        // these via BaseController::contract() — never bare "new X(...)".

        // Keep the canonical API-class aliases (academic/finance/attendance/

        // reports/system) defined first so aliasMap() keeps them stable.


        self::govern('App\API\Modules\auth\AuthAPI', 'auth', 'auth.authapi');
        self::govern('App\API\Modules\admission\StudentAdmissionWorkflow', 'admission', 'admission.studentadmissionworkflow');
        self::govern('App\API\Modules\admission\AdmissionAdminManager', 'admission', 'admission.admissionadminmanager');
        self::govern('App\API\Modules\admission\AdmissionPolicy', 'admission', 'admission.admissionpolicy');
        self::govern('App\API\Modules\students\StudentPromotionService', 'students', 'students.studentpromotionservice');
        self::govern('App\API\Modules\students\AwardCertificateService', 'students', 'students.awardcertificateservice');
        self::govern('App\API\Modules\students\StudentsAPI', 'students', 'students.studentsapi');
        self::govern('App\API\Modules\students\StudentProfileManager', 'students', 'students.studentprofilemanager');
        self::govern('App\API\Modules\students\FamilyGroupsManager', 'students', 'students.familygroupsmanager');
        self::govern('App\API\Modules\students\StudentIDCardService', 'students', 'students.studentidcardservice');
        self::govern('App\API\Modules\students\StudentTransferService', 'students', 'students.studenttransferservice');
        self::govern('App\API\Modules\students\StudentInsightsService', 'students', 'students.studentinsightsservice');
        self::govern('App\API\Modules\students\StudentService', 'students', 'students.studentservice');
        self::govern('App\API\Modules\students\StudentLeadershipService', 'students', 'students.studentleadershipservice');
        self::govern('App\API\Modules\students\StudentParentService', 'students', 'students.studentparentservice');
        self::govern('App\API\Modules\students\StudentIDCardGenerator', 'students', 'students.studentidcardgenerator');
        self::govern('App\API\Modules\schedules\SchedulesAPI', 'schedules', 'schedules.schedulesapi');
        self::govern('App\API\Modules\health\HealthAPI', 'health', 'health.healthapi');
        self::govern('App\API\Modules\boarding\BoardingManager', 'boarding', 'boarding.boardingmanager');
        self::govern('App\API\Modules\inventory\UniformSalesManager', 'inventory', 'inventory.uniformsalesmanager');
        self::govern('App\API\Modules\inventory\InventoryAPI', 'inventory', 'inventory.inventoryapi');
        self::govern('App\API\Modules\activities\SportsManager', 'activities', 'activities.sportsmanager');
        self::govern('App\API\Modules\activities\ActivitiesAPI', 'activities', 'activities.activitiesapi');
        self::govern('App\API\Modules\reports\MealReportManager', 'reports', 'reports.mealreportmanager');
        self::govern('App\API\Modules\counseling\CounselingAPI', 'counseling', 'counseling.counselingapi');
        self::govern('App\API\Modules\maintenance\MaintenanceAPI', 'maintenance', 'maintenance.maintenanceapi');
        self::govern('App\API\Modules\website\WebsiteManager', 'website', 'website.websitemanager');
        self::govern('App\API\Modules\users\UsersAPI', 'users', 'users.usersapi');
        self::govern('App\API\Modules\users\RoleManager', 'users', 'users.rolemanager');
        self::govern('App\API\Modules\users\UserPermissionManager', 'users', 'users.userpermissionmanager');
        self::govern('App\API\Modules\users\PermissionManager', 'users', 'users.permissionmanager');
        self::govern('App\API\Modules\users\UserRoleManager', 'users', 'users.userrolemanager');
        self::govern('App\API\Modules\finance\FeeManager', 'finance', 'finance.feemanager');
        self::govern('App\API\Modules\academic\AcademicCalendarService', 'academic', 'academic.academiccalendarservice');
        self::govern('App\API\Modules\communications\CommunicationsManager', 'communications', 'communications.communicationsmanager');
        self::govern('App\API\Modules\communications\templates\TemplateLoader', 'communications', 'communications.templateloader');
        self::govern('App\API\Modules\system\SystemConfigAPI', 'system', 'system.systemconfigapi');
        self::govern('App\API\Modules\system\DashboardRegistryManager', 'system', 'system.dashboardregistrymanager');
        self::govern('App\API\Modules\system\SystemAdminManager', 'system', 'system.systemadminmanager');
        self::govern('App\API\Modules\system\MediaManager', 'system', 'system.mediamanager');
        self::govern('App\API\Modules\academic\AcademicYearManager', 'academic', 'academic.academicyearmanager');
        self::govern('App\API\Modules\academic\AcademicManager', 'academic', 'academic.academicmanager');
        self::govern('App\API\Modules\academic\AcademicCurriculumService', 'academic', 'academic.academiccurriculumservice');
        self::govern('App\API\Modules\academic\AcademicReportService', 'academic', 'academic.academicreportservice');
        self::govern('App\API\Modules\academic\AcademicYearService', 'academic', 'academic.academicyearservice');
        self::govern('App\API\Modules\academic\AcademicExamService', 'academic', 'academic.academicexamservice');
        self::govern('App\API\Modules\academic\AcademicCohortProjectionService', 'academic', 'academic.academiccohortprojectionservice');
        self::govern('App\API\Modules\chaplaincy\ChaplaincyAPI', 'chaplaincy', 'chaplaincy.chaplaincyapi');
        self::govern('App\API\Modules\library\LibraryAPI', 'library', 'library.libraryapi');
        self::govern('App\API\Modules\payments\PaymentsAPI', 'payments', 'payments.paymentsapi');
        self::govern('App\API\Modules\transport\StudentTransportEntitlementManager', 'transport', 'transport.studenttransportentitlementmanager');
        self::govern('App\API\Modules\transport\TransportAPI', 'transport', 'transport.transportapi');
        self::govern('App\API\Modules\attendance\AttendancePermissionService', 'attendance', 'attendance.attendancepermissionservice');
        self::govern('App\API\Modules\attendance\AttendanceStaffService', 'attendance', 'attendance.attendancestaffservice');
        self::govern('App\API\Modules\attendance\AttendanceManager', 'attendance', 'attendance.attendancemanager');
        self::govern('App\API\Modules\attendance\AttendanceStudentService', 'attendance', 'attendance.attendancestudentservice');
        self::govern('App\API\Modules\parent\ParentPortalManager', 'parent', 'parent.parentportalmanager');
        self::govern('App\API\Modules\finance\ExpenseManager', 'finance', 'finance.expensemanager');
        self::govern('App\API\Modules\finance\TransportBillingManager', 'finance', 'finance.transportbillingmanager');
        self::govern('App\API\Modules\finance\FinanceService', 'finance', 'finance.financeservice');
        self::govern('App\API\Modules\finance\AllowanceTemplateAPI', 'finance', 'finance.allowancetemplateapi');
        self::govern('App\API\Modules\finance\PaymentReconciliationAPI', 'finance', 'finance.paymentreconciliationapi');
        self::govern('App\API\Modules\staff\StaffLeaveManager', 'staff', 'staff.staffleavemanager');
        self::govern('App\API\Modules\staff\StaffAPI', 'staff', 'staff.staffapi');
        self::govern('App\API\Modules\staff\StaffPayrollManager', 'staff', 'staff.staffpayrollmanager');
        self::govern('App\API\Modules\staff\StaffOnboardingManager', 'staff', 'staff.staffonboardingmanager');
        self::govern('App\API\Modules\staff\StaffIDCardGenerator', 'staff', 'staff.staffidcardgenerator');
        self::govern('App\API\Modules\staff\StaffPerformanceManager', 'staff', 'staff.staffperformancemanager');
        self::govern('App\API\Modules\communications\CommunicationsAPI', 'communications', 'communications.communicationsapi');
        self::govern('App\API\Modules\communications\StaffMeetingManager', 'communications', 'communications.staffmeetingmanager');
        self::govern('App\API\Modules\students\PromotionManager', 'students', 'students.promotionmanager');
        self::govern('App\API\Modules\students\PortfolioManager', 'students', 'students.portfoliomanager');
        self::govern('App\API\Modules\Import\DataImporter', 'import', 'import.dataimporter');
        self::govern('App\API\Services\auth\DeviceSessionManager', 'auth', 'auth.devicesessionmanager');
        self::govern('App\API\Services\InternTeacherAnalyticsService', '', 'internteacheranalyticsservice');
        self::govern('App\API\Services\TeacherCurriculumScopeService', '', 'teachercurriculumscopeservice');
        self::govern('App\API\Services\catalog\CatalogStockService', 'catalog', 'catalog.catalogstockservice');
        self::govern('App\API\Services\catalog\CatalogCommerceService', 'catalog', 'catalog.catalogcommerceservice');
        self::govern('App\API\Services\TeacherAnalyticsService', '', 'teacheranalyticsservice');
        self::govern('App\API\Services\StaffRecordsService', '', 'staffrecordsservice');
        self::govern('App\API\Services\CurriculumProposalService', '', 'curriculumproposalservice');
        self::govern('App\API\Services\OperatingModeService', '', 'operatingmodeservice');
        self::govern('App\API\Services\SchoolAdminAnalyticsService', '', 'schooladminanalyticsservice');
        self::govern('App\API\Services\UploadService', '', 'uploadservice');
        self::govern('App\API\Services\AttendanceRegisterService', '', 'attendanceregisterservice');
        self::govern('App\API\Services\FinanceCrudService', '', 'financecrudservice');
        self::govern('App\API\Services\IpAccessControlService', '', 'ipaccesscontrolservice');
        self::govern('App\API\Services\StaffAppointmentsService', '', 'staffappointmentsservice');
        self::govern('App\API\Services\StaffMigrationService', '', 'staffmigrationservice');
        self::govern('App\API\Services\SystemAdministrationService', '', 'systemadministrationservice');
        self::govern('App\API\Services\AcademicContextService', '', 'academiccontextservice');
        self::govern('App\API\Services\DirectorAnalyticsService', '', 'directoranalyticsservice');
        self::govern('App\API\Services\DeputyAcademicAnalyticsService', '', 'deputyacademicanalyticsservice');
        self::govern('App\API\Services\OTPDeliveryService', '', 'otpdeliveryservice');
        self::govern('App\API\Services\EnvironmentPhaseService', '', 'environmentphaseservice');
        self::govern('App\API\Services\HeadteacherAnalyticsService', '', 'headteacheranalyticsservice');
        self::govern('App\API\Services\payments\SupplierDisbursementService', 'payments', 'payments.supplierdisbursementservice');
        self::govern('App\API\Services\payments\TransportPaymentService', 'payments', 'payments.transportpaymentservice');
        self::govern('App\API\Services\payments\UniformPaymentService', 'payments', 'payments.uniformpaymentservice');
        self::govern('App\API\Services\payments\PaymentRoutingService', 'payments', 'payments.paymentroutingservice');
        self::govern('App\API\Services\payments\StudentFundTransferService', 'payments', 'payments.studentfundtransferservice');
        self::govern('App\API\Services\payments\StatutoryRemittanceService', 'payments', 'payments.statutoryremittanceservice');
        self::govern('App\API\Services\payments\KcbTransferReconciliationService', 'payments', 'payments.kcbtransferreconciliationservice');
        self::govern('App\API\Services\payments\KcbFundsTransferService', 'payments', 'payments.kcbfundstransferservice');
        self::govern('App\API\Services\payments\UniformCatalogService', 'payments', 'payments.uniformcatalogservice');
        self::govern('App\API\Services\payments\ParentRefundService', 'payments', 'payments.parentrefundservice');
        self::govern('App\API\Services\TeacherScopeService', '', 'teacherscopeservice');
        self::govern('App\API\Services\DelegationService', '', 'delegationservice');
        self::govern('App\API\Services\TestDataManagementService', '', 'testdatamanagementservice');
        self::govern('App\API\Services\SubjectTeacherAnalyticsService', '', 'subjectteacheranalyticsservice');
        self::govern('App\API\Services\ReportCardReleaseService', '', 'reportcardreleaseservice');
        self::govern('App\API\Services\DeputyDisciplineAnalyticsService', '', 'deputydisciplineanalyticsservice');
        self::govern('App\API\Services\StaffDomainAccessService', '', 'staffdomainaccessservice');
        self::govern('App\API\Services\StaffGateAttendanceService', '', 'staffgateattendanceservice');
        self::govern('App\API\Services\CommunicationOutboxService', '', 'communicationoutboxservice');
        self::govern('App\API\Services\StaffLifecycleService', '', 'stafflifecycleservice');
        self::govern('App\API\Services\AnalyticsExportAuditService', '', 'analyticsexportauditservice');
        self::govern('App\API\Services\PasskeyService', '', 'passkeyservice');
        self::govern('App\API\Services\AuthSessionService', '', 'authsessionservice');
        self::govern('App\API\Services\TwoFactorService', '', 'twofactorservice');
        self::govern('App\API\Services\NotificationService', '', 'notificationservice');
        self::govern('App\API\Services\SystemAdminAnalyticsService', '', 'systemadminanalyticsservice');
        self::govern('App\API\Services\FinancialReconciliationService', '', 'financialreconciliationservice');
        self::govern('App\API\Services\ClassTeacherAnalyticsService', '', 'classteacheranalyticsservice');
        self::govern('App\API\Services\StaffTeachingAssignmentService', '', 'staffteachingassignmentservice');

        self::register([
            'id' => 'system.info',
            'summary' => 'Read-only application and environment status (contract-layer mirror of system.info).',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'handler' => static function (array $params, array $ctx): array {
                return [
                    'app' => 'Kingsway Preparatory School',
                    'layer' => 'ServiceContractBroker (gRPC-style contract layer)',
                    'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
                    'database' => 'KingsWayAcademy',
                    'authenticated_user' => (int) ($ctx['user_id'] ?? 0),
                ];
            },
        ]);

        self::register([
            'id' => 'academic.api',
            'summary' => 'Grants the AcademicAPI governed service through the contract layer.',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\academic\\AcademicAPI',
            'method' => 'getTermTransitionContext',
        ]);

        self::register([
            'id' => 'finance.api',
            'summary' => 'Grants the FinanceAPI governed service through the contract layer.',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\finance\\FinanceAPI',
            'method' => 'getFinancialSummaryReport',
        ]);

        self::register([
            'id' => 'attendance.api',
            'summary' => 'Grants the AttendanceAPI governed service through the contract layer.',
            'auth' => 'permission:attendance.view',
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\attendance\\AttendanceAPI',
            'method' => 'getStaffAttendanceSummary',
        ]);

        self::register([
            'id' => 'system.api',
            'summary' => 'System Administrator: governed SystemAPI grant contract.',
            'auth' => 'role:System Administrator',
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\system\\SystemAPI',
            'method' => 'listAlbums',
        ]);

        self::register([
            'id' => 'admission.api.advance_after_payment',
            'summary' => 'Cross-module governed boundary: advance an admission application after a confirmed payment.',
            'auth' => null,
            'schema' => [
                'required' => ['application_id'],
                'additionalProperties' => false,
                'properties' => [
                    'application_id' => ['type' => 'int', 'min' => 1],
                ],
            ],
            'result' => [
                'required' => ['advanced', 'application_id'],
                'properties' => [
                    'advanced' => ['type' => 'bool'],
                    'application_id' => ['type' => 'int'],
                ],
            ],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\admission\\StudentAdmissionWorkflow',
            'method' => 'advanceApplicationAfterConfirmedPayment',
        ]);
    }

    /**
     * Govern a concrete FQCN so the accessor may grant it and the coverage
     * lint accepts it. Public so unit tests may register fixtures.
     */
    public static function govern(string $class, string $group, string $canonicalContract): void
    {
        self::boot();
        self::$governed[$class] = [
            'group' => $group,
            'contract' => $canonicalContract,
        ];
    }

    /**
     * Register a contract definition under the given id.
     *
     * @throws LogicException on duplicate or malformed id / auth / mode.
     */
    public static function register(array $def): void
    {
        self::boot();
        $id = (string) ($def['id'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9._]{1,120}$/', $id)) {
            throw new LogicException("Invalid contract id '{$id}'.");
        }
        if (isset(self::$registry[$id])) {
            throw new LogicException("Contract '{$id}' is already registered.");
        }

        $mode = (string) ($def['mode'] ?? 'sync');
        if (!in_array($mode, ['sync', 'async'], true)) {
            throw new LogicException("Contract '{$id}' has an invalid mode '{$mode}'.");
        }

        $auth = isset($def['auth']) ? (string) $def['auth'] : null;
        if ($auth !== null && !preg_match('/^(role:|permission:).{1,64}$/', $auth)) {
            throw new LogicException("Contract '{$id}' has an invalid auth descriptor.");
        }

        self::$registry[$id] = [
            '_summary' => (string) ($def['summary'] ?? ''),
            '_mode' => $mode,
            '_auth' => $auth,
            '_schema' => isset($def['schema']) && is_array($def['schema']) ? $def['schema'] : [],
            '_result' => isset($def['result']) && is_array($def['result']) ? $def['result'] : null,
            '_service' => (string) ($def['service'] ?? ''),
            '_method' => (string) ($def['method'] ?? ''),
            'handler' => $def['handler'] ?? null,
        ];
    }

    /**
     * Resolve a contract id to its definition, or null when unknown.
     */
    public static function resolve(string $id): ?array
    {
        self::boot();
        return self::$registry[$id] ?? null;
    }

    public static function has(string $id): bool
    {
        return self::resolve($id) !== null;
    }

    /**
     * Governed-service inventory: FQCN -> ['group','contract'].
     */
    public static function governed(): array
    {
        self::boot();
        return self::$governed;
    }

    public static function governedContains(string $fqcn): bool
    {
        self::boot();
        return isset(self::$governed[$fqcn]);
    }

    /**
     * Full governed FQCN list.
     */
    public static function governedClassNames(): array
    {
        return array_keys(self::governed());
    }

    /**
     * Contract catalogue grouped by subsystem, sorted for deterministic tests.
     */
    public static function methodsGrouped(): array
    {
        self::boot();
        $byGroup = [];
        foreach (self::$registry as $id => $def) {
            $group = explode('.', $id, 2)[0];
            $byGroup[$group][] = [
                'id' => $id,
                'summary' => (string) $def['_summary'],
                'mode' => (string) $def['_mode'],
                'auth' => $def['_auth'],
                'service' => (string) $def['_service'],
                'method' => (string) $def['_method'],
                'schema' => RpcSchemaValidator::describe((array) $def['_schema']),
            ];
        }
        ksort($byGroup);
        foreach ($byGroup as &$list) {
            usort($list, static function (array $a, array $b): int {
                return strcmp($a['id'], $b['id']);
            });
        }
        return $byGroup;
    }
}