<?php
/**
 * Staff Appointments Page
 * Handles internal appointments for existing staff and new staff appointments from recruitment.
 */
if (!isset($staffAppointmentsTitle)) {
    $staffAppointmentsTitle = 'Staff Appointments';
}
if (!isset($staffAppointmentsDescription)) {
    $staffAppointmentsDescription = 'Manage existing-staff appointments and new-staff recruitment appointments separately.';
}
if (isset($staffAppointmentsContext) && is_array($staffAppointmentsContext)) {
    echo '<script>window.STAFF_APPOINTMENTS_CONTEXT = ' .
        json_encode($staffAppointmentsContext, JSON_UNESCAPED_SLASHES) .
        ';</script>' . PHP_EOL;
}
?>

<div id="staff-appointments-page" class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars($staffAppointmentsTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($staffAppointmentsDescription, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button class="btn btn-outline-success" id="refreshStaffAppointmentsBtn" type="button">
            <i class="fas fa-sync-alt me-2"></i>Refresh
        </button>
    </div>

    <div id="staffAppointmentsAlert" class="alert d-none" role="alert"></div>

    <div class="row g-3 mb-4" id="staffAppointmentsSummary">
        <div class="col-md-6 col-xl-3" data-appointment-card="internal_pending">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Internal Pending</div>
                    <div class="display-6 fw-bold text-success" data-summary="internal_pending">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" data-appointment-card="internal_approved">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Internal Approved</div>
                    <div class="display-6 fw-bold text-primary" data-summary="internal_approved">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" data-appointment-card="new_submitted">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">New Pending</div>
                    <div class="display-6 fw-bold text-success" data-summary="new_submitted">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" data-appointment-card="new_approved">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Ready to Onboard</div>
                    <div class="display-6 fw-bold text-warning" data-summary="new_approved">0</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="staffAppointmentsTabs" role="tablist">
	        <li class="nav-item" role="presentation" data-appointment-tab="internal">
            <button class="nav-link active" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internalAppointmentsPane" type="button" role="tab">
                Internal Appointments
            </button>
        </li>
	        <li class="nav-item" role="presentation" data-appointment-tab="new">
            <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#newAppointmentsPane" type="button" role="tab">
                New Staff Appointments
            </button>
        </li>
    </ul>

    <div class="tab-content">
	        <section class="tab-pane fade show active" id="internalAppointmentsPane" role="tabpanel" aria-labelledby="internal-tab" data-appointment-pane="internal">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-0">Existing Staff Appointment Queue</h2>
                        <div class="text-muted small">Transfers, substantive appointments, reclassifications, and temporary acting roles.</div>
                    </div>
                    <button class="btn btn-success" id="openInternalAppointmentForm" type="button">
                        <i class="fas fa-user-tag me-2"></i>Submit Internal Appointment
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
	                                <tr id="internalAppointmentsHeader">
                                    <th>Staff</th>
                                    <th>Type</th>
                                    <th>Position Change</th>
                                    <th>Department Change</th>
                                    <th>Salary Change</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="internalAppointmentsBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">Loading internal appointments...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

	        <section class="tab-pane fade" id="newAppointmentsPane" role="tabpanel" aria-labelledby="new-tab" data-appointment-pane="new">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">New Staff Appointment Queue</h2>
                    <div class="text-muted small">Candidates from recruitment/careers move here after interview success for Director approval and School Admin onboarding.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
	                                <tr id="newAppointmentsHeader">
                                    <th>Candidate</th>
                                    <th>Contact</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Start Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="newAppointmentsBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">Loading new staff appointments...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php
$staffAccessJs = __DIR__ . '/../js/pages/staff_access.js';
$staffAppointmentsJs = __DIR__ . '/../js/pages/staff_appointments.js';
?>
<script src="<?= $appBase ?>/js/pages/staff_access.js?v=<?= file_exists($staffAccessJs) ? filemtime($staffAccessJs) : time() ?>"></script>
<script src="<?= $appBase ?>/js/pages/staff_appointments.js?v=<?= file_exists($staffAppointmentsJs) ? filemtime($staffAppointmentsJs) : time() ?>"></script>
