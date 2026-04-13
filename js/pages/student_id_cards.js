/**
 * Student ID Cards Controller
 * Page: pages/student_id_cards.php
 *
 * Wires ID card management to real Students API endpoints:
 * - /students/student
 * - /students/photo-upload
 * - /students/qr-code-generate[-enhanced]
 * - /students/id-card-generate
 * - /students/id-card-generate-class
 * - /students/id-card-get/{id}
 * - /students/id-card-statistics-get
 */

const StudentIdCardsController = {
  state: {
    students: [],
    classes: [],
    streams: [],
    selectedStudents: new Set(),
    currentCard: null,
    permissions: {
      canView: false,
      canUploadPhoto: false,
      canGenerateQr: false,
      canGenerateCard: false,
      canExport: false,
    },
  },
  ui: {},
  modals: {
    upload: null,
    idCard: null,
    bulkActions: null,
  },

  init: async function () {
    if (!window.AuthContext?.isAuthenticated?.()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.resolvePermissions();
    this.initModals();
    this.bindEvents();
    this.applyPermissionState();

    await this.loadClasses();
    await this.loadStudents();
    await this.loadStatistics();
  },

  cacheDom: function () {
    this.ui = {
      classFilter: document.getElementById("classFilter"),
      streamFilter: document.getElementById("streamFilter"),
      searchInput: document.getElementById("searchInput"),
      studentsList: document.getElementById("studentsList"),
      totalStudents: document.getElementById("totalStudents"),
      studentsWithPhotos: document.getElementById("studentsWithPhotos"),
      studentsWithQRCodes: document.getElementById("studentsWithQRCodes"),
      idCardsGenerated: document.getElementById("idCardsGenerated"),
      photoInput: document.getElementById("photoInput"),
      photoPreview: document.getElementById("photoPreview"),
      previewImage: document.getElementById("previewImage"),
      uploadStudentId: document.getElementById("uploadStudentId"),
      studentNameLabel: document.getElementById("studentNameLabel"),
      idCardPreview: document.getElementById("idCardPreview"),
      bulkActionType: document.getElementById("bulkActionType"),
      selectedStudentsRadio: document.getElementById("selectedStudents"),
      allStudentsRadio: document.getElementById("allStudents"),
      selectedStudentsList: document.getElementById("selectedStudentsList"),
      bulkStudentCheckboxes: document.getElementById("bulkStudentCheckboxes"),
    };
  },

  resolvePermissions: function () {
    const hasAny = (codes) => {
      if (!window.AuthContext?.hasAnyPermission) return false;
      return window.AuthContext.hasAnyPermission(codes);
    };

    const canView = hasAny([
      "students_qr_view",
      "students_qr_view_all",
      "students_qr_view_own",
      "students_view",
      "students_view_all",
      "students_view_own",
    ]);

    this.state.permissions = {
      canView,
      canUploadPhoto: hasAny([
        "students_qr_upload",
        "students_upload",
        "students_edit",
        "students_update",
        "students_create",
      ]),
      canGenerateQr: hasAny([
        "students_qr_generate",
        "students_qr_create",
        "students_generate",
        "students_edit",
      ]),
      canGenerateCard: hasAny([
        "students_qr_generate",
        "students_generate",
        "students_print",
        "students_edit",
      ]),
      canExport: hasAny([
        "students_qr_download",
        "students_qr_export",
        "students_export",
        "students_print",
      ]),
    };
  },

  initModals: function () {
    if (window.bootstrap?.Modal) {
      const uploadEl = document.getElementById("uploadPhotoModal");
      const idCardEl = document.getElementById("idCardModal");
      const bulkEl = document.getElementById("bulkActionsModal");

      if (uploadEl) this.modals.upload = new bootstrap.Modal(uploadEl);
      if (idCardEl) this.modals.idCard = new bootstrap.Modal(idCardEl);
      if (bulkEl) this.modals.bulkActions = new bootstrap.Modal(bulkEl);
    }
  },

  bindEvents: function () {
    const debounce = (fn, wait = 300) => {
      let timer = null;
      return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
      };
    };

    this.ui.classFilter?.addEventListener("change", async () => {
      await this.loadStreams();
      await this.loadStudents();
      await this.loadStatistics();
    });

    this.ui.streamFilter?.addEventListener("change", async () => {
      await this.loadStudents();
      await this.loadStatistics();
    });

    this.ui.searchInput?.addEventListener(
      "keyup",
      debounce(async () => {
        await this.loadStudents();
        await this.loadStatistics();
      }, 250),
    );

    this.ui.photoInput?.addEventListener("change", () => this.previewPhoto(this.ui.photoInput));

    this.ui.selectedStudentsRadio?.addEventListener("change", () => this.toggleBulkStudentSelection());
    this.ui.allStudentsRadio?.addEventListener("change", () => this.toggleBulkStudentSelection());
  },

  applyPermissionState: function () {
    if (this.state.permissions.canView) {
      return;
    }

    if (this.ui.studentsList) {
      this.ui.studentsList.innerHTML = `
        <div class="col-12">
          <div class="alert alert-warning mb-0">
            <i class="bi bi-shield-lock me-2"></i>
            You do not have permission to access Student ID Card management.
          </div>
        </div>
      `;
    }
  },

  loadClasses: async function () {
    if (!this.state.permissions.canView) return;

    try {
      const response = await window.API.academic.listClasses();
      const classes = this.unwrapList(response);
      this.state.classes = classes;

      if (!this.ui.classFilter) return;
      this.ui.classFilter.innerHTML = '<option value="">All Classes</option>';

      classes.forEach((cls) => {
        const option = document.createElement("option");
        option.value = cls.id;
        option.textContent = cls.name || cls.class_name || `Class ${cls.id}`;
        this.ui.classFilter.appendChild(option);
      });
    } catch (error) {
      console.error("Failed to load classes:", error);
      this.notify("Failed to load classes", "error");
    }
  },

  loadStreams: async function () {
    if (!this.ui.streamFilter) return;
    const classId = this.ui.classFilter?.value;

    this.ui.streamFilter.innerHTML = '<option value="">All Streams</option>';
    this.state.streams = [];

    if (!classId) return;

    try {
      const response = await window.API.academic.listStreams({
        class_id: classId,
      });
      const streams = this.unwrapList(response);
      this.state.streams = streams;

      streams.forEach((stream) => {
        const option = document.createElement("option");
        option.value = stream.id;
        option.textContent = stream.stream_name || stream.name || `Stream ${stream.id}`;
        this.ui.streamFilter.appendChild(option);
      });
    } catch (error) {
      console.error("Failed to load streams:", error);
      this.notify("Failed to load streams", "warning");
    }
  },

  loadStudents: async function () {
    if (!this.state.permissions.canView) return;

    if (this.ui.studentsList) {
      this.ui.studentsList.innerHTML = `
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2 mb-0">Loading students...</p>
        </div>
      `;
    }

    try {
      const params = {
        page: 1,
        limit: 500,
        status: "active",
      };
      const search = this.ui.searchInput?.value?.trim();
      const classId = this.ui.classFilter?.value;
      const streamId = this.ui.streamFilter?.value;

      if (search) params.search = search;
      if (classId) params.class_id = classId;
      if (streamId) params.stream_id = streamId;

      const response = await window.API.students.getAll(params);
      this.state.students = Array.isArray(response?.data) ? response.data : [];

      this.reconcileSelections();
      this.renderStudents();
      this.renderBulkStudentList();
      this.attachSelectionListeners();
    } catch (error) {
      console.error("Failed to load students:", error);
      if (this.ui.studentsList) {
        this.ui.studentsList.innerHTML = `
          <div class="col-12">
            <div class="alert alert-danger mb-0">
              Failed to load students. Please try again.
            </div>
          </div>
        `;
      }
    }
  },

  loadStatistics: async function () {
    if (!this.state.permissions.canView) return;

    const params = {};
    const search = this.ui.searchInput?.value?.trim();
    const classId = this.ui.classFilter?.value;
    const streamId = this.ui.streamFilter?.value;

    if (search) params.search = search;
    if (classId) params.class_id = classId;
    if (streamId) params.stream_id = streamId;

    try {
      const response = await window.API.students.getIdCardStatistics(params);
      const payload = this.unwrapPayload(response);
      const stats = payload?.data ?? payload ?? {};

      this.setText(this.ui.totalStudents, stats.total ?? 0);
      this.setText(this.ui.studentsWithPhotos, stats.with_photos ?? 0);
      this.setText(this.ui.studentsWithQRCodes, stats.with_qr_codes ?? 0);
      this.setText(this.ui.idCardsGenerated, stats.id_cards_generated ?? 0);
    } catch (error) {
      // Fallback to local aggregate from loaded list
      const total = this.state.students.length;
      const withPhotos = this.state.students.filter((s) => this.hasValue(s.photo_url)).length;
      const withQr = this.state.students.filter((s) => this.hasValue(s.qr_code_path)).length;
      const ready = this.state.students.filter((s) => this.hasValue(s.photo_url) && this.hasValue(s.qr_code_path)).length;
      this.setText(this.ui.totalStudents, total);
      this.setText(this.ui.studentsWithPhotos, withPhotos);
      this.setText(this.ui.studentsWithQRCodes, withQr);
      this.setText(this.ui.idCardsGenerated, ready);
    }
  },

  renderStudents: function () {
    if (!this.ui.studentsList) return;

    if (!this.state.students.length) {
      this.ui.studentsList.innerHTML = `
        <div class="col-12">
          <div class="alert alert-info mb-0">
            No students found for the selected filters.
          </div>
        </div>
      `;
      return;
    }

    this.ui.studentsList.innerHTML = this.state.students
      .map((student) => this.renderStudentCard(student))
      .join("");
  },

  renderStudentCard: function (student) {
    const id = Number(student.id || 0);
    const name = this.escapeHtml(this.getStudentName(student));
    const admission = this.escapeHtml(student.admission_no || "-");
    const classInfo = this.escapeHtml(
      `${student.class_name || "N/A"} - ${student.stream_name || "N/A"}`,
    );
    const photoUrl = this.getAvatarUrl(student);
    const hasPhoto = this.hasValue(student.photo_url);
    const hasQr = this.hasValue(student.qr_code_path);
    const canUpload = this.state.permissions.canUploadPhoto;
    const canGenerateQr = this.state.permissions.canGenerateQr;
    const canGenerateCard = this.state.permissions.canGenerateCard;
    const selected = this.state.selectedStudents.has(id) ? "checked" : "";

    return `
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="student-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="form-check m-0">
              <input
                class="form-check-input student-select-checkbox"
                type="checkbox"
                id="selectStudent${id}"
                data-student-id="${id}"
                ${selected}
              >
            </div>
            <small class="text-muted">${admission}</small>
          </div>

          <div class="d-flex gap-3">
            <img
              src="${photoUrl}"
              class="student-photo"
              alt="${name}"
              loading="lazy"
            >
            <div class="flex-grow-1">
              <h6 class="mb-1">${name}</h6>
              <small class="text-muted d-block">${classInfo}</small>

              <div class="mt-2 mb-3">
                ${
                  hasPhoto
                    ? `<span class="badge bg-success"><i class="bi bi-check-circle"></i> Photo</span>`
                    : `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> No Photo</span>`
                }
                ${
                  hasQr
                    ? `<span class="badge bg-success ms-1"><i class="bi bi-qr-code"></i> QR</span>`
                    : `<span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-circle"></i> No QR</span>`
                }
                ${
                  hasPhoto && hasQr
                    ? `<span class="badge bg-info ms-1"><i class="bi bi-credit-card"></i> Ready</span>`
                    : `<span class="badge bg-secondary ms-1"><i class="bi bi-credit-card"></i> Pending</span>`
                }
              </div>

              <div class="btn-group btn-group-sm flex-wrap" role="group">
                <button
                  class="btn btn-outline-primary"
                  onclick="openUploadModal(${id}, ${JSON.stringify(this.getStudentName(student))})"
                  title="Upload Photo"
                  ${canUpload ? "" : "disabled"}
                >
                  <i class="bi bi-camera"></i>
                </button>
                <button
                  class="btn btn-outline-info"
                  onclick="generateQRCode(${id})"
                  title="Generate QR Code"
                  ${canGenerateQr ? "" : "disabled"}
                >
                  <i class="bi bi-qr-code"></i>
                </button>
                <button
                  class="btn btn-outline-success"
                  onclick="generateIDCard(${id})"
                  title="Generate ID Card"
                  ${canGenerateCard ? "" : "disabled"}
                >
                  <i class="bi bi-credit-card"></i>
                </button>
                <button
                  class="btn btn-outline-secondary"
                  onclick="viewIDCard(${id})"
                  title="View ID Card"
                >
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  },

  reconcileSelections: function () {
    const validIds = new Set(this.state.students.map((s) => Number(s.id)));
    for (const id of this.state.selectedStudents) {
      if (!validIds.has(id)) {
        this.state.selectedStudents.delete(id);
      }
    }
  },

  renderBulkStudentList: function () {
    if (!this.ui.bulkStudentCheckboxes) return;
    this.ui.bulkStudentCheckboxes.innerHTML = this.state.students
      .map((student) => {
        const id = Number(student.id);
        const checked = this.state.selectedStudents.has(id) ? "checked" : "";
        return `
          <div class="form-check">
            <input class="form-check-input bulk-student-checkbox" type="checkbox" value="${id}" id="bulkStudent${id}" ${checked}>
            <label class="form-check-label" for="bulkStudent${id}">
              ${this.escapeHtml(this.getStudentName(student))} (${this.escapeHtml(student.admission_no || "-")})
            </label>
          </div>
        `;
      })
      .join("");
  },

  toggleBulkStudentSelection: function () {
    const showSelected = this.ui.selectedStudentsRadio?.checked;
    if (this.ui.selectedStudentsList) {
      this.ui.selectedStudentsList.classList.toggle("d-none", !showSelected);
    }
  },

  attachSelectionListeners: function () {
    document.querySelectorAll(".student-select-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", (event) => {
        const id = Number(event.target.getAttribute("data-student-id"));
        if (!id) return;
        if (event.target.checked) {
          this.state.selectedStudents.add(id);
        } else {
          this.state.selectedStudents.delete(id);
        }
        this.renderBulkStudentList();
      });
    });

    document.querySelectorAll(".bulk-student-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", (event) => {
        const id = Number(event.target.value);
        if (!id) return;
        if (event.target.checked) {
          this.state.selectedStudents.add(id);
        } else {
          this.state.selectedStudents.delete(id);
        }
        const cardCheckbox = document.getElementById(`selectStudent${id}`);
        if (cardCheckbox) {
          cardCheckbox.checked = event.target.checked;
        }
      });
    });
  },

  openUploadModal: function (studentId, studentName) {
    if (!this.state.permissions.canUploadPhoto) {
      this.notify("You do not have permission to upload photos.", "warning");
      return;
    }

    this.setValue(this.ui.uploadStudentId, studentId);
    this.setText(this.ui.studentNameLabel, studentName || "Student");
    this.ui.photoInput && (this.ui.photoInput.value = "");
    this.ui.photoPreview?.classList.add("d-none");
    this.modals.upload?.show();
  },

  previewPhoto: function (input) {
    if (!input?.files?.length) return;

    const file = input.files[0];
    const maxBytes = 5 * 1024 * 1024;
    if (file.size > maxBytes) {
      this.notify("File size must be less than 5MB.", "warning");
      input.value = "";
      this.ui.photoPreview?.classList.add("d-none");
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      if (this.ui.previewImage) {
        this.ui.previewImage.src = event.target?.result || "";
      }
      this.ui.photoPreview?.classList.remove("d-none");
    };
    reader.readAsDataURL(file);
  },

  uploadPhoto: async function () {
    if (!this.state.permissions.canUploadPhoto) {
      this.notify("You do not have permission to upload photos.", "warning");
      return;
    }

    const studentId = Number(this.ui.uploadStudentId?.value || 0);
    const file = this.ui.photoInput?.files?.[0];

    if (!studentId) {
      this.notify("Invalid student selected for photo upload.", "error");
      return;
    }
    if (!file) {
      this.notify("Please choose a photo to upload.", "warning");
      return;
    }

    const formData = new FormData();
    formData.append("student_id", String(studentId));
    formData.append("photo", file);

    try {
      const response = await window.API.students.uploadPhoto(formData);
      const payload = this.unwrapPayload(response);
      if (payload?.status === "error") {
        throw new Error(payload.message || "Photo upload failed");
      }

      this.notify(payload?.message || "Photo uploaded successfully.", "success");
      this.modals.upload?.hide();
      await this.loadStudents();
      await this.loadStatistics();
      this.attachSelectionListeners();
    } catch (error) {
      console.error("Photo upload error:", error);
      this.notify(error.message || "Failed to upload student photo.", "error");
    }
  },

  generateQRCode: async function (studentId) {
    if (!this.state.permissions.canGenerateQr) {
      this.notify("You do not have permission to generate QR codes.", "warning");
      return;
    }

    try {
      let response;
      try {
        response = await window.API.students.generateEnhancedQrCode(studentId);
      } catch (enhancedErr) {
        response = await window.API.students.generateQrCode(studentId);
      }

      const payload = this.unwrapPayload(response);
      if (payload?.status === "error") {
        throw new Error(payload.message || "QR generation failed");
      }

      this.notify(payload?.message || "QR code generated successfully.", "success");
      await this.loadStudents();
      await this.loadStatistics();
      this.attachSelectionListeners();
    } catch (error) {
      console.error("QR generation error:", error);
      this.notify(error.message || "Failed to generate QR code.", "error");
    }
  },

  generateIDCard: async function (studentId) {
    if (!this.state.permissions.canGenerateCard) {
      this.notify("You do not have permission to generate ID cards.", "warning");
      return;
    }

    try {
      const response = await window.API.students.generateIdCard(studentId);
      const payload = this.unwrapPayload(response);
      if (payload?.status === "error") {
        throw new Error(payload.message || "ID card generation failed");
      }

      const resultData = payload?.data ?? payload;
      if (resultData?.view_url) {
        this.state.currentCard = {
          ...(this.state.currentCard || {}),
          student_id: studentId,
          view_url: this.normalizeAssetPath(resultData.view_url),
        };
      }

      this.notify(payload?.message || "ID card generated successfully.", "success");
      await this.loadStudents();
      await this.loadStatistics();
      this.attachSelectionListeners();
      await this.viewIDCard(studentId);
    } catch (error) {
      console.error("ID card generation error:", error);
      this.notify(error.message || "Failed to generate ID card.", "error");
    }
  },

  viewIDCard: async function (studentId) {
    try {
      const response = await window.API.students.getIdCard(studentId);
      const payload = this.unwrapPayload(response);
      const data = payload?.data ?? payload;

      if (!data || !data.id) {
        throw new Error(payload?.message || "Unable to load student ID card data.");
      }

      this.state.currentCard = {
        student_id: data.id,
        view_url: this.normalizeAssetPath(data.view_url || ""),
      };

      this.renderIDCardPreview(data);
      this.modals.idCard?.show();
    } catch (error) {
      console.error("View ID card error:", error);
      this.notify(error.message || "Failed to load ID card preview.", "error");
    }
  },

  renderIDCardPreview: function (cardData) {
    if (!this.ui.idCardPreview) return;

    const name = this.escapeHtml(
      cardData.full_name || `${cardData.first_name || ""} ${cardData.last_name || ""}`.trim(),
    );
    const admission = this.escapeHtml(cardData.admission_no || "-");
    const className = this.escapeHtml(cardData.class_name || "N/A");
    const streamName = this.escapeHtml(cardData.stream_name || "N/A");
    const dob = this.escapeHtml(cardData.date_of_birth || "N/A");
    const photo = this.normalizeAssetPath(cardData.photo_url) || this.makeAvatarDataUri(name);
    const qr = this.normalizeAssetPath(cardData.qr_code_url || cardData.qr_code_path || "");

    this.ui.idCardPreview.innerHTML = `
      <div class="id-card">
        <div class="id-card-header">KINGSWAY PREPARATORY SCHOOL</div>
        <div class="id-card-body">
          <img src="${photo}" class="id-card-photo" alt="${name}">
          <div class="id-card-info">
            <p><strong>${name || "Student"}</strong></p>
            <p>Adm: ${admission}</p>
            <p>Class: ${className}</p>
            <p>Stream: ${streamName}</p>
            <p>DOB: ${dob}</p>
          </div>
          <div class="id-card-qr">
            ${
              qr
                ? `<img src="${qr}" alt="QR Code" style="width:100%;height:100%;object-fit:contain;">`
                : "QR"
            }
          </div>
        </div>
      </div>
    `;
  },

  printIDCard: function () {
    const content = this.ui.idCardPreview?.innerHTML?.trim();
    if (!content) {
      this.notify("No ID card is loaded for printing.", "warning");
      return;
    }

    const printWindow = window.open("", "_blank");
    if (!printWindow) {
      this.notify("Please allow popups to print the ID card.", "warning");
      return;
    }

    printWindow.document.write(`
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Student ID Card</title>
        <style>
          body { margin: 0; padding: 24px; font-family: Arial, sans-serif; }
          .id-card { margin: 0 auto; width: 3.375in; height: 2.125in; border: 1px solid #000; }
          .id-card-header { background: #1e3a8a; color: #fff; font-size: 12px; font-weight: 700; text-align: center; padding: 8px; }
          .id-card-body { display: flex; gap: 10px; padding: 10px; position: relative; }
          .id-card-photo { width: 80px; height: 100px; object-fit: cover; border: 1px solid #d1d5db; }
          .id-card-info { font-size: 10px; line-height: 1.2; }
          .id-card-info p { margin: 2px 0; }
          .id-card-qr { position: absolute; width: 58px; height: 58px; right: 10px; bottom: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #d1d5db; font-size: 8px; color: #6b7280; }
        </style>
      </head>
      <body>${content}</body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  },

  downloadIDCard: function () {
    const studentId = this.state.currentCard?.student_id;
    if (!studentId) {
      this.notify("Open a student ID card preview first.", "warning");
      return;
    }

    // Open generated HTML card (server output) when available; otherwise open preview route.
    const url =
      this.state.currentCard?.view_url ||
      (window.APP_BASE || "") + `/api/students/id-card-get/${encodeURIComponent(studentId)}`;
    window.open(url, "_blank");
  },

  generateBulkIDCards: async function () {
    if (!this.state.permissions.canGenerateCard) {
      this.notify("You do not have permission to generate ID cards.", "warning");
      return;
    }

    const classId = this.ui.classFilter?.value;
    if (!classId) {
      this.notify("Select a class first to run class bulk generation.", "warning");
      return;
    }

    const confirmed = window.confirm("Generate ID cards for all active students in the selected class?");
    if (!confirmed) return;

    try {
      const response = await window.API.students.generateClassIdCards(classId);
      const payload = this.unwrapPayload(response);
      const data = payload?.data ?? payload ?? {};

      const total = data.successful ?? data.generated ?? data.total ?? 0;
      this.notify(payload?.message || `Generated ${total} ID cards successfully.`, "success");
      await this.loadStudents();
      await this.loadStatistics();
      this.attachSelectionListeners();
    } catch (error) {
      console.error("Bulk ID card generation failed:", error);
      this.notify(error.message || "Bulk ID card generation failed.", "error");
    }
  },

  executeBulkAction: async function () {
    const action = this.ui.bulkActionType?.value || "generate_cards";
    const selectedOnly = !!this.ui.selectedStudentsRadio?.checked;

    if (!selectedOnly) {
      if (action === "generate_qr") {
        await this.generateQRCodesForStudents(this.state.students.map((s) => Number(s.id)));
      } else if (action === "generate_cards") {
        await this.generateBulkIDCards();
      } else {
        this.exportIDCards();
      }
      return;
    }

    const ids = Array.from(this.state.selectedStudents);
    if (!ids.length) {
      this.notify("No students selected.", "warning");
      return;
    }

    if (action === "generate_qr") {
      await this.generateQRCodesForStudents(ids);
    } else if (action === "generate_cards") {
      await this.generateCardsForStudents(ids);
    } else {
      this.exportIDCards(ids);
    }
  },

  generateQRCodesForStudents: async function (studentIds) {
    if (!this.state.permissions.canGenerateQr) {
      this.notify("You do not have permission to generate QR codes.", "warning");
      return;
    }

    let success = 0;
    for (const studentId of studentIds) {
      try {
        await window.API.students.generateEnhancedQrCode(studentId);
        success += 1;
      } catch (error) {
        console.warn("QR generation failed for student", studentId, error);
      }
    }

    this.notify(`Generated QR codes for ${success} of ${studentIds.length} students.`, "info");
    await this.loadStudents();
    await this.loadStatistics();
    this.attachSelectionListeners();
  },

  generateCardsForStudents: async function (studentIds) {
    if (!this.state.permissions.canGenerateCard) {
      this.notify("You do not have permission to generate ID cards.", "warning");
      return;
    }

    let success = 0;
    for (const studentId of studentIds) {
      try {
        await window.API.students.generateIdCard(studentId);
        success += 1;
      } catch (error) {
        console.warn("ID card generation failed for student", studentId, error);
      }
    }

    this.notify(`Generated ID cards for ${success} of ${studentIds.length} students.`, "info");
    await this.loadStudents();
    await this.loadStatistics();
    this.attachSelectionListeners();
  },

  exportIDCards: function (limitIds = null) {
    if (!this.state.permissions.canExport) {
      this.notify("You do not have permission to export ID card data.", "warning");
      return;
    }

    const whitelist = Array.isArray(limitIds) && limitIds.length ? new Set(limitIds.map(Number)) : null;
    const rows = this.state.students
      .filter((student) => (whitelist ? whitelist.has(Number(student.id)) : true))
      .map((student) => ({
        admission_no: student.admission_no || "",
        first_name: student.first_name || "",
        last_name: student.last_name || "",
        class_name: student.class_name || "",
        stream_name: student.stream_name || "",
        has_photo: this.hasValue(student.photo_url) ? "Yes" : "No",
        has_qr_code: this.hasValue(student.qr_code_path) ? "Yes" : "No",
      }));

    if (!rows.length) {
      this.notify("No student rows available for export.", "warning");
      return;
    }

    const headers = Object.keys(rows[0]);
    const csvLines = [headers.join(",")];
    rows.forEach((row) => {
      csvLines.push(
        headers
          .map((header) => {
            const value = String(row[header] ?? "");
            return `"${value.replace(/"/g, '""')}"`;
          })
          .join(","),
      );
    });

    const csv = csvLines.join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `student_id_cards_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  resetFilters: async function () {
    this.setValue(this.ui.searchInput, "");
    this.setValue(this.ui.classFilter, "");
    if (this.ui.streamFilter) {
      this.ui.streamFilter.innerHTML = '<option value="">All Streams</option>';
    }

    this.state.selectedStudents.clear();
    await this.loadStudents();
    await this.loadStatistics();
    this.attachSelectionListeners();
  },

  getStudentName: function (student) {
    return `${student.first_name || ""} ${student.last_name || ""}`.trim() || "Unnamed Student";
  },

  hasValue: function (value) {
    return value !== null && value !== undefined && String(value).trim() !== "";
  },

  unwrapPayload: function (response) {
    // Common response shapes in this codebase:
    // 1) {status,data,...}
    // 2) {data:{status,data,...}}
    // 3) {success,data,...}
    if (!response) return null;
    if (response.data && typeof response.data === "object" && "status" in response.data) {
      return response.data;
    }
    if (typeof response === "object" && ("status" in response || "success" in response)) {
      return response;
    }
    if (response.data && typeof response.data === "object") {
      return response.data;
    }
    return response;
  },

  unwrapList: function (response) {
    const payload = this.unwrapPayload(response);
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.students)) return payload.students;
    if (Array.isArray(payload?.classes)) return payload.classes;
    if (Array.isArray(payload?.streams)) return payload.streams;
    if (Array.isArray(payload?.items)) return payload.items;
    return [];
  },

  normalizeAssetPath: function (path) {
    const value = String(path || "").trim();
    if (!value) return "";
    if (/^(https?:)?\/\//i.test(value) || value.startsWith("data:")) return value;
    if (value.startsWith(window.APP_BASE + '/')) return value;
    if (value.startsWith("/")) return `${window.APP_BASE || ''}${value}`;
    return (window.APP_BASE || "") + `/${value.replace(/^\/+/, "")}`;
  },

  getAvatarUrl: function (student) {
    const normalized = this.normalizeAssetPath(student.photo_url || "");
    if (normalized) return normalized;
    return this.makeAvatarDataUri(this.getStudentName(student));
  },

  makeAvatarDataUri: function (name) {
    const initials = String(name || "?")
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join("");
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" width="160" height="200" viewBox="0 0 160 200">
        <rect width="160" height="200" fill="#e2e8f0" />
        <circle cx="80" cy="70" r="28" fill="#94a3b8" />
        <rect x="40" y="115" width="80" height="52" rx="12" fill="#94a3b8" />
        <text x="80" y="192" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" fill="#334155">${initials || "ST"}</text>
      </svg>
    `;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
  },

  escapeHtml: function (value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  setText: function (element, value) {
    if (element) {
      element.textContent = String(value ?? "");
    }
  },

  setValue: function (element, value) {
    if (element) {
      element.value = value ?? "";
    }
  },

  notify: function (message, type = "info") {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
      return;
    }
    // Fallback for pages where notification modal is not loaded
    window.alert(`${String(type).toUpperCase()}: ${message}`);
  },
};

