# RBAC/navigation redesign plan

## Goals
- Reset the `permissions`, `role_permissions`, `route_permissions`, `role_routes`, `role_sidebar_menus`, and related navigation tables to match the new module/action/component model.  The System Administrator will keep system-level rights only.  Module-based roles (Director, Headteacher, Deputy Heads, Accountant, etc.) get only the permissions they need.
- Ensure every route and sidebar link is guarded by a permission that reflects both page access and component/action rights (create vs view vs edit vs approve).  The permission catalog remains the 4,473 rows, but they are reorganized into module/action buckets.

## Approach
1. **Design the new schema/data layout**
   * Document modules (System, Students, Academics, Finance, Scheduling, Inventory, Boarding, Transport, Activities, Communications, HR, Reports) and their action tiers (view, create, edit, delete, approve, publish, export, conflict).  Each permission code will map to one module+action+component.
   * Identify the routes/sidebar tree for each module and tie them to `route_permissions`.  Modularize the menu so each role gets only the groups they own (System role gets system menus; Director gets Finance/Reports/Students; etc.).
   * Specify action permissions for each component within the page (e.g., `manage_students` table edit/delete, per-row buttons, fees tab, promotion workflow). Document this in a matrix.
   * Finalize the per-role mapping for the 11 roles, ensuring Headteacher and deputies cover admissions and Director covers approvals.

2. **Prepare migration strategy**
   * Back up existing tables (permissions, role_permissions, route_permissions, role_routes, sidebar_menu_items, role_sidebar_menus, dashboards, routes).  Export via SQL dumps to `backups/` with timestamps.
   * Draft new migration SQL scripts that:
     - Truncate the tables mentioned above.
     - Insert the restructured permission catalog grouped by module + action (all 4,473 rows reinserted using the new mapping).  This may be scripted or generated from the permission matrix.
     - Populate `routes` and `route_permissions` to match the new route assignments, then rebuild `role_routes` to tie each role to the cleaned set.
     - Rebuild `sidebar_menu_items` and `role_sidebar_menus` according to the new navigation groups.
   * Wrap the migration in a transaction where possible, and include rollback statements (copy current data to temporary tables prior to truncate).

3. **Update application code**
   * Extend `RoleBasedUI` with the new modules/action sets (including component-level guards).  Each permission should be defined as `MODULE_ACTION_COMPONENT`.  Implement helper functions to check both page and action permissions before showing elements.
   * Update `js/index.js` route guard to rely on the new `route_permissions` table rather than hardcoded route names.
   * Ensure the backend APIs consult the reorganized permissions when performing authorization checks (`RBACMiddleware`, `SystemConfigController`, controllers for students/finance/schedules).  System Admin stays limited to system tables.

4. **Validation & rollout**
   * After migration, run a verification script listing per-role permissions and sidebars; compare against the desired matrix.  Log discrepancies for review.
   * Re-run the “validation report” (from earlier) to ensure counts return to zero (no missing role_routes, no orphaned menus, etc.).
   * Provide documentation for each role detailing their permitted routes/actions/components.

5. **Monitoring & rollback**
   * Keep the backups and migration scripts under version control; if anything goes wrong, restore the backup tables and routes before applying the new design.
   * Plan to run this migration in a controlled window (overnight) due to the destructive nature; notify stakeholders beforehand.

## Next steps
1. Approve the module/action/component design and per-role matrix.  I can draft the matrix in Excel/Markdown for your review.
2. After approval, generate the migration SQL scripts with the new permission assignments and include rollback sections.
3. Update the front-end `RoleBasedUI` checks and backend authorization to use the reorganized data.
4. Execute the migration with backups, verify, and document the final state.
