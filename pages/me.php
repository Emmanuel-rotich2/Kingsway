<?php
/**
 * User Profile Page
 *
 * Purpose: View and manage user profile
 * Features:
 * - Profile information display
 * - Password change
 * - Role and permissions overview
 * - Login history
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
            <p class="text-muted mb-0">View and manage your account information</p>
        </div>
    </div>

    <div class="row">
        <!-- Profile Card -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <div id="profileAvatar" class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:100px;height:100px;font-size:2.5rem;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <h5 id="profileName" class="mb-1">Loading...</h5>
                    <p class="text-muted mb-2" id="profileEmail">--</p>
                    <span class="badge bg-primary" id="profileRole">--</span>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Employee ID</span>
                        <span id="profileEmployeeId">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Phone</span>
                        <span id="profilePhone">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Department</span>
                        <span id="profileDepartment">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        <span id="profileStatus" class="badge bg-success">Active</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Last Login</span>
                        <span id="profileLastLogin">--</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Details & Settings -->
        <div class="col-md-8 mb-4">
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#profileInfo">
                        <i class="fas fa-info-circle me-1"></i> Profile Info
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#changePassword">
                        <i class="fas fa-lock me-1"></i> Change Password
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#myRoles">
                        <i class="fas fa-shield-alt me-1"></i> Roles & Permissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#loginHistory">
                        <i class="fas fa-history me-1"></i> Login History
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Profile Info Tab -->
                <div class="tab-pane fade show active" id="profileInfo">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-edit me-1"></i> Personal Information</h6>
                        </div>
                        <div class="card-body">
                            <form id="profileForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="firstName" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="lastName" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <input type="text" class="form-control" id="gender" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="text" class="form-control" id="dob" readonly>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" id="address" rows="2" readonly></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="changePassword">
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-key me-1"></i> Change Password</h6>
                        </div>
                        <div class="card-body">
                            <form id="passwordForm">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="currentPassword" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="newPassword" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirmPassword" required>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="button" class="btn btn-warning" onclick="ProfileController.changePassword()">
                                            <i class="fas fa-save me-1"></i> Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Roles & Permissions Tab -->
                <div class="tab-pane fade" id="myRoles">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-user-tag me-1"></i> My Roles & Permissions</h6>
                        </div>
                        <div class="card-body">
                            <h6>Assigned Roles</h6>
                            <div id="rolesList" class="mb-4">
                                <span class="text-muted">Loading...</span>
                            </div>
                            <h6>Permissions Summary</h6>
                            <div id="permissionsSummary">
                                <span class="text-muted">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login History Tab -->
                <div class="tab-pane fade" id="loginHistory">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-clock me-1"></i> Recent Login Activity</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="loginHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>IP Address</th>
                                            <th>Device</th>
                                            <th>Browser</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Loading login history...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/me.js?v=<?php echo time(); ?>"></script>
