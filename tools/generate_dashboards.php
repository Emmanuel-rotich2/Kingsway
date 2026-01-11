<?php
/**
 * Generate all dashboard files for the 29 roles
 */

$roles = [
    ['key' => 'system_administrator', 'label' => 'System Administrator', 'icon' => 'bi-gear-fill', 'color' => '#6f42c1'],
    ['key' => 'director_owner', 'label' => 'Director/Owner', 'icon' => 'bi-building', 'color' => '#dc3545'],
    ['key' => 'school_administrative_officer', 'label' => 'School Administrative Officer', 'icon' => 'bi-briefcase', 'color' => '#0d6efd'],
    ['key' => 'headteacher', 'label' => 'Headteacher', 'icon' => 'bi-mortarboard-fill', 'color' => '#198754'],
    ['key' => 'deputy_head_academic', 'label' => 'Deputy Head - Academic', 'icon' => 'bi-person-badge', 'color' => '#0dcaf0'],
    ['key' => 'deputy_head_discipline', 'label' => 'Deputy Head - Discipline', 'icon' => 'bi-person-badge', 'color' => '#0dcaf0'],
    ['key' => 'class_teacher', 'label' => 'Class Teacher', 'icon' => 'bi-journal-text', 'color' => '#6610f2'],
    ['key' => 'subject_teacher', 'label' => 'Subject Teacher', 'icon' => 'bi-book', 'color' => '#fd7e14'],
    ['key' => 'intern_student_teacher', 'label' => 'Intern/Student Teacher', 'icon' => 'bi-person-workspace', 'color' => '#20c997'],
    ['key' => 'school_accountant', 'label' => 'School Accountant', 'icon' => 'bi-calculator', 'color' => '#ffc107'],
    ['key' => 'accounts_assistant', 'label' => 'Accounts Assistant', 'icon' => 'bi-cash-coin', 'color' => '#ffca2c'],
    ['key' => 'registrar', 'label' => 'Registrar', 'icon' => 'bi-person-vcard', 'color' => '#0d6efd'],
    ['key' => 'secretary', 'label' => 'Secretary', 'icon' => 'bi-file-earmark-text', 'color' => '#6c757d'],
    ['key' => 'store_manager', 'label' => 'Store Manager', 'icon' => 'bi-box-seam', 'color' => '#795548'],
    ['key' => 'store_attendant', 'label' => 'Store Attendant', 'icon' => 'bi-box', 'color' => '#8d6e63'],
    ['key' => 'catering_manager_cook_lead', 'label' => 'Catering Manager/Cook Lead', 'icon' => 'bi-egg-fried', 'color' => '#e91e63'],
    ['key' => 'cook_food_handler', 'label' => 'Cook/Food Handler', 'icon' => 'bi-egg', 'color' => '#f06292'],
    ['key' => 'matron_housemother', 'label' => 'Matron/Housemother', 'icon' => 'bi-house-heart', 'color' => '#9c27b0'],
    ['key' => 'hod_food_nutrition', 'label' => 'HOD - Food & Nutrition', 'icon' => 'bi-apple', 'color' => '#4caf50'],
    ['key' => 'hod_games_sports', 'label' => 'HOD - Games & Sports', 'icon' => 'bi-trophy', 'color' => '#ff9800'],
    ['key' => 'hod_talent_development', 'label' => 'HOD - Talent Development', 'icon' => 'bi-star', 'color' => '#ffd700'],
    ['key' => 'hod_transport', 'label' => 'HOD - Transport', 'icon' => 'bi-bus-front', 'color' => '#607d8b'],
    ['key' => 'driver', 'label' => 'Driver', 'icon' => 'bi-car-front', 'color' => '#455a64'],
    ['key' => 'school_counselor_chaplain', 'label' => 'School Counselor/Chaplain', 'icon' => 'bi-heart-pulse', 'color' => '#e91e63'],
    ['key' => 'security_officer', 'label' => 'Security Officer', 'icon' => 'bi-shield', 'color' => '#212529'],
    ['key' => 'cleaner_janitor', 'label' => 'Cleaner/Janitor', 'icon' => 'bi-droplet', 'color' => '#17a2b8'],
    ['key' => 'librarian', 'label' => 'Librarian', 'icon' => 'bi-book-half', 'color' => '#6f42c1'],
    ['key' => 'activities_coordinator', 'label' => 'Activities Coordinator', 'icon' => 'bi-calendar-event', 'color' => '#fd7e14'],
    ['key' => 'parent_guardian', 'label' => 'Parent/Guardian', 'icon' => 'bi-people', 'color' => '#0dcaf0'],
    ['key' => 'visiting_staff', 'label' => 'Visiting Staff', 'icon' => 'bi-person-check', 'color' => '#6c757d'],
];

foreach ($roles as $role) {
    $filename = __DIR__ . '/../components/dashboards/' . $role['key'] . '_dashboard.php';

    $template = <<<PHP
<?php
// {$role['label']} Dashboard
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

\$summaryCards = [
    [
        'title' => 'Overview',
        'count' => 0,
        'percent' => 100,
        'days' => 1,
        'icon' => '{$role['icon']}',
        'bgColor' => '{$role['color']}',
        'iconColor' => 'text-white',
        'iconSize' => 'fs-3',
        'textColor' => 'text-white',
        'subTextColor' => 'text-white-50',
        'cardClass' => 'card-rounded small-card shadow-sm',
        'iconPosition' => 'start'
    ]
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0"><i class="{$role['icon']} me-2"></i>{$role['label']} Dashboard</h2>
            <p class="text-muted">Welcome to your dashboard</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php foreach (\$summaryCards as \$card): ?>
            <div class="col-md-6 col-lg-3">
                <?php renderCard(\$card); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header" style="background-color: {$role['color']}; color: white;">
                    <h5 class="mb-0"><i class="{$role['icon']} me-2"></i>Dashboard Content</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Dashboard content and tools will be displayed here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

PHP;

    file_put_contents($filename, $template);
    echo "✅ Created: {$role['key']}_dashboard.php\n";
}

echo "\n🎉 All dashboard files created successfully!\n";
echo "📁 Location: components/dashboards/\n";
echo "📊 Total files: " . count($roles) . "\n";
