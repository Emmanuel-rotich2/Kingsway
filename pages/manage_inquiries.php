<?php
/* Contact Inquiries — standalone page (split from Website Management). */

?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-envelope-open text-success me-2"></i>Contact Inquiries</h4>
      <p class="text-muted small mb-0 mt-1">Review messages sent through the public contact page.</p>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fce4ec"><i class="bi bi-envelope-open text-danger"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statInquiries">—</div><div class="text-muted small">Total Inquiries</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fff8e1"><i class="bi bi-bell text-warning"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statNew">—</div><div class="text-muted small">New / Unread</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <select id="inquiryStatusFilter" class="form-select form-select-sm" style="width:160px">
      <option value="">All statuses</option>
      <option value="new">New</option>
      <option value="read">Read</option>
      <option value="replied">Replied</option>
    </select>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="inquiriesController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Name</th><th scope="col">Email</th><th scope="col">Phone</th><th scope="col">Subject</th><th scope="col">Message</th><th scope="col">Status</th><th scope="col">Date</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="inquiriesTableBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/manage_inquiries.js'); ?>
