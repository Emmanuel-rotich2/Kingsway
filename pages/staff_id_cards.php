<?php
/**
 * Kingsway Preparatory School
 * Staff Security Passes administrative workspace.
 *
 * Compatibility:
 * - route remains staff_id_cards;
 * - API and permission identifiers remain staff.id_cards.*;
 * - the user-facing document is a portrait lanyard security pass.
 */
?>

<div class="container-fluid py-4" data-staff-page data-required-permission="staff.id_cards.view">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="bi bi-person-badge me-2" aria-hidden="true"></i>
                Staff Security Passes
            </h1>
            <p class="page-subtitle mb-0">
                Generate, preview, print and issue portrait staff passes for identification and controlled access.
            </p>
        </div>

        <div class="header-actions flex-wrap">
            <button
                type="button"
                class="btn btn-primary"
                id="generateOnePassBtn"
                data-permission="staff.id_cards.manage"
            >
                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                Generate One
            </button>

            <button
                type="button"
                class="btn btn-outline-primary"
                id="generateBulkPassesBtn"
                data-permission="staff.id_cards.manage"
            >
                <i class="bi bi-collection me-1" aria-hidden="true"></i>
                Generate Bulk
            </button>

            <button type="button" class="btn btn-outline-secondary" id="refreshPassesBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                Refresh
            </button>
        </div>
    </header>

    <section class="stats-grid cols-4 mb-4" aria-label="Staff security-pass summary">
        <article class="stat-card accent-green">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Current Staff</div>
                    <div class="fs-3 fw-bold" id="passTotalCount">0</div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-people" aria-hidden="true"></i>
                </div>
            </div>
        </article>

        <article class="stat-card">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Generated</div>
                    <div class="fs-3 fw-bold" id="passGeneratedCount">0</div>
                </div>
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                </div>
            </div>
        </article>

        <article class="stat-card accent-green">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Issued</div>
                    <div class="fs-3 fw-bold" id="passIssuedCount">0</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                </div>
            </div>
        </article>

        <article class="stat-card accent-gold">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Missing / Revoked</div>
                    <div class="fs-3 fw-bold" id="passAttentionCount">0</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                </div>
            </div>
        </article>
    </section>

    <section class="card mb-4" aria-label="Security-pass filters and bulk actions">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4">
                    <label class="visually-hidden" for="passSearch">Search staff security passes</label>
                    <input
                        type="search"
                        class="form-control"
                        id="passSearch"
                        placeholder="Search staff, number, department, position or pass"
                        autocomplete="off"
                    >
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="visually-hidden" for="passStatusFilter">Pass status</label>
                    <select class="form-select" id="passStatusFilter">
                        <option value="">All statuses</option>
                        <option value="missing">Missing</option>
                        <option value="generated">Generated</option>
                        <option value="issued">Issued</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="visually-hidden" for="passDepartmentFilter">Department</label>
                    <select class="form-select" id="passDepartmentFilter">
                        <option value="">All departments</option>
                    </select>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <button type="button" class="btn btn-outline-info" id="previewSelectedPassesBtn" disabled>
                            <i class="bi bi-eye me-1" aria-hidden="true"></i>
                            Preview
                        </button>

                        <button type="button" class="btn btn-outline-dark" id="printSelectedPassesBtn" disabled>
                            <i class="bi bi-printer me-1" aria-hidden="true"></i>
                            Print
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-warning"
                            id="regenerateSelectedPassesBtn"
                            data-permission="staff.id_cards.manage"
                            disabled
                        >
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                            Regenerate
                        </button>
                    </div>
                </div>
            </div>

            <div class="small text-muted mt-2" aria-live="polite">
                <span id="selectedPassesCount">0</span> selected
            </div>
        </div>
    </section>

    <section class="card" aria-label="Staff security-pass register">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="staffSecurityPassesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="selectAllVisiblePasses"
                                aria-label="Select all visible staff"
                            >
                        </th>
                        <th scope="col">Staff</th>
                        <th scope="col">Staff No.</th>
                        <th scope="col">Department</th>
                        <th scope="col">Position</th>
                        <th scope="col">Pass No.</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffSecurityPassesBody">
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Loading staff security passes...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted" id="passResultsSummary">0 records</span>
            <span class="small text-muted">Portrait lanyard security-pass format</span>
        </div>
    </section>
