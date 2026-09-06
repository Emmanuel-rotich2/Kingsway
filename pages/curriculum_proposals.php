<?php
/** Assignment-scoped curriculum proposals, review queue and version history. */
?>
<style>
.curriculum-workspace{--cw-green:#0f6b46;--cw-gold:#f4c20d}.cw-hero{background:linear-gradient(125deg,#083d2b,#118456);color:#fff;border-radius:18px;padding:1.5rem}.cw-scope-chip{display:inline-flex;gap:.4rem;align-items:center;background:#eef8f3;border:1px solid #cce7da;border-radius:999px;padding:.35rem .7rem;margin:.2rem;color:#155b41}.cw-card{border:1px solid #e4e9e7;border-left:5px solid var(--cw-green);border-radius:14px;background:#fff;padding:1rem;box-shadow:0 4px 16px rgba(20,55,40,.05)}.cw-card.submitted{border-left-color:#e4a900}.cw-card.rejected{border-left-color:#dc3545}.cw-card.approved{border-left-color:#198754}.cw-timeline{position:relative;padding-left:2rem}.cw-timeline:before{content:"";position:absolute;left:.6rem;top:.5rem;bottom:.5rem;width:3px;background:#d8e8df}.cw-event{position:relative;margin-bottom:1rem}.cw-event:before{content:"";position:absolute;left:-1.75rem;top:.35rem;width:14px;height:14px;border-radius:50%;background:var(--cw-gold);border:3px solid #fff;box-shadow:0 0 0 2px var(--cw-green)}.cw-diff{background:#f7f9f8;border-radius:10px;padding:.75rem;font-size:.9rem;white-space:pre-wrap}.cw-empty{padding:3rem;text-align:center;color:#718078;border:1px dashed #ccd8d1;border-radius:14px}
</style>

<div class="curriculum-workspace">
  <section class="cw-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><div class="small text-uppercase opacity-75 fw-bold">Academic governance</div><h3 class="mb-1">Curriculum Proposals & History</h3><p class="mb-0 opacity-75">Trace how every learning area changes across academic years and terms.</p></div>
    <button class="btn btn-warning fw-semibold" id="newProposalBtn"><i class="bi bi-plus-circle me-1"></i> New Proposal</button>
  </section>

  <div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong id="scopeTitle">Loading teaching scope…</strong><div class="text-muted small">Your access follows class and learning-area assignments for the selected academic year.</div></div><select class="form-select form-select-sm w-auto" id="yearFilter"></select></div>
    <div id="scopeChips" class="mt-2"></div>
  </div></div>

  <ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#proposalPane">Proposals <span class="badge text-bg-light ms-1" id="proposalCount">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#historyPane">Version History</button></li>
  </ul>
  <div class="tab-content">
    <section class="tab-pane fade show active" id="proposalPane">
      <div class="d-flex gap-2 flex-wrap mb-3"><select class="form-select form-select-sm w-auto" id="statusFilter"><option value="">All statuses</option><option value="submitted">Awaiting review</option><option value="draft">Draft</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><select class="form-select form-select-sm w-auto" id="entityFilter"><option value="">All curriculum items</option><option value="learning_area">Learning areas</option><option value="strand">Strands</option><option value="sub_strand">Sub-strands</option></select></div>
      <div class="row g-3" id="proposalCards"></div>
    </section>
    <section class="tab-pane fade" id="historyPane">
      <div class="d-flex gap-2 flex-wrap mb-3"><select class="form-select form-select-sm w-auto" id="historyYear"><option value="">All academic years</option></select><select class="form-select form-select-sm w-auto" id="historyArea"><option value="">All assigned learning areas</option></select><select class="form-select form-select-sm w-auto" id="historyEntity"><option value="">All levels</option><option value="learning_area">Learning area</option><option value="strand">Strand</option><option value="sub_strand">Sub-strand</option></select></div>
      <div class="cw-timeline" id="historyTimeline"></div>
    </section>
  </div>
</div>

<div class="modal fade" id="proposalModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Curriculum Change Proposal</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <form id="proposalForm"><input type="hidden" id="proposalId"><div class="row g-3">
    <div class="col-md-4"><label class="form-label">Curriculum item</label><select class="form-select" id="proposalEntity" required><option value="strand">Strand</option><option value="sub_strand">Sub-strand</option><option value="learning_area">Learning area</option></select></div>
    <div class="col-md-4"><label class="form-label">Change</label><select class="form-select" id="proposalAction" required><option value="create">Add</option><option value="update">Modify</option><option value="remove">Retire / remove</option></select></div>
    <div class="col-md-4"><label class="form-label">Effective term</label><select class="form-select" id="proposalTerm"><option value="">Whole academic year</option></select></div>
    <div class="col-md-6"><label class="form-label">Learning area</label><select class="form-select" id="proposalArea" required></select></div>
    <div class="col-md-6"><label class="form-label">Grade / class level</label><select class="form-select" id="proposalGrade" required></select></div>
    <div class="col-12 d-none" id="targetWrap"><label class="form-label">Existing item</label><select class="form-select" id="proposalTarget"></select></div>
    <div class="col-md-6 d-none" id="parentStrandWrap"><label class="form-label">Parent strand</label><select class="form-select" id="proposalParentStrand"></select></div>
    <div class="col-md-6 proposed-field"><label class="form-label">Proposed code</label><input class="form-control" id="proposalCode" maxlength="20"></div>
    <div class="col-md-6 proposed-field"><label class="form-label">Proposed name</label><input class="form-control" id="proposalName" maxlength="200"></div>
    <div class="col-12 proposed-field"><label class="form-label">Proposed description</label><textarea class="form-control" id="proposalDescription" rows="3"></textarea></div>
    <div class="col-12"><label class="form-label">Reason and expected academic benefit</label><textarea class="form-control" id="proposalRationale" rows="3" required></textarea></div>
    <div class="col-md-6 admin-source d-none"><label class="form-label">Authority source</label><select class="form-select" id="proposalSource"><option value="school">School decision</option><option value="ministry">Ministry of Education</option><option value="import">Curriculum import</option><option value="teacher">Teacher proposal</option></select></div>
    <div class="col-md-6 admin-source d-none"><label class="form-label">Circular / document reference</label><input class="form-control" id="proposalReference"></div>
  </div></form>
</div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-outline-success" id="saveDraftBtn">Save Draft</button><button class="btn btn-success" id="saveSubmitBtn">Save & Submit</button></div></div></div></div>

<div class="modal fade" id="reviewModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">School Administrator Review</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="reviewProposalId"><div id="reviewSummary" class="cw-diff mb-3"></div><label class="form-label">Review notes</label><textarea class="form-control" id="reviewNotes" rows="4"></textarea></div><div class="modal-footer"><button class="btn btn-outline-danger" data-decision="rejected">Reject</button><button class="btn btn-success" data-decision="approved">Approve & Apply</button></div></div></div></div>

<?php asset_script($appBase, 'js/pages/curriculum_proposals.js'); ?>
