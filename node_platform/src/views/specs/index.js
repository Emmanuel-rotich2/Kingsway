/**
 * Server-registered data operations.
 *
 * Add an operation only after its existing PHP endpoints, permissions, row
 * scopes, response limits, and invalidation behavior have been verified.
 * Node returns domain data; PHP pages and JS controllers own presentation.
 */
export const viewSpecs = new Map();

viewSpecs.set('students/id-cards', {
  view: 'students/id-cards',
  service: 'php',
  permissions: ['students_view'],
  queries: [
    { key: 'cards', endpoint: '/api/students/id-cards' },
    { key: 'meta', endpoint: '/api/students/id-card-meta' },
  ],
  aggregations: {},
});

// The existing BoardingController endpoints enforce their own permission and
// role checks. Keep the plan permission list empty so Node does not reject an
// operator who is admitted through PHP's established named-role fallback.
// The original bearer is forwarded to each source endpoint and PHP remains
// responsible for the final authorization decision.
viewSpecs.set('boarding/admin-overview', {
  view: 'boarding/admin-overview',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/boarding/stats' },
    { key: 'dormitories', endpoint: '/api/boarding/dormitories' },
    { key: 'activity', endpoint: '/api/boarding/activity' },
  ],
  aggregations: {},
});

// PHP enforces the operator's default student context and row visibility.
// Node gathers source transport pages into a complete authorized roster; all
// visible class/status filtering and pagination stay in the page controller.
viewSpecs.set('students/portfolio-roster', {
  view: 'students/portfolio-roster',
  service: 'php',
  permissions: [],
  queries: [
    {
      key: 'roster',
      endpoint: '/api/students/context-list',
      pagination: {
        collection: 'students',
        pageParam: 'page',
        limitParam: 'limit',
        pageSize: 100,
        maxPages: 50,
      },
    },
  ],
  aggregations: {},
});

// Inventory page reads remain PHP-authorized at each registered source
// endpoint. Node coordinates and bounds the read set; page controllers render
// the returned domain data into their existing PHP markup.
const activeInventoryItems = {
  key: 'items',
  endpoint: '/api/inventory/items-list',
  pagination: {
    collection: 'items',
    pageParam: 'page',
    limitParam: 'limit',
    pageSize: 100,
    maxPages: 100,
  },
};

viewSpecs.set('inventory/item-catalog', {
  view: 'inventory/item-catalog',
  service: 'php',
  permissions: [],
  queries: [activeInventoryItems],
  aggregations: {},
});

viewSpecs.set('inventory/manage-overview', {
  view: 'inventory/manage-overview',
  service: 'php',
  permissions: [],
  queries: [
    activeInventoryItems,
    { key: 'categories', endpoint: '/api/inventory/categories-list' },
    { key: 'locations', endpoint: '/api/inventory/locations-list' },
    { key: 'lowStock', endpoint: '/api/inventory/items-low-stock' },
    { key: 'valuation', endpoint: '/api/inventory/items-stock-valuation' },
    {
      key: 'requisitions',
      endpoint: '/api/inventory/requisitions-list',
      pagination: {
        collection: 'items',
        pageParam: 'page',
        limitParam: 'limit',
        pageSize: 100,
        maxPages: 100,
      },
    },
  ],
  aggregations: {},
});

viewSpecs.set('inventory/stock-register', {
  view: 'inventory/stock-register',
  service: 'php',
  permissions: [],
  queries: [
    activeInventoryItems,
    {
      key: 'movements',
      endpoint: '/api/inventory/movements-list',
      pagination: {
        collection: 'items',
        pageParam: 'page',
        limitParam: 'limit',
        pageSize: 100,
        maxPages: 100,
      },
    },
  ],
  aggregations: {},
});

viewSpecs.set('inventory/requisition-workspace', {
  view: 'inventory/requisition-workspace',
  service: 'php',
  permissions: [],
  queries: [
    activeInventoryItems,
    {
      key: 'requisitions',
      endpoint: '/api/inventory/requisitions-list',
      pagination: {
        collection: 'items',
        pageParam: 'page',
        limitParam: 'limit',
        pageSize: 100,
        maxPages: 100,
      },
    },
  ],
  aggregations: {},
});

// Finance page initial reads are independent. PHP still authorizes every
// endpoint; Node returns the source JSON without applying UI filters or page
// state. Mutations and generated documents continue through PHP workflows.
viewSpecs.set('finance/management-overview', {
  view: 'finance/management-overview',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'payments', endpoint: '/api/finance', staticParams: { type: 'payments' } },
    { key: 'payrolls', endpoint: '/api/finance/payrolls-list' },
    { key: 'fees', endpoint: '/api/finance/fees-annual-summary' },
  ],
  aggregations: {},
});

// Academic manager's initial summary and year cards share these three
// independent, read-only PHP sources. Term/class/subject editors remain page
// controller calls because their later requests depend on selected records.
viewSpecs.set('academic/manager-overview', {
  view: 'academic/manager-overview',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas/list' },
    { key: 'years', endpoint: '/api/academic/years/list' },
  ],
  aggregations: {},
});

