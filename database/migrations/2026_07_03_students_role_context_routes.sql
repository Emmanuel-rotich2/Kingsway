-- Students role-context route registry and role grants.
-- Fixes route guard denials such as Director -> students_overview.

START TRANSACTION;

INSERT INTO routes (name, url, domain, module, description, controller, action, is_active)
VALUES
  ('students_overview', 'home.php?route=students_overview', 'SCHOOL', 'Students', 'Role-scoped student oversight view', NULL, NULL, 1),
  ('students_oversview', 'home.php?route=students_overview', 'SCHOOL', 'Students', 'Typo compatibility alias for students_overview', NULL, NULL, 1),
  ('academic_students', 'home.php?route=academic_students', 'SCHOOL', 'Students', 'Deputy Academic student context view', NULL, NULL, 1),
  ('discipline_students', 'home.php?route=discipline_students', 'SCHOOL', 'Students', 'Deputy Discipline student context view', NULL, NULL, 1),
  ('boarding_students', 'home.php?route=boarding_students', 'SCHOOL', 'Students', 'Boarding student context view', NULL, NULL, 1),
  ('catering_boarding_students', 'home.php?route=catering_boarding_students', 'SCHOOL', 'Students', 'Catering boarder meal-planning context view', NULL, NULL, 1),
  ('transport_passengers', 'home.php?route=transport_passengers', 'SCHOOL', 'Students', 'Driver assigned passenger context view', NULL, NULL, 1),
  ('student_welfare', 'home.php?route=student_welfare', 'SCHOOL', 'Students', 'Counselor and chaplain welfare student context view', NULL, NULL, 1),
  ('my_students_list', 'home.php?route=my_students_list', 'SCHOOL', 'Students', 'Class teacher assigned students context view', NULL, NULL, 1),
  ('subject_students_list', 'home.php?route=subject_students_list', 'SCHOOL', 'Students', 'Subject teacher assigned students context view', NULL, NULL, 1),
  ('students_by_class', 'home.php?route=students_by_class', 'SCHOOL', 'Students', 'Subject teacher students by class context view', NULL, NULL, 1),
  ('view_class_lists', 'home.php?route=view_class_lists', 'SCHOOL', 'Students', 'Assigned student class list context view', NULL, NULL, 1),
  ('all_students', 'home.php?route=all_students', 'SCHOOL', 'Students', 'Legacy safe scoped student directory', NULL, NULL, 1),
  ('student_profiles', 'home.php?route=student_profiles', 'SCHOOL', 'Students', 'Role-aware student profile view', NULL, NULL, 1)
ON DUPLICATE KEY UPDATE
  url = VALUES(url),
  domain = VALUES(domain),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- Admin/System Admin: operational and compatibility access.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN (
  'manage_students',
  'all_students',
  'student_profiles',
  'students_overview',
  'students_oversview',
  'academic_students',
  'discipline_students',
  'boarding_students',
  'catering_boarding_students',
  'transport_passengers',
  'student_welfare',
  'my_students_list',
  'subject_students_list',
  'students_by_class',
  'view_class_lists'
)
WHERE roles.id IN (2, 4)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Director and Headteacher: oversight.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('students_overview', 'students_oversview', 'all_students', 'student_profiles')
WHERE roles.id IN (3, 5)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Deputy Academic.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('academic_students', 'all_students', 'student_profiles', 'my_students_list')
WHERE roles.id = 6
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Deputy Discipline.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('discipline_students', 'student_welfare', 'all_students', 'student_profiles')
WHERE roles.id = 63
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Class Teacher.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('my_students_list', 'all_students', 'student_profiles', 'view_class_lists')
WHERE roles.id = 7
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Subject Teacher.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('subject_students_list', 'students_by_class', 'view_class_lists', 'all_students', 'student_profiles')
WHERE roles.id = 8
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Boarding Master / Matron.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('boarding_students', 'all_students', 'student_profiles')
WHERE roles.id = 18
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Cateress / Catering Manager.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('catering_boarding_students', 'all_students')
WHERE roles.id = 16
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Driver.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('transport_passengers', 'all_students')
WHERE roles.id = 23
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Chaplain / Counselor.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('student_welfare', 'all_students', 'student_profiles')
WHERE roles.id = 24
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Parent / Guardian roles, if present in an installation.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT roles.id, routes.id, 1
FROM roles
JOIN routes ON routes.name IN ('all_students', 'student_profiles')
WHERE LOWER(roles.name) IN ('parent', 'guardian')
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

COMMIT;
