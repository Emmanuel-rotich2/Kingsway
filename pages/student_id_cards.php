<?php
/**
 * Student ID Cards Management Page
 * Complete implementation for managing student photos, QR codes, and ID card generation
 * Embedded in app_layout.php
 */
?>

<div class="container-fluid py-4">
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
</style>

<!-- Scripts -->
<script>
let uploadModal;
let idCardModal;
let bulkActionsModal;
let students = [];
let selectedStudents = new Set();

document.addEventListener("DOMContentLoaded", () => {
    uploadModal = new bootstrap.Modal("#uploadPhotoModal");
    idCardModal = new bootstrap.Modal("#idCardModal");
    bulkActionsModal = new bootstrap.Modal("#bulkActionsModal");

    loadClasses();
    loadStudents();
    loadStatistics();
});

// ==================== DATA LOADING ====================

/** Load class list */
async function loadClasses() {
    try {
        const response = await apiCall('/classes', 'GET');
        const select = document.getElementById("classFilter");
        select.innerHTML = '<option value="">All Classes</option>';

        (response.data || []).forEach(cls => {
            select.innerHTML += `<option value="${cls.id}">${cls.name}</option>`;
        });
    } catch (error) {
        console.error('Failed to load classes:', error);
        showNotification('Failed to load classes', 'error');
    }
}

/** Load students */
async function loadStudents() {
    const list = document.getElementById("studentsList");
    list.innerHTML = `<div class="text-center py-5"><div class="spinner-border"></div></div>`;

    try {
        const search = document.getElementById("searchInput").value;
        const classId = document.getElementById("classFilter").value;
        const streamId = document.getElementById("streamFilter").value;

        const params = {};
        if (search) params.search = search;
        if (classId) params.class_id = classId;
        if (streamId) params.stream_id = streamId;

        const response = await apiCall('/students', 'GET', null, params);
        students = response.data || [];
        renderStudents();

    } catch (error) {
        console.error('Failed to load students:', error);
        list.innerHTML = `<div class="alert alert-danger">Failed to load students.</div>`;
    }
}

