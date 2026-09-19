<?php
/* Create Lesson Plan — standalone creation page for teachers/interns. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.clp-form label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.clp-form input,.clp-form select,.clp-form textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.clp-form input:focus,.clp-form select:focus,.clp-form textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-journal-plus text-success me-2"></i>Create Lesson Plan</h4>
      <p class="text-muted small mb-0 mt-1">Draft a lesson plan for one of your assigned learning areas, then submit for review.</p>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body clp-form">
      <form id="lessonPlanForm">
        <div class="row g-3">
          <div class="col-12"><label>Approved Scheme Row *</label><select id="lpScheme" required><option value="">Loading approved scheme rows…</option></select><div id="lpSchemeHint" class="form-text">Choose the week and planned scheme row first. The lesson date must fall within that week.</div><div class="card border-success-subtle bg-light mt-2"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2"><div><strong><i class="bi bi-stars text-success me-1"></i>Lesson-plan assistant</strong><div class="small text-muted">Drafts from the approved scheme context; teacher editing and academic approval remain required.</div></div><button type="button" class="btn btn-outline-success btn-sm" id="queueAiLessonPlanDraft"><i class="bi bi-stars me-1"></i>Draft lesson plan</button></div><div id="aiLessonPlanDrafts" class="mt-2"></div></div></div></div>
          <div class="col-md-6"><label>Lesson Focus *</label><input type="text" name="topic" id="lpTopic" required></div>
          <div class="col-md-6"><label>Subtopic</label><input type="text" name="subtopic" id="lpSubtopic"></div>
          <div class="col-md-4">
            <label>Learning Area</label>
            <select name="learning_area_id" id="lpLearningArea" required disabled><option value="">Select a scheme row</option></select>
          </div>
          <div class="col-md-4">
            <label>Strand / Sub-strand</label>
            <select name="unit_id" id="lpUnit" required disabled><option value="">Select a scheme row</option></select>
          </div>
          <div class="col-md-4" id="lpClassField">
            <label>Class / Stream</label>
            <select name="stream_id" id="lpStream" required disabled><option value="">Select a scheme row</option></select>
            <input type="hidden" name="class_id" id="lpClass">
          </div>
          <div class="col-md-4" id="lpTeacherField">
            <label>Teacher *</label>
            <select name="teacher_id" id="lpTeacher" required><option value="">Select Teacher</option></select>
          </div>
          <div class="col-md-4"><label>Lesson Date *</label><input type="date" name="lesson_date" id="lpDate" required></div>
          <div class="col-md-4"><label>Duration (minutes) *</label><input type="number" name="duration" id="lpDuration" value="40" min="1" required></div>
          <div class="col-12"><label>Specific learning outcomes *</label><div id="lpOutcomes" class="border rounded p-2 bg-light"><span class="text-muted small">Select an approved scheme row first.</span></div></div>
          <div class="col-12"><label>Learning experiences *</label><div id="lpExperiences" class="border rounded p-2 bg-light"><span class="text-muted small">Select an approved scheme row first.</span></div></div>
          <div class="col-12"><label>Lesson activities *</label><div id="lpActivities" class="border rounded p-2 bg-light"><span class="text-muted small">Add the concrete activities delivered in this lesson.</span></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="lpAddActivity"><i class="bi bi-plus"></i> Add activity</button></div>
          <div class="col-md-6"><label>Resources / Teaching Aids</label><div id="lpResources" class="border rounded p-2 bg-light"><span class="text-muted small">No resources configured for this sub-strand yet.</span></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="lpAddResource"><i class="bi bi-plus"></i> Add resource</button></div>
          <div class="col-md-6"><label>Assessment tools</label><div id="lpAssessmentTools" class="border rounded p-2 bg-light"><span class="text-muted small">Assessment tools configured for this learning area will appear here.</span></div></div>
          <div class="col-md-6"><label>Assessment rubric criteria</label><div id="lpAssessmentRubrics" class="border rounded p-2 bg-light"><span class="text-muted small">Select an assessment tool first.</span></div></div>
          <div class="col-md-6"><label>Core competencies</label><div id="lpCompetencies" class="border rounded p-2 bg-light"></div></div>
          <div class="col-md-6"><label>Rubric criteria / levels</label><div id="lpRubrics" class="border rounded p-2 bg-light"></div></div>
          <div class="col-12"><label>Key inquiry questions</label><div id="lpQuestions" class="border rounded p-2 bg-light"></div></div>
          <div class="col-12"><label>Expected coverage</label><div id="lpCoverage" class="border rounded p-2 bg-light"><span class="text-muted small">Add the atomic coverage items expected from this lesson.</span></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="lpAddCoverage"><i class="bi bi-plus"></i> Add coverage item</button></div>
        </div>
        <div class="mt-4 d-flex gap-2">
          <button type="button" class="btn btn-outline-primary" onclick="createLessonPlanController.save('draft')"><i class="bi bi-save me-1"></i>Save as Draft</button>
          <button type="button" class="btn btn-success" onclick="createLessonPlanController.save('submitted')"><i class="bi bi-send me-1"></i>Create &amp; Submit for Review</button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/create_lesson_plan.js'); ?>
<?php asset_script($appBase, 'js/pages/ai_lesson_plan_draft.js'); ?>