// Class streams reference rows are independent, context-free reads PHP already
// authorizes. The page keeps class/status search and filtering client-side.
viewSpecs.set('academic/class-streams', {
  view: 'academic/class-streams',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'teachers', endpoint: '/api/academic/teachers-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Class teacher assignment reference rows are three independent, context-free
// reads currently issued sequentially by the page; compose them in one call.
viewSpecs.set('academic/class-teacher-references', {
  view: 'academic/class-teacher-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'teachers', endpoint: '/api/staff/teachers' },
    { key: 'years', endpoint: '/api/academic/years/list' },
  ],
  aggregations: {},
});

// Assessment rubric reference rows are independent, context-free reads issued
// in parallel by the page; rubric rows themselves stay a filtered direct read.
viewSpecs.set('academic/assessment-rubrics-reference', {
  view: 'academic/assessment-rubrics-reference',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classifications', endpoint: '/api/academic/assessment-classifications' },
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas' },
    { key: 'tools', endpoint: '/api/academic/assessment-tools' },
  ],
  aggregations: {},
});

// Exam moderation reference rows (classes, subjects) are independent,
// context-free reads; pending-moderation rows stay a filtered direct read.
viewSpecs.set('academic/moderation-references', {
  view: 'academic/moderation-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'subjects', endpoint: '/api/academic/subjects' },
  ],
  aggregations: {},
});

// Formative assessment dropdown references (terms, subjects, types) are
// context-free; classes stay a DataStore offline-first read on the page.
viewSpecs.set('academic/formative-assessment-reference', {
  view: 'academic/formative-assessment-reference',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas-list' },
    { key: 'types', endpoint: '/api/academic/assessment-types', staticParams: { filter: 'formative' } },
  ],
  aggregations: {},
});

// Assessment overview dropdown references (terms, types) are context-free;
// classes stay a DataStore offline-first read and assessments stay filtered.
viewSpecs.set('academic/assessment-overview-reference', {
  view: 'academic/assessment-overview-reference',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'types', endpoint: '/api/academic/assessment-types' },
  ],
  aggregations: {},
});

// CBC curriculum reference rows (strands, core competencies, academic years)
// are unconditional, context-free reads. learning-areas and curriculum-summary
// carry a manager-only include_inactive flag, so they stay direct.
viewSpecs.set('academic/curriculum-reference', {
  view: 'academic/curriculum-reference',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'strands', endpoint: '/api/academic/strands' },
    { key: 'competencies', endpoint: '/api/academic/core-competencies' },
    { key: 'years', endpoint: '/api/academic/years/list', staticParams: { limit: '100' } },
  ],
  aggregations: {},
});

// Add Results dropdown references (years, classes, learning areas, terms,
// assessment types) are all unconditional, context-free reads.
viewSpecs.set('academic/add-results-references', {
  view: 'academic/add-results-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas/list' },
    { key: 'terms', endpoint: '/api/academic/terms' },
    { key: 'assessmentTypes', endpoint: '/api/academic/assessment-types' },
  ],
  aggregations: {},
});

viewSpecs.set('academic/enter-results-references', {
  view: 'academic/enter-results-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas/list' },
    { key: 'years', endpoint: '/api/academic/years/list' },
  ],
  aggregations: {},
});

// Class results filter references (years, terms, subjects) are context-free.
// The teacher-scoped class list and the filtered performance-overview stay
// direct, because both depend on the signed-in teacher / selected filters.
viewSpecs.set('academic/class-results-references', {
  view: 'academic/class-results-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'subjects', endpoint: '/api/academic/subjects-list' },
  ],
  aggregations: {},
});

// Subject results filter references (years, terms, classes) are context-free.
// The teacher-scoped subject list and the filtered results feed stay direct.
viewSpecs.set('academic/subject-results-references', {
  view: 'academic/subject-results-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
  ],
  aggregations: {},
});

// CBC curriculum editor reference rows (learning areas, strands) are
// context-free. The teacher's curriculum assignment scope stays a direct read.
viewSpecs.set('academic/curriculum-cbc-reference', {
  view: 'academic/curriculum-cbc-reference',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas' },
    { key: 'strands', endpoint: '/api/academic/strands' },
  ],
  aggregations: {},
});

// Subject schemes reference rows (academic years, subjects) are context-free;
// the teacher-scoped scheme list stays direct.
viewSpecs.set('academic/subject-schemes-references', {
  view: 'academic/subject-schemes-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'subjects', endpoint: '/api/academic/subjects' },
  ],
  aggregations: {},
});

// Results studio reference data (terms, classes, exam periods, learning areas,
// grading systems, assessment classifications) are all context-free.
viewSpecs.set('academic/view-results-references', {
  view: 'academic/view-results-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list', staticParams: { limit: '200' } },
    { key: 'examPeriods', endpoint: '/api/academic/exam-periods' },
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas/list' },
    { key: 'gradingSystems', endpoint: '/api/academic/grading-systems' },
    { key: 'classifications', endpoint: '/api/academic/assessment-classifications' },
  ],
  aggregations: {},
});

// Exam setup reference data (years, terms, classes, learning areas) are
// context-free; the filtered exam list stays direct.
viewSpecs.set('academic/exam-setup-references', {
  view: 'academic/exam-setup-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms/list' },
    { key: 'classes', endpoint: '/api/academic/classes/list' },
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas/list' },
  ],
  aggregations: {},
});

