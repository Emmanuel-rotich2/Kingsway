<?php
/**
 * At-Risk Students — PARTIAL
 * Lists students flagged as at-risk: chronic absentees, open discipline cases, counseling referrals.
 * JS controller: js/pages/at_risk_students.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>At-Risk Students</h2>
      <small class="text-muted">Chronic absentees · Open discipline cases · Counseling referrals</small>
    </div>
    <button class="btn btn-outline-secondary" onclick="atRiskController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export CSV
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="arStatAbsent">—</div>
          <div class="text-muted small">Chronic Absentees</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="arStatCases">—</div>
          <div class="text-muted small">Active Discipline Cases</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="arStatCounseling">—</div>
          <div class="text-muted small">Counseling Referrals</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <ul class="nav nav-tabs mb-3" id="arFilterTabs">
    <li class="nav-item">
      <button class="nav-link active" data-ar-tab="all" onclick="atRiskController.filterByTab('all', this)">All</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-ar-tab="attendance" onclick="atRiskController.filterByTab('attendance', this)">Attendance</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-ar-tab="discipline" onclick="atRiskController.filterByTab('discipline', this)">Discipline</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-ar-tab="counseling" onclick="atRiskController.filterByTab('counseling', this)">Counseling</button>
    </li>
  </ul>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="arTableContainer">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>js/pages/at_risk_students.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => atRiskController.init());</script>
