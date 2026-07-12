<?php
/**
 * Student ID Cards Page
 * Generate, renew, replace, preview, print, and issue official student school ID cards
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>
<style>
    .id-cards-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f0f4f8 0%, #e8f0f8 48%, #fff8e1 100%);
    }

    .id-cards-hero {
        border: 1px solid rgba(23, 162, 184, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(19, 132, 150, 0.18);
    }

    .id-cards-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .id-cards-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(23, 162, 184, 0.08);
    }

    .id-cards-panel .card {
        border-color: rgba(23, 162, 184, 0.16);
    }

    .stat-card {
        border: none;
        border-radius: 1rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    /* ID Card Preview Styling */
    .id-card-preview {
        width: 350px;
        height: 220px;
        border: 2px solid #0f5132;
        border-radius: 14px;
        background: #fffdf4;
        color: #143d2b;
        overflow: hidden;
        position: relative;
        box-shadow: 0 0.75rem 1.5rem rgba(15, 81, 50, 0.18);
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
    }

    .id-card-preview::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at top right, rgba(212, 175, 55, 0.26), transparent 36%),
            linear-gradient(90deg, rgba(15, 81, 50, 0.08), transparent 42%);
        pointer-events: none;
    }

    .id-card-preview-front,
    .id-card-preview-back {
        position: relative;
        z-index: 1;
        height: 100%;
    }

    .id-card-preview-front {
        display: grid;
        grid-template-rows: 48px 20px 1fr 22px;
        background: linear-gradient(180deg, #fffdf4 0%, #fff7dc 100%);
    }

    .id-card-header {
        display: grid;
        grid-template-columns: 42px 1fr;
        gap: 8px;
        align-items: center;
        padding: 7px 12px;
        background: linear-gradient(135deg, #0f5132 0%, #1f7a4d 100%);
        color: #fff;
    }

    .id-card-logo {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #fff;
        object-fit: contain;
        padding: 3px;
        border: 1px solid rgba(212, 175, 55, 0.8);
    }

    .id-card-school-name {
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.05;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .id-card-school-meta {
        color: #f7d774;
        font-size: 0.48rem;
        line-height: 1.15;
        font-weight: 600;
        text-transform: uppercase;
    }

    .id-card-title-strip {
        background: #d4af37;
        color: #143d2b;
        font-size: 0.58rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-align: center;
        text-transform: uppercase;
        padding: 3px 8px;
    }

    .id-card-front-body {
        display: grid;
        grid-template-columns: 92px 1fr;
        gap: 10px;
        padding: 10px 12px 7px;
        align-items: start;
    }

    .id-card-photo-wrap {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: center;
    }

    .id-card-photo {
        width: 82px;
        height: 94px;
        object-fit: cover;
        border: 3px solid #d4af37;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 0.25rem 0.5rem rgba(15, 81, 50, 0.18);
    }

    .id-card-admission-pill {
        width: 82px;
        border-radius: 999px;
        background: #0f5132;
        color: #fff;
        font-size: 0.5rem;
        font-weight: 800;
        text-align: center;
        padding: 3px 4px;
    }

    .id-card-name {
        color: #0f5132;
        font-size: 0.82rem;
        line-height: 1.1;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .id-card-detail-grid {
        display: grid;
        grid-template-columns: 64px 1fr;
        gap: 3px 7px;
        font-size: 0.56rem;
        line-height: 1.2;
    }

    .id-card-detail-label {
        color: #6b5b1a;
        font-weight: 800;
        text-transform: uppercase;
    }

    .id-card-detail-value {
        color: #143d2b;
        font-weight: 700;
    }

    .id-card-footer-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        padding: 3px 12px;
        background: #0f5132;
        color: #fff;
        font-size: 0.5rem;
        font-weight: 700;
    }

    .id-card-preview-back {
        display: grid;
        grid-template-columns: 118px 1fr;
        gap: 10px;
        padding: 12px;
        background: linear-gradient(135deg, #fffdf4 0%, #f7edc4 100%);
        font-size: 0.56rem;
    }

    .id-card-back-qr-panel {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid rgba(15, 81, 50, 0.2);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.72);
        padding: 8px;
    }

    .id-card-qr {
        width: 84px;
        height: 84px;
        background: white;
        padding: 5px;
        border-radius: 6px;
        border: 1px solid rgba(15, 81, 50, 0.24);
        object-fit: contain;
    }

    .id-card-qr-placeholder {
        width: 84px;
        height: 84px;
        border: 1px dashed #0f5132;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #0f5132;
        background: #fff;
        font-size: 0.48rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .id-card-back-title {
        color: #0f5132;
        font-size: 0.62rem;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .id-card-back-detail {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        border-bottom: 1px solid rgba(15, 81, 50, 0.12);
        padding: 2px 0;
    }

    .id-card-back-detail span:first-child {
        color: #6b5b1a;
        font-weight: 800;
        text-transform: uppercase;
    }

    .id-card-back-detail span:last-child {
        color: #143d2b;
        font-weight: 700;
        text-align: right;
    }

    .id-card-footer {
        margin-top: 6px;
        border-radius: 7px;
        background: rgba(15, 81, 50, 0.1);
        color: #143d2b;
        padding: 5px;
        font-size: 0.48rem;
        line-height: 1.2;
        text-align: center;
        font-weight: 700;
    }

    @media (max-width: 767.98px) {
        .id-cards-page {
            padding: 1rem;
        }
    }

    /* Print Styles */
    @media print {
        body * {
            visibility: hidden;
        }
        #printContainer, #printContainer * {
            visibility: visible;
        }
        #printContainer {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>

<div class="id-cards-page">
    <!-- Hero Section -->
    <div class="id-cards-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Student Management</p>
                <h4 class="mb-1">Student ID Cards</h4>
                <p class="mb-0 text-muted">Generate, renew, replace, preview, print, and issue official student school ID cards</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light" id="refreshBtn">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-light" id="generateSelectedBtn">
                    <i class="bi bi-card-checklist me-2"></i>Generate Selected
                </button>
                <button class="btn btn-light" id="printSelectedBtn">
                    <i class="bi bi-printer me-2"></i>Print Selected
                </button>
                <button class="btn btn-light" id="exportBtn">
                    <i class="bi bi-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalStudents">—</div>
                        <div class="text-muted small">Total Students</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statWithIDs">—</div>
                        <div class="text-muted small">With IDs</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statWithoutIDs">—</div>
                        <div class="text-muted small">Without IDs</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-printer"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statPrinted">—</div>
                        <div class="text-muted small">Printed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statIssued">—</div>
                        <div class="text-muted small">Issued</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statLost">—</div>
                        <div class="text-muted small">Lost</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-orange bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statExpired">—</div>
                        <div class="text-muted small">Expired/Expiring</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-purple bg-opacity-10 text-primary">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statReplaced">—</div>
                        <div class="text-muted small">Replaced</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="id-cards-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Academic Year</label>
                <select id="filterAcademicYear" class="form-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Class</label>
                <select id="filterClass" class="form-select">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Stream</label>
                <select id="filterStream" class="form-select">
                    <option value="">All Streams</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Gender</label>
                <select id="filterGender" class="form-select">
                    <option value="">All</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Student Status</label>
                <select id="filterStudentStatus" class="form-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                    <option value="transferred">Transferred</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">ID Status</label>
                <select id="filterCardStatus" class="form-select">
                    <option value="">All Status</option>
                    <option value="not_generated">No ID</option>
                    <option value="generated">Generated</option>
                    <option value="printed">Printed</option>
                    <option value="issued">Issued</option>
                    <option value="lost">Lost</option>
                    <option value="damaged">Damaged</option>
                    <option value="expired">Expired</option>
                    <option value="replaced">Replaced</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" id="searchStudents" class="form-control" placeholder="Name, admission no, UPI, card no...">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-flex gap-2 w-100">
                    <button class="btn btn-primary flex-grow-1" id="applyFiltersBtn">Apply</button>
                    <button class="btn btn-outline-secondary flex-grow-1" id="resetFiltersBtn">Reset</button>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        <div id="loadingState" class="alert alert-info d-none">
            <div class="spinner-border spinner-border-sm me-2"></div>
            Loading student ID cards...
        </div>
        <div id="errorState" class="alert alert-danger d-none"></div>
        <div id="forbiddenState" class="alert alert-warning d-none">
            <i class="bi bi-exclamation-triangle me-2"></i>
            You do not have permission to manage student ID cards.
        </div>

        <!-- Main Table -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Students</h6>
                <div>
                    <input type="checkbox" id="selectAll" class="form-check-input me-2">
                    <small class="text-muted">Select All</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th><input type="checkbox" id="headerCheckbox"></th>
                                <th>Photo</th>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Gender</th>
                                <th>ID Card No</th>
                                <th>QR Status</th>
                                <th>ID Status</th>
                                <th>Issue Date</th>
                                <th>Expiry Year</th>
                                <th>Last Action</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="15" class="text-center py-4">
                                    <div class="spinner-border text-info" role="status"></div>
                                    <div class="mt-2 text-muted">Loading...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Container (hidden unless printing) -->
<div id="printContainer" style="display:none;"></div>

<!-- ID Card Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Student ID Card Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="previewTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#frontSide">Front Side</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#backSide">Back Side</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="frontSide">
                        <div class="text-center">
                            <div id="cardFrontPreview" class="id-card-preview mx-auto"></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="backSide">
                        <div class="text-center">
                            <div id="cardBackPreview" class="id-card-preview mx-auto"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="previewGenerateBtn">Generate Card</button>
                <button type="button" class="btn btn-info" id="previewGenerateQRBtn">Generate QR</button>
                <button type="button" class="btn btn-primary" id="previewPrintBtn">Print Card</button>
                <button type="button" class="btn btn-warning" id="previewMarkPrintedBtn">Mark Printed</button>
                <button type="button" class="btn btn-outline-success" id="previewMarkIssuedBtn">Mark Issued</button>
                <button type="button" class="btn btn-outline-warning" id="previewRenewBtn">Renew</button>
                <button type="button" class="btn btn-outline-danger" id="previewReplaceBtn">Replace</button>
            </div>
        </div>
    </div>
</div>

<!-- Generate ID Card Modal -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Generate ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="generateForm">
                <div class="modal-body">
                    <input type="hidden" id="generateStudentId">
                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <select id="generateAcademicYear" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" id="generateIssueDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Year</label>
                        <input type="number" id="generateExpiryYear" class="form-control" min="2024" max="2030" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Card Template</label>
                        <select id="generateTemplate" class="form-select">
                            <option value="standard">Standard Template</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="generateQR" class="form-check-input" checked>
                            <label class="form-check-label" for="generateQR">Generate QR Code</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="generateNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Generate Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Renew ID Card Modal -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Renew ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="renewForm">
                <div class="modal-body">
                    <input type="hidden" id="renewStudentId">
                    <input type="hidden" id="renewCardId">
                    <div class="mb-3">
                        <label class="form-label">Old Card Number</label>
                        <input type="text" id="renewOldCardNo" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Renewal Reason</label>
                        <select id="renewReason" class="form-select" required>
                            <option value="expired">Expired</option>
                            <option value="damaged">Damaged</option>
                            <option value="correction">Correction</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Expiry Year</label>
                        <input type="number" id="renewExpiryYear" class="form-control" min="2024" max="2030" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" id="renewIssueDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="renewNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="revokeOldCard" class="form-check-input" checked>
                            <label class="form-check-label" for="revokeOldCard">Revoke old card</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Renew Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Replace ID Card Modal -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Replace Lost/Damaged ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceForm">
                <div class="modal-body">
                    <input type="hidden" id="replaceStudentId">
                    <input type="hidden" id="replaceCardId">
                    <div class="mb-3">
                        <label class="form-label">Old Card Number</label>
                        <input type="text" id="replaceOldCardNo" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Replacement Reason</label>
                        <select id="replaceReason" class="form-select" required>
                            <option value="lost">Lost</option>
                            <option value="damaged">Damaged</option>
                            <option value="expired">Expired</option>
                            <option value="correction">Correction</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" id="replaceIssueDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Expiry Year</label>
                        <input type="number" id="replaceExpiryYear" class="form-control" min="2024" max="2030" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="replaceNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="revokeOldCard" class="form-check-input" checked>
                            <label class="form-check-label" for="revokeOldCard">Revoke old card</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Replace Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Issued Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Mark ID Card as Issued</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="issueForm">
                <div class="modal-body">
                    <input type="hidden" id="issueStudentId">
                    <input type="hidden" id="issueCardId">
                    <div class="mb-3">
                        <label class="form-label">Card Number</label>
                        <input type="text" id="issueCardNo" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issued To</label>
                        <input type="text" id="issueIssuedTo" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issued By</label>
                        <input type="text" id="issueIssuedBy" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue Date/Time</label>
                        <input type="text" id="issueDateTime" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="issueConfirm" class="form-check-input" required>
                            <label class="form-check-label" for="issueConfirm">I confirm this card has been issued to the student</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Mark Issued</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">ID Card History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_BASE = window.APP_BASE || <?= json_encode($appBase) ?>;
</script>

<script
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/student_id_cards.js?v=<?= time() ?>"
    onload="console.log('student_id_cards.js script tag loaded successfully')"
    onerror="console.error('FAILED to load student_id_cards.js. Check path:', this.src)">
</script>