// Timetable filter references (classes, teachers, rooms) are context-free.
// Leadership/admin only; teaching staff skip the filters entirely.
viewSpecs.set('academic/timetable-references', {
  view: 'academic/timetable-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'teachers', endpoint: '/api/academic/teachers-list' },
    { key: 'rooms', endpoint: '/api/schedules/rooms-get' },
  ],
  aggregations: {},
});

// Academic reports reference data (years, classes, learning areas) are
// context-free; the year-dependent term list stays direct.
viewSpecs.set('academic/academic-reports-references', {
  view: 'academic/academic-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'learningAreas', endpoint: '/api/academic/learning-areas/list' },
  ],
  aggregations: {},
});

// Timetable management reference data (classes, learning areas, terms, rooms)
// is context-free. The teacher-directory read stays direct because it is
// permission-gated per user, as does the filtered timetable matrix itself.
viewSpecs.set('academic/manage-timetable-references', {
  view: 'academic/manage-timetable-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'rooms', endpoint: '/api/schedules/rooms-get' },
  ],
  aggregations: {},
});

// AI advisory review feeds are two fixed-scope reads of the same endpoint;
// approval and queueing remain PHP domain endpoints.
viewSpecs.set('academic/ai-academic-reviews-references', {
  view: 'academic/ai-academic-reviews-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'own', endpoint: '/api/academic/ai-scheme-drafts', staticParams: { scope: 'own' } },
    { key: 'review', endpoint: '/api/academic/ai-scheme-drafts', staticParams: { scope: 'review' } },
  ],
  aggregations: {},
});

viewSpecs.set('academic/ai-assessment-draft-references', {
  view: 'academic/ai-assessment-draft-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'own', endpoint: '/api/academic/ai-assessment-drafts', staticParams: { scope: 'own' } },
    { key: 'review', endpoint: '/api/academic/ai-assessment-drafts', staticParams: { scope: 'review' } },
  ],
  aggregations: {},
});

// Exam timetable draft references are context-free; the draft list itself is
// reloaded after every write and the year-scoped stream list stays direct.
viewSpecs.set('academic/exam-timetable-drafts-references', {
  view: 'academic/exam-timetable-drafts-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'types', endpoint: '/api/academic/assessment-types', staticParams: { filter: 'summative' } },
    { key: 'staff', endpoint: '/api/staff/staff', staticParams: { limit: '1000' } },
  ],
  aggregations: {},
});

// Performance trend filter references are context-free; the analysis reads are
// parameterised by the selected year/class/subject and stay direct.
viewSpecs.set('academic/performance-trends-references', {
  view: 'academic/performance-trends-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas/list' },
  ],
  aggregations: {},
});

// Teacher performance review references are context-free; filters are applied
// client-side in the page controller.
viewSpecs.set('academic/teacher-performance-reviews-references', {
  view: 'academic/teacher-performance-reviews-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'reviews', endpoint: '/api/staff/performance-reviews' },
    { key: 'teachers', endpoint: '/api/staff/teachers' },
    { key: 'subjects', endpoint: '/api/academic/learning-areas/list' },
  ],
  aggregations: {},
});

// Admissions startup reads are coordinated in parallel. Stage decisions,
// uploads, payments and enrollment writes remain on PHP domain endpoints.
viewSpecs.set('admissions/workspace', {
  view: 'admissions/workspace',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'policy', endpoint: '/api/admission/policy' },
    { key: 'stageMatrix', endpoint: '/api/admission/stage-matrix' },
    { key: 'queues', endpoint: '/api/admission/queues' },
    { key: 'placementClasses', endpoint: '/api/admission/placement-classes' },
    { key: 'parents', endpoint: '/api/students/parents/list' },
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'openTerms', endpoint: '/api/admission/open-terms' },
  ],
  aggregations: {},
});

// Student form lookup data can be read concurrently. The paginated student
// table itself remains loaded and filtered by the page's existing flow; this
// operation does not accept UI filter or pagination parameters.
viewSpecs.set('students/management-reference-data', {
  view: 'students/management-reference-data',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'importContext', endpoint: '/api/students/import-context' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'studentTypes', endpoint: '/api/finance/student-types-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'parents', endpoint: '/api/students/parents/list' },
  ],
  aggregations: {},
});

// Fee Structure Viewer: static dropdowns for years, levels, student types, terms.
// The paginated fee table, review queue, and matrix are filter-dependent and stay direct.
viewSpecs.set('finance/fee-structure-viewer-references', {
  view: 'finance/fee-structure-viewer-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'levels', endpoint: '/api/academic/levels-list' },
    { key: 'studentTypes', endpoint: '/api/finance/student-types-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Manage Admissions Payments: static years list. The payment queue is filter-scoped and stays direct.
viewSpecs.set('finance/manage-admissions-payments-references', {
  view: 'finance/manage-admissions-payments-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
  ],
  aggregations: {},
});

// Manage Boarding Fee Structure: static reference data for year/level/type/term/class.
viewSpecs.set('finance/manage-boarding-fee-structure-references', {
  view: 'finance/manage-boarding-fee-structure-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'levels', endpoint: '/api/academic/levels-list' },
    { key: 'studentTypes', endpoint: '/api/finance/student-types-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
  ],
  aggregations: {},
});

// Payment Reconciliation: static lookups for students, fund sources, uniform sales.
// The reconciliation case list is filter-scoped and stays direct.
viewSpecs.set('finance/payment-reconciliation-references', {
  view: 'finance/payment-reconciliation-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '1000', status: 'active' } },
    { key: 'fundSources', endpoint: '/api/finance/student-fund-sources' },
    { key: 'uniformSales', endpoint: '/api/inventory/list-uniform-sales', staticParams: { limit: '1000' } },
  ],
  aggregations: {},
});

// Payment Reports: static stats, trends, and revenue sources.
viewSpecs.set('finance/payment-reports-references', {
  view: 'finance/payment-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/payments/stats' },
    { key: 'trends', endpoint: '/api/payments/collection-trends' },
    { key: 'sources', endpoint: '/api/payments/revenue-sources' },
  ],
  aggregations: {},
});

