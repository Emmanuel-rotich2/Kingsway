# EXECUTION GUIDE: Database Authorization Fix

**Date**: 2026-03-29
**Status**: Fix Script Ready - Corrected with Proper Column Names
**Task**: Populate role_routes with sidebar-assigned routes

---

## Quick Summary

The Director role has 567 sidebar items assigned but NO entries in `role_routes` table. The authorization filter in MenuBuilderService checks role_routes and filters out all 567 items (0 returned).

**Solution**: Run the SQL script to populate role_routes with all routes corresponding to sidebar items.

---

## Step 1: Verify Database Schema

Before executing, verify the sidebar_menu_items columns:

```bash
mysql -u root -padmin123 KingsWayAcademy -e "DESC sidebar_menu_items;" | head -20
```

**Expected output**:
```
Field        Type                    Null  Key  Default              Extra
id           int(10) unsigned        NO    PRI  NULL                 auto_increment
name         varchar(100)            NO    UNI  NULL
label        varchar(100)            NO         NULL
icon         varchar(100)            YES        NULL
url          varchar(255)            YES        NULL
route_id     int(10) unsigned        YES   MUL  NULL          ← This is the key column
parent_id    int(10) unsigned        YES   MUL  NULL
...
```

**Key Column**: `route_id` (not `route_name`)

---

## Step 2: Execute the Fix Script

Navigate to project directory:

```bash
cd /home/prof_angera/Projects/php_pages/Kingsway
```

Run the migration script:

```bash
mysql -u root -padmin123 --skip-ssl -P3306 KingsWayAcademy \
  < database/migrations/2026_03_29_fix_director_authorization.sql
```

**Or use Navicat/PHPMyAdmin if CLI is blocked:**
1. Open the database client
2. Select `KingsWayAcademy` database
3. Paste contents of `database/migrations/2026_03_29_fix_director_authorization.sql`
4. Execute as query

---

## Step 3: Expected Output

The script will show:

```
| check_type              | value |
| Director Role ID        | 3     |
| Sidebar Items Assigned to Director | count |
| Sidebar Items Assigned to Director | 567   |
| ... (analysis queries) |       |
| Role_routes entries for Director AFTER insertion | [new count] |
| Sidebar items with route_routes coverage | [should match above] |
| ... (role analysis)     |       |
| message                 | STATUS: All missing role_routes entries have been populated. |
| next_step               | NEXT: Test Director login - sidebar items should now pass authorization filter. |
```

---

## Step 4: Verify the Fix

After execution, verify that Director now has route_routes entries:

```bash
mysql -u root -padmin123 KingsWayAcademy -e \
"SELECT COUNT(*) as route_routes_count FROM role_routes WHERE role_id = 3;"
```

**Expected result**: Should show a number close to or equal to 567 (depending on parent item nodes)

---

## Step 5: Test Login via curl

Test the Director login to verify sidebar items are now returned:

```bash
curl -X POST http://localhost/Kingsway/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test_director","password":"Pass123!@"}' | jq .
```

**Expected response structure**:
```json
{
  "token": "...",
  "user": { "id": ..., "name": "Test Director", ... },
  "permissions": [25 permissions...],
  "sidebar_items": [
    {
      "id": 1,
      "label": "Dashboard",
      "icon": "...",
      "subitems": [...]
    },
    ...hundreds of items...
  ]
}
```

**Key Check**: `sidebar_items` array should now contain 567+ items (not 0 or 1)

---

## Step 6: Analyze the Response

Extract just the sidebar count:

```bash
curl -s -X POST http://localhost/Kingsway/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test_director","password":"Pass123!@"}' | \
  jq '.sidebar_items | length'
```

**Expected output**: ~567 (exact count depends on structure)

---

## Step 7: Rollback If Needed

If something breaks, rollback using the backup created during script execution:

```bash
mysql -u root -padmin123 KingsWayAcademy -e \
"TRUNCATE role_routes; INSERT INTO role_routes SELECT * FROM backup_role_routes_20260329_fix;"
```

This restores the table to its state before the fix.

---

## What Each Section Does

