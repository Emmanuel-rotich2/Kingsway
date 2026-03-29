#!/usr/bin/env bash
# After running database/migrations/2026_03_30_*.sql and 2026_03_31_*.sql,
# export role_permissions for merging into a self-contained seed (optional).
#
# Usage:
#   MYSQL_PWD=... ./scripts/export_role_permissions_after_migrations.sh > /tmp/role_permissions_data.sql
#
# Then replace the TRUNCATE + INSERT section for role_permissions in
# database/KingsWayAcademy.sql, or keep using migrations after base import.
set -euo pipefail
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
MYSQLDUMP="${MYSQLDUMP:-/opt/lampp/bin/mysqldump}"

exec "$MYSQLDUMP" -u "$DBU" -p"${MYSQL_PWD:-}" \
  --no-create-info --skip-comments --compact \
  "$DB" role_permissions