// Student Fees: static classes, years, and terms for the current year.
// The paginated ledger is filter-scoped and stays direct.
viewSpecs.set('finance/student-fees-references', {
  view: 'finance/student-fees-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Supplier Payments: static payables list and supplier financial accounts.
// The page loads both at startup (payables + filtered financial accounts).
viewSpecs.set('finance/supplier-payments-references', {
  view: 'finance/supplier-payments-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'payables', endpoint: '/api/finance/supplier-payables' },
    { key: 'accounts', endpoint: '/api/finance/financial-accounts' },
  ],
  aggregations: {},
});

// Transport Fees: static routes, stops, students, and financial accounts.
viewSpecs.set('finance/transport-fees-references', {
  view: 'finance/transport-fees-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'routes', endpoint: '/api/transport/all-routes' },
    { key: 'stops', endpoint: '/api/transport/all-stops' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
    { key: 'accounts', endpoint: '/api/finance/financial-accounts' },
  ],
  aggregations: {},
});

// Class Placement: static placement-classes reference data.
viewSpecs.set('admissions/class-placement-references', {
  view: 'admissions/class-placement-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
  ],
  aggregations: {},
});

// Inventory Reports: static stock levels, requisitions summary, adjustment logs.
viewSpecs.set('inventory/inventory-reports-references', {
  view: 'inventory/inventory-reports-references',
service: 'php',
  permissions: [],
  queries: [
    { key: 'stock', endpoint: '/api/reports/inventory-stock-levels' },
    { key: 'requisitions', endpoint: '/api/reports/requisitions-summary' },
    { key: 'adjustments', endpoint: '/api/reports/inventory-adjustment-logs' },
  ],
  aggregations: {},
});

// Requisitions: static reference data for the requisitions list page.
viewSpecs.set('inventory/requisitions-references', {
  view: 'inventory/requisitions-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'requisitions', endpoint: '/api/inventory/requisitions' },
  ],
  aggregations: {},
});

// Stock Movements: static items and movements reference data.
viewSpecs.set('inventory/stock-references', {
  view: 'inventory/stock-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'items', endpoint: '/api/inventory/assets' },
    { key: 'movements', endpoint: '/api/inventory/stock' },
  ],
  aggregations: {},
});

// Stock Reports: static valuation and usage reference data.
viewSpecs.set('inventory/stock-reports-references', {
  view: 'inventory/stock-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'valuation', endpoint: '/api/inventory/items-stock-valuation' },
    { key: 'usage', endpoint: '/api/reports/inventory-usage-rates' },
  ],
  aggregations: {},
});

// AI Inventory Review: static AI replenishment reviews.
viewSpecs.set('inventory/ai-inventory-review-references', {
  view: 'inventory/ai-inventory-review-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'reviews', endpoint: '/api/inventory/ai-replenishment-reviews', staticParams: { scope: 'own|review' } },
  ],
  aggregations: {},
});

// Food Low Stock: static food stock items at low/reorder/out-of-stock levels.
viewSpecs.set('inventory/food-low-stock-references', {
  view: 'inventory/food-low-stock-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'items', endpoint: '/api/catering/food-stock', staticParams: { low_stock: '1', limit: '200' } },
  ],
  aggregations: {},
});

// Food Stock Levels: static food stock levels for all items.
viewSpecs.set('inventory/food-stock-levels-references', {
  view: 'inventory/food-stock-levels-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'items', endpoint: '/api/catering/food-stock', staticParams: { limit: '200' } },
  ],
  aggregations: {},
});

// Low Stock Alerts: static items below reorder level.
viewSpecs.set('inventory/low-stock-alerts-references', {
  view: 'inventory/low-stock-alerts-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'items', endpoint: '/api/inventory/items-low-stock' },
  ],
  aggregations: {},
});

// Uniform Stock Intake: static suppliers, purchase orders, products for stock intake.
viewSpecs.set('inventory/uniform-stock-intake-references', {
  view: 'inventory/uniform-stock-intake-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'suppliers', endpoint: '/api/commerce/stock-intake-options', staticParams: { include: 'suppliers' } },
    { key: 'purchaseOrders', endpoint: '/api/commerce/stock-intake-options', staticParams: { include: 'purchase_orders' } },
    { key: 'products', endpoint: '/api/commerce/stock-intake-options', staticParams: { include: 'products' } },
  ],
  aggregations: {},
});

