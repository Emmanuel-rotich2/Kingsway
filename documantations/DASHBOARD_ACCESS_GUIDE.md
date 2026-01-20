# Dashboard Access Guide - Quick Reference

**How to Access the Dashboards**

---

## For Users

### 1. **System Administrator Dashboard** (Role ID: 2)
**URL**: `http://localhost/Kingsway/pages/dashboard.php`

**Who Can Access**: System administrators only

**What You'll See**:
- Infrastructure health metrics
- Authentication audit logs
- Active session monitoring
- System error and warning logs
- API performance metrics
- System uptime statistics

**Key Charts**:
- System Activity (API load)
- Authentication Trends

---

### 2. **Director Dashboard** (Role ID: 3)
**URL**: `http://localhost/Kingsway/pages/dashboard.php`

**Who Can Access**: Director/Principal only

**What You'll See**:
- Total enrollment (with growth %)
- Staff strength breakdown
- Fee collection rate
- Daily attendance statistics
- Fee collection trends (12-month comparison)
- Enrollment growth trajectory
- Pending approvals workflow

**Auto-Refresh**: Every 1 hour

---

## For Developers

### Testing the Dashboards

#### Option 1: Direct URL Access
```
http://localhost/Kingsway/pages/dashboard.php
```
(Dashboard auto-detects role and loads correct interface)

#### Option 2: From Navigation
- Currently: No navigation link (can add to navbar)
- Future: Add "Dashboard" link to main menu

#### Option 3: Browser Console Testing
```javascript
// Check current dashboard info
DashboardRouter.getDashboardInfo()

// Manually switch to a different role
DashboardRouter.switchToDashboard(3)  // Switch to Director

// Refresh dashboard data
directorDashboardController.loadDashboardData()
```

---

## Testing with Sample Credentials

### System Admin Test Account
- **Email**: admin@school.com
- **Password**: (ask system administrator)
- **Role**: System Administrator (ID: 2)
- **Dashboard**: System Admin Dashboard

### Director Test Account
- **Email**: director@school.com
- **Password**: (ask system administrator)
- **Role**: Director (ID: 3)
- **Dashboard**: Director Dashboard

---

## Dashboard Features by Type

### System Admin Dashboard
Features available:
- ✅ Infrastructure monitoring
- ✅ Security audit logs
- ✅ System error tracking
- ✅ API performance monitoring
- ✅ 1-hour auto-refresh
- ⏳ Advanced filtering (coming soon)

### Director Dashboard
Features available:
- ✅ KPI summary cards
- ✅ Fee collection trends
- ✅ Enrollment growth tracking
- ✅ Pending approval workflow
- ✅ 1-hour auto-refresh
- ⏳ Communications log (placeholder)
- ⏳ Financial summary (placeholder)
- ⏳ Approval actions (coming soon)

---

## Troubleshooting

### "Dashboard Not Loading"
1. **Check Authentication**
   - Are you logged in?
   - Is your session active?
   - Try logging in again

2. **Check Role Assignment**
   - Does your user have role ID 2 or 3?
   - Check admin panel: Manage Users → View Roles
   - Ask system administrator to assign role

3. **Check Browser Console**
   - Open DevTools (F12)
   - Check Console tab for errors
   - Screenshot error message and report

### "API Endpoints Returning Errors"
1. **Check Backend**
   - Is API running? http://localhost/Kingsway/api
   - Are controllers loaded?
   - Check API logs: /api/logs/

2. **Check Database**
   - Is database running?
   - Are tables created?
   - Run database migrations

3. **Check Permissions**
   - Does role have permission for endpoint?
   - Check roles_permissions table
   - Ask system administrator to grant permission

### "Charts Not Displaying"
1. **Check JavaScript Console**
   - Any Chart.js errors?
   - Any script loading errors?

2. **Check Data**
   - Are API endpoints returning data?
   - Test in browser console:
     ```javascript
     window.API.dashboard.getStudentStats()
         .then(data => console.log(data))
     ```

