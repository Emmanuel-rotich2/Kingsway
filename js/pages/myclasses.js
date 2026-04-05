/**
 * My Classes & Subjects Controller
 * Teacher view: loads assigned classes/subjects via API, upload materials
 */
const myclassesController = (() => {
  let myClasses = [];

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    await loadMyClasses();
  }

  async function loadMyClasses() {
    const container = document.getElementById("classesContainer");
    try {
      // Try teacher-specific endpoint first, then fallback
      let res;
      try {
        res = await API.academic.getTeacherClasses();
      } catch (e) {
        try {
          res = await API.apiCall(
            "/api/?route=teachers&action=my-classes",
            "GET",
          );
        } catch (e2) {
          res = await API.academic.listClasses();
        }
      }
      myClasses = res?.data || res || [];

      // Load subjects for each class
      for (const cls of myClasses) {
        try {
          const subRes = await API.academic.getTeacherSubjects(
            cls.id || cls.class_id,
          );
          cls.subjects = subRes?.data || subRes || [];
        } catch (e) {
          cls.subjects = [];
        }
        // Load student count
        try {
          const stuRes = await API.students.get(cls.id || cls.class_id);
          cls.student_count = (stuRes?.data || stuRes || []).length;
        } catch (e) {
          cls.student_count = 0;
        }
      }

      updateStats();
      renderClasses();
    } catch (e) {
      console.error("Load classes:", e);
      container.innerHTML =
        '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Could not load your classes. Please try again later.</div>';
    }
  }

  function updateStats() {
    const el = (id) => document.getElementById(id);
    const totalSubjects = myClasses.reduce(
      (sum, c) => sum + (c.subjects?.length || 0),
      0,
    );
    const totalStudents = myClasses.reduce(
      (sum, c) => sum + (c.student_count || 0),
      0,
    );
    if (el("myTotalClasses"))
      el("myTotalClasses").textContent = myClasses.length;
    if (el("myTotalSubjects"))
      el("myTotalSubjects").textContent = totalSubjects;
    if (el("myTotalStudents"))
      el("myTotalStudents").textContent = totalStudents;
    if (el("myLessonsWeek"))
      el("myLessonsWeek").textContent = totalSubjects * 3; // estimate
  }

  function renderClasses() {
    const container = document.getElementById("classesContainer");
    if (myClasses.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>You have not been assigned any classes yet.</div>';
      return;
    }
    let html = "";
    myClasses.forEach((cls) => {
      const className = cls.class_name || cls.name || "";
      const classId = cls.id || cls.class_id;
      const subjects = cls.subjects || [];
      const studentCount = cls.student_count || 0;

      html += `<div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>${className}</h5>
                    <div>
                        <span class="badge bg-light text-dark me-2"><i class="bi bi-people me-1"></i>${studentCount} students</span>
                        <span class="badge bg-light text-dark"><i class="bi bi-book me-1"></i>${subjects.length} subjects</span>
                    </div>
                </div>
                <div class="card-body">`;

      if (subjects.length > 0) {
        html += '<ul class="list-group list-group-flush">';
        subjects.forEach((sub) => {
          const subName = sub.subject_name || sub.name || "";
          const subId = sub.id || sub.subject_id;
          html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-book me-2"></i>${subName}</div>
                        <div class="btn-group btn-group-sm">
                            <a href=(window.APP_BASE || "") + "/home.php?route=enter_results&class_id=${classId}&subject_id=${subId}" class="btn btn-outline-success" title="Enter Marks"><i class="bi bi-pencil-square"></i></a>
                            <a href=(window.APP_BASE || "") + "/home.php?route=all_lesson_plans" class="btn btn-outline-info" title="Lesson Plans"><i class="bi bi-file-text"></i></a>
                            <button class="btn btn-outline-primary" onclick="myclassesController.openUploadModal(${classId},${subId})" title="Upload Material"><i class="bi bi-upload"></i></button>
                        </div>
                    </li>`;
        });
        html += "</ul>";
      } else {
        html +=
          '<p class="text-muted mb-0">No subjects assigned for this class.</p>';
      }

      html += `</div>
                <div class="card-footer bg-light">
                    <div class="btn-group btn-group-sm">
                        <a href=(window.APP_BASE || "") + "/home.php?route=mark_attendance&class_id=${classId}" class="btn btn-outline-primary"><i class="bi bi-check2-square me-1"></i>Attendance</a>
                        <a href=(window.APP_BASE || "") + "/home.php?route=view_attendance&class_id=${classId}" class="btn btn-outline-info"><i class="bi bi-bar-chart me-1"></i>Reports</a>
                        <a href=(window.APP_BASE || "") + "/home.php?route=class_details&class_id=${classId}" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i>Class Details</a>
                    </div>
                </div>
            </div>`;
    });
    container.innerHTML = html;
  }

  function openUploadModal(classId, subjectId) {
    document.getElementById("upload_class_id").value = classId;
    document.getElementById("upload_subject_id").value = subjectId;
    document.getElementById("upload_title").value = "";
    document.getElementById("upload_file").value = "";
    new bootstrap.Modal(document.getElementById("uploadModal")).show();
  }

  async function uploadMaterial() {
    const classId = document.getElementById("upload_class_id").value;
    const subjectId = document.getElementById("upload_subject_id").value;
    const title = document.getElementById("upload_title").value;
    const file = document.getElementById("upload_file").files[0];
    if (!title || !file) {
      alert("Please fill in all fields.");
      return;
    }
    try {
      const formData = new FormData();
      formData.append("class_id", classId);
      formData.append("subject_id", subjectId);
      formData.append("title", title);
      formData.append("file", file);
      await API.apiCall(
        "/api/?route=materials&action=upload",
        "POST",
        formData,
        true,
      );
      bootstrap.Modal.getInstance(
        document.getElementById("uploadModal"),
      )?.hide();
      alert("Material uploaded successfully!");
    } catch (e) {
      console.error("Upload:", e);
      alert("Upload failed. Please try again.");
    }
  }

  function printSchedule() {
    const content =
      document.getElementById("classesContainer")?.innerHTML || "";
    const w = window.open("", "", "width=900,height=700");
    w.document.write(
      `<html><head><title>My Classes</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"></head><body class="p-4"><h3>My Classes & Assigned Subjects</h3>${content}</body></html>`,
    );
    w.document.close();
    w.print();
  }

  return { init, openUploadModal, uploadMaterial, printSchedule };
})();

document.addEventListener("DOMContentLoaded", () => myclassesController.init());