</div>

<div class="modal fade" id="generateStaffSecurityPassModal" tabindex="-1" aria-labelledby="generateStaffSecurityPassTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateStaffSecurityPassTitle">
                    <i class="bi bi-person-badge me-2" aria-hidden="true"></i>
                    Generate Staff Security Pass
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="securityPassStaffId">Staff member</label>
                        <select class="form-select" id="securityPassStaffId" required>
                            <option value="">Select staff</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="securityPassSide">Pass side</label>
                        <select class="form-select" id="securityPassSide">
                            <option value="both">Front and back</option>
                            <option value="front">Front only</option>
                            <option value="back">Back only</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="securityPassPrintMode">Output layout</label>
                        <select class="form-select" id="securityPassPrintMode">
                            <option value="direct_card">Direct portrait pass</option>
                            <option value="a4_pdf">A4 pass sheet</option>
                        </select>
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    The QR credential identifies this pass at a checkpoint. Fingerprint verification belongs to the approved security-device integration and is never encoded on the pass.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="generateSecurityPassSubmitBtn">
                    <i class="bi bi-magic me-1" aria-hidden="true"></i>
                    Generate and Preview
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkStaffSecurityPassModal" tabindex="-1" aria-labelledby="bulkStaffSecurityPassTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkStaffSecurityPassTitle">
                    <i class="bi bi-collection me-2" aria-hidden="true"></i>
                    Bulk Staff Security Passes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info" id="bulkSecurityPassSummary">
                    Select staff records from the table first.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="bulkSecurityPassPrintMode">Output layout</label>
                        <select class="form-select" id="bulkSecurityPassPrintMode">
                            <option value="a4_pdf">A4 portrait pass sheets</option>
                            <option value="direct_card">One portrait pass per PDF page</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <span class="form-label d-block">Pass sides</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="bulkSecurityPassFront" checked>
                            <label class="form-check-label" for="bulkSecurityPassFront">Front</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="bulkSecurityPassBack" checked>
                            <label class="form-check-label" for="bulkSecurityPassBack">Back</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-info" id="previewBulkSecurityPassesBtn">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>
                    Preview Existing
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    id="generateBulkSecurityPassesSubmitBtn"
                    data-permission="staff.id_cards.manage"
                >
                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                    Generate / Regenerate
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="staffSecurityPassPreviewModal" tabindex="-1" aria-labelledby="staffSecurityPassPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="staffSecurityPassPreviewTitle">Staff Security Pass Preview</h5>
                    <small class="text-muted" id="staffSecurityPassPreviewSubtitle">Review the generated pass before printing or issuing.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <div class="border rounded p-3 h-100 bg-light" id="staffSecurityPassPreviewMeta">
                            <span class="text-muted">No pass selected.</span>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="ratio ratio-4x3 border rounded bg-light">
                            <iframe
                                id="staffSecurityPassPreviewFrame"
                                title="Staff security pass preview"
                                class="border-0"
                                loading="eager"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="openSecurityPassDocumentBtn" disabled>
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                    Open
                </button>
                <button type="button" class="btn btn-outline-dark" id="printSecurityPassDocumentBtn" disabled>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>
                    Print
                </button>
                <button
                    type="button"
                    class="btn btn-success"
                    id="issueSecurityPassBtn"
                    data-permission="staff.id_cards.manage"
                    disabled
                >
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                    Mark Issued
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$staffAccessJsPath = __DIR__ . '/../js/pages/staff_access.js';
$staffSecurityPassJsPath = __DIR__ . '/../js/pages/staff_id_cards.js';
$staffAccessJsVersion = is_file($staffAccessJsPath) ? filemtime($staffAccessJsPath) : time();
$staffSecurityPassJsVersion = is_file($staffSecurityPassJsPath) ? filemtime($staffSecurityPassJsPath) : time();
?>
<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/staff_access.js?v=<?= (int) $staffAccessJsVersion ?>"></script>
<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/staff_id_cards.js?v=<?= (int) $staffSecurityPassJsVersion ?>"></script>
