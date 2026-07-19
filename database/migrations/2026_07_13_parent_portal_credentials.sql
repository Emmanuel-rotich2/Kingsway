-- ============================================================================
-- 2026_07_13_parent_portal_credentials.sql
-- Provision demo parent-portal passwords so the portal is testable end-to-end.
--
-- Demo login: any active parent's email + password = `parent123`
-- (bcrypt of the string 'parent123', PASSWORD_DEFAULT).
--
-- This is a DEMO/seed credential for local testing. In production, parents must
-- set their own password via the reset/OTP flow and these rows should be
-- rotated. Do NOT treat this as a secure credential.
-- ============================================================================

UPDATE parents
SET portal_password = '$2y$12$EeZZTGwsMN1SQUwjVkHc5.vCESuQgmCsCAvEyqwhGrsHUIBWh32ym'
WHERE status = 'active'
  AND email IS NOT NULL
  AND email <> '';

-- Report how many rows were provisioned (informational; safe to run repeatedly)
SELECT ROW_COUNT() AS parents_provisioned;