3. **Check HTML**
   - Are canvas elements in DOM?
   - Check page source (Ctrl+U)
   - Look for `<canvas id="..."></canvas>`

---

## API Endpoints Reference

### System Admin Endpoints
```
GET /api/system/auth-events
GET /api/system/active-sessions
GET /api/system/uptime
GET /api/system/health-errors
GET /api/system/health-warnings
GET /api/system/api-load
```

### Director Endpoints
```
GET /api/students/stats
GET /api/staff/stats
GET /api/payments/stats
GET /api/attendance/today
GET /api/payments/collection-trends
GET /api/system/pending-approvals
```

### Testing Endpoints
```bash
# System endpoints
curl http://localhost/Kingsway/api/?route=system&action=auth-events

# Business endpoints
curl http://localhost/Kingsway/api/?route=students&action=stats
curl http://localhost/Kingsway/api/?route=payments&action=collection-trends
```

---

## Browser Requirements

### Minimum Requirements
- **Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Must be enabled
- **Cookies**: Must be enabled (for sessions)
- **Screen Size**: 1024x768 minimum

### Recommended
- **Browser**: Latest version
- **Screen Size**: 1366x768 or larger
- **Connection**: Broadband (for faster loading)

---

## Keyboard Shortcuts

### Dashboard Navigation
- **Tab**: Navigate between elements
- **Enter**: Activate buttons
- **Escape**: Close modal dialogs

### Multi-Role Users
- **Alt+D**: Open role switcher dropdown (if applicable)

### Delegation Policy ✅

- **Full-role delegation is not permitted.** The system enforces per-menu-item delegation only.
- When delegating responsibilities, administrators must delegate specific sidebar items (functions) using the `role_delegations_items` mechanism — not the entire role/sidebars.
- If a Headteacher delegates a function to deputies, the delegation should be given explicitly to *both* deputy roles (e.g., Role 6 and Role 63) as needed — do **not** give all functions to a single deputy.
- Attempts to create active full-role delegations are blocked and recorded in an audit table; contact an administrator to perform fine-grained delegation.

---

## Performance Tips

1. **Clear Browser Cache**
   - Ctrl+Shift+Delete
   - Clear cached images/files
   - Reload dashboard (Ctrl+F5)

2. **Reduce Load**
   - Close other browser tabs
   - Disable browser extensions
   - Clear browser history/cache

3. **Check Connection**
   - Use fast, stable connection
   - Avoid VPN if possible
   - Check ping to server

---

## Feedback & Issues

### Report an Issue
1. Take a screenshot of the error
2. Note your role (System Admin/Director)
3. Note what you were trying to do
4. Email to: tech-support@school.com

### Request a Feature
1. Describe what you need
2. Explain how it will help
3. Email to: feature-request@school.com

---

## Related Documentation

- [ROUTING_IMPLEMENTATION_SUMMARY.md](ROUTING_IMPLEMENTATION_SUMMARY.md) - How routing works
- [DASHBOARD_DESIGN_SPECIFICATION.md](DASHBOARD_DESIGN_SPECIFICATION.md) - Design for all 19 roles
- [SYSTEM_ENDPOINTS_IMPLEMENTATION.md](SYSTEM_ENDPOINTS_IMPLEMENTATION.md) - System endpoints reference
- [DIRECTOR_DASHBOARD_IMPLEMENTATION.md](DIRECTOR_DASHBOARD_IMPLEMENTATION.md) - Director dashboard details
- [SECURITY_FIX_SYSTEM_ADMIN_DASHBOARD.md](SECURITY_FIX_SYSTEM_ADMIN_DASHBOARD.md) - Security implementation
- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Complete project overview

---

## Contact & Support

**System Administrator Support**:
- Email: sysadmin@school.com
- Phone: +254-xxx-xxx-xxx
- Available: Mon-Fri 8am-5pm

**Dashboard Issues**:
- Email: tech-support@school.com
- Include: screenshot, role, browser, steps to reproduce

---

**Last Updated**: December 28, 2025  
**Current Version**: 1.0  
**Status**: Production Ready