### Section 1: Backup
Creates `backup_role_routes_20260329_fix` table for rollback

### Section 2: Analysis
Shows current state:
- Director role ID = 3
- 567 sidebar items assigned to Director
- Count of items with valid route_id references
- Current role_routes entries (should be 0)

### Section 3: Identification
Lists all routes that need to be added to role_routes
(shows missing mappings for each sidebar item)

### Section 4: Insertion
Executes the INSERT that adds all missing entries:
```sql
INSERT INTO role_routes (role_id, route_id, created_at, updated_at)
SELECT @director_role_id, smi.route_id, NOW(), NOW()
FROM sidebar_menu_items smi
JOIN role_sidebar_menus rsm ON rsm.sidebar_menu_id = smi.id
WHERE rsm.role_id = @director_role_id
AND smi.route_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM role_routes rr WHERE ...);
```

### Section 5: Verification
Shows counts before/after and confirms all sidebar items are now covered

### Section 6: Cross-Check
Shows same analysis for ALL roles (not just Director)
Useful for identifying which other roles also need this fix

---

## Why This Fix Works

**MenuBuilderService Authorization Filter Logic**:
```php
$authorization = $configService->isUserAuthorizedForRoute(
    $userId,
    $roleId,
    $routeName
);
// Checks: Does role_routes have an entry for this route?
return (bool) ($authorization['authorized'] ?? false);
```

**Before Fix**:
- Director has 0 role_routes entries
- Authorization check returns false for ALL routes
- All 567 sidebar items filtered out
- User gets 0 items in response

**After Fix**:
- Director has 567 role_routes entries (one for each sidebar route)
- Authorization check returns true for all assigned routes
- All 567 sidebar items pass filter
- User gets complete sidebar in response

---

## Next Steps After Successful Test

1. **Document results**: Note the actual sidebar item count returned
2. **Fix other roles**: Apply same pattern to Principal, Accountant, HOD, etc.
3. **Commit changes**: Add notes to git about completed fixes
4. **Deploy**: Code is ready (already integrated in previous phase)
5. **Monitor**: Check logs for authorization-related messages for 24 hours

---

## Files Involved

| File | Purpose | Location |
|------|---------|----------|
| Fix Script | SQL migration to populate role_routes | `database/migrations/2026_03_29_fix_director_authorization.sql` |
| Explanation | This document | `DATABASE_FIX_AUTHORIZATION_EXPLANATION.md` |
| Authorization Filter Code | What checks the entries | `api/services/MenuBuilderService.php` line 510-523 |
| Login Endpoint | Where response is built | `api/modules/auth/AuthAPI.php` |

---

## Status Tracking

- [ ] Verify database schema (`DESC sidebar_menu_items`)
- [ ] Execute fix script
- [ ] View script output (all sections should show results)
- [ ] Verify role_routes count increased
- [ ] Test Director login via curl
- [ ] Analyze response: Check sidebar_items array size
- [ ] Confirm FIX SUCCESSFUL: 567+ items returned (not 0-1)
- [ ] Apply same fix to other roles
- [ ] Deploy to production
- [ ] Monitor for 24 hours
- [ ] Document final results

---

## Questions & Troubleshooting

**Q: Error "Unknown variable 'ssl-mode=DISABLED'"**
A: Use `--skip-ssl` instead (as shown in examples above)

**Q: Error "Can't connect to local server"**
A: Use TCP port: `-P3306` (as shown) or use GUI client (Navicat/PHPMyAdmin)

**Q: Script runs but no visible INSERT output**
A: INSERTs don't show count in MySQL output. Check verification queries or run:
```bash
mysql -u root -padmin123 KingsWayAcademy -e \
"SELECT COUNT(*) FROM role_routes WHERE role_id = 3;"
```

**Q: Sidebar items still not showing after fix**
A: Might be another role causing issue. Check that test_director is NOT assigned to multiple conflicting roles.

**Q: How much bigger is role_routes after fix?**
A: Should add ~560-570 new entries (one per sidebar item minus parent-only items)

---

**Ready to execute when user confirms. Status: ✅ Script corrected and ready for execution.**