// Food Consumption: static food consumption trends reference data.
viewSpecs.set('catering/food-consumption-references', {
  view: 'catering/food-consumption-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'trends', endpoint: '/api/reports/food-consumption-trends' },
  ],
  aggregations: {},
});

// Food Orders: static suppliers, categories for the orders list page.
viewSpecs.set('catering/food-orders-references', {
  view: 'catering/food-orders-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'suppliers', endpoint: '/api/inventory/suppliers-list' },
    { key: 'categories', endpoint: '/api/inventory/categories-list' },
  ],
  aggregations: {},
});

// Food Store: static categories, locations, suppliers for the food store page.
viewSpecs.set('catering/food-store-references', {
  view: 'catering/food-store-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'categories', endpoint: '/api/inventory/categories-list' },
    { key: 'locations', endpoint: '/api/inventory/locations-list' },
    { key: 'suppliers', endpoint: '/api/inventory/suppliers-list' },
  ],
  aggregations: {},
});

// Meal Statistics: static meal statistics reference data.
viewSpecs.set('catering/meal-statistics-references', {
  view: 'catering/meal-statistics-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/inventory/items-stock-valuation' },
  ],
  aggregations: {},
});

// Menu Planning: static menu planning reference data.
viewSpecs.set('catering/menu-planning-references', {
  view: 'catering/menu-planning-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'menus', endpoint: '/api/catering/menu' },
  ],
  aggregations: {},
});

// Today's Menu: static today's menu reference data.
viewSpecs.set('catering/todays-menu-references', {
  view: 'catering/todays-menu-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'menu', endpoint: '/api/catering/menu' },
  ],
  aggregations: {},
});

// Weekly Menu: static weekly menu reference data.
viewSpecs.set('catering/weekly-menu-references', {
  view: 'catering/weekly-menu-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'weeklyMenu', endpoint: '/api/catering/menu' },
  ],
  aggregations: {},
});

// Transport Admin: static routes, vehicles, drivers, stops reference data.
viewSpecs.set('transport/transport-admin-references', {
  view: 'transport/transport-admin-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'routes', endpoint: '/api/transport/all-routes' },
    { key: 'vehicles', endpoint: '/api/transport/all-vehicles' },
    { key: 'drivers', endpoint: '/api/transport/all-drivers' },
    { key: 'stops', endpoint: '/api/transport/all-stops' },
  ],
  aggregations: {},
});

// Transport Dashboard: static routes, drivers, vehicles, stops for passenger list.
viewSpecs.set('transport/transport-dashboard-references', {
  view: 'transport/transport-dashboard-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'routes', endpoint: '/api/transport/all-routes' },
    { key: 'drivers', endpoint: '/api/transport/all-drivers' },
    { key: 'vehicles', endpoint: '/api/transport/all-vehicles' },
    { key: 'stops', endpoint: '/api/transport/all-stops' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Transport Operator: static driver manifest reference data.
viewSpecs.set('transport/transport-operator-references', {
  view: 'transport/transport-operator-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'manifest', endpoint: '/api/transport/driver-manifest', staticParams: { date: '', trip_session: '' } },
  ],
  aggregations: {},
});

// Transport Passengers: static routes, vehicles, classes, streams for passenger list.
viewSpecs.set('transport/transport-passengers-references', {
  view: 'transport/transport-passengers-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'routes', endpoint: '/api/transport/all-routes' },
    { key: 'vehicles', endpoint: '/api/transport/all-vehicles' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Academic Applications: static placement-classes and placement-tests reference data.
viewSpecs.set('admissions/academic-applications-references', {
  view: 'admissions/academic-applications-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
    { key: 'placementTests', endpoint: '/api/admission/placement-tests' },
  ],
  aggregations: {},
});

// Headteacher Applications: static classes + filtered queues via generic pass-through.
viewSpecs.set('admissions/headteacher-applications-references', {
  view: 'admissions/headteacher-applications-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
  ],
  aggregations: {},
});

// Admission Decisions: static classes + filtered queues via generic pass-through.
viewSpecs.set('admissions/admission-decisions-references', {
  view: 'admissions/admission-decisions-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
  ],
  aggregations: {},
});

// Pending Admission Approvals: static classes + filtered queues via generic pass-through.
viewSpecs.set('admissions/pending-approvals-references', {
  view: 'admissions/pending-approvals-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
  ],
  aggregations: {},
});

// Admissions Workspace: static classes + queues via generic pass-through.
viewSpecs.set('admissions/workspace-references', {
  view: 'admissions/workspace-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/admission/placement-classes' },
  ],
  aggregations: {},
});

// Boarding Reports: static stats and dormitory occupancy.
viewSpecs.set('boarding/boarding-reports-references', {
  view: 'boarding/boarding-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/boarding/stats' },
    { key: 'occupancy', endpoint: '/api/boarding/occupancy' },
  ],
  aggregations: {},
});

// Boarding Roll Call: static dormitories and sessions for the roll call page.
viewSpecs.set('boarding/boarding-rollcall-references', {
  view: 'boarding/boarding-rollcall-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'dormitories', endpoint: '/api/attendance/dormitories' },
    { key: 'sessions', endpoint: '/api/attendance/sessions', staticParams: { type: 'boarding' } },
  ],
  aggregations: {},
});

