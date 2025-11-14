<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Cards - Kingsway Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .student-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .student-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .student-photo {
            width: 100px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #007bff;
        }

        .upload-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-zone:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }

        .status-badge {
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-credit-card-2-front"></i> Student ID Card Management</h2>
                <p class="text-muted">Upload photos, generate QR codes, and create student ID cards</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-3">
                <select class="form-select" id="classFilter" onchange="loadStudents()">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="streamFilter" onchange="loadStudents()">
                    <option value="">All Streams</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchInput"
                    placeholder="Search by name or admission number..." onkeyup="loadStudents()">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="generateBulkIDCards()">
                    <i class="bi bi-printer"></i> Bulk Generate
                </button>
            </div>
        </div>

        <!-- Students List -->
        <div id="studentsList" class="row">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div class="modal fade" id="uploadPhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Student Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student: <strong id="studentNameLabel"></strong></label>
                    </div>
                    <div class="upload-zone" onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="mt-2 mb-0">Click to select photo</p>
                        <small class="text-muted">Max 5MB, JPG/PNG only</small>
                    </div>
                    <input type="file" id="photoInput" accept="image/jpeg,image/jpg,image/png" style="display: none;"
                        onchange="previewPhoto(this)">
                    <div id="photoPreview" class="mt-3 text-center" style="display: none;">
                        <img id="previewImage" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                    </div>
                    <input type="hidden" id="uploadStudentId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="uploadPhoto()">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/api.js"></script>
    <script>
        let uploadModal;
        let students = [];

        document.addEventListener('DOMContentLoaded', function () {
            uploadModal = new bootstrap.Modal(document.getElementById('uploadPhotoModal'));
            loadClasses();
            loadStudents();
        });

        async function loadClasses() {
            try {
                const response = await apiCall('/api/academic.php?action=list-classes', 'GET');
                const classSelect = document.getElementById('classFilter');

                if (response.data && response.data.classes) {
                    response.data.classes.forEach(cls => {
                        const option = document.createElement('option');
                        option.value = cls.id;
                        option.textContent = cls.name;
                        classSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load classes:', error);
            }
        }

        async function loadStudents() {
            try {
                const search = document.getElementById('searchInput').value;
                const classId = document.getElementById('classFilter').value;

                let url = '/api/students.php?action=list';
                if (search) url += `&search=${encodeURIComponent(search)}`;
                if (classId) url += `&class_id=${classId}`;

                const response = await apiCall(url, 'GET');

                if (response.status === 'success') {
                    students = response.data.students;
                    renderStudents();
                }
            } catch (error) {
                console.error('Failed to load students:', error);
                document.getElementById('studentsList').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">Failed to load students: ${error.message}</div>
                    </div>
                `;
            }
        }

        function renderStudents() {
            const container = document.getElementById('studentsList');

            if (students.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">No students found</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = students.map(student => `
                <div class="col-md-6 col-lg-4">
                    <div class="student-card">
                        <div class="d-flex gap-3">
                            <div>
                                <img src="${student.photo_url || '/images/default_avatar.png'}" 
                                     alt="${student.first_name}" 
                                     class="student-photo"
                                     onerror="this.src='/images/default_avatar.png'">
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${student.first_name} ${student.last_name}</h6>
                                <small class="text-muted d-block">${student.admission_no}</small>
                                <small class="text-muted d-block">${student.class_name || 'N/A'} - ${student.stream_name || 'N/A'}</small>
                                
                                <div class="mt-2">
                                    ${student.photo_url ?
                    '<span class="badge bg-success status-badge"><i class="bi bi-check-circle"></i> Photo</span>' :
                    '<span class="badge bg-warning status-badge"><i class="bi bi-exclamation-circle"></i> No Photo</span>'
                }
                                    ${student.qr_code_path ?
                    '<span class="badge bg-success status-badge ms-1"><i class="bi bi-qr-code"></i> QR</span>' :
                    '<span class="badge bg-warning status-badge ms-1"><i class="bi bi-exclamation-circle"></i> No QR</span>'
                }
                                </div>
                                
                                <div class="btn-group mt-3" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openUploadModal(${student.id}, '${student.first_name} ${student.last_name}')" title="Upload Photo">
                                        <i class="bi bi-camera"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="generateQRCode(${student.id})" title="Generate QR">
                                        <i class="bi bi-qr-code"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="generateIDCard(${student.id})" title="Generate ID Card">
                                        <i class="bi bi-credit-card"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function openUploadModal(studentId, studentName) {
            document.getElementById('uploadStudentId').value = studentId;
            document.getElementById('studentNameLabel').textContent = studentName;
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('photoInput').value = '';
            uploadModal.show();
        }

        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('photoPreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        async function uploadPhoto() {
            const studentId = document.getElementById('uploadStudentId').value;
            const fileInput = document.getElementById('photoInput');

            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a photo');
                return;
            }

            const formData = new FormData();
            formData.append('photo', fileInput.files[0]);

            try {
                const response = await fetch(`/api/students.php?action=upload-photo&id=${studentId}`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Photo uploaded successfully!');
                    uploadModal.hide();
                    loadStudents();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Failed to upload photo: ' + error.message);
            }
        }

        async function generateQRCode(studentId) {
            try {
                const response = await apiCall(`/api/students.php?action=generate-enhanced-qr&id=${studentId}`, 'GET');

                if (response.status === 'success') {
                    alert('QR code generated successfully!');
                    loadStudents();
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('Failed to generate QR code: ' + error.message);
            }
        }

        async function generateIDCard(studentId) {
            try {
                const response = await apiCall(`/api/students.php?action=generate-id-card&id=${studentId}`, 'GET');

                if (response.status === 'success') {
                    window.open(response.data.view_url, '_blank');
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('Failed to generate ID card: ' + error.message);
            }
        }

        async function generateBulkIDCards() {
            const classId = document.getElementById('classFilter').value;
            const streamId = document.getElementById('streamFilter').value;

            if (!classId) {
                alert('Please select a class');
                return;
            }

            if (!confirm('Generate ID cards for all students in this class/stream?')) {
                return;
            }

            try {
                let url = `/api/students.php?action=generate-class-id-cards&class_id=${classId}`;
                if (streamId) url += `&stream_id=${streamId}`;

                const response = await apiCall(url, 'GET');

                if (response.status === 'success') {
                    alert(`Generated ${response.data.successful} ID cards successfully!`);
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('Failed to generate bulk ID cards: ' + error.message);
            }
        }
    </script>
</body>

</html>