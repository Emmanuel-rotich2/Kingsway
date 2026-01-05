# Director Dashboard - Quick Reference Guide

## Architecture at a Glance

```
HTML Template → JavaScript Controller → API Wrapper → Backend Controller → Database
director_       directorDashboard      api.js         StudentsController  MySQL
owner_dashboard Controller.js          API.dashboard  PaymentsController
.php                                   .*             AttendanceController
```

**Golden Rule**: Database queries ONLY in backend controllers, never in frontend code.

---

## 8 Dashboard Cards

| Card | Element ID | Data Source | Format |
|------|------------|------------|--------|
| Total Enrollment | `#totalEnrollment` | Students/getStats | Number |
| Staff Strength | `#staffStrength` | Staff/getStats | Number |
| Teaching Staff | `#teacherCount` | Staff/getStats | Number |
| Fees Collected (MTD) | `#monthlyFees` | Payments/getStats | Currency |
| Attendance Rate | `#attendanceRate` | Attendance/getTodayAttendance | Percentage |
| Pending Approvals | `#pendingApprovals` | System/getPendingApprovals | Number |
| Pending Admissions | `#pendingAdmissions` | Admissions/getPending | Number |
| Overdue Payments | `#overduePayments` | Payments/getStats | Number |
| Staff Attendance | `#staffAttendance` | Staff/getStats | Number |

---

## 2 Charts

| Chart | Element ID | Type | Data Source |
|-------|-----------|------|-------------|
| Grade Distribution | `#gradeChart` | Bar | Students/getStats |
| Staff Distribution | `#deptChart` | Doughnut | Staff/getStats |

---

## 3 Data Sections

| Section | Element ID | Data Source |
|---------|-----------|-------------|
| Financial by Class | `#classFinancialsTable` | Students/getStats |
| Recent Admissions | `#recentAdmissionsTable` | Admissions/getPending |
| Announcements | `#announcementsContainer` | Activities/getList |

---

## Key Files

| File | Purpose | Lines |
|------|---------|-------|
| `/components/dashboards/director_owner_dashboard.php` | HTML template | 246 |
| `/js/dashboards/directorDashboardController.js` | Data orchestration | 350 |
| `/api/controllers/StudentsController.php` | Student data | Modified |
| `/api/controllers/StaffController.php` | Staff data | Modified |
| `/api/controllers/PaymentsController.php` | Payment data | Modified |
| `/api/controllers/AttendanceController.php` | Attendance data | Modified |
| `/api/controllers/AdmissionController.php` | Admission data | Modified |
| `/api/controllers/ActivitiesController.php` | Activity data | Modified |

---

## API Endpoints (7 Total)

```
GET /students/stats                    → Student count, grades, financials
GET /staff/stats                       → Staff count, departments, attendance
GET /payments/stats                    → Fees collected, outstanding, overdue
GET /attendance/today-attendance       → Today's student attendance %
GET /system/pending-approvals          → Pending approvals for user
GET /admissions/pending                → Pending admissions count & list
GET /activities/list                   → Recent activities/announcements
```

---

## Controller Init Code

```javascript
// Automatic on page load (no manual init needed)
document.addEventListener('DOMContentLoaded', () => 
    directorDashboardController.init()
);
```

---

## Data Binding Example

```javascript
// When API returns: { total_students: 1240 }
API.dashboard.getStudentStats()
    .then(data => {
        // Controller updates the element
        document.getElementById('totalEnrollment')
            .textContent = data.total_students; // "1240"
    });
```

---

## Adding a New Card

### 1. Add to HTML
```html
<h4 id="myNewCard">--</h4>
```

### 2. Update Controller
```javascript
updateMetricCards: function() {
    // ... existing code ...
    if (data.someData?.newValue) {
        document.getElementById('myNewCard')
            .textContent = data.someData.newValue;
    }
}
```

### 3. Update Backend API
```php
// In appropriate controller
public function getStats(...) {
    // ... existing queries ...
    'newValue' => $newValue
}
```

---

## Adding a New Chart

### 1. Add Canvas to HTML
```html
<canvas id="myChart"></canvas>
```

### 2. Add Initialization in Controller
```javascript
initializeMyChart: function(data) {
    const ctx = document.getElementById('myChart');
    this.charts.myChart = new Chart(ctx, {
        type: 'bar', // or 'line', 'pie', etc.
        data: {
            labels: data.map(d => d.label),
            datasets: [...]
        }
    });
}
```

### 3. Call from updateCharts()
```javascript
updateCharts: function() {
    if (data.myChartData) {
        this.initializeMyChart(data.myChartData);
    }
}
```

---

## Debugging

### Check Data Load
```javascript
// Console
directorDashboardController.dashboardData
```

### Check Specific Card
```javascript
// Console
document.getElementById('totalEnrollment').textContent
```

### Check API Response
```javascript
// Console
API.dashboard.getStudentStats().then(d => console.log(d))
```

### Monitor Network
1. F12 → Network tab
2. Refresh dashboard
3. Look for 7 API calls

---

## Common Customizations

### Change Number Format
```javascript
// From: 1000000
// To: 1,000,000
formatNumber(1000000) // "1,000,000"
```

### Change Currency Format
```javascript
// From: 1000000
// To: KES 1,000,000.00
formatCurrency(1000000) // "1,000,000.00"
```

### Change Card Colors
Edit HTML:
```html
<h4 style="color: #0066cc;">--</h4>  <!-- Change hex color -->
```

### Change Chart Colors
Edit Controller:
```javascript
backgroundColor: ['#27ae60', '#f39c12', '#e67e22'] // RGB colors
```

---

## Performance Tips

✅ **Already Optimized**:
- Parallel API calls (Promise.all)
- No polling or timers
- Chart reuse (destroy/recreate)
- HTML escaping for security

🔧 **If Slow**:
1. Check database indexes
2. Profile SQL queries
3. Add caching to controller methods
4. Reduce data size in API responses

---

## Testing Checklist

- [ ] All 8 cards show numbers (not "--")
- [ ] Charts render without errors
- [ ] Tables populate with data
- [ ] No JavaScript errors in console
- [ ] All 7 API calls succeed (HTTP 200)
- [ ] Dashboard loads in <2 seconds
- [ ] Refresh button works
- [ ] Mobile responsive
- [ ] Director sees only their data (RBAC)

---

## Production Checklist

Before deploying:
- [ ] Run all tests above
- [ ] Check database has sample data
- [ ] Verify JWT token generation
- [ ] Test RBAC permissions
- [ ] Monitor API logs
- [ ] Set up error tracking
- [ ] Document for users
- [ ] Plan rollback procedure

---

## Support Contacts

**Issues**?:
1. Check `/api/logs/` for errors
2. Review browser console (F12)
3. Verify database connectivity
4. Check user permissions (JWT token)

**Questions**?:
- See: `DIRECTOR_DASHBOARD_API_ARCHITECTURE.md`
- See: `DIRECTOR_DASHBOARD_IMPLEMENTATION_VERIFICATION.md`

---

## Version History

| Date | Version | Changes |
|------|---------|---------|
| 2025-12-20 | 1.0 | Initial implementation |

---

**Status**: ✅ Production Ready

