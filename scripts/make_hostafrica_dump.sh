#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   scripts/make_hostafrica_dump.sh [input.sql] [output.sql]
#
# Defaults:
#   input  = database/KingsWayAcademy.sql
#   output = database/KingsWayAcademy.hostafrica.sql
#
# This removes statements that typically fail on shared hosting
# and strips hardcoded DEFINER values.

INPUT_FILE="${1:-database/KingsWayAcademy.sql}"
OUTPUT_FILE="${2:-database/KingsWayAcademy.hostafrica.sql}"

if [[ ! -f "$INPUT_FILE" ]]; then
  echo "Input file not found: $INPUT_FILE" >&2
  exit 1
fi

perl -ne '
  BEGIN { $skip_stmt = 0; }

  # If we are inside a skipped multi-line statement, keep discarding
  # lines until the terminating semicolon.
  if ($skip_stmt) {
    if (/;\s*$/) { $skip_stmt = 0; }
    next;
  }

  next if /^DROP DATABASE IF EXISTS /;
  next if /^CREATE DATABASE IF NOT EXISTS /;
  next if /^USE `[^`]+`;/;
  next if /^\s*-{6,}\s*$/;

  # Drop phpMyAdmin internal metadata statements entirely.
  # For any multi-line statement touching pma__ tables, skip through semicolon.
  if (/^\s*(INSERT|REPLACE|CREATE|ALTER|DROP|TRUNCATE|DELETE|UPDATE)\b.*\bpma__/i) {
    $skip_stmt = 1 unless /;\s*$/;
    next;
  }
  next if /pma__/;

  s/DEFINER=`[^`]+`@`[^`]+`//g;
  print;
' "$INPUT_FILE" > "$OUTPUT_FILE"

echo "Created HostAfrica-safe dump: $OUTPUT_FILE"
