(function () {
  function formatTime(value) {
    if (!value) return "-";
    const str = String(value);
    return str.length >= 5 ? str.slice(0, 5) : str;
  }

  function formatDate(value) {
    if (!value) return "-";
    const when = new Date(value);
    if (Number.isNaN(when.getTime())) return value;
    return when.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function renderScheduleRow(entry) {
    const teacher =
      entry.teacher_name ||
      `${entry.teacher_first_name || ""} ${entry.teacher_last_name || ""}`.trim() ||
      "-";
    const subject = entry.subject_name || entry.subject || "-";
    const room = entry.room_name || entry.room || entry.location || "-";
    const termLabel =
      entry.term_name || (entry.term_number ? `Term ${entry.term_number}` : "-");
    const day = entry.day_of_week || entry.day || "-";
    const timeRange = `${formatTime(entry.start_time)} - ${formatTime(
      entry.end_time,
    )}`;
    const classLabel = entry.class_name || entry.class || "-";

    return `
      <tr>
        <td>${day}</td>
        <td>${timeRange}</td>
        <td>${classLabel}</td>
        <td>${subject}</td>
        <td>${teacher}</td>
        <td>${room}</td>
        <td><span class="badge bg-light text-dark">${termLabel}</span></td>
      </tr>`;
  }

  function renderHolidayItem(holiday) {
    const label =
      holiday.title || holiday.name || holiday.description || "Holiday / Event";
    const when = formatDate(holiday.date || holiday.holiday_date || holiday.start_date);
    const typeLabel = (holiday.day_type || holiday.type || "Holiday").replace(/_/g, " ");
    return `
      <li class="list-group-item d-flex justify-content-between align-items-start">
        <div>
          <strong>${label}</strong>
          <div class="small text-muted">${when}</div>
        </div>
        <span class="badge bg-warning text-dark">${typeLabel}</span>
      </li>`;
  }

  function renderScheduleContent(schedules, holidays) {
    const scheduleRows = schedules.length
      ? schedules.map(renderScheduleRow).join("")
      : '<tr><td colspan="7" class="text-center text-muted py-4">Schedule not yet available for this student.</td></tr>';

    const holidaysList = holidays.length
      ? `<ul class="list-group list-group-flush">
          ${holidays.map(renderHolidayItem).join("")}
        </ul>`
      : '<div class="text-muted text-center py-3">No holidays configured for this term.</div>';

    return `
      <div class="row gy-3">
        <div class="col-lg-8">
          <h6 class="text-primary mb-2">Class Timetable</h6>
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead class="table-light">
                <tr>
                  <th>Day</th>
                  <th>Time</th>
                  <th>Class</th>
                  <th>Subject</th>
                  <th>Teacher</th>
                  <th>Room</th>
                  <th>Term</th>
                </tr>
              </thead>
              <tbody>${scheduleRows}</tbody>
            </table>
          </div>
        </div>
        <div class="col-lg-4">
          <h6 class="text-primary mb-2">Term Holidays</h6>
          ${holidaysList}
        </div>
      </div>`;
  }

  function ensureScheduleTab() {
    const navTabs = document.getElementById("studentDetailTabs");
    if (!navTabs || document.querySelector('#studentDetailTabs a[href="#tabSchedule"]')) {
      return;
    }

    const disciplineLink = navTabs.querySelector('a[href="#tabDiscipline"]');
    const scheduleNav = document.createElement("li");
    scheduleNav.className = "nav-item";
    scheduleNav.innerHTML =
      '<a class="nav-link" data-bs-toggle="tab" href="#tabSchedule"><i class="bi bi-calendar-week"></i> Schedule</a>';

    if (disciplineLink && disciplineLink.parentElement) {
      disciplineLink.parentElement.insertAdjacentElement("beforebegin", scheduleNav);
    } else {
      navTabs.appendChild(scheduleNav);
    }

    const tabContent = navTabs.parentElement?.querySelector(".tab-content");
    if (!tabContent) return;
    if (document.getElementById("tabSchedule")) return;

    const disciplinePane = tabContent.querySelector("#tabDiscipline");
    const schedulePane = document.createElement("div");
    schedulePane.className = "tab-pane fade";
    schedulePane.id = "tabSchedule";
    schedulePane.innerHTML =
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading schedule...</div></div>';

    if (disciplinePane) {
      disciplinePane.insertAdjacentElement("beforebegin", schedulePane);
    } else {
      tabContent.appendChild(schedulePane);
    }
  }

  async function loadStudentSchedule(studentId) {
    const schedulePane = document.getElementById("tabSchedule");
    if (!schedulePane) return;
    schedulePane.innerHTML =
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading schedule...</div></div>';

    try {
      const response = await window.API.schedules.getStudentSchedules(studentId);
      const payload = response?.data ?? response ?? {};
      const schedules =
        payload?.schedules ?? payload?.data?.schedules ?? (Array.isArray(payload) ? payload : []);
      const holidays =
        payload?.holidays ?? payload?.data?.holidays ?? [];
      schedulePane.innerHTML = renderScheduleContent(schedules, holidays);
    } catch (error) {
      schedulePane.innerHTML = `<div class="alert alert-warning">Unable to load schedule.${error?.message ? ` ${error.message}` : ""}</div>`;
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("viewStudentModal");
    if (!modal) return;
    modal.addEventListener("shown.bs.modal", async () => {
      const content = document.getElementById("viewStudentContent");
      const studentId = content?.dataset?.studentId;
      if (!studentId) return;
      ensureScheduleTab();
      await loadStudentSchedule(studentId);
    });
  });
})();
