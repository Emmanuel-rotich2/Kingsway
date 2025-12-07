<?php
// dashboards.php - Auto-generated dashboard config from database
// Generated: 2025-12-07 00:32:28
// DO NOT EDIT MANUALLY - regenerate using tools/generate_dashboard_config.php

return [
    // Role: System Administrator Dashboard
    'system_administrator' => [
        'label' => 'System Administrator Dashboard',
        'permissions' => array(
            0 => 'dashboard_system_administrator_access',
        ),
        'menu_items' => [
            [
                'label' => 'Academics',
                'url' => 'manage_academics',
                'icon' => 'bi-mortarboard',
                'permissions' => array(
                    0 => 'academics_all_permissions',
                ),
            ],
            [
                'label' => 'Attendance',
                'url' => 'mark_attendance',
                'icon' => 'bi-pencil-square',
                'permissions' => array(
                    0 => 'attendance_all_permissions',
                ),
            ],
            [
                'label' => 'Boarding',
                'url' => 'manage_boarding',
                'icon' => 'bi-house',
                'permissions' => array(
                    0 => 'boarding_all_permissions',
                ),
            ],
            [
                'label' => 'Communications',
                'url' => 'manage_communications',
                'icon' => 'bi-chat-dots',
                'permissions' => array(
                    0 => 'communications_all_permissions',
                ),
            ],
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_all_permissions',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_all_permissions',
                ),
            ],
            [
                'label' => 'System Settings',
                'url' => 'system_settings',
                'icon' => 'bi-gear',
                'permissions' => array(
                    0 => 'system_all_permissions',
                ),
            ],
            [
                'label' => 'Transport',
                'url' => 'manage_transport',
                'icon' => 'bi-bus-front',
                'permissions' => array(
                    0 => 'transport_all_permissions',
                ),
            ],
            [
                'label' => 'Users & Access',
                'url' => 'manage_users',
                'icon' => 'bi-person-gear',
                'permissions' => array(
                    0 => 'users_all_permissions',
                ),
            ],
            [
                'label' => 'Workflows',
                'url' => 'manage_workflows',
                'icon' => 'bi-diagram-3',
                'permissions' => array(
                    0 => 'workflow_all_permissions',
                ),
            ],
        ],
    ],

    // Role: Director/Owner Dashboard
    'director_owner' => [
        'label' => 'Director/Owner Dashboard',
        'permissions' => array(
            0 => 'dashboard_director_owner_access',
        ),
        'menu_items' => [
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_approve',
                    1 => 'students_create',
                    2 => 'students_delete',
                    3 => 'students_edit',
                    4 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: School Administrative Officer Dashboard
    'school_administrative_officer' => [
        'label' => 'School Administrative Officer Dashboard',
        'permissions' => array(
            0 => 'dashboard_school_administrative_officer_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_approve',
                    1 => 'academic_assessments_create',
                    2 => 'academic_assessments_delete',
                    3 => 'academic_assessments_edit',
                    4 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Classes',
                'url' => 'manage_classes',
                'icon' => 'bi-journal',
                'permissions' => array(
                    0 => 'academic_classes_approve',
                    1 => 'academic_classes_create',
                    2 => 'academic_classes_delete',
                    3 => 'academic_classes_edit',
                    4 => 'academic_classes_view',
                ),
            ],
            [
                'label' => 'Lesson Plans',
                'url' => 'manage_lesson_plans',
                'icon' => 'bi-file-text',
                'permissions' => array(
                    0 => 'academic_lesson_plans_approve',
                    1 => 'academic_lesson_plans_create',
                    2 => 'academic_lesson_plans_delete',
                    3 => 'academic_lesson_plans_edit',
                    4 => 'academic_lesson_plans_view',
                ),
            ],
            [
                'label' => 'Results',
                'url' => 'view_results',
                'icon' => 'bi-bar-chart',
                'permissions' => array(
                    0 => 'academic_results_approve',
                    1 => 'academic_results_create',
                    2 => 'academic_results_delete',
                    3 => 'academic_results_edit',
                    4 => 'academic_results_view',
                ),
            ],
            [
                'label' => 'Subjects',
                'url' => 'manage_subjects',
                'icon' => 'bi-book',
                'permissions' => array(
                    0 => 'academic_subjects_approve',
                    1 => 'academic_subjects_create',
                    2 => 'academic_subjects_delete',
                    3 => 'academic_subjects_edit',
                    4 => 'academic_subjects_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_approve',
                    1 => 'academic_timetable_create',
                    2 => 'academic_timetable_delete',
                    3 => 'academic_timetable_edit',
                    4 => 'academic_timetable_view',
                ),
            ],
            [
                'label' => 'Activities',
                'url' => 'manage_activities',
                'icon' => 'bi-trophy',
                'permissions' => array(
                    0 => 'activities_approve',
                    1 => 'activities_create',
                    2 => 'activities_delete',
                    3 => 'activities_edit',
                    4 => 'activities_view',
                ),
            ],
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_approve',
                    1 => 'students_create',
                    2 => 'students_delete',
                    3 => 'students_edit',
                    4 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: Headteacher Dashboard
    'headteacher' => [
        'label' => 'Headteacher Dashboard',
        'permissions' => array(
            0 => 'dashboard_headteacher_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_approve',
                    1 => 'academic_assessments_create',
                    2 => 'academic_assessments_delete',
                    3 => 'academic_assessments_edit',
                    4 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Classes',
                'url' => 'manage_classes',
                'icon' => 'bi-journal',
                'permissions' => array(
                    0 => 'academic_classes_approve',
                    1 => 'academic_classes_create',
                    2 => 'academic_classes_delete',
                    3 => 'academic_classes_edit',
                    4 => 'academic_classes_view',
                ),
            ],
            [
                'label' => 'Lesson Plans',
                'url' => 'manage_lesson_plans',
                'icon' => 'bi-file-text',
                'permissions' => array(
                    0 => 'academic_lesson_plans_approve',
                    1 => 'academic_lesson_plans_create',
                    2 => 'academic_lesson_plans_delete',
                    3 => 'academic_lesson_plans_edit',
                    4 => 'academic_lesson_plans_view',
                ),
            ],
            [
                'label' => 'Results',
                'url' => 'view_results',
                'icon' => 'bi-bar-chart',
                'permissions' => array(
                    0 => 'academic_results_approve',
                    1 => 'academic_results_create',
                    2 => 'academic_results_delete',
                    3 => 'academic_results_edit',
                    4 => 'academic_results_view',
                ),
            ],
            [
                'label' => 'Subjects',
                'url' => 'manage_subjects',
                'icon' => 'bi-book',
                'permissions' => array(
                    0 => 'academic_subjects_approve',
                    1 => 'academic_subjects_create',
                    2 => 'academic_subjects_delete',
                    3 => 'academic_subjects_edit',
                    4 => 'academic_subjects_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_approve',
                    1 => 'academic_timetable_create',
                    2 => 'academic_timetable_delete',
                    3 => 'academic_timetable_edit',
                    4 => 'academic_timetable_view',
                ),
            ],
            [
                'label' => 'Activities',
                'url' => 'manage_activities',
                'icon' => 'bi-trophy',
                'permissions' => array(
                    0 => 'activities_approve',
                    1 => 'activities_create',
                    2 => 'activities_delete',
                    3 => 'activities_edit',
                    4 => 'activities_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_approve',
                    1 => 'students_create',
                    2 => 'students_delete',
                    3 => 'students_edit',
                    4 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: Deputy Headteacher Dashboard
    'deputy_headteacher' => [
        'label' => 'Deputy Headteacher Dashboard',
        'permissions' => array(
            0 => 'dashboard_deputy_headteacher_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_approve',
                    1 => 'academic_assessments_create',
                    2 => 'academic_assessments_delete',
                    3 => 'academic_assessments_edit',
                    4 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Results',
                'url' => 'view_results',
                'icon' => 'bi-bar-chart',
                'permissions' => array(
                    0 => 'academic_results_approve',
                    1 => 'academic_results_create',
                    2 => 'academic_results_delete',
                    3 => 'academic_results_edit',
                    4 => 'academic_results_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: Class Teacher Dashboard
    'class_teacher' => [
        'label' => 'Class Teacher Dashboard',
        'permissions' => array(
            0 => 'dashboard_class_teacher_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_approve',
                    1 => 'academic_assessments_create',
                    2 => 'academic_assessments_delete',
                    3 => 'academic_assessments_edit',
                    4 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Lesson Plans',
                'url' => 'manage_lesson_plans',
                'icon' => 'bi-file-text',
                'permissions' => array(
                    0 => 'academic_lesson_plans_approve',
                    1 => 'academic_lesson_plans_create',
                    2 => 'academic_lesson_plans_delete',
                    3 => 'academic_lesson_plans_edit',
                    4 => 'academic_lesson_plans_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: Subject Teacher Dashboard
    'subject_teacher' => [
        'label' => 'Subject Teacher Dashboard',
        'permissions' => array(
            0 => 'dashboard_subject_teacher_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_approve',
                    1 => 'academic_assessments_create',
                    2 => 'academic_assessments_delete',
                    3 => 'academic_assessments_edit',
                    4 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Lesson Plans',
                'url' => 'manage_lesson_plans',
                'icon' => 'bi-file-text',
                'permissions' => array(
                    0 => 'academic_lesson_plans_approve',
                    1 => 'academic_lesson_plans_create',
                    2 => 'academic_lesson_plans_delete',
                    3 => 'academic_lesson_plans_edit',
                    4 => 'academic_lesson_plans_view',
                ),
            ],
            [
                'label' => 'Results',
                'url' => 'view_results',
                'icon' => 'bi-bar-chart',
                'permissions' => array(
                    0 => 'academic_results_approve',
                    1 => 'academic_results_create',
                    2 => 'academic_results_delete',
                    3 => 'academic_results_edit',
                    4 => 'academic_results_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: Intern/Student Teacher Dashboard
    'intern_student_teacher' => [
        'label' => 'Intern/Student Teacher Dashboard',
        'permissions' => array(
            0 => 'dashboard_intern_student_teacher_access',
        ),
        'menu_items' => [
            [
                'label' => 'Assessments',
                'url' => 'manage_assessments',
                'icon' => 'bi-clipboard-check',
                'permissions' => array(
                    0 => 'academic_assessments_view',
                ),
            ],
            [
                'label' => 'Lesson Plans',
                'url' => 'manage_lesson_plans',
                'icon' => 'bi-file-text',
                'permissions' => array(
                    0 => 'academic_lesson_plans_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: School Accountant Dashboard
    'school_accountant' => [
        'label' => 'School Accountant Dashboard',
        'permissions' => array(
            0 => 'dashboard_school_accountant_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Accounts Assistant Dashboard
    'accounts_assistant' => [
        'label' => 'Accounts Assistant Dashboard',
        'permissions' => array(
            0 => 'dashboard_accounts_assistant_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Registrar Dashboard
    'registrar' => [
        'label' => 'Registrar Dashboard',
        'permissions' => array(
            0 => 'dashboard_registrar_access',
        ),
        'menu_items' => [
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: Secretary Dashboard
    'secretary' => [
        'label' => 'Secretary Dashboard',
        'permissions' => array(
            0 => 'dashboard_secretary_access',
        ),
        'menu_items' => [
            [
                'label' => 'Classes',
                'url' => 'manage_classes',
                'icon' => 'bi-journal',
                'permissions' => array(
                    0 => 'academic_classes_view',
                ),
            ],
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_edit',
                    1 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: Store Manager Dashboard
    'store_manager' => [
        'label' => 'Store Manager Dashboard',
        'permissions' => array(
            0 => 'dashboard_store_manager_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Store Attendant Dashboard
    'store_attendant' => [
        'label' => 'Store Attendant Dashboard',
        'permissions' => array(
            0 => 'dashboard_store_attendant_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Catering Manager/Cook Lead Dashboard
    'catering_manager_cook_lead' => [
        'label' => 'Catering Manager/Cook Lead Dashboard',
        'permissions' => array(
            0 => 'dashboard_catering_manager_cook_lead_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Cook/Food Handler Dashboard
    'cook_food_handler' => [
        'label' => 'Cook/Food Handler Dashboard',
        'permissions' => array(
            0 => 'dashboard_cook_food_handler_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Matron/Housemother Dashboard
    'matron_housemother' => [
        'label' => 'Matron/Housemother Dashboard',
        'permissions' => array(
            0 => 'dashboard_matron_housemother_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: HOD - Food & Nutrition Dashboard
    'hod___food_&_nutrition' => [
        'label' => 'HOD - Food & Nutrition Dashboard',
        'permissions' => array(
            0 => 'dashboard_hod___food_&_nutrition_access',
        ),
        'menu_items' => [
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
        ],
    ],

    // Role: HOD - Games & Sports Dashboard
    'hod___games_&_sports' => [
        'label' => 'HOD - Games & Sports Dashboard',
        'permissions' => array(
            0 => 'dashboard_hod___games_&_sports_access',
        ),
        'menu_items' => [
            [
                'label' => 'Activities',
                'url' => 'manage_activities',
                'icon' => 'bi-trophy',
                'permissions' => array(
                    0 => 'activities_approve',
                    1 => 'activities_create',
                    2 => 'activities_delete',
                    3 => 'activities_edit',
                    4 => 'activities_view',
                ),
            ],
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
        ],
    ],

    // Role: HOD - Talent Development Dashboard
    'hod___talent_development' => [
        'label' => 'HOD - Talent Development Dashboard',
        'permissions' => array(
            0 => 'dashboard_hod___talent_development_access',
        ),
        'menu_items' => [
            [
                'label' => 'Activities',
                'url' => 'manage_activities',
                'icon' => 'bi-trophy',
                'permissions' => array(
                    0 => 'activities_approve',
                    1 => 'activities_create',
                    2 => 'activities_delete',
                    3 => 'activities_edit',
                    4 => 'activities_view',
                ),
            ],
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
        ],
    ],

    // Role: HOD - Transport Dashboard
    'hod___transport' => [
        'label' => 'HOD - Transport Dashboard',
        'permissions' => array(
            0 => 'dashboard_hod___transport_access',
        ),
        'menu_items' => [
            [
                'label' => 'Staff',
                'url' => 'manage_staff',
                'icon' => 'bi-person-workspace',
                'permissions' => array(
                    0 => 'staff_approve',
                    1 => 'staff_create',
                    2 => 'staff_delete',
                    3 => 'staff_edit',
                    4 => 'staff_view',
                ),
            ],
        ],
    ],

    // Role: Driver Dashboard
    'driver' => [
        'label' => 'Driver Dashboard',
        'permissions' => array(
            0 => 'dashboard_driver_access',
        ),
        'menu_items' => [
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: School Counselor/Chaplain Dashboard
    'school_counselor_chaplain' => [
        'label' => 'School Counselor/Chaplain Dashboard',
        'permissions' => array(
            0 => 'dashboard_school_counselor_chaplain_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Security Officer Dashboard
    'security_officer' => [
        'label' => 'Security Officer Dashboard',
        'permissions' => array(
            0 => 'dashboard_security_officer_access',
        ),
        'menu_items' => [
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: Cleaner/Janitor Dashboard
    'cleaner_janitor' => [
        'label' => 'Cleaner/Janitor Dashboard',
        'permissions' => array(
            0 => 'dashboard_cleaner_janitor_access',
        ),
        'menu_items' => [
            [
                'label' => 'Timetable',
                'url' => 'manage_timetable',
                'icon' => 'bi-calendar3',
                'permissions' => array(
                    0 => 'academic_timetable_view',
                ),
            ],
        ],
    ],

    // Role: Librarian Dashboard
    'librarian' => [
        'label' => 'Librarian Dashboard',
        'permissions' => array(
            0 => 'dashboard_librarian_access',
        ),
        'menu_items' => [
        ],
    ],

    // Role: Activities Coordinator Dashboard
    'activities_coordinator' => [
        'label' => 'Activities Coordinator Dashboard',
        'permissions' => array(
            0 => 'dashboard_activities_coordinator_access',
        ),
        'menu_items' => [
            [
                'label' => 'Activities',
                'url' => 'manage_activities',
                'icon' => 'bi-trophy',
                'permissions' => array(
                    0 => 'activities_approve',
                    1 => 'activities_create',
                    2 => 'activities_delete',
                    3 => 'activities_edit',
                    4 => 'activities_view',
                ),
            ],
        ],
    ],

    // Role: Parent/Guardian Dashboard
    'parent_guardian' => [
        'label' => 'Parent/Guardian Dashboard',
        'permissions' => array(
            0 => 'dashboard_parent_guardian_access',
        ),
        'menu_items' => [
            [
                'label' => 'Results',
                'url' => 'view_results',
                'icon' => 'bi-bar-chart',
                'permissions' => array(
                    0 => 'academic_results_view',
                ),
            ],
            [
                'label' => 'Students',
                'url' => 'manage_students',
                'icon' => 'bi-people',
                'permissions' => array(
                    0 => 'students_view',
                ),
            ],
        ],
    ],

    // Role: Visiting Staff Dashboard
    'visiting_staff' => [
        'label' => 'Visiting Staff Dashboard',
        'permissions' => array(
            0 => 'dashboard_visiting_staff_access',
        ),
        'menu_items' => [
        ],
    ],

];