// Boarding Students: static metadata for the boarding dashboard.
viewSpecs.set('boarding/boarding-students-references', {
  view: 'boarding/boarding-students-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/students/boarding-meta', staticParams: { include: 'academic_years' } },
    { key: 'classes', endpoint: '/api/students/boarding-meta', staticParams: { include: 'classes' } },
{ key: 'streams', endpoint: '/api/students/boarding-meta', staticParams: { include: 'streams' } },
    { key: 'dormitories', endpoint: '/api/students/boarding-meta', staticParams: { include: 'dormitories' } },
  ],
  aggregations: {}
});

// Student Fund Transfers: static sources and students for transfer form.
viewSpecs.set('students/student-fund-transfers-references', {
  view: 'students/student-fund-transfers-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'sources', endpoint: '/api/finance/student-fund-sources' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '1000', status: 'active' } },
  ],
  aggregations: {},
});

// Student Growth: static terms and classes for the growth dashboard.
viewSpecs.set('students/student-growth-references', {
  view: 'students/student-growth-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
  ],
  aggregations: {},
});

// Student Health: static academic years, classes, streams for the health dashboard.
viewSpecs.set('students/student-health-references', {
  view: 'students/student-health-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Student Leadership: static houses, positions, awards for the leadership page.
viewSpecs.set('students/student-leadership-references', {
  view: 'students/student-leadership-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'houses', endpoint: '/api/students/houses' },
    { key: 'positions', endpoint: '/api/students/leadership/positions' },
    { key: 'awards', endpoint: '/api/students/awards' },
    { key: 'categories', endpoint: '/api/students/awards/categories' },
    { key: 'types', endpoint: '/api/students/awards/types' },
  ],
  aggregations: {},
});

// Student Performance: static academic years, terms, classes, streams for the performance page.
viewSpecs.set('students/student-performance-references', {
  view: 'students/student-performance-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Student Progress Reports: static academic years, terms for the progress reports page.
viewSpecs.set('students/student-progress-reports-references', {
  view: 'students/student-progress-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'years', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
  ],
  aggregations: {},
});

// Student Promotion: static academic years, classes, streams for the promotion page.
viewSpecs.set('students/student-promotion-references', {
  view: 'students/student-promotion-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
  ],
  aggregations: {},
});

// Student Route Assignment: static routes and stops for the route assignment page.
viewSpecs.set('students/student-route-assignment-references', {
  view: 'students/student-route-assignment-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'routes', endpoint: '/api/transport/all-routes' },
    { key: 'stops', endpoint: '/api/transport/all-stops' },
  ],
  aggregations: {},
});

// Student Subject Performance: static academic years, terms, subjects for the performance page.
viewSpecs.set('students/student-subject-performance-references', {
  view: 'students/student-subject-performance-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'subjects', endpoint: '/api/academic/subjects-list' },
  ],
  aggregations: {},
});

// Student Welfare: static academic years, terms, classes, streams, staff, students for the welfare page.
viewSpecs.set('students/student-welfare-references', {
  view: 'students/student-welfare-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'academicYears', endpoint: '/api/academic/years/list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'staff', endpoint: '/api/staff/staff', staticParams: { limit: '1000' } },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
  ],
  aggregations: {},
});

// Student Fund Transfers: static sources and students for transfer form.
viewSpecs.set('finance/student-fund-transfers-references', {
  view: 'finance/student-fund-transfers-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'sources', endpoint: '/api/finance/student-fund-sources' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '1000', status: 'active' } },
  ],
  aggregations: {},
});

// Communications: static message templates, channels, contacts.
viewSpecs.set('communications/communications-references', {
  view: 'communications/communications-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'templates', endpoint: '/api/communications/templates' },
    { key: 'channels', endpoint: '/api/communications/channels' },
    { key: 'contacts', endpoint: '/api/communications/contacts' },
  ],
  aggregations: {},
});

// Manage Communications: static templates, lists, channels.
viewSpecs.set('communications/manage-communications-references', {
  view: 'communications/manage-communications-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'templates', endpoint: '/api/communications/templates' },
    { key: 'lists', endpoint: '/api/communications/lists' },
    { key: 'channels', endpoint: '/api/communications/channels' },
  ],
  aggregations: {},
});

// Activity Reports: static activities, participants, types.
viewSpecs.set('reports/activity-reports-references', {
  view: 'reports/activity-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'activities', endpoint: '/api/activities/list' },
    { key: 'participants', endpoint: '/api/activities/participants-list' },
    { key: 'types', endpoint: '/api/activities/categories-list' },
  ],
  aggregations: {},
});

// Attendance Reports: static classes, streams, terms.
viewSpecs.set('reports/attendance-reports-references', {
  view: 'reports/attendance-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Class Report Cards: static classes, streams, terms, students.
viewSpecs.set('reports/class-report-cards-references', {
  view: 'reports/class-report-cards-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
  ],
  aggregations: {},
});

// Comparative Reports: static classes, streams, terms, subjects.
viewSpecs.set('reports/comparative-reports-references', {
  view: 'reports/comparative-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'subjects', endpoint: '/api/academic/subjects-list' },
  ],
  aggregations: {},
});

