DROP TRIGGER IF EXISTS trg_log_scheme_changes;

DELIMITER //
CREATE TRIGGER trg_log_scheme_changes
AFTER UPDATE ON schemes_of_work
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
    VALUES (
        'UPDATE',
        'schemes_of_work',
        NEW.id,
        (SELECT s.user_id FROM staff s WHERE s.id = NEW.approved_by LIMIT 1),
        JSON_OBJECT(
            'old_status', OLD.status,
            'new_status', NEW.status,
            'old_week_number', OLD.week_number,
            'new_week_number', NEW.week_number
        ),
        'success',
        NOW()
    );
END//
DELIMITER ;