// Global wrappers for inline HTML handlers in pages/student_id_cards.php
window.loadStudents = () => StudentIdCardsController.loadStudents();
window.loadStreams = () => StudentIdCardsController.loadStreams();
window.resetFilters = () => StudentIdCardsController.resetFilters();
window.openUploadModal = (id, name) => StudentIdCardsController.openUploadModal(id, name);
window.previewPhoto = (input) => StudentIdCardsController.previewPhoto(input);
window.uploadPhoto = () => StudentIdCardsController.uploadPhoto();
window.generateQRCode = (studentId) => StudentIdCardsController.generateQRCode(studentId);
window.generateIDCard = (studentId) => StudentIdCardsController.generateIDCard(studentId);
window.viewIDCard = (studentId) => StudentIdCardsController.viewIDCard(studentId);
window.printIDCard = () => StudentIdCardsController.printIDCard();
window.downloadIDCard = () => StudentIdCardsController.downloadIDCard();
window.generateBulkIDCards = () => StudentIdCardsController.generateBulkIDCards();
window.exportIDCards = () => StudentIdCardsController.exportIDCards();
window.executeBulkAction = () => StudentIdCardsController.executeBulkAction();

document.addEventListener("DOMContentLoaded", async () => {
  await StudentIdCardsController.init();
  StudentIdCardsController.attachSelectionListeners();
});