// Conduct Reports: static classes, streams, terms, offense types.
viewSpecs.set('reports/conduct-reports-references', {
  view: 'reports/conduct-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'offenseTypes', endpoint: '/api/students/discipline-meta' },
  ],
  aggregations: {},
});

// Counseling Reports: static students, counselors, case types.
viewSpecs.set('reports/counseling-reports-references', {
  view: 'reports/counseling-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
    { key: 'counselors', endpoint: '/api/staff/counselors' },
    { key: 'caseTypes', endpoint: '/api/students/counseling-meta' },
  ],
  aggregations: {},
});

// Discipline Reports: static classes, streams, terms, offenses.
viewSpecs.set('reports/discipline-reports-references', {
  view: 'reports/discipline-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'offenses', endpoint: '/api/students/discipline-meta' },
  ],
  aggregations: {},
});

// Enrollment Reports: static classes, streams, academic years.
viewSpecs.set('reports/enrollment-reports-references', {
  view: 'reports/enrollment-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'years', endpoint: '/api/academic/years/list' },
  ],
  aggregations: {},
});

// Exception Reports: static exception types, students.
viewSpecs.set('reports/exception-reports-references', {
  view: 'reports/exception-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'exceptionTypes', endpoint: '/api/finance/exception-reports' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
  ],
  aggregations: {},
});

// Exeat Reports: static students, exeat types, statuses.
viewSpecs.set('reports/exeat-reports-references', {
  view: 'reports/exeat-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
    { key: 'exeatTypes', endpoint: '/api/attendance/permission-types' },
    { key: 'statuses', endpoint: '/api/students/boarding-meta' },
  ],
  aggregations: {},
});

// Generate Class Report: static classes, streams, terms.
viewSpecs.set('reports/generate-class-report-references', {
  view: 'reports/generate-class-report-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Generate Subject Report: static subjects, classes, streams, terms.
viewSpecs.set('reports/generate-subject-report-references', {
  view: 'reports/generate-subject-report-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'subjects', endpoint: '/api/academic/subjects-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Health Reports: static classes, streams, health categories.
viewSpecs.set('reports/health-reports-references', {
  view: 'reports/health-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'categories', endpoint: '/api/students/health-meta' },
  ],
  aggregations: {},
});

// Participation Reports: static activities, participants, classes.
viewSpecs.set('reports/participation-reports-references', {
  view: 'reports/participation-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'activities', endpoint: '/api/activities/list' },
    { key: 'participants', endpoint: '/api/activities/participants-list' },
    { key: 'classes', endpoint: '/api/academic/classes-list' },
  ],
  aggregations: {},
});

// Purchase Reports: static suppliers, items, categories.
viewSpecs.set('reports/purchase-reports-references', {
  view: 'reports/purchase-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'suppliers', endpoint: '/api/inventory/suppliers-list' },
    { key: 'items', endpoint: '/api/inventory/items-list' },
    { key: 'categories', endpoint: '/api/inventory/categories-list' },
  ],
  aggregations: {},
});

// Report Cards: static classes, streams, terms, students.
viewSpecs.set('reports/report-cards-references', {
  view: 'reports/report-cards-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
    { key: 'students', endpoint: '/api/students/get-all', staticParams: { limit: '500', status: 'active' } },
  ],
  aggregations: {},
});

// Term Reports: static classes, streams, terms.
viewSpecs.set('reports/term-reports-references', {
  view: 'reports/term-reports-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'classes', endpoint: '/api/academic/classes-list' },
    { key: 'streams', endpoint: '/api/academic/streams-list' },
    { key: 'terms', endpoint: '/api/academic/terms-list' },
  ],
  aggregations: {},
});

// Dashboard: Director/Owner
viewSpecs.set('dashboards/director-owner-references', {
  view: 'dashboards/director-owner-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'summary', endpoint: '/api/dashboard/director/summary' },
    { key: 'payment_trends', endpoint: '/api/payments/trends' },
    { key: 'revenue_sources', endpoint: '/api/payments/revenue-sources' },
    { key: 'academic_kpis', endpoint: '/api/academics/kpis' },
    { key: 'performance_matrix', endpoint: '/api/academics/performance-matrix' },
    { key: 'attendance_trends', endpoint: '/api/attendance/trends' },
    { key: 'risks', endpoint: '/api/dashboard/director/risks' },
    { key: 'announcements', endpoint: '/api/dashboard/director/announcements' },
  ],
  aggregations: {},
});

// Dashboard: Driver
viewSpecs.set('dashboards/driver-references', {
  view: 'dashboards/driver-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'route', endpoint: '/api/transport/my-route' },
    { key: 'vehicle', endpoint: '/api/transport/my-vehicle' },
  ],
  aggregations: {},
});

// Dashboard: Matron/Housemother
viewSpecs.set('dashboards/matron-housemother-references', {
  view: 'dashboards/matron-housemother-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/boarding/stats' },
    { key: 'occupancy', endpoint: '/api/boarding/occupancy' },
    { key: 'roll_call', endpoint: '/api/boarding/roll-call' },
    { key: 'exeats', endpoint: '/api/boarding/exeats' },
  ],
  aggregations: {},
});

// Dashboard: School Administrative Officer
viewSpecs.set('dashboards/school-administrative-officer-references', {
  view: 'dashboards/school-administrative-officer-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/school-admin/full' },
  ],
  aggregations: {},
});

