<?php
/**
 * System Settings Page
 * HTML structure - logic handled by js/pages/system_settings.js
 * Accessible to: System Administrator, Director
 */
?>

<div>
    <h2 class="mb-4">
        <i class="bi bi-gear-fill"></i> System Settings
        <small class="text-muted fs-6 ms-2" id="settingsLastSaved"></small>
    </h2>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#sys-general" id="sys-general-tab">
                <i class="bi bi-building me-1"></i>General
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sys-academic" id="sys-academic-tab">
                <i class="bi bi-mortarboard me-1"></i>Academic
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sys-finance" id="sys-finance-tab">
                <i class="bi bi-currency-dollar me-1"></i>Finance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sys-notifications" id="sys-notifications-tab">
                <i class="bi bi-bell me-1"></i>Notifications
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sys-security" id="sys-security-tab">
                <i class="bi bi-shield-lock me-1"></i>Security
            </a>
        </li>
    </ul>

    <div class="tab-content" id="systemSettingsTabContent">
        <!-- General -->
        <div id="sys-general" class="tab-pane fade show active">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">General Settings</h5></div>
                <div class="card-body">
                    <form id="sysGeneralForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">School Name</label>
                                <input type="text" id="sysSchoolName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Code / KNEC Code</label>
                                <input type="text" id="sysSchoolCode" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Contact Email</label>
                                <input type="email" id="sysEmail" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" id="sysPhone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Physical Address</label>
                            <input type="text" id="sysAddress" class="form-control">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Headteacher Name</label>
                                <input type="text" id="sysHeadteacher" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Logo URL</label>
                                <input type="text" id="sysLogoUrl" class="form-control" placeholder="/images/logo.png">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveGeneralBtn">
                            <i class="bi bi-save me-1"></i>Save General Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Academic -->
        <div id="sys-academic" class="tab-pane fade">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Academic Settings</h5></div>
                <div class="card-body">
                    <form id="sysAcademicForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Current Academic Year</label>
                                <input type="text" id="sysAcademicYear" class="form-control" placeholder="2026">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Terms Per Year</label>
                                <select id="sysTermsPerYear" class="form-select">
                                    <option value="2">2 Terms</option>
                                    <option value="3" selected>3 Terms</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pass Mark (%)</label>
                                <input type="number" id="sysPassMark" class="form-control" min="0" max="100" value="50">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Curriculum</label>
                                <select id="sysCurriculum" class="form-select">
                                    <option value="cbc">CBC (Competency Based Curriculum)</option>
                                    <option value="8-4-4">8-4-4</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grading Scale</label>
                                <select id="sysGradingScale" class="form-select">
                                    <option value="exceeding">Exceeding/Meeting/Approaching/Below</option>
                                    <option value="a-e">A-E</option>
                                    <option value="1-4">1-4</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveAcademicBtn">
                            <i class="bi bi-save me-1"></i>Save Academic Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Finance -->
        <div id="sys-finance" class="tab-pane fade">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Finance Settings</h5></div>
                <div class="card-body">
                    <form id="sysFinanceForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Currency</label>
                                <input type="text" id="sysCurrency" class="form-control" value="KES">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" id="sysTaxRate" class="form-control" step="0.01" min="0" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Late Fee Penalty (%)</label>
                                <input type="number" id="sysLateFee" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">School Bank Account</label>
                                <input type="text" id="sysBankAccount" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" id="sysBankName" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">M-Pesa Paybill / Till Number</label>
                                <input type="text" id="sysMpesaPaybill" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">M-Pesa Account Name</label>
                                <input type="text" id="sysMpesaAccount" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveFinanceBtn">
                            <i class="bi bi-save me-1"></i>Save Finance Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div id="sys-notifications" class="tab-pane fade">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Notification Settings</h5></div>
                <div class="card-body">
                    <form id="sysNotificationsForm">
                        <h6 class="text-muted mb-3">SMS (Africa's Talking)</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysEnableSMS" class="form-check-input">
                                    <label class="form-check-label" for="sysEnableSMS">Enable SMS Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysSmsOnPayment" class="form-check-input">
                                    <label class="form-check-label" for="sysSmsOnPayment">SMS on Fee Payment</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysSmsOnAbsence" class="form-check-input">
                                    <label class="form-check-label" for="sysSmsOnAbsence">SMS on Student Absence</label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3">Email</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysEnableEmail" class="form-check-input">
                                    <label class="form-check-label" for="sysEnableEmail">Enable Email Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Admin Email (receives system alerts)</label>
                                <input type="email" id="sysAdminEmail" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveNotificationsBtn">
                            <i class="bi bi-save me-1"></i>Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div id="sys-security" class="tab-pane fade">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Security Settings</h5></div>
                <div class="card-body">
                    <form id="sysSecurityForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" id="sysSessionTimeout" class="form-control" min="5" max="480" value="60">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Max Login Attempts</label>
                                <input type="number" id="sysMaxLoginAttempts" class="form-control" min="3" max="10" value="5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lockout Duration (minutes)</label>
                                <input type="number" id="sysLockoutDuration" class="form-control" min="1" value="15">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysRequire2FA" class="form-check-input">
                                    <label class="form-check-label" for="sysRequire2FA">Require 2FA for Admin Roles</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" id="sysLogAllActivity" class="form-check-input">
                                    <label class="form-check-label" for="sysLogAllActivity">Log All User Activity</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password Min Length</label>
                                <input type="number" id="sysPasswordMinLen" class="form-control" min="6" max="32" value="8">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveSecurityBtn">
                            <i class="bi bi-save me-1"></i>Save Security Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $appBase ?>js/pages/system_settings.js"></script>
