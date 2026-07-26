<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'classTeacherDashboard',
    'title' => 'Class Teacher Dashboard',
    'subtitle' => 'Your class register, lessons, assessments and student progress.',
    'icon' => 'bi-people',
    'controller_file' => 'class_teacher_dashboard.js',
    'cards' => [
        ['id' => 'ctStudents', 'label' => 'My Students', 'icon' => 'bi-people', 'colour' => 'dsc-indigo', 'subtitle_id' => 'ctStudentsSub'],
        ['id' => 'ctAttendance', 'label' => 'Attendance Today', 'icon' => 'bi-person-check', 'colour' => 'dsc-green', 'subtitle_id' => 'ctAttendanceSub'],
        ['id' => 'ctAssessments', 'label' => 'Pending Assessments', 'icon' => 'bi-clipboard2-check', 'colour' => 'dsc-orange', 'subtitle_id' => 'ctAssessmentsSub'],
        ['id' => 'ctLessonPlans', 'label' => 'Lesson Plans', 'icon' => 'bi-journal-text', 'colour' => 'dsc-blue', 'subtitle_id' => 'ctLessonPlansSub']
    ],
    'charts' => [
        ['id' => 'ctAttendanceChart', 'title' => 'Weekly Attendance', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6'],
        ['id' => 'ctPerformanceChart', 'title' => 'Assessment Performance', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'ctScheduleBody', 'title' => 'Today’s Schedule', 'columns' => ['Time', 'Subject', 'Room', 'Status'], 'route' => 'timetable', 'column' => 'col-xl-6'],
        ['body_id' => 'ctRosterBody', 'title' => 'Student Roster', 'columns' => ['Admission No.', 'Student', 'Gender', 'Status'], 'route' => 'my_students_list', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Mark Attendance', 'route' => 'mark_attendance', 'icon' => 'bi-check2-square'],
        ['label' => 'Student List', 'route' => 'my_students_list', 'icon' => 'bi-people'],
        ['label' => 'Lesson Plans', 'route' => 'manage_lesson_plans', 'icon' => 'bi-journal-text'],
        ['label' => 'Enter Marks', 'route' => 'formative_assessments', 'icon' => 'bi-pencil-square']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
