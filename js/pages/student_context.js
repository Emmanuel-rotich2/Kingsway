(function () {
  const configs = {
    full_management: { title: "Manage Students", filters: ["gender", "status"] },
    oversight: { title: "Students Overview", filters: ["gender", "status"] },
    academic: { title: "Academic Students", filters: ["gender", "status"] },
    discipline: { title: "Discipline Students", filters: ["gender", "status"] },
    boarding: { title: "Boarding Students", filters: ["gender", "status"] },
    catering: { title: "Boarding Meal Planning", filters: ["gender"] },
    transport: { title: "My Passengers", filters: [] },
    welfare: { title: "Student Welfare", filters: ["gender", "status"] },
    teacher_class: { title: "My Students", filters: ["gender", "status"] },
    subject_teacher: { title: "Subject Students", filters: ["gender", "status"] },
    parent_children: { title: "My Children", filters: [] },
  };

  const columnCatalog = [
    { key: "admission_no", label: "Admission No." },
    { key: "full_name", label: "Name", fallback: (s) => [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(" ") },
    { key: "class_name", label: "Class" },
    { key: "stream_name", label: "Stream" },
    { key: "gender", label: "Gender", format: titleCase },
    { key: "status", label: "Status", format: titleCase },
    { key: "boarding_status", label: "Boarding", format: titleCase },
    { key: "route_name", label: "Route" },
    { key: "stop_name", label: "Stop" },
    { key: "parent_phone", label: "Guardian Phone" },
    { key: "discipline_cases_count", label: "Cases" },
    { key: "open_discipline_cases", label: "Open Cases" },
    { key: "blood_group", label: "Blood" },
  ];

  const controller = {
    root: null,
    context: "",
    readOnly: false,
    page: 1,
    limit: 25,
    pagination: null,
    fields: [],
    actions: [],

    init(root) {
      this.root = root;
      this.context = root.dataset.studentContext || "";
      this.readOnly = root.dataset.readOnly === "1";
      this.bind();
      this.waitForAuth().then(() => this.load()).catch((error) => this.renderError(error.message));
    },

    waitForAuth() {
      return new Promise((resolve, reject) => {
        let attempts = 0;
        const check = async () => {
          attempts += 1;
          if (window.AuthContext && window.API) {
            await window.AuthContext?.ready();
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
          setTimeout(check, 50);
        };
        check();
      });
    },

    bind() {
      const search = this.root.querySelector("#studentContextSearch");
      const gender = this.root.querySelector("#studentContextGender");
      const status = this.root.querySelector("#studentContextStatus");
      let timer = null;
      search?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          this.page = 1;
          this.load();
        }, 250);
      });
      [gender, status].forEach((el) => el?.addEventListener("change", () => {
        this.page = 1;
        this.load();
      }));
      this.root.querySelector('[data-page-action="prev"]')?.addEventListener("click", () => {        if (this.page > 1) {
          this.page -= 1;
          this.load();
        }
      });
      this.root.querySelector('[data-page-action="next"]')?.addEventListener("click", () => {
        if (this.pagination && this.page < this.pagination.total_pages) {
          this.page += 1;
          this.load();
        }
      });
      // Rows-per-page selector: the page count follows the rows the user
      // displays and the data size (the staff-table pagination shape).
      this.root.querySelector("#studentContextPageSize")?.addEventListener("change", (event) => {
        const value = Number(event.target.value);
        if (value > 0) {
          this.limit = value;
          this.page = 1;
          this.load();
        }
      });
    },

    params() {
      const params = { page: this.page, limit: this.limit };
      if (this.context) params.context = this.context;
      const search = this.root.querySelector("#studentContextSearch")?.value?.trim();
      const gender = this.root.querySelector("#studentContextGender")?.value;
      const status = this.root.querySelector("#studentContextStatus")?.value;
      if (search) params.search = search;
      if (gender) params.gender = gender;
      if (status) params.status = status;
      return params;
    },

    async load() {
      this.renderLoading();
      try {
        const response = await window.API.students.contextList(this.params());
        const payload = response?.data ?? response;
        this.context = payload.context || this.context;
        this.fields = payload.fields || [];
        this.actions = this.readOnly ? ["view"] : (payload.actions || ["view"]);
        this.pagination = payload.pagination || { page: 1, total: 0, total_pages: 0 };
        this.applyConfig();
        this.renderActions();
        this.renderRows(payload.students || []);
      } catch (error) {
        if (error?.response?.code === 403 || error?.code === 403 || error?.state === "forbidden") {
          this.renderForbidden(error.message);
          return;
        }
        this.renderError(error.message || "Unable to load students");
      }
    },

    applyConfig() {
      const config = configs[this.context] || {};
      const title = this.root.querySelector("#studentContextTitle");
      if (title && config.title) title.textContent = title.textContent || config.title;

      this.root.querySelectorAll("[data-filter]").forEach((filter) => {
        const name = filter.dataset.filter;
        filter.classList.toggle("d-none", config.filters && !config.filters.includes(name));
      });
    },

    renderActions() {
      const target = this.root.querySelector("#studentContextActions");
      if (!target) return;
      target.innerHTML = "";
      if (this.actions.includes("create") && !this.readOnly) {
        target.appendChild(button("Add Student", "bi-plus-circle", () => {
          window.location.href = (window.APP_BASE || "") + "/home.php?route=manage_students";
        }));
      }
      if (this.actions.includes("promotion_tools")) {
        target.appendChild(button("Promotion", "bi-arrow-up-circle", () => {
          window.location.href = (window.APP_BASE || "") + "/home.php?route=student_promotion";
        }));
      }
      if (this.actions.includes("transport_attendance")) {
        target.appendChild(button("Attendance", "bi-clipboard-check", () => {
          window.location.href = (window.APP_BASE || "") + "/home.php?route=mark_attendance";
        }));
      }
    },

    renderRows(students) {
      if (!students.length) {
        this.renderState("empty", "No students found for this context.");
        return;
      }

      const columns = columnCatalog.filter((column) => this.fields.includes(column.key) || column.key === "full_name");
      columns.push({ key: "__actions", label: "Actions" });

      this.root.querySelector("#studentContextHead").innerHTML = columns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join("");
      this.root.querySelector("#studentContextBody").innerHTML = students.map((student) => {
        return `<tr>${columns.map((column) => {
          if (column.key === "__actions") {
            return `<td><button class="btn btn-sm btn-outline-primary" data-profile-id="${escapeHtml(student.id)}"><i class="bi bi-person-lines-fill"></i></button></td>`;
          }
          const raw = column.fallback ? column.fallback(student) : student[column.key];
          const value = column.format ? column.format(raw) : raw;
          return `<td>${escapeHtml(value || "-")}</td>`;
        }).join("")}</tr>`;
      }).join("");

      this.root.querySelectorAll("[data-profile-id]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = Number(btn.getAttribute("data-profile-id"));
          // Open the SAME student-details modal the School Administrator uses
          // (viewStudentModal, rendered by manage_students.js) instead of
          // navigating away — the Director/Headteacher reads the profile
          // right here on the overview page.
          const openModal = () => {
            const opener = window.studentsManagementController?.viewStudent;
            if (typeof opener === "function") {
              opener(id);
              return;
            }
            const suffix = this.context ? `&context=${encodeURIComponent(this.context)}` : "";
            window.location.href = `${window.APP_BASE || ""}/home.php?route=student_profiles&id=${encodeURIComponent(id)}${suffix}`;
          };
          if (window.studentsManagementController?.viewStudent) {
            openModal();
          } else {
            // The modal's controller ships with the page's snippet; load it
            // once, then open.
            const script = document.createElement("script");
            script.src = `${window.APP_BASE || ""}/js/pages/manage_students.js?v=${Date.now()}`;
            script.onload = openModal;
            document.head.appendChild(script);
          }
        });
      });

      this.root.querySelector("#studentContextState").classList.add("d-none");
      this.root.querySelector("#studentContextTableWrap").classList.remove("d-none");
      this.renderPager();
    },

    renderPager() {
      const pager = this.root.querySelector("#studentContextPager");
      const info = this.root.querySelector("#studentContextPageInfo");
      const position = this.root.querySelector("#studentContextPagePosition");
      if (!this.pagination || !pager || !info) return;
      pager.classList.remove("d-none");
      const total = Number(this.pagination.total || 0);
      const page = Number(this.pagination.page || 1);
      const pages = Math.max(1, Number(this.pagination.total_pages || Math.ceil(total / (this.limit || 25)) || 1));
      const from = total ? (page - 1) * (this.limit || 25) + 1 : 0;
      const to = Math.min(page * (this.limit || 25), total);
      info.textContent = `Showing ${from}–${to} of ${total}`;
      if (position) position.textContent = `${page} / ${pages}`;
    },

    renderLoading() {
      this.root.querySelector("#studentContextTableWrap").classList.add("d-none");
      this.root.querySelector("#studentContextPager").classList.add("d-none");
      this.root.querySelector("#studentContextState").className = "border rounded bg-white p-4 text-center text-muted";
      this.root.querySelector("#studentContextState").innerHTML = '<div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div><div>Loading students...</div>';
    },

    renderForbidden(message) {
      this.renderState("forbidden", message || "You are not allowed to view this student context.");
    },

    renderError(message) {
      this.renderState("error", message || "Unable to load students.");
    },

    renderState(type, message) {
      const icons = { empty: "bi-inbox", forbidden: "bi-shield-lock", error: "bi-exclamation-triangle" };
      this.root.querySelector("#studentContextTableWrap").classList.add("d-none");
      this.root.querySelector("#studentContextPager").classList.add("d-none");
      const state = this.root.querySelector("#studentContextState");
      state.className = "border rounded bg-white p-4 text-center text-muted";
      state.innerHTML = `<i class="bi ${icons[type] || icons.empty} fs-2 d-block mb-2"></i><div>${escapeHtml(message)}</div>`;
    },
  };

  function button(label, icon, onClick) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-primary btn-sm";
    btn.innerHTML = `<i class="bi ${icon} me-1"></i>${escapeHtml(label)}`;
    btn.addEventListener("click", onClick);
    return btn;
  }

  function titleCase(value) {
    return String(value || "").replace(/_/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
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

  const activeInstances = [];

  function initAll() {
    document.querySelectorAll(".student-context-page").forEach((root) => {
      if (root.dataset.initialized === "1") return;
      root.dataset.initialized = "1";
      const instance = Object.create(controller);
      activeInstances.push(instance);
      instance.init(root);
    });
  }

  window.StudentContextRealtimeController = {
    async load() {
      for (const instance of activeInstances) {
        await instance.load();
      }
    },
  };
  window.APIRealtime?.register?.('StudentContextRealtimeController', window.StudentContextRealtimeController, ['students']);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})();
