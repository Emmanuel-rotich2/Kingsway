<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/system_settings.php
?>

<div class="container mt-1">
    <h2 class="mb-4">
        <i class="bi bi-gear"></i> System Settings
    </h2>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#general">General</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#academic">Academic</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#finance">Finance</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#notifications">Notifications</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#security">Security</a>
        </li>
    </ul>

    <div class="tab-content">
        <div id="general" class="tab-pane fade show active">
            <div class="card">
                <div class="card-header">
                    <h5>General Settings</h5>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">School Name</label>
                            <input type="text" class="form-control" value="Kingsway School">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">School Logo</label>
                            <input type="file" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" value="info@kingsway.ac.ke">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" value="+254700000000">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="academic" class="tab-pane fade">
            <div class="card">
                <div class="card-header">
                    <h5>Academic Settings</h5>
                </div>
                <div class="card-body">
                    <p>Configure academic year, terms, grading system, and curriculum settings.</p>
                </div>
            </div>
        </div>

        <div id="finance" class="tab-pane fade">
            <div class="card">
                <div class="card-header">
                    <h5>Finance Settings</h5>
                </div>
                <div class="card-body">
                    <p>Configure payment methods, fee structures, and financial reporting.</p>
                </div>
            </div>
        </div>

        <div id="notifications" class="tab-pane fade">
            <div class="card">
                <div class="card-header">
                    <h5>Notification Settings</h5>
                </div>
                <div class="card-body">
                    <p>Configure SMS, email, and system notification preferences.</p>
                </div>
            </div>
        </div>

        <div id="security" class="tab-pane fade">
            <div class="card">
                <div class="card-header">
                    <h5>Security Settings</h5>
                </div>
                <div class="card-body">
                    <p>Configure password policies, session timeout, and security options.</p>
                </div>
            </div>
        </div>
    </div>
</div>