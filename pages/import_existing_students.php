<?php
/**
 * Import Existing Students Page
 * HTML structure only - all logic in js/pages/students.js (importStudentsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-primary text-white">
    <h2 class="mb-0">📥 Import Existing Students</h2>
  </div>
  <div class="card-body">
    <div class="alert alert-info" role="alert">
      <strong>📋 Supported Format:</strong> Upload CSV or Excel file with columns: FirstName, LastName, Email, AdmissionNumber, Class, Status
    </div>

    <!-- Import Methods -->
    <ul class="nav nav-tabs mb-4" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="file-upload-tab" data-bs-toggle="tab" data-bs-target="#fileUpload" type="button" role="tab">File Upload</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="template-download-tab" data-bs-toggle="tab" data-bs-target="#templateDownload" type="button" role="tab">Download Template</button>
      </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- File Upload Tab -->
      <div class="tab-pane fade show active" id="fileUpload" role="tabpanel">
        <form id="importForm" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Select File (CSV or Excel)</label>
            <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx,.xls" required>
            <small class="text-muted">Maximum file size: 5MB</small>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" id="skipHeader" class="form-check-input">
            <label class="form-check-label" for="skipHeader">First row contains column headers</label>
          </div>
          <button type="submit" class="btn btn-primary">Import Students</button>
        </form>

        <!-- Progress Section -->
        <div id="importProgress" class="mt-4" style="display:none;">
          <div class="progress mb-3">
            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
          </div>
          <p id="progressText" class="text-muted">Processing...</p>
        </div>

        <!-- Results Section -->
        <div id="importResults" class="mt-4" style="display:none;">
          <div class="alert" id="resultsAlert" role="alert"></div>
          <div id="resultsSummary"></div>
        </div>
      </div>

      <!-- Template Download Tab -->
      <div class="tab-pane fade" id="templateDownload" role="tabpanel">
        <div class="row">
          <div class="col-md-6">
            <div class="card border-primary">
              <div class="card-body">
                <h5 class="card-title">📊 CSV Template</h5>
                <p class="card-text">Download a template in CSV format to fill with student data.</p>
                <button type="button" class="btn btn-primary" onclick="importStudentsController.downloadTemplate('csv')">
                  Download CSV
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-success">
              <div class="card-body">
                <h5 class="card-title">📑 Excel Template</h5>
                <p class="card-text">Download a template in Excel format to fill with student data.</p>
                <button type="button" class="btn btn-success" onclick="importStudentsController.downloadTemplate('xlsx')">
                  Download Excel
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Sample Data Section -->
        <div class="mt-4">
          <h5>Sample Data Format</h5>
          <table class="table table-sm table-bordered">
            <thead class="table-light">
              <tr>
                <th>FirstName</th>
                <th>LastName</th>
                <th>Email</th>
                <th>AdmissionNumber</th>
                <th>Class</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Faith</td>
                <td>Wanjiku</td>
                <td>faith@example.com</td>
                <td>ADM001</td>
                <td>Grade 4</td>
                <td>Active</td>
              </tr>
              <tr>
                <td>Mercy</td>
                <td>Mwikali</td>
                <td>mercy@example.com</td>
                <td>ADM002</td>
                <td>Grade 5</td>
                <td>Active</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
      </div>
    </div>
  </div>
</div>
        }

        .method-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }

        .method-card.active {
            border-color: #007bff;
            background: #f8f9ff;
        }

        .upload-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s;
        }

        .upload-zone.dragover {
            border-color: #007bff;
            background: #f0f7ff;
        }

        .progress-container {
            display: none;
            margin-top: 20px;
        }

        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }

        .result-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }

        .result-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .result-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../layouts/app_layout.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2><i class="bi bi-people-fill"></i> Import Existing Students</h2>
                    <p class="text-muted">Add students who are already enrolled in your school</p>
                </div>
            </div>

            <!-- Method Selection -->
            <div class="row">
                <div class="col-12">
                    <div class="import-section">
                        <h4 class="mb-4">Choose Import Method</h4>

                        <div class="row">
                            <!-- Method 1: Single Student -->
                            <div class="col-md-4">
                                <div class="method-card" onclick="selectMethod('single')">
                                    <div class="text-center mb-3">
                                        <i class="bi bi-person-plus-fill" style="font-size: 3rem; color: #007bff;"></i>
                                    </div>
                                    <h5 class="text-center">Add Single Student</h5>
                                    <p class="text-center text-muted">Quick form to add one student at a time</p>
                                </div>
                            </div>

                            <!-- Method 2: Multiple Students Form -->
                            <div class="col-md-4">
                                <div class="method-card" onclick="selectMethod('multiple')">
                                    <div class="text-center mb-3">
                                        <i class="bi bi-list-ul" style="font-size: 3rem; color: #28a745;"></i>
                                    </div>
                                    <h5 class="text-center">Add Multiple Students</h5>
                                    <p class="text-center text-muted">Fill form to add multiple students</p>
                                </div>
                            </div>

                            <!-- Method 3: CSV/Excel Import -->
                            <div class="col-md-4">
                                <div class="method-card" onclick="selectMethod('file')">
                                    <div class="text-center mb-3">
                                        <i class="bi bi-file-earmark-spreadsheet-fill"
                                            style="font-size: 3rem; color: #ffc107;"></i>
                                    </div>
                                    <h5 class="text-center">Import from File</h5>
                                    <p class="text-center text-muted">Upload CSV/Excel file with student data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Student Form -->
            <div id="singleStudentSection" class="import-section" style="display: none;">
                <h4 class="mb-4"><i class="bi bi-person-plus"></i> Add Single Existing Student</h4>
                <form id="singleStudentForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Admission Number</label>
                            <input type="text" class="form-control" name="admission_no"
                                placeholder="Auto-generated if empty">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Admission Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="admission_date" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" id="classSelect" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stream</label>
                            <input type="text" class="form-control" name="stream_name" value="A">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Assessment Number</label>
                            <input type="text" class="form-control" name="assessment_number">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5>Parent/Guardian Information (Optional)</h5>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Parent First Name</label>
                            <input type="text" class="form-control" name="parent[first_name]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Parent Last Name</label>
                            <input type="text" class="form-control" name="parent[last_name]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Parent Phone</label>
                            <input type="tel" class="form-control" name="parent[phone_1]">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Relationship</label>
                            <select class="form-select" name="parent[relationship]">
                                <option value="parent">Parent</option>
                                <option value="mother">Mother</option>
                                <option value="father">Father</option>
                                <option value="guardian">Guardian</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Add Student
                        </button>
                        <button type="reset" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Multiple Students Form -->
            <div id="multipleStudentSection" class="import-section" style="display: none;">
                <h4 class="mb-4"><i class="bi bi-list-ul"></i> Add Multiple Existing Students</h4>
                <div class="mb-3">
                    <button type="button" class="btn btn-success" onclick="addStudentRow()">
                        <i class="bi bi-plus-circle"></i> Add Student Row
                    </button>
                </div>

                <div id="studentRowsContainer"></div>

                <div class="mt-4">
                    <button type="button" class="btn btn-primary btn-lg" onclick="submitMultipleStudents()">
                        <i class="bi bi-check-circle"></i> Add All Students
                    </button>
                </div>
            </div>

            <!-- File Import Section -->
            <div id="fileImportSection" class="import-section" style="display: none;">
                <h4 class="mb-4"><i class="bi bi-file-earmark-spreadsheet"></i> Import from CSV/Excel</h4>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Template Required:</strong> Please download and use our template to ensure proper
                    formatting.
                    <button class="btn btn-sm btn-info float-end" onclick="downloadTemplate()">
                        <i class="bi bi-download"></i> Download Template
                    </button>
                </div>

                <div class="upload-zone" id="uploadZone">
                    <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                    <h5 class="mt-3">Drop your CSV/Excel file here</h5>
                    <p class="text-muted">or click to browse</p>
                    <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" style="display: none;">
                    <button class="btn btn-primary mt-2" onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-folder-open"></i> Choose File
                    </button>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgress"
                            role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-center mt-2" id="progressText">Processing...</p>
                </div>

                <div id="importResults" class="mt-4"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/api.js"></script>
    <script>
        let currentMethod = null;
        let studentRowCount = 0;

        // Load classes on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadClasses();
        });

        // Select import method
        function selectMethod(method) {
            currentMethod = method;

            // Update active state
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Hide all sections
            document.getElementById('singleStudentSection').style.display = 'none';
            document.getElementById('multipleStudentSection').style.display = 'none';
            document.getElementById('fileImportSection').style.display = 'none';

            // Show selected section
            if (method === 'single') {
                document.getElementById('singleStudentSection').style.display = 'block';
            } else if (method === 'multiple') {
                document.getElementById('multipleStudentSection').style.display = 'block';
                if (studentRowCount === 0) {
                    addStudentRow();
                    addStudentRow();
                }
            } else if (method === 'file') {
                document.getElementById('fileImportSection').style.display = 'block';
            }
        }

        // Load classes from API
        async function loadClasses() {
            try {
                const response = await apiCall('/api/academic.php?action=list-classes', 'GET');
                const select = document.getElementById('classSelect');

                if (response.data && response.data.classes) {
                    response.data.classes.forEach(cls => {
                        const option = document.createElement('option');
                        option.value = cls.id;
                        option.textContent = cls.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load classes:', error);
            }
        }

        // Single student form submission
        document.getElementById('singleStudentForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {};

            // Convert FormData to nested object
            for (let [key, value] of formData.entries()) {
                if (key.includes('[')) {
                    const match = key.match(/(\w+)\[(\w+)\]/);
                    if (match) {
                        if (!data[match[1]]) data[match[1]] = {};
                        data[match[1]][match[2]] = value;
                    }
                } else {
                    data[key] = value;
                }
            }

            try {
                const response = await apiCall('/api/students.php?action=add-existing-student', 'POST', data);

                if (response.status === 'success') {
                    alert('Student added successfully!');
                    this.reset();
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('Failed to add student: ' + error.message);
            }
        });

        // Add student row for multiple entry
        function addStudentRow() {
            studentRowCount++;
            const container = document.getElementById('studentRowsContainer');
            const row = document.createElement('div');
            row.className = 'card mb-3';
            row.innerHTML = `
                <div class="card-header bg-light d-flex justify-content-between">
                    <span>Student ${studentRowCount}</span>
                    <button class="btn btn-sm btn-danger" onclick="this.closest('.card').remove()">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" class="form-control" placeholder="First Name*" name="first_name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" placeholder="Last Name*" name="last_name" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="gender" required>
                                <option value="">Gender*</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="class_id" required>
                                <option value="">Class*</option>
                                ${Array.from(document.getElementById('classSelect').options).slice(1).map(opt =>
                `<option value="${opt.value}">${opt.text}</option>`
            ).join('')}
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(row);
        }

        // Submit multiple students
        async function submitMultipleStudents() {
            const cards = document.querySelectorAll('#studentRowsContainer .card');
            const students = [];

            cards.forEach(card => {
                const inputs = card.querySelectorAll('input, select');
                const student = {};
                inputs.forEach(input => {
                    if (input.name && input.value) {
                        student[input.name] = input.value;
                    }
                });
                if (Object.keys(student).length > 0) {
                    student.admission_date = new Date().toISOString().split('T')[0];
                    students.push(student);
                }
            });

            if (students.length === 0) {
                alert('Please add at least one student');
                return;
            }

            try {
                const response = await apiCall('/api/students.php?action=add-multiple-existing', 'POST', {
                    students: students
                });

                if (response.status === 'success' || response.status === 'partial') {
                    alert(response.message);
                    displayImportResults(response.data);
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('Failed to add students: ' + error.message);
            }
        }

        // File upload handling
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');

        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        async function handleFileUpload(file) {
            const formData = new FormData();
            formData.append('file', file);

            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('uploadProgress').style.width = '50%';

            try {
                const response = await fetch('/api/students.php?action=import-existing-students', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                document.getElementById('uploadProgress').style.width = '100%';

                setTimeout(() => {
                    document.getElementById('progressContainer').style.display = 'none';
                    displayImportResults(result.data);
                }, 500);

            } catch (error) {
                alert('Upload failed: ' + error.message);
                document.getElementById('progressContainer').style.display = 'none';
            }
        }

        function displayImportResults(data) {
            const resultsDiv = document.getElementById('importResults');
            let html = `
                <div class="alert alert-info">
                    <h5>Import Summary</h5>
                    <ul>
                        <li>Total Processed: ${data.total}</li>
                        <li class="text-success">Successful: ${data.successful}</li>
                        <li class="text-danger">Failed: ${data.failed}</li>
                        ${data.skipped ? `<li class="text-warning">Skipped: ${data.skipped}</li>` : ''}
                    </ul>
                </div>
            `;

            if (data.errors && data.errors.length > 0) {
                html += '<h6>Errors:</h6>';
                data.errors.forEach(err => {
                    html += `<div class="result-item result-error">
                        <strong>Row ${err.row || err.index}:</strong> ${err.error}
                    </div>`;
                });
            }

            if (data.warnings && data.warnings.length > 0) {
                html += '<h6>Warnings:</h6>';
                data.warnings.forEach(warn => {
                    html += `<div class="result-item result-warning">
                        <strong>Row ${warn.row}:</strong> ${warn.message}
                    </div>`;
                });
            }

            resultsDiv.innerHTML = html;
        }

        async function downloadTemplate() {
            try {
                const response = await apiCall('/api/students.php?action=import-template', 'GET');

                if (response.status === 'success') {
                    const csv = [
                        response.data.headers.join(','),
                        response.data.sample[0].map(val => `"${val}"`).join(',')
                    ].join('\n');

                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'student_import_template.csv';
                    a.click();
                }
            } catch (error) {
                alert('Failed to download template: ' + error.message);
            }
        }
    </script>
</body>

</html>