(function () {
  const state = {
    root: null,
    context: "",
    studentId: "",
    tabs: [],
    student: null,

    init() {
      this.root = document.getElementById("studentProfileContext");
      if (!this.root) return;
      const params = new URLSearchParams(window.location.search);
      this.studentId = params.get("id") || params.get("student_id") || "";
      this.context = params.get("context") || "";
      this.waitForAuth().then(() => {
        if (this.studentId) {
          this.loadProfile(this.studentId);
        } else {
          this.showSearch();
        }
      }).catch((error) => this.renderState("error", error.message));
    },

    waitForAuth() {
      return new Promise((resolve, reject) => {
        let attempts = 0;
        const tick = () => {
          attempts += 1;
          if (window.AuthContext && window.API) {
            if (!AuthContext.isAuthenticated()) {
              window.location.href = (window.APP_BASE || "") + "/index.php";
              return;
            }
            resolve();
            return;
          }
          if (attempts > 80) {
            reject(new Error("Authentication system not loaded. Please refresh the page."));
            return;
          }
          setTimeout(tick, 50);
        };
        tick();
      });
    },

    showSearch() {
      this.hideState();
      this.root.querySelector("#studentProfileSearch").classList.remove("d-none");
      const input = this.root.querySelector("#studentProfileSearchInput");
      let timer = null;
      input.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => this.search(input.value.trim()), 250);
      });
      this.search("");
    },

    async search(term) {
      try {
        const params = { limit: 20 };
        if (this.context) params.context = this.context;
        if (term) params.search = term;
        const response = await API.students.contextList(params);
        const payload = response?.data ?? response;
        this.context = payload.context || this.context;
        const results = this.root.querySelector("#studentProfileResults");
        const students = payload.students || [];
        if (!students.length) {
          results.innerHTML = '<div class="text-muted py-4 text-center">No students found.</div>';
          return;
        }
        results.innerHTML = students.map((student) => {
          const name = student.full_name || [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
          const meta = [student.admission_no, student.class_name, student.stream_name].filter(Boolean).join(" · ");
          const suffix = this.context ? `&context=${encodeURIComponent(this.context)}` : "";
          return `<a class="list-group-item list-group-item-action" href="${window.APP_BASE || ""}/home.php?route=student_profiles&id=${encodeURIComponent(student.id)}${suffix}">
            <div class="fw-semibold">${escapeHtml(name)}</div>
            <div class="small text-muted">${escapeHtml(meta)}</div>
          </a>`;
        }).join("");
      } catch (error) {
        this.renderState("error", error.message || "Unable to search students.");
      }
    },

    async loadProfile(id) {
      this.renderState("loading", "Loading profile...");
      try {
        const params = {};
        if (this.context) params.context = this.context;
        const response = await API.students.contextProfile(id, params);
        const payload = response?.data ?? response;
        this.student = payload.student;
        this.tabs = payload.tabs || ["summary"];
        this.context = payload.context || this.context;
        this.renderProfile();
      } catch (error) {
        if (error?.response?.code === 403 || error?.code === 403) {
          this.renderState("forbidden", error.message || "You are not allowed to view this profile.");
          return;
        }
        this.renderState("error", error.message || "Unable to load profile.");
      }
    },

    renderProfile() {
      this.hideState();
      this.root.querySelector("#studentProfileView").classList.remove("d-none");
      const s = this.student || {};
      const name = s.full_name || [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(" ");
      this.root.querySelector("#studentProfileName").textContent = name || "Student";
      this.root.querySelector("#studentProfileMeta").textContent = [s.admission_no, s.class_name, s.stream_name, titleCase(s.status)].filter(Boolean).join(" · ");

      const tabs = this.root.querySelector("#studentProfileTabs");
      tabs.innerHTML = this.tabs.map((tab, index) => `
        <li class="nav-item">
          <button class="nav-link ${index === 0 ? "active" : ""}" type="button" data-profile-tab="${escapeHtml(tab)}">${escapeHtml(titleCase(tab))}</button>
        </li>
      `).join("");
      tabs.querySelectorAll("[data-profile-tab]").forEach((button) => {
        button.addEventListener("click", () => {
          tabs.querySelectorAll(".nav-link").forEach((item) => item.classList.remove("active"));
          button.classList.add("active");
          this.renderTab(button.dataset.profileTab);
        });
      });
      this.renderTab(this.tabs[0] || "summary");
    },

    renderTab(tab) {
      const content = this.root.querySelector("#studentProfileTabContent");
      const s = this.student || {};
      const groups = {
        summary: ["admission_no", "full_name", "gender", "date_of_birth", "status", "class_name", "stream_name"],
        guardians: ["parent_name", "parent_phone", "parent_email", "parent_address"],
        academic: ["assessment_number", "assessment_status", "admission_date"],
        boarding: ["boarding_status", "student_type_name"],
        transport: ["route_name", "stop_name"],
        discipline: ["discipline_cases_count", "open_discipline_cases", "highest_discipline_severity"],
        finance: ["is_sponsored", "sponsor_name", "sponsor_type", "sponsor_waiver_percentage"],
        welfare: ["date_of_birth", "blood_group", "parent_name", "parent_phone"],
        meal_planning: ["boarding_status", "student_type_name", "class_name", "stream_name"],
      };
      const keys = groups[tab] || groups.summary;
      content.innerHTML = `<div class="row g-3">${keys.map((key) => `
        <div class="col-md-4">
          <div class="text-muted small">${escapeHtml(label(key))}</div>
          <div class="fw-semibold">${escapeHtml(formatValue(s[key]))}</div>
        </div>
      `).join("")}</div>`;
    },

    renderState(type, message) {
      this.root.querySelector("#studentProfileSearch").classList.add("d-none");
      this.root.querySelector("#studentProfileView").classList.add("d-none");
      const icons = { loading: "spinner-border text-primary", error: "bi bi-exclamation-triangle fs-2", forbidden: "bi bi-shield-lock fs-2" };
      const stateBox = this.root.querySelector("#studentProfileState");
      stateBox.classList.remove("d-none");
      if (type === "loading") {
        stateBox.innerHTML = `<div class="${icons.loading} mb-2" role="status" aria-hidden="true"></div><div>${escapeHtml(message)}</div>`;
      } else {
        stateBox.innerHTML = `<i class="${icons[type] || icons.error} d-block mb-2"></i><div>${escapeHtml(message)}</div>`;
      }
    },

    hideState() {
      this.root.querySelector("#studentProfileState").classList.add("d-none");
    },
  };

  function label(key) {
    return titleCase(key.replace(/_/g, " "));
  }

  function titleCase(value) {
    return String(value || "").replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function formatValue(value) {
    if (value === null || value === undefined || value === "") return "-";
    if (value === "0") return "No";
    if (value === "1") return "Yes";
    return titleCase(String(value).replace(/_/g, " "));
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    }[char]));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => state.init());
  } else {
    state.init();
  }
})();
