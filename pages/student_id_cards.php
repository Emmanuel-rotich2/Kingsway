<?php
/**
 * Student ID Cards Management Page
 * Complete implementation for managing student photos, QR codes, and ID card generation
 * Embedded in app_layout.php
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-credit-card-2-front"></i> Student ID Card Management</h2>
            <p class="text-muted">Upload photos, generate QR codes, and print ID cards.</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="generateBulkIDCards()">
                <i class="bi bi-printer"></i> Bulk Generate
            </button>
            <button class="btn btn-outline-secondary" onclick="exportIDCards()">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Class</label>
            <select class="form-select" id="classFilter" onchange="loadStudents()">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Stream</label>
            <select class="form-select" id="streamFilter" onchange="loadStudents()">
                <option value="">All Streams</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Search Student</label>
            <input type="text" class="form-control" id="searchInput"
                placeholder="Search name or admission number..." onkeyup="loadStudents()">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Students</h6>
                    <h3 class="text-primary mb-0" id="totalStudents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">With Photos</h6>
                    <h3 class="text-success mb-0" id="studentsWithPhotos">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">With QR Codes</h6>
                    <h3 class="text-info mb-0" id="studentsWithQRCodes">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">ID Cards Generated</h6>
                    <h3 class="text-warning mb-0" id="idCardsGenerated">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Student List -->
    <div id="studentsList" class="row">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="text-muted mt-2">Loading students...</p>
        </div>
    </div>
</div>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-camera"></i> Upload Student Photo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="fw-semibold mb-1">Student:</p>
                <p id="studentNameLabel" class="mb-3 text-primary"></p>

                <div class="upload-zone" onclick="document.getElementById('photoInput').click()">
                    <i class="bi bi-cloud-upload fs-1 text-secondary"></i>
                    <p class="mt-2">Click to select photo</p>
                    <small class="text-muted">JPEG/PNG — Max 5MB</small>
                </div>

                <input type="file" id="photoInput" accept="image/*" class="d-none" onchange="previewPhoto(this)">

                <div id="photoPreview" class="mt-3 text-center d-none">
                    <img id="previewImage" class="rounded shadow" style="max-width: 100%; max-height: 300px;">
                </div>

                <input type="hidden" id="uploadStudentId">
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="uploadPhoto()">
                    <i class="bi bi-upload"></i> Upload Photo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ID Card Preview Modal -->
<div class="modal fade" id="idCardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-credit-card"></i> Student ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="idCardPreview" class="text-center">
                    <!-- ID Card will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" onclick="printIDCard()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button class="btn btn-success" onclick="downloadIDCard()">
                    <i class="bi bi-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-gear"></i> Bulk Actions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Action</label>
                    <select class="form-select" id="bulkActionType">
                        <option value="generate_qr">Generate QR Codes</option>
                        <option value="generate_cards">Generate ID Cards</option>
                        <option value="export_data">Export Data</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Students</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="studentSelection" id="allStudents" value="all" checked>
                        <label class="form-check-label" for="allStudents">
                            All filtered students
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="studentSelection" id="selectedStudents" value="selected">
                        <label class="form-check-label" for="selectedStudents">
                            Selected students only
                        </label>
                    </div>
                </div>

                <div id="selectedStudentsList" class="d-none">
                    <small class="text-muted">Select students from the list below:</small>
                    <div id="bulkStudentCheckboxes" class="mt-2" style="max-height: 200px; overflow-y: auto;">
                        <!-- Checkboxes will be populated here -->
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="executeBulkAction()">
                    <i class="bi bi-play"></i> Execute
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.student-card {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    transition: .3s;
    background: white;
}

.student-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.student-photo {
    width: 80px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e9ecef;
}

.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: .3s;
    cursor: pointer;
}

.upload-zone:hover {
    border-color: #0d6efd;
    background: #eef5ff;
}

.id-card {
    width: 3.375in;
    height: 2.125in;
    border: 1px solid #000;
    margin: 0 auto;
    position: relative;
    background: white;
    font-family: Arial, sans-serif;
}

.id-card-header {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    padding: 8px;
    text-align: center;
    font-size: 12px;
    font-weight: bold;
}

.id-card-body {
    display: flex;
    padding: 10px;
    height: calc(100% - 32px);
}

.id-card-photo {
    width: 80px;
    height: 100px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
    margin-right: 15px;
}

.id-card-info {
    flex: 1;
    font-size: 10px;
}

.id-card-info p {
    margin: 2px 0;
    line-height: 1.2;
}

.id-card-qr {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 60px;
    height: 60px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: #6b7280;
}

@media (max-width: 768px) {
    .student-card {
        padding: 14px;
    }

    .student-photo {
        width: 62px;
        height: 80px;
    }

    .id-card {
        transform: scale(0.9);
        transform-origin: top center;
    }
}
</style>

<!-- Scripts -->
<script src="<?= $appBase ?>/js/pages/student_id_cards.js"></script>
