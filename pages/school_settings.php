<?php
/**
 * School Settings Page
 * HTML structure only - all logic in js/pages/settings.js (schoolSettingsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-primary text-white">
    <h2 class="mb-0">⚙️ School Settings</h2>
  </div>
  <div class="card-body">
    <!-- Settings Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">Academic</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button" role="tab">Fees & Finance</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">System</button>
      </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- General Settings -->
      <div class="tab-pane fade show active" id="general" role="tabpanel">
        <form id="generalSettingsForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">School Name</label>
              <input type="text" id="schoolName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">School Code</label>
              <input type="text" id="schoolCode" class="form-control" required>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" id="schoolEmail" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" id="schoolPhone" class="form-control" required>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Address</label>
              <input type="text" id="schoolAddress" class="form-control" required>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Principal Name</label>
              <input type="text" id="principalName" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Deputy Principal Name</label>
              <input type="text" id="deputyPrincipalName" class="form-control">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Save General Settings</button>
        </form>
      </div>

      <!-- Academic Settings -->
      <div class="tab-pane fade" id="academic" role="tabpanel">
        <form id="academicSettingsForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Current Academic Year</label>
              <input type="text" id="academicYear" class="form-control" placeholder="e.g., 2024" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Academic Calendar Type</label>
              <select id="academicCalendar" class="form-select">
                <option value="trimester">Trimester</option>
                <option value="semester">Semester</option>
                <option value="term">Term</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Grading Scale</label>
              <select id="gradingScale" class="form-select">
                <option value="a-f">A-F (100-0)</option>
                <option value="1-9">1-9 Scale</option>
                <option value="4.0">4.0 GPA</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Pass Mark</label>
              <input type="number" id="passMark" class="form-control" min="0" max="100" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Save Academic Settings</button>
        </form>
      </div>

      <!-- Fees & Finance Settings -->
      <div class="tab-pane fade" id="fees" role="tabpanel">
        <form id="feesSettingsForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Default Currency</label>
              <input type="text" id="currency" class="form-control" placeholder="KES" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tax Rate (%)</label>
              <input type="number" id="taxRate" class="form-control" step="0.01" min="0" max="100" required>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Bank Account</label>
              <input type="text" id="bankAccount" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Bank Name</label>
              <input type="text" id="bankName" class="form-control">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Save Finance Settings</button>
        </form>
      </div>

      <!-- System Settings -->
      <div class="tab-pane fade" id="system" role="tabpanel">
        <form id="systemSettingsForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Backup Frequency</label>
              <select id="backupFrequency" class="form-select">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Session Timeout (minutes)</label>
              <input type="number" id="sessionTimeout" class="form-control" min="5" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-check-label">
              <input type="checkbox" id="enableNotifications" class="form-check-input">
              Enable Email Notifications
            </label>
          </div>
          <div class="mb-3">
            <label class="form-check-label">
              <input type="checkbox" id="enableSMS" class="form-check-input">
              Enable SMS Notifications
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Save System Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>