<?php
/**
 * Shared learning-resource library (teaching materials or past papers).
 *
 * Expected variables:
 *  - $resourceKind       'material' | 'past_paper'
 *  - $resourceOpenUpload  true to open the add form on load
 *
 * Teachers and academic leadership add files (documents, slides, sheets,
 * text, images, video, audio, zip) or links. The School Administrator
 * publishes each item as Internal (staff) or Public (also on the parent
 * portal), rejects it with a note, or unpublishes it. Files are opened through
 * short-lived signed links after the server checks the viewer.
 */
$resourceKind = ($resourceKind ?? 'material') === 'past_paper' ? 'past_paper' : 'material';
$resourceOpenUpload = !empty($resourceOpenUpload);
$isPaper = $resourceKind === 'past_paper';
?>
<style>
@media print {
    #resourceLibrary .d-print-none { display: none !important; }
    #resourceLibrary .table-responsive { overflow: visible; }
}
</style>
<div class="container-fluid py-4" id="resourceLibrary"
     data-kind="<?= $resourceKind ?>" data-open-upload="<?= $resourceOpenUpload ? '1' : '0' ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="bi <?= $isPaper ? 'bi-file-earmark-text' : 'bi-collection' ?> me-2"></i><?= $isPaper ? 'Past Papers' : 'Teaching Materials' ?></h2>
            <div class="text-muted small"><?= $isPaper
                ? 'Past examination papers and marking schemes for revision. Published public papers also appear on the parent portal.'
                : 'Notes, worksheets, slides, videos, audio, images and links. Published public items also appear on the parent portal.' ?></div>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button class="btn btn-outline-primary btn-sm" type="button" id="rlExport"><i class="bi bi-download me-1"></i>CSV</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="rlPrint"><i class="bi bi-printer me-1"></i>Print</button>
            <button class="btn btn-primary btn-sm d-none" type="button" id="rlAddBtn" data-year-write><i class="bi bi-plus-lg me-1"></i><?= $isPaper ? 'Add past paper' : 'Add material' ?></button>
        </div>
    </div>

    <div class="border rounded bg-white p-3 mb-3 d-print-none">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlStatus">Show</label>
                <select class="form-select" id="rlStatus">
                    <option value="">All I can see</option>
                    <option value="published">Published</option>
                    <option value="pending">Awaiting publishing</option>
                    <option value="mine">My uploads</option>
                    <option value="rejected">Returned</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlScope">Audience</label>
                <select class="form-select" id="rlScope">
                    <option value="">Internal and public</option>
                    <option value="internal">Internal (staff only)</option>
                    <option value="public">Public (parent portal)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlGrade">Grade</label>
                <select class="form-select" id="rlGrade"><option value="">All grades</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlArea">Learning area</label>
                <select class="form-select" id="rlArea"><option value="">All learning areas</option></select>
            </div>
            <?php if ($isPaper): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlExamYear">Year set</label>
                <select class="form-select" id="rlExamYear"><option value="">All years</option></select>
            </div>
            <?php else: ?>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlMedia">Type</label>
                <select class="form-select" id="rlMedia">
                    <option value="">All types</option>
                    <option value="document">Documents</option><option value="presentation">Presentations</option>
                    <option value="spreadsheet">Spreadsheets</option><option value="text">Text</option>
                    <option value="image">Images</option><option value="video">Videos</option>
                    <option value="audio">Audio</option><option value="link">Links</option><option value="archive">Zip</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="rlSearch">Search</label>
                <input type="search" class="form-control" id="rlSearch" placeholder="Title or description">
            </div>
        </div>
    </div>

    <div class="table-responsive border rounded bg-white">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr id="rlHead"></tr></thead>
            <tbody id="rlBody"><tr><td class="text-center text-muted py-4">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="rlModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rlModalTitle"><?= $isPaper ? 'Add past paper' : 'Add teaching material' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rlForm" novalidate>
                    <input type="hidden" id="rlId">
                    <div class="row g-3">
                        <div class="col-12" id="rlSourceWrap">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="rlSource" id="rlSourceFile" value="file" checked>
                                <label class="btn btn-outline-primary btn-sm" for="rlSourceFile"><i class="bi bi-upload me-1"></i>Upload a file</label>
                                <input type="radio" class="btn-check" name="rlSource" id="rlSourceLink" value="link">
                                <label class="btn btn-outline-primary btn-sm" for="rlSourceLink"><i class="bi bi-link-45deg me-1"></i>Add a link</label>
                            </div>
                        </div>
                        <div class="col-12" id="rlFileWrap">
                            <label class="form-label" for="rlFile">File *</label>
                            <input type="file" class="form-control" id="rlFile">
                            <div class="form-text">PDF, Word, PowerPoint, Excel, text, images, video (MP4/WebM/MOV), audio (MP3/WAV/M4A/OGG) or zip, up to 40 MB. For long videos use a YouTube or Drive link.</div>
                        </div>
                        <div class="col-12 d-none" id="rlLinkWrap">
                            <label class="form-label" for="rlLink">Link *</label>
                            <input type="url" class="form-control" id="rlLink" placeholder="https://…">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="rlTitle">Title *</label>
                            <input type="text" class="form-control" id="rlTitle" maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="rlFormGrade">Grade</label>
                            <select class="form-select" id="rlFormGrade"><option value="">All grades</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="rlFormArea">Learning area</label>
                            <select class="form-select" id="rlFormArea"><option value="">General (no learning area)</option></select>
                        </div>
                        <?php if ($isPaper): ?>
                        <div class="col-md-2">
                            <label class="form-label" for="rlFormExamYear">Year set *</label>
                            <input type="number" class="form-control" id="rlFormExamYear" min="2000" max="<?= (int) date('Y') + 1 ?>" value="<?= (int) date('Y') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rlFormTerm">Term</label>
                            <select class="form-select" id="rlFormTerm"><option value="">—</option><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rlFormExamType">Exam</label>
                            <select class="form-select" id="rlFormExamType">
                                <option value="opener">Opener</option><option value="mid_term">Mid-term</option><option value="end_term" selected>End-term</option>
                                <option value="mock">Mock</option><option value="kpsea">KPSEA</option><option value="kjsea">KJSEA</option>
                                <option value="keya">KEYA</option><option value="internal">Internal / other school</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-12" id="rlSchemeWrap">
                            <label class="form-label" for="rlScheme">Marking scheme (optional)</label>
                            <input type="file" class="form-control" id="rlScheme">
                        </div>
                        <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label" for="rlFormType">Kind of material</label>
                            <select class="form-select" id="rlFormType">
                                <option value="notes">Notes</option><option value="worksheet">Worksheet</option><option value="presentation">Presentation</option>
                                <option value="revision">Revision material</option><option value="video_lesson">Video lesson</option>
                                <option value="audio">Audio</option><option value="activity">Activity / project</option><option value="reference">Reference / link</option><option value="other">Other</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label" for="rlDescription">Description</label>
                            <textarea class="form-control" id="rlDescription" rows="2" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12 small text-muted">New items wait for the School Administrator, who publishes them for staff only or for parents too.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="me-auto small" id="rlFormState"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="rlSave" data-year-write>Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rlPublishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Publish</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="rlPubId">
                <div class="fw-semibold mb-2" id="rlPubTitle"></div>
                <label class="form-check mb-2"><input class="form-check-input" type="radio" name="rlPubScope" value="internal" checked> <span class="form-check-label"><strong>Internal</strong>: all staff can open it</span></label>
                <label class="form-check mb-3"><input class="form-check-input" type="radio" name="rlPubScope" value="public"> <span class="form-check-label"><strong>Public</strong>: staff, and parents on the parent portal</span></label>
                <label class="form-label" for="rlPubNote">Note to the uploader (optional; required when returning)</label>
                <textarea class="form-control" id="rlPubNote" rows="2" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="rlReject">Return to uploader</button>
                <button type="button" class="btn btn-success" id="rlPublish"><i class="bi bi-check2-circle me-1"></i>Publish</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/resource_library.js'); ?>
