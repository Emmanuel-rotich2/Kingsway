-- ============================================================================
-- Security Fixes Migration - June 25, 2026
-- ============================================================================
-- This migration records the security fixes applied to the payment webhook
-- processing and authentication systems.
--
-- Fixed Issues:
-- 1. CRITICAL: Payment webhook signature validation was not enforced
-- 2. CRITICAL: Race condition in C2B payment processing (TOCTOU)
-- 3. HIGH: Hardcoded debug credentials removed from production code
-- 4. HIGH: Missing payment amount validation against outstanding balance
-- 5. MEDIUM: Webhook signature validation configuration not enforced
-- 6. MEDIUM: Inadequate error handling masking critical failures
--
-- Fixes Applied:
-- - Removed hardcoded TEST_USER from AuthMiddleware.php
-- - Added mandatory signature validation to BankPaymentWebhook
-- - Wrapped C2B payment insertion in atomic transaction with row-level locking
-- - Added payment amount validation with outstanding balance checks
-- - Improved error handling to distinguish expected vs unexpected errors
-- - Updated PaymentsController to pass actual request headers to webhook handlers
-- ============================================================================

-- Add security audit table for tracking webhook processing issues
CREATE TABLE IF NOT EXISTS payment_security_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('signature_validation_failed', 'duplicate_payment_attempt', 'invalid_amount', 'balance_exceeded', 'race_condition_detected') NOT NULL,
    webhook_source VARCHAR(50) NOT NULL,
    transaction_ref VARCHAR(255),
    student_id INT,
    amount DECIMAL(10, 2),
    ip_address VARCHAR(45),
    headers JSON,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_event_type (event_type),
    INDEX idx_transaction_ref (transaction_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update payment_webhooks_log to include security flags
ALTER TABLE payment_webhooks_log
ADD COLUMN IF NOT EXISTS signature_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS validation_error VARCHAR(255),
ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45),
ADD COLUMN IF NOT EXISTS request_method VARCHAR(10),
ADD COLUMN IF NOT EXISTS requires_review BOOLEAN DEFAULT FALSE;

-- Create stored procedure to record security events
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sp_record_payment_security_event(
    IN p_event_type VARCHAR(50),
    IN p_webhook_source VARCHAR(50),
    IN p_transaction_ref VARCHAR(255),
    IN p_student_id INT,
    IN p_amount DECIMAL(10, 2),
    IN p_ip_address VARCHAR(45),
    IN p_details TEXT
)
BEGIN
    INSERT INTO payment_security_audit (
        event_type,
        webhook_source,
        transaction_ref,
        student_id,
        amount,
        ip_address,
        details
    ) VALUES (
        p_event_type,
        p_webhook_source,
        p_transaction_ref,
        p_student_id,
        p_amount,
        p_ip_address,
        p_details
    );
END //
DELIMITER ;

-- Create view for security monitoring
CREATE OR REPLACE VIEW v_payment_security_alerts AS
SELECT
    id,
    event_type,
    webhook_source,
    transaction_ref,
    student_id,
    amount,
    ip_address,
    details,
    created_at,
    CASE
        WHEN event_type = 'signature_validation_failed' THEN 'HIGH'
        WHEN event_type = 'race_condition_detected' THEN 'CRITICAL'
        WHEN event_type = 'invalid_amount' THEN 'MEDIUM'
        WHEN event_type = 'balance_exceeded' THEN 'MEDIUM'
        ELSE 'LOW'
    END AS severity,
    CASE
        WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 1 THEN 'Recent'
        WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 1 THEN 'Today'
        ELSE 'Older'
    END AS time_window
FROM payment_security_audit
ORDER BY created_at DESC;

-- Create index for payment validation
CREATE INDEX IF NOT EXISTS idx_mpesa_code ON mpesa_transactions(mpesa_code, created_at);
CREATE INDEX IF NOT EXISTS idx_bank_ref ON bank_transactions(transaction_ref, created_at);

-- Log the migration completion
INSERT INTO system_migrations (name, description, status, applied_at)
VALUES (
    'security_fixes_2026_06_25',
    'Security fixes: webhook validation, race condition handling, amount validation, error handling improvements',
    'applied',
    NOW()
) ON DUPLICATE KEY UPDATE applied_at = NOW();
