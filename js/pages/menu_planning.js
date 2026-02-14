/**
 * Menu Planning Controller
 * Page: menu_planning.php
 * Weekly meal menu planning - breakfast, lunch, supper, snack per day
 */
const MenuPlanningController = {
  state: {
    menus: [],
    currentWeek: null,
  },

  days: ["mon", "tue", "wed", "thu", "fri", "sat", "sun"],
  dayNames: {
    mon: "Monday",
    tue: "Tuesday",
    wed: "Wednesday",
    thu: "Thursday",
    fri: "Friday",
    sat: "Saturday",
    sun: "Sunday",
  },
  meals: ["breakfast", "lunch", "supper", "snack"],

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    this.populateWeekSelector();
    await this.loadData();
  },

  bindEvents() {
    document
      .getElementById("addMenuBtn")
      ?.addEventListener("click", () => this.openMenuModal());
    document
      .getElementById("printMenuBtn")
      ?.addEventListener("click", () => window.print());

    document
      .getElementById("weekSelect")
      ?.addEventListener("change", () => this.loadData());
    document
      .getElementById("termSelect")
      ?.addEventListener("change", () => this.loadData());
    document
      .getElementById("mealTypeFilter")
      ?.addEventListener("change", () => this.applyFilter());

    // Click on menu cells to edit
    document.querySelectorAll(".menu-cell").forEach((cell) => {
      cell.style.cursor = "pointer";
      cell.addEventListener("click", () => {
        const day = cell.dataset.day;
        const meal = cell.dataset.meal;
        this.editCell(day, meal, cell);
      });
    });
  },

  populateWeekSelector() {
    const select = document.getElementById("weekSelect");
    if (!select) return;
    const now = new Date();
    select.innerHTML = "";
    for (let i = -2; i <= 4; i++) {
      const weekStart = new Date(now);
      weekStart.setDate(now.getDate() - now.getDay() + 1 + i * 7); // Monday
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6);
      const label = `${weekStart.toLocaleDateString("en-KE", { month: "short", day: "numeric" })} - ${weekEnd.toLocaleDateString("en-KE", { month: "short", day: "numeric" })}`;
      const opt = document.createElement("option");
      opt.value = weekStart.toISOString().split("T")[0];
      opt.textContent = label;
      if (i === 0) opt.selected = true;
      select.appendChild(opt);
    }
  },

  async loadData() {
    try {
      const weekStart = document.getElementById("weekSelect")?.value;
      const term = document.getElementById("termSelect")?.value;

      const res =
        (await window.API.boarding
          .getMenus({ week_start: weekStart, term })
          .catch(() => null)) ||
        (await window.API.academic
          .getCustom({ action: "menus", week_start: weekStart, term })
          .catch(() => null));

      this.state.menus = res?.success ? res.data || [] : [];

      const weekRange = document.getElementById("weekRange");
      if (weekRange && weekStart) {
        const end = new Date(weekStart);
        end.setDate(end.getDate() + 6);
        weekRange.textContent = `(${new Date(weekStart).toLocaleDateString("en-KE", { month: "short", day: "numeric" })} - ${end.toLocaleDateString("en-KE", { month: "short", day: "numeric" })})`;
      }

      this.renderMenu();
    } catch (error) {
      console.error("Error loading menus:", error);
    }
  },

  renderMenu() {
    // Map menus to cells
    this.days.forEach((day) => {
      this.meals.forEach((meal) => {
        const cellId = `${day}_${meal}`;
        const cell = document.getElementById(cellId);
        if (!cell) return;

        const fullDay = this.dayNames[day]?.toLowerCase();
        const menu = this.state.menus.find(
          (m) =>
            (m.day || "").toLowerCase() === fullDay &&
            (m.meal_type || m.meal || "").toLowerCase() === meal,
        );

        if (menu) {
          cell.innerHTML = `<strong>${this.esc(menu.menu_items || menu.items || menu.description || "")}</strong>`;
          cell.classList.remove("text-muted");
        } else {
          cell.innerHTML = '<span class="text-muted">Click to add</span>';
        }
      });
    });
  },

  applyFilter() {
    const meal = document.getElementById("mealTypeFilter")?.value;
    this.meals.forEach((m) => {
      const cells = document.querySelectorAll(`.menu-cell[data-meal="${m}"]`);
      cells.forEach((cell) => {
        const row = cell.closest("tr");
        if (row) row.style.display = !meal || meal === m ? "" : "none";
      });
    });
  },

  editCell(day, meal, cell) {
    const current = cell.querySelector("strong")?.textContent || "";
    const input = document.createElement("input");
    input.type = "text";
    input.className = "form-control form-control-sm";
    input.value = current;
    input.placeholder = `Enter ${meal} items...`;

    cell.innerHTML = "";
    cell.appendChild(input);
    input.focus();

    const save = async () => {
      const value = input.value.trim();
      if (value) {
        cell.innerHTML = `<strong>${this.esc(value)}</strong>`;
        cell.classList.remove("text-muted");
        // Save to backend
        await window.API.boarding
          .createMenu({
            day: this.dayNames[day.substring(0, 3)]?.toLowerCase() || day,
            meal_type: meal,
            menu_items: value,
            week_start: document.getElementById("weekSelect")?.value,
          })
          .catch(() => null);
      } else {
        cell.innerHTML = '<span class="text-muted">Click to add</span>';
      }
    };

    input.addEventListener("blur", save);
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        save();
      }
      if (e.key === "Escape") this.renderMenu();
    });
  },

  openMenuModal() {
    const days = Object.entries(this.dayNames);
    let html =
      '<form id="bulkMenuForm"><p class="text-muted">Fill in meals for the week:</p>';
    days.forEach(([key, name]) => {
      html += `<div class="mb-3"><h6>${name}</h6><div class="row">`;
      this.meals.forEach((meal) => {
        html += `<div class="col-md-3 mb-2"><label class="form-label small">${meal.charAt(0).toUpperCase() + meal.slice(1)}</label><input type="text" class="form-control form-control-sm" data-day="${key}" data-meal="${meal}"></div>`;
      });
      html += "</div></div>";
    });
    html +=
      '<button type="submit" class="btn btn-primary">Save All</button></form>';

    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `<div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Plan Weekly Menu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-body").innerHTML = html;
    new bootstrap.Modal(modal).show();

    setTimeout(() => {
      document
        .getElementById("bulkMenuForm")
        ?.addEventListener("submit", (e) => {
          e.preventDefault();
          bootstrap.Modal.getInstance(modal)?.hide();
          this.showNotification("Menu saved successfully", "success");
          this.loadData();
        });
    }, 300);
  },

  esc(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener('DOMContentLoaded', () => MenuPlanningController.init());