// Dashboard: Store Manager
viewSpecs.set('dashboards/store-manager-references', {
  view: 'dashboards/store-manager-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'dashboard', endpoint: '/api/inventory/dashboard' },
    { key: 'uniform', endpoint: '/api/inventory/uniform-dashboard' },
    { key: 'low_stock', endpoint: '/api/inventory/uniform-low-stock' },
  ],
  aggregations: {},
});

// Dashboard: Support Staff
viewSpecs.set('dashboards/support-staff-references', {
  view: 'dashboards/support-staff-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'access', endpoint: '/api/staff/access-context' },
    { key: 'catering_stats', endpoint: '/api/catering/stats' },
    { key: 'catering_stock', endpoint: '/api/catering/food-stock' },
    { key: 'exeats', endpoint: '/api/boarding/exeats' },
    { key: 'maintenance', endpoint: '/api/maintenance/dashboard-summary' },
  ],
  aggregations: {},
});

// Dashboard: System Administrator
viewSpecs.set('dashboards/system-administrator-references', {
  view: 'dashboards/system-administrator-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'active_sessions', endpoint: '/api/dashboard/system-admin/active-sessions' },
    { key: 'api_load', endpoint: '/api/dashboard/system-admin/api-load' },
    { key: 'auth_events', endpoint: '/api/dashboard/system-admin/auth-events' },
    { key: 'health_errors', endpoint: '/api/dashboard/system-admin/health-errors' },
    { key: 'health_warnings', endpoint: '/api/dashboard/system-admin/health-warnings' },
    { key: 'uptime', endpoint: '/api/dashboard/system-admin/uptime' },
  ],
  aggregations: {},
});

// Dashboard: Class Teacher
viewSpecs.set('dashboards/class-teacher-references', {
  view: 'dashboards/class-teacher-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/class-teacher/full' },
  ],
  aggregations: {},
});

// Dashboard: Deputy Head Academic
viewSpecs.set('dashboards/deputy-head-academic-references', {
  view: 'dashboards/deputy-head-academic-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/deputy-academic/full' },
  ],
  aggregations: {},
});

// Dashboard: Deputy Head Discipline
viewSpecs.set('dashboards/deputy-head-discipline-references', {
  view: 'dashboards/deputy-head-discipline-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/deputy-discipline/full' },
  ],
  aggregations: {},
});

// Dashboard: Headteacher
viewSpecs.set('dashboards/headteacher-references', {
  view: 'dashboards/headteacher-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/headteacher/full' },
  ],
  aggregations: {},
});

// Dashboard: HOD Talent Development
viewSpecs.set('dashboards/hod-talent-development-references', {
  view: 'dashboards/hod-talent-development-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'summary', endpoint: '/api/activities/statistics/get' },
    { key: 'activities', endpoint: '/api/activities' },
    { key: 'schedule', endpoint: '/api/activities/schedules/list' },
  ],
  aggregations: {},
});

// Dashboard: Intern/Student Teacher
viewSpecs.set('dashboards/intern-student-teacher-references', {
  view: 'dashboards/intern-student-teacher-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/dashboard/intern-teacher/full' },
  ],
  aggregations: {},
});

// Dashboard: School Accountant
viewSpecs.set('dashboards/school-accountant-references', {
  view: 'dashboards/school-accountant-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'financial', endpoint: '/api/dashboard/accountant/financial' },
    { key: 'payments', endpoint: '/api/dashboard/accountant/payments', staticParams: { limit: 15 } },
    { key: 'defaulters', endpoint: '/api/dashboard/accountant/financial', staticParams: { pivot: 'top-defaulters', limit: 10 } },
    { key: 'unmatched', endpoint: '/api/payments/unmatched-mpesa', staticParams: { limit: 20 } },
  ],
  aggregations: {},
});

// Dashboard: School Counselor Chaplain
viewSpecs.set('dashboards/school-counselor-chaplain-references', {
  view: 'dashboards/school-counselor-chaplain-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'summary', endpoint: '/api/counseling/summary' },
  ],
  aggregations: {},
});

// Dashboard: Catering Manager Cook Lead
viewSpecs.set('dashboards/catering-manager-cook-lead-references', {
  view: 'dashboards/catering-manager-cook-lead-references',
  service: 'php',
  permissions: [],
  queries: [
    { key: 'stats', endpoint: '/api/catering/stats' },
    { key: 'menu', endpoint: '/api/catering/menu' },
    { key: 'low_stock', endpoint: '/api/catering/food-stock', staticParams: { low_stock: 1, limit: 10 } },
  ],
  aggregations: {},
});

export function registerViewSpec(key, spec) {
  if (typeof key !== 'string' || !/^[a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*$/i.test(key)) {
    throw new TypeError('View operation key must be entity/view.');
  }
  if (!spec || spec.view !== key || !Array.isArray(spec.permissions) || !Array.isArray(spec.queries)) {
    throw new TypeError(`Invalid data operation spec: ${key}`);
  }
  viewSpecs.set(key, Object.freeze(spec));
}

export function getViewSpec(key) {
  return viewSpecs.get(key);
}

export function listViewSpecs() {
  return Array.from(viewSpecs.keys());
}
