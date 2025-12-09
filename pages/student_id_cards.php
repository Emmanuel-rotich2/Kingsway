<?php
/**
 * Student ID Cards Page
 * HTML structure only - all logic in js/pages/reports.js (studentIDCardsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h2 class="mb-0">🎫 Student ID Cards</h2>
    <button class="btn btn-light btn-sm" onclick="studentIDCardsController.generateAll()">🖨️ Generate All</button>
  </div>
  <div class="card-body">
    <!-- Filter Section -->
    <div class="row mb-4">
      <div class="col-md-6">
        <input type="text" id="searchStudents" class="form-control" placeholder="Search students..." 
               onkeyup="studentIDCardsController.search(this.value)">
      </div>
      <div class="col-md-3">
        <select id="classFilter" class="form-select" onchange="studentIDCardsController.filterByClass(this.value)">
          <option value="">-- All Classes --</option>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-secondary w-100" onclick="studentIDCardsController.resetFilters()">Reset</button>
      </div>
    </div>

    <!-- Students Grid -->
    <div id="cardsContainer" class="row">
      <p class="text-muted text-center w-100">Loading students...</p>
    </div>
  </div>
</div>

<!-- Template for ID Card -->
<template id="cardTemplate">
  <div class="col-md-4 mb-4">
    <div class="card student-card border">
      <div class="card-body text-center">
        <img id="cardPhoto" src="images/placeholder.png" class="img-fluid mb-3 rounded" style="width: 120px; height: 150px; object-fit: cover;">
        <h6 class="card-title" id="cardName">Student Name</h6>
        <p class="small text-muted mb-2" id="cardAdmission">ADM000</p>
        <p class="small text-muted mb-3" id="cardClass">Class</p>
        <div id="cardQR" style="width: 100px; height: 100px; margin: 0 auto; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
          <small class="text-muted">QR Code</small>
        </div>
        <div class="mt-3">
          <button class="btn btn-sm btn-primary" onclick="studentIDCardsController.generateCard(this)">Generate</button>
          <button class="btn btn-sm btn-secondary" onclick="studentIDCardsController.printCard(this)">Print</button>
        </div>
      </div>
    </div>
  </div>
</template>
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
    </style>
</head>

<body>

    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-credit-card-2-front"></i> Student ID Card Management</h2>
                <p class="text-muted">Upload photos, generate QR codes, and print ID cards.</p>
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
                <button class="btn btn-primary w-100" onclick="generateBulkIDCards()">
                    <i class="bi bi-printer"></i> Bulk Generate
                </button>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/api.js"></script>

    <script>
        let uploadModal;
        let students = [];

        document.addEventListener("DOMContentLoaded", () => {
            uploadModal = new bootstrap.Modal("#uploadPhotoModal");
            loadClasses();
            loadStudents();
        });

        /** Load class list */
        async function loadClasses() {
            try {
                const res = await apiCall('/api/academic.php?action=list-classes', 'GET');
                const select = document.getElementById("classFilter");
                res.data.classes.forEach(cls => {
                    select.innerHTML += `<option value="${cls.id}">${cls.name}</option>`;
                });
            } catch (error) { console.error(error); }
        }

        /** Load students */
        async function loadStudents() {
            const list = document.getElementById("studentsList");
            list.innerHTML = `<div class="text-center py-5"><div class="spinner-border"></div></div>`;

            try {
                const search = document.getElementById("searchInput").value;
                const classId = document.getElementById("classFilter").value;

                let url = `/api/students.php?action=list`;
                if (search) url += `&search=${search}`;
                if (classId) url += `&class_id=${classId}`;

                const res = await apiCall(url, 'GET');
                students = res.data.students;
                renderStudents();

            } catch (e) {
                list.innerHTML = `<div class="alert alert-danger">Failed to load students.</div>`;
            }
        }

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

                            <img src="${s.photo_url ?? '/images/default_avatar.png'}"
                                 class="student-photo"
                                 onerror="this.src='/images/default_avatar.png'">

                            <div class="flex-grow-1">
                                <h6 class="mb-1">${s.first_name} ${s.last_name}</h6>
                                <small class="text-muted">${s.admission_no}</small><br>
                                <small class="text-muted">${s.class_name ?? 'N/A'} - ${s.stream_name ?? 'N/A'}</small>

                                <div class="mt-2">
                                    ${s.photo_url
                                        ? `<span class="badge bg-success"><i class="bi bi-check-circle"></i> Photo</span>`
                                        : `<span class="badge bg-warning"><i class="bi bi-exclamation-circle"></i> No Photo</span>`
                                    }
                                    ${s.qr_code_path
                                        ? `<span class="badge bg-success ms-1"><i class="bi bi-qr-code"></i> QR</span>`
                                        : `<span class="badge bg-warning ms-1"><i class="bi bi-exclamation-circle"></i> No QR</span>`
                                    }
                                </div>

                                <div class="btn-group mt-3">
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="openUploadModal(${s.id}, '${s.first_name} ${s.last_name}')">
                                        <i class="bi bi-camera"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="generateQRCode(${s.id})">
                                        <i class="bi bi-qr-code"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="generateIDCard(${s.id})">
                                        <i class="bi bi-credit-card"></i>
                                    </button>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            `).join('');
        }

        /** Open upload modal */
        function openUploadModal(id, name) {
            document.getElementById("uploadStudentId").value = id;
            document.getElementById("studentNameLabel").textContent = name;
            document.getElementById("photoPreview").classList.add("d-none");
            uploadModal.show();
        }

        /** Preview photo before upload */
        function previewPhoto(input) {
            if (!input.files.length) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById("previewImage").src = e.target.result;
                document.getElementById("photoPreview").classList.remove("d-none");
            };
            reader.readAsDataURL(input.files[0]);
        }

        /** Upload student photo */
        async function uploadPhoto() {
            const fileInput = document.getElementById("photoInput");
            const id = document.getElementById("uploadStudentId").value;

            if (!fileInput.files.length) {
                alert("Please select a photo");
                return;
            }

            const formData = new FormData();
            formData.append("photo", fileInput.files[0]);

            try {
                const res = await fetch(`/api/students.php?action=upload-photo&id=${id}`, {
                    method: "POST",
                    body: formData
                });

                const json = await res.json();

                if (json.status === "success") {
                    uploadModal.hide();
                    loadStudents();
                } else {
                    alert(json.message);
                }

            } catch (e) {
                alert("Error uploading photo");
            }
        }

        /** Generate QR code */
        async function generateQRCode(id) {
            const res = await apiCall(`/api/students.php?action=generate-enhanced-qr&id=${id}`, 'GET');
            if (res.status === 'success') loadStudents();
        }

        /** Generate a single ID card */
        async function generateIDCard(id) {
            const res = await apiCall(`/api/students.php?action=generate-id-card&id=${id}`, 'GET');
            if (res.status === 'success') window.open(res.data.view_url, '_blank');
        }

        /** Bulk ID card generation */
        async function generateBulkIDCards() {
            const classId = document.getElementById("classFilter").value;
            if (!classId) {
                alert("Select a class first");
                return;
            }

            if (!confirm("Generate ID cards for this entire class?")) return;

            const res = await apiCall(`/api/students.php?action=generate-class-id-cards&class_id=${classId}`, 'GET');

            if (res.status === 'success') {
                alert(`Generated ${res.data.successful} ID cards`);
            }
        }
    </script>
</body>
</html>
