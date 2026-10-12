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
    { key: 'cards', endpoint: '/api/students/id-cards', params: ['filters'] },
    { key: 'meta', endpoint: '/api/students/id-card-meta' },
    { key: 'status', endpoint: '/api/students/card-status', params: ['filters'] },
  ],
  aggregations: {
    summary: {
      dataSource: 'status',
      totalStudents: 'count()',
      withIDs: "filter(status != 'not_generated').count()",
      withoutIDs: "filter(status == 'not_generated').count()",
      printed: "filter(status == 'printed').count()",
      issued: "filter(status == 'issued').count()",
      lost: "filter(status == 'lost').count()",
      expired: "filter(status == 'expired').count()",
      replaced: "filter(status == 'replaced').count()",
    },
    tables: {
      byClass: { groupBy: ['class_name'], cols: ['class_name', 'totalStudents', 'withIDs', 'withoutIDs'] },
      byStream: { groupBy: ['class_name', 'stream_name'], cols: ['class_name', 'stream_name', 'totalStudents', 'withIDs'] },
      detail: {
        cols: [
          'id', 'admission_no', 'first_name', 'middle_name', 'last_name', 'gender',
          'student_status', 'photo_url', 'class_name', 'stream_name', 'card_id',
          'card_number', 'card_status', 'issue_date', 'expiry_year', 'qr_token',
        ],
      },
    },
    filterOptions: {
      class: 'unique(class_name)',
      stream: 'unique(stream_name)',
      gender: 'unique(gender)',
      studentStatus: 'unique(student_status)',
      cardStatus: 'unique(card_status)',
    },
  },
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

// This endpoint selects the operator's default student context and filters
// fields/rows in PHP. Node only gathers its bounded pages; the browser can then
// filter the complete authorized roster locally.
viewSpecs.set('students/portfolio-roster', {
  view: 'students/portfolio-roster',
  service: 'php',
  permissions: [],
  queries: [
    {
      key: 'roster',
      endpoint: '/api/students/context-list',
      params: ['filters'],
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
