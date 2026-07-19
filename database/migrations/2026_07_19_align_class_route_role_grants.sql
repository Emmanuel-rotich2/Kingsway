START TRANSACTION;

DELETE rr
FROM role_routes rr
JOIN routes r ON r.id = rr.route_id
WHERE r.name = 'manage_classes'
  AND rr.role_id NOT IN (3, 4, 5, 6);

DELETE rr
FROM role_routes rr
JOIN routes r ON r.id = rr.route_id
WHERE r.name = 'class_streams'
  AND rr.role_id NOT IN (4, 6);

DELETE rr
FROM role_routes rr
JOIN routes r ON r.id = rr.route_id
WHERE r.name = 'class_capacity'
  AND rr.role_id NOT IN (4);

DELETE rr
FROM role_routes rr
JOIN routes r ON r.id = rr.route_id
WHERE r.name = 'student_promotion'
  AND rr.role_id NOT IN (4, 6);

UPDATE role_routes rr
JOIN routes r ON r.id = rr.route_id
SET rr.is_allowed = 1
WHERE (r.name = 'manage_classes' AND rr.role_id IN (3, 4, 5, 6))
   OR (r.name = 'class_streams' AND rr.role_id IN (4, 6))
   OR (r.name = 'class_capacity' AND rr.role_id IN (4))
   OR (r.name = 'student_promotion' AND rr.role_id IN (4, 6));

INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT desired.role_id, r.id, 1
FROM routes r
JOIN (
    SELECT 'manage_classes' AS route_name, 3 AS role_id UNION ALL
    SELECT 'manage_classes', 4 UNION ALL
    SELECT 'manage_classes', 5 UNION ALL
    SELECT 'manage_classes', 6 UNION ALL
    SELECT 'class_streams', 4 UNION ALL
    SELECT 'class_streams', 6 UNION ALL
    SELECT 'class_capacity', 4 UNION ALL
    SELECT 'student_promotion', 4 UNION ALL
    SELECT 'student_promotion', 6
) desired ON desired.route_name = r.name
LEFT JOIN role_routes existing
    ON existing.route_id = r.id
   AND existing.role_id = desired.role_id
WHERE existing.id IS NULL;

COMMIT;
