#!/usr/bin/env bash
# Run Kingsway SQL migrations in a safe order so RBAC matches SystemConfigService +
# MenuBuilderService (strict route + required permissions).
#
# Usage:
#   MYSQL_PWD=yourpass ./scripts/run_kingsway_migrations.sh
#   DB=KingsWayAcademy DBU=root MYSQL_PWD=pass ./scripts/run_kingsway_migrations.sh
#
# Do NOT run 2026_03_29_rbac_workflow_sync.sql again — it fails when schema already
# exists. Use 2026_04_01_rbac_schema_extensions_idempotent.sql + 2026_04_01_rbac_module_tagging_updates.sql instead.
# Do NOT run 2026_03_29_route_permissions_detailed.sql after 2026_03_30_rebuild_* — it can
# reintroduce multi-permission route rows; the rebuild script is canonical.
#
set -euo pipefail

MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
M="$ROOT/database/migrations"

run_sql() {
  local name="$1"
  local file="$2"
  echo ""
  echo ">>> $name"
  "$MYSQL" -u "$DBU" -p"${MYSQL_PWD:-}" "$DB" < "$file"
  echo "    OK"
}

echo "=== Kingsway migrations (DB=$DB) ==="

run_sql "timetable_system_setup" "$M/timetable_system_setup.sql"
run_sql "lesson_plans_and_timetable_fixes" "$M/lesson_plans_and_timetable_fixes.sql"
run_sql "2026_02_07_fix_orphaned_role_routes" "$M/2026_02_07_fix_orphaned_role_routes.sql"
run_sql "2026_03_29_fix_director_authorization" "$M/2026_03_29_fix_director_authorization.sql"
run_sql "2026_04_01_rbac_schema_extensions_idempotent" "$M/2026_04_01_rbac_schema_extensions_idempotent.sql"
run_sql "2026_04_01_rbac_module_tagging_updates" "$M/2026_04_01_rbac_module_tagging_updates.sql"
run_sql "2026_03_30_rebuild_route_permissions_and_role_routes" "$M/2026_03_30_rebuild_route_permissions_and_role_routes.sql"
run_sql "2026_03_30_role_permissions_from_blueprint" "$M/2026_03_30_role_permissions_from_blueprint.sql"
run_sql "2026_03_31_director_vs_admin_fix_workflow_linkage" "$M/2026_03_31_director_vs_admin_fix_workflow_linkage.sql"

echo ""
echo "=== Validation (SQL) ==="
"$MYSQL" -u "$DBU" -p"${MYSQL_PWD:-}" "$DB" < "$M/2026_03_31_validate_rbac_consistency.sql"

echo ""
PHP_VERIFY="${PHP_VERIFY:-/opt/lampp/bin/php}"
if [[ -x "$PHP_VERIFY" ]]; then
  echo "=== PHP UI alignment (MenuBuilder vs SystemConfigService) ==="
  "$PHP_VERIFY" "$ROOT/scripts/verify_rbac_ui_alignment.php" || exit 1
else
  echo "=== Skipping verify_rbac_ui_alignment.php (set PHP_VERIFY to php with pdo_mysql) ==="
fi
echo "=== Done ==="
