-- Align workflow stage advancement procedure with the current workflow tables.
-- The previous procedure wrote instance_id/from_stage/to_stage into workflow_history,
-- but this schema stores those columns in workflow_stage_history.

DROP PROCEDURE IF EXISTS sp_advance_workflow_stage;

DELIMITER $$

CREATE PROCEDURE sp_advance_workflow_stage(
    IN p_instance_id INT,
    IN p_to_stage VARCHAR(50),
    IN p_action VARCHAR(50),
    IN p_user_id INT,
    IN p_remarks TEXT,
    IN p_data_json LONGTEXT
)
BEGIN
    DECLARE v_current_stage VARCHAR(50);

    SELECT current_stage
      INTO v_current_stage
      FROM workflow_instances
     WHERE id = p_instance_id;

    INSERT INTO workflow_stage_history (
        instance_id,
        stage_code,
        from_stage,
        to_stage,
        action_taken,
        processed_by,
        remarks,
        data_json
    ) VALUES (
        p_instance_id,
        p_to_stage,
        v_current_stage,
        p_to_stage,
        p_action,
        COALESCE(p_user_id, 1),
        p_remarks,
        p_data_json
    );

    UPDATE workflow_instances
       SET current_stage = p_to_stage,
           stage_code = p_to_stage
     WHERE id = p_instance_id;
END$$

DELIMITER ;