/** Load statistics */
async function loadStatistics() {
    try {
        const response = await apiCall('/students/id-cards/statistics', 'GET');

        document.getElementById('totalStudents').textContent = response.total || 0;
        document.getElementById('studentsWithPhotos').textContent = response.with_photos || 0;
        document.getElementById('studentsWithQRCodes').textContent = response.with_qr_codes || 0;
        document.getElementById('idCardsGenerated').textContent = response.id_cards_generated || 0;
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

/** Load streams for selected class */
async function loadStreams() {
    const classId = document.getElementById('classFilter').value;
    const streamSelect = document.getElementById('streamFilter');

    if (!classId) {
        streamSelect.innerHTML = '<option value="">All Streams</option>';
        return;
    }

    try {
        const response = await apiCall(`/classes/${classId}/streams`, 'GET');
        streamSelect.innerHTML = '<option value="">All Streams</option>';

        (response.data || []).forEach(stream => {
            streamSelect.innerHTML += `<option value="${stream.id}">${stream.name}</option>`;
        });
    } catch (error) {
        console.error('Failed to load streams:', error);
    }
}

// ==================== UI RENDERING ====================

/** Render student cards */
function renderStudents() {
    const container = document.getElementById("studentsList");

    if (students.length === 0) {
        container.innerHTML = `<div class="alert alert-info">No students found.</div>`;
        return;
    }

    container.innerHTML = students.map(s => `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="student-card">
                <div class="d-flex gap-3">
                    <img src="${s.photo_url || '/images/default_avatar.png'}"
                         class="student-photo"
                         onerror="this.src='/images/default_avatar.png'">

                    <div class="flex-grow-1">
                        <h6 class="mb-1">${s.first_name} ${s.last_name}</h6>
                        <small class="text-muted d-block">${s.admission_number}</small>
                        <small class="text-muted d-block">${s.class_name || 'N/A'} - ${s.stream_name || 'N/A'}</small>

                        <div class="mt-2 mb-3">
                            ${s.photo_url
                                ? `<span class="badge bg-success"><i class="bi bi-check-circle"></i> Photo</span>`
                                : `<span class="badge bg-warning"><i class="bi bi-exclamation-circle"></i> No Photo</span>`
                            }
                            ${s.qr_code_path
                                ? `<span class="badge bg-success ms-1"><i class="bi bi-qr-code"></i> QR</span>`
                                : `<span class="badge bg-warning ms-1"><i class="bi bi-exclamation-circle"></i> No QR</span>`
                            }
                            ${s.id_card_generated
                                ? `<span class="badge bg-info ms-1"><i class="bi bi-credit-card"></i> Card</span>`
                                : `<span class="badge bg-secondary ms-1"><i class="bi bi-credit-card"></i> No Card</span>`
                            }
                        </div>

                        <div class="btn-group btn-group-sm flex-wrap">
                            <button class="btn btn-outline-primary"
                                onclick="openUploadModal(${s.id}, '${s.first_name} ${s.last_name}')"
                                title="Upload Photo">
                                <i class="bi bi-camera"></i>
                            </button>
                            <button class="btn btn-outline-info"
                                onclick="generateQRCode(${s.id})"
                                title="Generate QR Code">
                                <i class="bi bi-qr-code"></i>
                            </button>
                            <button class="btn btn-outline-success"
                                onclick="generateIDCard(${s.id})"
                                title="Generate ID Card">
                                <i class="bi bi-credit-card"></i>
                            </button>
                            <button class="btn btn-outline-secondary"
                                onclick="viewIDCard(${s.id})"
                                title="View ID Card">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// ==================== PHOTO MANAGEMENT ====================

/** Open upload modal */
function openUploadModal(id, name) {
    document.getElementById("uploadStudentId").value = id;
    document.getElementById("studentNameLabel").textContent = name;
    document.getElementById("photoPreview").classList.add("d-none");
    document.getElementById("photoInput").value = "";
    uploadModal.show();
}

/** Preview photo before upload */
function previewPhoto(input) {
    if (!input.files.length) return;

    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        alert("File size must be less than 5MB");
        input.value = "";
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById("previewImage").src = e.target.result;
        document.getElementById("photoPreview").classList.remove("d-none");
    };
    reader.readAsDataURL(file);
}

/** Upload student photo */
async function uploadPhoto() {
    const fileInput = document.getElementById("photoInput");
    const id = document.getElementById("uploadStudentId").value;

    if (!fileInput.files.length) {
        showNotification("Please select a photo", "warning");
        return;
    }

    const formData = new FormData();
    formData.append("photo", fileInput.files[0]);

    try {
        const response = await fetch(`/Kingsway/api/students/${id}/photo`, {
            method: "POST",
            body: formData,
            credentials: 'include'
        });

        const result = await response.json();

        if (result.status === "success") {
            showNotification("Photo uploaded successfully", "success");
            uploadModal.hide();
            loadStudents();
            loadStatistics();
        } else {
            showNotification(result.message || "Failed to upload photo", "error");
        }
    } catch (error) {
        console.error('Upload error:', error);
        showNotification("Error uploading photo", "error");
    }
}

// ==================== QR CODE MANAGEMENT ====================

/** Generate QR code */
async function generateQRCode(studentId) {
    try {
        const response = await apiCall(`/students/${studentId}/qr-code`, 'POST');

        if (response.status === 'success') {
            showNotification('QR Code generated successfully', 'success');
            loadStudents();
            loadStatistics();
        } else {
            showNotification(response.message || 'Failed to generate QR Code', 'error');
        }
    } catch (error) {
        console.error('QR Code generation error:', error);
        showNotification('Failed to generate QR Code', 'error');
    }
}

// ==================== ID CARD MANAGEMENT ====================

/** Generate a single ID card */
async function generateIDCard(studentId) {
    try {
        const response = await apiCall(`/students/${studentId}/id-card`, 'POST');

        if (response.status === 'success') {
            showNotification('ID Card generated successfully', 'success');
            loadStudents();
            loadStatistics();
            // Optionally show the card
            viewIDCard(studentId);
        } else {
            showNotification(response.message || 'Failed to generate ID Card', 'error');
        }
    } catch (error) {
        console.error('ID Card generation error:', error);
        showNotification('Failed to generate ID Card', 'error');
    }
}

/** View ID card */
async function viewIDCard(studentId) {
    try {
        const response = await apiCall(`/students/${studentId}/id-card`, 'GET');

        if (response.status === 'success' && response.data) {
            renderIDCardPreview(response.data);
            idCardModal.show();
        } else {
            showNotification('ID Card not found. Generate it first.', 'warning');
        }
    } catch (error) {
        console.error('View ID Card error:', error);
        showNotification('Failed to load ID Card', 'error');
    }
}

/** Render ID card preview */
function renderIDCardPreview(cardData) {
    const preview = document.getElementById('idCardPreview');

    preview.innerHTML = `
        <div class="id-card">
            <div class="id-card-header">
                KINGSWAY PREPARATORY SCHOOL
            </div>
            <div class="id-card-body">
                <img src="${cardData.photo_url || '/images/default_avatar.png'}"
                     class="id-card-photo"
                     onerror="this.src='/images/default_avatar.png'">
                <div class="id-card-info">
                    <p><strong>${cardData.first_name} ${cardData.last_name}</strong></p>
                    <p>Adm: ${cardData.admission_number}</p>
                    <p>Class: ${cardData.class_name || 'N/A'}</p>
                    <p>Stream: ${cardData.stream_name || 'N/A'}</p>
                    <p>DOB: ${cardData.date_of_birth || 'N/A'}</p>
                </div>
                <div class="id-card-qr">
                    ${cardData.qr_code_url ? `<img src="${cardData.qr_code_url}" style="width: 100%; height: 100%;">` : 'QR'}
                </div>
            </div>
        </div>
    `;
}

/** Print ID card */
function printIDCard() {
    const printWindow = window.open('', '_blank');
    const cardHtml = document.getElementById('idCardPreview').innerHTML;

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student ID Card</title>
            <style>
                body { margin: 0; padding: 20px; }
                .id-card { margin: 0 auto; }
                @media print {
                    body { margin: 0; }
                    .id-card { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            ${cardHtml}
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.print();
}

/** Download ID card */
function downloadIDCard() {
    // This would typically generate a PDF on the server
    // For now, we'll show a placeholder
    showNotification('Download feature coming soon', 'info');
}

// ==================== BULK OPERATIONS ====================

/** Generate bulk ID cards */
async function generateBulkIDCards() {
    const classId = document.getElementById("classFilter").value;
    if (!classId) {
        showNotification("Please select a class first", "warning");
        return;
    }

    if (!confirm("Generate ID cards for all students in this class?")) return;

    try {
        const response = await apiCall('/students/id-cards/bulk', 'POST', {
            class_id: classId,
            action: 'generate'
        });

        if (response.status === 'success') {
            showNotification(`Generated ${response.generated} ID cards successfully`, 'success');
            loadStudents();
            loadStatistics();
        } else {
            showNotification(response.message || 'Bulk generation failed', 'error');
        }
    } catch (error) {
        console.error('Bulk generation error:', error);
        showNotification('Failed to generate bulk ID cards', 'error');
    }
}

/** Export ID cards data */
async function exportIDCards() {
    const classId = document.getElementById("classFilter").value;
    const params = classId ? `?class_id=${classId}` : '';

    window.open(`/Kingsway/api/students/id-cards/export${params}`, '_blank');
}

/** Reset filters */
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('classFilter').value = '';
    document.getElementById('streamFilter').innerHTML = '<option value="">All Streams</option>';
    loadStudents();
}

// ==================== UTILITIES ====================

/** Show notification */
function showNotification(message, type = 'info') {
    // Simple alert for now - can be replaced with toast notifications
    alert(`${type.toUpperCase()}: ${message}`);
}

// Attach event listeners for class filter change
document.getElementById('classFilter').addEventListener('change', loadStreams);
</script>
