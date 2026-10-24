/*
 * Student Fees Page Controller
 * Manages student fee tracking, statements, and payment recording.
 */

const StudentFeesController = {
  data: {
    rows: [],
    classes: [],
    years: [],
    academicYearTerms: [],
    currentTermNumber: null,
    pagination: { page: 1, limit: 25, total: 0 },
    selectedStudentIds: new Set(),
    summary: {
      total_due: 0,
      total_paid: 0,
      total_balance: 0,
      collection_rate: 0,
    },
  },
  state: { referenceCache: null },
  filters: {
    search: "",
    class_name: "",
    status: "",
    term_number: "",
    academic_year: "",
    page: 1,
    limit: 25,
  },
  ui: {},

  notify: function (message, type) {
    if (typeof showNotification === "function") {
      showNotification(message, type || "info");
    } else {
      window.alert(message);
    }
  },

  escapeHtml: function (value) {
    const div = document.createElement("div");
    div.textContent = value == null ? "" : String(value);
    return div.innerHTML;
  },

  /**
   * Sibling page controllers all expose `esc` and use it when building option
   * lists. This controller defined `escapeHtml` but rendered the filter
   * dropdowns with `this.esc`, so every one of those renders threw
   * "this.esc is not a function". Because `loadInitialData` swallowed the
   * error, the academic-year dropdown kept only its static "All years"
   * option, the term list was never requested, and the table came up empty.
   */
  esc: function (value) {
    return this.escapeHtml(value);
  },

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    await this.loadInitialData();
    await this.loadPaymentStatus();
    this.attachEvents();
    this.bindRealtimeCollaboration();
    window.APIRealtime?.register?.("student_fees", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadInitialData();
        await this.loadPaymentStatus();
      }
    }, ["finance", "payments"]);
  },

  /**
   * Realtime collaboration for the fee ledger:
   *  - declares `fee_account:<studentId>` presence while the payment or
   *    fee-statement modal is open on a student, so a colleague posting to
   *    the same account is told someone is already in it;
   *  - when a PAYMENT_ALERT (or any finance-domain change) arrives while a
   *    student's fee-statement modal is open, refreshes just that one
   *    student's statement in place instead of reloading the whole ledger
   *    list and discarding the open modal, scroll and filters.
   */
  bindRealtimeCollaboration: function () {
    const presenceFor = () => `fee_account:${this.currentStudentId || 0}`;

    if (this.ui.feeDetailsModal) {
      this.ui.feeDetailsModal.addEventListener("shown.bs.modal", () => {
        if (this.currentStudentId) window.RealtimePresence?.begin(presenceFor());
      });
      this.ui.feeDetailsModal.addEventListener("hidden.bs.modal", () => {
        window.RealtimePresence?.end(presenceFor());
      });
    }
    if (this.ui.paymentModal) {
      this.ui.paymentModal.addEventListener("shown.bs.modal", () => {
        const id = Number(this.ui.paymentStudent?.value || 0);
        if (id) window.RealtimePresence?.begin(`fee_account:${id}`);
      });
      this.ui.paymentModal.addEventListener("hidden.bs.modal", () => {
        const id = Number(this.ui.paymentStudent?.value || 0);
        if (id) window.RealtimePresence?.end(`fee_account:${id}`);
      });
      // Moving the payment to another student moves the declaration with it.
      this.ui.paymentStudent?.addEventListener("change", () => {
        const previous = Number(this.ui.paymentStudent.dataset.rtStudent || 0);
        if (previous) window.RealtimePresence?.end(`fee_account:${previous}`);
        const id = Number(this.ui.paymentStudent.value || 0);
        this.ui.paymentStudent.dataset.rtStudent = id || "";
        if (id && this.ui.paymentModal.classList.contains("show")) {
          window.RealtimePresence?.begin(`fee_account:${id}`);
        }
      });
    }

    window.APIRealtime?.registerPatch?.({
      id: "student_fees_ledger",
      targets: ["finance", "payments", "student_fee_balances"],
      apply: (descriptor) => {
        const type = String(descriptor.type || "").toUpperCase();

        // Events that never need any refresh - handle them silently
        const livenessTypes = new Set(["HEARTBEAT", "PRESENCE", "CONFLICT_HINT"]);
        if (livenessTypes.has(type)) return true;

        const isFinanceChange = type === "PAYMENT_ALERT"
          || (descriptor.domain === "finance" && (descriptor.action === "record_payment" || descriptor.action === "posted"))
          || type === "ROW_UPDATED"
          || type === "DATA_CHANGED";
        if (!isFinanceChange) return false;

        // If a specific student statement modal is open, refresh just that one
        const modalOpen = this.ui.feeDetailsModal?.classList.contains("show");
        if (modalOpen && this.currentStudentId) {
          this.openFeeDetails(this.currentStudentId);
          return true;
        }

        // Otherwise, try to patch the main table rows in place
        const payload = descriptor.payload || {};
        const targets = Array.isArray(payload.targets) ? payload.targets : [];
        const studentIds = new Set();

        // Extract student IDs from targets like "student_fee_balances:123" or "finance/student/456"
        for (const target of targets) {
          const match = String(target).match(/(?:student[_-]?id|fee[_-]?balances?)[/:](\d+)/i);
          if (match) studentIds.add(Number(match[1]));
        }

        // Also check for student_id in payload
        if (payload.student_id) studentIds.add(Number(payload.student_id));
        if (payload.student_ids) {
          (Array.isArray(payload.student_ids) ? payload.student_ids : [payload.student_ids]).forEach(id => studentIds.add(Number(id)));
        }

        if (studentIds.size > 0) {
          this.patchTableRows(Array.from(studentIds));
          return true;
        }

        // No specific student IDs to patch - don't trigger full refresh
        // Return true to indicate "handled, no refresh needed"
        return true;
      },
    });

    // Register a lower-priority full refresh that only fires when patches don't handle it
    window.APIRealtime?.register?.(
      "student_fees",
      { refresh: () => this.loadPaymentStatus() },
      ["finance", "payments"],
    );
  },

  cacheDom: function () {
    this.ui = {
      searchInput: document.getElementById("searchStudent"),
      classFilter: document.getElementById("classFilter"),
      statusFilter: document.getElementById("statusFilter"),
      termFilter: document.getElementById("termFilter"),
      recordPaymentBtn: document.getElementById("recordPaymentBtn"),
      exportBtn: document.getElementById("exportBtn"),
      awardScholarshipBtn: document.getElementById("awardScholarshipBtn"),
      waiveFeesBtn: document.getElementById("waiveFeesBtn"),
      printSelectedFeesBtn: document.getElementById("printSelectedFeesBtn"),
      selectAllFeeStudents: document.getElementById("selectAllFeeStudents"),
      tableBody: document.querySelector("#feesTable tbody"),
      pagination: document.getElementById("pagination"),
      totalExpected: document.getElementById("totalExpected"),
      totalCollected: document.getElementById("totalCollected"),
      totalOutstanding: document.getElementById("totalOutstanding"),
      collectionRate: document.getElementById("collectionRate"),
      expectedPeriodLabel: document.getElementById("expectedPeriodLabel"),
      collectedPeriodLabel: document.getElementById("collectedPeriodLabel"),
      outstandingPeriodLabel: document.getElementById("outstandingPeriodLabel"),
      ratePeriodLabel: document.getElementById("ratePeriodLabel"),
      expectedTermLine: document.getElementById("expectedTermLine"),
      collectedTermLine: document.getElementById("collectedTermLine"),
      outstandingTermLine: document.getElementById("outstandingTermLine"),
      rateTermLine: document.getElementById("rateTermLine"),
      yearFilter: document.getElementById("yearFilter"),
      perPageFilter: document.getElementById("perPageFilter"),
      paymentModal: document.getElementById("paymentModal"),
      paymentForm: document.getElementById("paymentForm"),
      paymentStudent: document.getElementById("paymentStudent"),
      paymentStudentId: document.getElementById("studentId"),
      paymentAmount: document.getElementById("amount"),
      paymentMethod: document.getElementById("paymentMethod"),
      paymentReference: document.getElementById("reference"),
      paymentDate: document.getElementById("paymentDate"),
      paymentNotes: document.getElementById("notes"),
      savePaymentBtn: document.getElementById("savePaymentBtn"),
      outstandingAmount: document.getElementById("outstandingAmount"),
      feeDetailsModal: document.getElementById("feeDetailsModal"),
      studentName: document.getElementById("studentName"),
      admNo: document.getElementById("admNo"),
      modalTotalFee: document.getElementById("modalTotalFee"),
      modalTotalPaid: document.getElementById("modalTotalPaid"),
      modalBalance: document.getElementById("modalBalance"),
      feeBreakdownBody: document.getElementById("feeBreakdownBody"),
      paymentHistoryBody: document.getElementById("paymentHistoryBody"),
      printStatementBtn: document.getElementById("printStatementBtn"),
      manageAssistanceBtn: document.getElementById("manageAssistanceBtn"),
      assistanceModal: document.getElementById("studentAssistanceModal"),
      assistanceForm: document.getElementById("studentAssistanceForm"),
      assistanceStudentId: document.getElementById("assistanceStudentId"),
      assistanceStudentLabel: document.getElementById("assistanceStudentLabel"),
      assistanceYear: document.getElementById("assistanceYear"),
      assistanceProgram: document.getElementById("assistanceProgram"),
      assistancePeriodType: document.getElementById("assistancePeriodType"),
      assistanceTerm: document.getElementById("assistanceTerm"),
      assistanceTermWrap: document.getElementById("assistanceTermWrap"),
      assistanceStartsWrap: document.getElementById("assistanceStartsWrap"),
      assistanceEndsWrap: document.getElementById("assistanceEndsWrap"),
      assistanceStartsOn: document.getElementById("assistanceStartsOn"),
      assistanceEndsOn: document.getElementById("assistanceEndsOn"),
      assistanceCoverage: document.getElementById("assistanceCoverage"),
      assistancePercentageWrap: document.getElementById("assistancePercentageWrap"),
      assistancePercentage: document.getElementById("assistancePercentage"),
      assistanceAmountWrap: document.getElementById("assistanceAmountWrap"),
      assistanceAmount: document.getElementById("assistanceAmount"),
      assistanceReason: document.getElementById("assistanceReason"),
      assistanceNotes: document.getElementById("assistanceNotes"),
      assistanceAwardsBody: document.getElementById("studentAssistanceAwardsBody"),
      saveAssistanceBtn: document.getElementById("saveAssistanceBtn"),
      waiverModal: document.getElementById("studentWaiverModal"),
      waiverStudentLabel: document.getElementById("waiverStudentLabel"),
      waiverYear: document.getElementById("waiverYear"),
      waiverScope: document.getElementById("waiverScope"),
      waiverAmountWrap: document.getElementById("waiverAmountWrap"),
      waiverAmount: document.getElementById("waiverAmount"),
      waiverReason: document.getElementById("waiverReason"),
      waiverNotes: document.getElementById("waiverNotes"),
      saveWaiverBtn: document.getElementById("saveWaiverBtn"),
      // Prompt Parent modal elements
      promptParentModal: document.getElementById("promptParentModal"),
      promptStudentId: document.getElementById("promptStudentId"),
      promptStudentName: document.getElementById("promptStudentName"),
      promptAdmNo: document.getElementById("promptAdmNo"),
      promptClass: document.getElementById("promptClass"),
      promptBalance: document.getElementById("promptBalance"),
      promptParentName: document.getElementById("promptParentName"),
      promptPhone: document.getElementById("promptPhone"),
      promptAmount: document.getElementById("promptAmount"),
      promptOutstanding: document.getElementById("promptOutstanding"),
      promptPurpose: document.getElementById("promptPurpose"),
      promptDescription: document.getElementById("promptDescription"),
      sendStkPushBtn: document.getElementById("sendStkPushBtn"),
      // Payment Trends modal elements
      paymentTrendsModal: document.getElementById("paymentTrendsModal"),
      paymentTrendsContent: document.getElementById("paymentTrendsContent"),
      exportTrendsBtn: document.getElementById("exportTrendsBtn"),
    };
  },

  attachEvents: function () {
    if (this.ui.searchInput) {
      this.ui.searchInput.addEventListener(
        "input",
        this.debounce((event) => {
          this.filters.search = event.target.value.trim();
          this.filters.page = 1;
          this.loadPaymentStatus();
        }, 300),
      );
    }

    if (this.ui.classFilter) {
      this.ui.classFilter.addEventListener("change", (event) => {
        this.filters.class_name = event.target.value;
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.statusFilter) {
      this.ui.statusFilter.addEventListener("change", (event) => {
        const value = event.target.value;
        const statusMap = {
          paid: "paid",
          partial: "partial",
          unpaid: "pending",
          overpaid: "paid",
        };
        this.filters.status = value ? statusMap[value] || value : "";
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.termFilter) {
      this.ui.termFilter.addEventListener("change", (event) => {
        const value = event.target.value;
        this.filters.term_number = value ? value : "";
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.perPageFilter) {
      this.ui.perPageFilter.addEventListener("change", (event) => {
        const value = Number(event.target.value);
        this.filters.limit = value > 0 ? value : 25;
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.yearFilter) {
      this.ui.yearFilter.addEventListener("change", async (event) => {
        const value = event.target.value;
        this.filters.academic_year = value ? value : "";
        this.filters.page = 1;
        try {
          await this.loadTermsForYear(this.filters.academic_year);
        } catch (error) {
          console.error("Failed to load terms for year:", error);
          this.populateTermFilter([]);
        }
        this.loadPaymentStatus();
      });
    }

    if (this.ui.recordPaymentBtn) {
      this.ui.recordPaymentBtn.addEventListener("click", () => {
        this.openPaymentModal();
      });
    }

    if (this.ui.exportBtn) {
      this.ui.exportBtn.addEventListener("click", () => this.exportTable());
    }
    const awardAllowed = this.canManageAwards();
    if (this.ui.awardScholarshipBtn) {
      this.ui.awardScholarshipBtn.hidden = !awardAllowed;
      this.ui.awardScholarshipBtn.addEventListener("click", () => this.openAssistanceModal());
    }
    if (this.ui.waiveFeesBtn) {
      this.ui.waiveFeesBtn.hidden = !awardAllowed;
      this.ui.waiveFeesBtn.addEventListener("click", () => this.openWaiverModal());
    }
    if (this.ui.printSelectedFeesBtn) this.ui.printSelectedFeesBtn.addEventListener("click", () => this.printSelectedFeeAccounts());
    if (this.ui.selectAllFeeStudents) this.ui.selectAllFeeStudents.addEventListener("change", (event) => this.toggleAllStudents(event.target.checked));

    if (this.ui.paymentStudent) {
      this.ui.paymentStudent.addEventListener("change", async (event) => {
        const studentId = event.target.value;
        this.ui.paymentStudentId.value = studentId || "";
        await this.updateOutstandingAmount(studentId);
      });
    }

    if (this.ui.paymentMethod) {
      this.ui.paymentMethod.addEventListener("change", () => {
        const method = this.ui.paymentMethod.value;
        const refDiv = document.getElementById("referenceDiv");
        if (refDiv) {
          refDiv.style.display = method === "cash" ? "none" : "block";
        }
      });
    }

    if (this.ui.savePaymentBtn) {
      this.ui.savePaymentBtn.addEventListener("click", () =>
        this.savePayment(),
      );
    }

    if (this.ui.printStatementBtn) {
      this.ui.printStatementBtn.addEventListener("click", () => {
        this.printFeeStatement();
      });
    }
    if (this.ui.manageAssistanceBtn) {
      this.ui.manageAssistanceBtn.addEventListener("click", () => this.openAssistanceModal(this.currentStudentId));
    }
    if (this.ui.assistanceCoverage) {
      this.ui.assistanceCoverage.addEventListener("change", () => this.updateAssistanceCoverageFields());
    }
    if (this.ui.assistancePeriodType) {
      this.ui.assistancePeriodType.addEventListener("change", () => this.updateAssistancePeriodFields());
    }
    if (this.ui.waiverScope) this.ui.waiverScope.addEventListener("change", () => {
      const scoped = this.ui.waiverScope.value !== "full";
      this.ui.waiverAmountWrap?.classList.toggle("d-none", !scoped);
      const label = document.getElementById("waiverAmountLabel");
      if (label) label.textContent = this.ui.waiverScope.value === "percentage" ? "Percentage of current balance (%)" : "Amount per learner (KES)";
      if (this.ui.waiverAmount) this.ui.waiverAmount.max = this.ui.waiverScope.value === "percentage" ? "100" : "";
    });
    if (this.ui.saveAssistanceBtn) {
      this.ui.saveAssistanceBtn.addEventListener("click", () => this.saveAssistance());
    }
    if (this.ui.saveWaiverBtn) this.ui.saveWaiverBtn.addEventListener("click", () => this.saveWaiver());

    // Prompt Parent (STK Push) events
    if (this.ui.sendStkPushBtn) {
      this.ui.sendStkPushBtn.addEventListener("click", () => this.sendStkPush());
    }

    // Payment Trends events
    if (this.ui.exportTrendsBtn) {
      this.ui.exportTrendsBtn.addEventListener("click", () => this.exportPaymentTrends());
    }
  },

  canManageAwards: function () {
    const user = window.AuthContext?.getUser?.() || {};
    const roles = [user.role_name, ...(Array.isArray(user.roles) ? user.roles.map((r) => r?.name || r) : [])]
      .filter(Boolean).map((r) => String(r).toLowerCase());
    return roles.some((r) => ["director", "school administrator", "system administrator"].includes(r));
  },

  /**
   * Each loader is independent on purpose.
   *
   * This used to be one try/catch around three calls, so a single throw — the
   * undefined this.esc in populateYearFilter, for instance — skipped the
   * remaining loaders and left the page showing its static "All years"
   * placeholder, no terms, and no ledger. Now a failure in one dropdown is
   * reported on its own and the rest of the workspace still loads.
   */
  loadInitialData: async function () {
    const errors = [];

    await this.loadReferencesComposed();

    const [classes, years] = await Promise.all([
      this.loadClasses().catch((error) => {
        errors.push("classes");
        console.error("Failed to load classes:", error);
        return [];
      }),
      this.loadYears().catch((error) => {
        errors.push("academic years");
        console.error("Failed to load academic years:", error);
        return [];
      }),
    ]);

    if (classes.length) {
      this.data.classes = classes;
      this.populateClassFilter(classes);
    }

    if (years.length) {
      this.data.years = years;
      this.populateYearFilter(years);
      const currentYear = years.find(
        (year) => year.is_current == 1 || year.is_current === "1",
      );
      const active = this.normalizeAcademicYearValue(
        (this.ui.yearFilter && this.ui.yearFilter.value) ||
          (currentYear &&
            (currentYear.year_code || currentYear.year || currentYear.name)) ||
          "",
      );
      this.filters.academic_year = active;
    }

    try {
      await this.loadTermsForYear(this.filters.academic_year);
    } catch (error) {
      errors.push("terms");
      console.error("Failed to load terms:", error);
      this.populateTermFilter([]);
    }

    if (errors.length) {
      this.notify(
        `Could not load fee filter options: ${errors.join(
          ", ",
        )}. The ledger below still shows real data.`,
        "warning",
      );
    }
  },

  loadReferencesComposed: async function () {
    try {
      const response = await window.API.nodeViews.resolve("finance", "student-fees-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student fees Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  loadClasses: async function () {
    const cache = this.state.referenceCache;
    const resp = cache?.classes !== undefined ? cache.classes : await window.API.academic.listClasses();
    return this.unwrapList(resp);
  },

  loadYears: async function () {
    const cache = this.state.referenceCache;
    const resp = cache?.years !== undefined ? cache.years : await window.API.academic.listYears();
    return this.unwrapList(resp);
  },

  loadPaymentStatus: async function () {
    try {
      const params = { ...this.filters };

      // The class picker carries the class id; send it under the key the
      // ledger filters on so a "Grade 8" selection is matched by identity
      // rather than by a label that varies with the stream.
      if (params.class_name && this.ui.classFilter) {
        const option = this.ui.classFilter.selectedOptions?.[0];
        const className = option?.dataset?.className || params.class_name;
        if (option && option.value && /^\d+$/.test(option.value)) {
          params.class_id = Number(option.value);
          delete params.class_name;
        } else if (className && className !== params.class_name) {
          params.class_name = className;
        }
      }

      const response =
        await window.API.finance.getStudentPaymentStatusList(params);
      const payload = response?.data ?? response;
      const items = payload?.items ?? payload?.data?.items ?? [];
      const pagination = payload?.pagination ??
        payload?.data?.pagination ?? { page: 1, limit: 25, total: 0 };
      const summary =
        payload?.summary ?? payload?.data?.summary ?? this.data.summary;

      this.data.rows = Array.isArray(items) ? items : [];
      this.data.pagination = {
        page: pagination.page || 1,
        limit: pagination.limit || this.filters.limit,
        total: pagination.total || 0,
      };
      this.data.summary = summary;
      if (summary?.academic_year) {
        this.data.summaryYear = summary.academic_year;
      }

      this.renderTable();
      this.renderSummary();
      this.renderPagination();
      this.populatePaymentStudents();
    } catch (error) {
      // Never leave a silent empty table: a broken filter and genuinely no
      // fee rows must look different to the operator.
      console.error("Failed to load fee status:", error);
      this.data.rows = [];
      this.renderTable();
      this.notify(
        "Could not read the fee ledger. The filters above are still usable — adjust them or retry.",
        "danger",
      );
    }
  },

  renderSummary: function () {
    const summary = this.data.summary || {};
    // Everything comes from the database context tables (academic_year_*):
    // the year label, the current term and the per-term figures. Nothing is
    // hardcoded — the school runs for years and the workspace follows the
    // calendar data.
    const yearLabel = summary.academic_year || "";
    const currentTerm = Number(summary.current_term_number || 0);
    const hasCurrent = summary.has_current_term === true || Number(summary.has_current_term || 0) === 1;
    const period = summary.period_label || "Whole Year";
    const setPeriod = (el, text) => {
      if (el) el.textContent = text;
    };
    setPeriod(this.ui.expectedPeriodLabel, yearLabel || period);
    setPeriod(this.ui.collectedPeriodLabel, yearLabel || period);
    setPeriod(this.ui.outstandingPeriodLabel, yearLabel || period);
    setPeriod(this.ui.ratePeriodLabel, yearLabel || period);

    this.ui.totalExpected.textContent = this.formatCurrency(
      summary.total_due || 0,
    );
    this.ui.totalCollected.textContent = this.formatCurrency(
      summary.total_paid || 0,
    );
    this.ui.totalOutstanding.textContent = this.formatCurrency(
      summary.total_balance || 0,
    );
    this.ui.collectionRate.textContent = `${summary.collection_rate || 0}%`;

    // The selected-term (or current-term) figures below each annual figure —
    // picked from the summary's terms, which come from the actual term rows of
    // the selected year. The summary always carries every term of that year,
    // so drilling into Term 1 still shows the whole year's annual position.
    const terms = Array.isArray(summary.terms) ? summary.terms : [];
    const selectedTermNumber = this.filters.term_number
      ? this.termNumberOf(this.filters.term_number)
      : (hasCurrent ? currentTerm : 0);
    const selectedTerm =
      terms.find((t) => this.termNumberOf(t.term_number) === selectedTermNumber) ||
      null;
    const termLine = (el, format) => {
      if (!el) return;
      if (!selectedTerm) {
        el.textContent = "No term data for this year";
        return;
      }
      const suffix = Number(selectedTerm.term_number) === currentTerm && hasCurrent
        ? " (current)"
        : (this.filters.term_number ? " (selected)" : "");
      el.textContent = `Term ${selectedTerm.term_number}${suffix}: ${format(selectedTerm)}`;
    };
    termLine(this.ui.expectedTermLine, (t) => this.formatCurrency(t.total_due || 0));
    termLine(this.ui.collectedTermLine, (t) => this.formatCurrency(t.total_paid || 0));
    termLine(this.ui.outstandingTermLine, (t) => this.formatCurrency(t.total_balance || 0));
    termLine(this.ui.rateTermLine, (t) => `${t.collection_rate || 0}%`);

    this.renderTermProgress(summary);
  },

  renderTermProgress: function (summary) {
    const strip = document.getElementById("termProgressStrip");
    if (!strip) return;
    const terms = Array.isArray(summary.terms) ? summary.terms : [];
    if (!terms.length) {
      strip.innerHTML =
        '<div class="col-12 text-muted small">No term data available yet.</div>';
      return;
    }
    const annual = summary.annual || {};
    strip.innerHTML = terms
      .map((t) => {
        const rate = Number(t.collection_rate || 0);
        const barColor =
          rate >= 95 ? "bg-success" : rate >= 75 ? "bg-warning" : "bg-danger";
        const isCurrent = Number(t.term_number) === this.data.currentTermNumber;
        return (
          '<div class="col-md-4 mb-2">' +
          '<div class="d-flex justify-content-between small">' +
          `<span>${this.esc(t.label || "Term " + t.term_number)}${isCurrent ? ' <span class="badge bg-primary">Current</span>' : ""}</span>` +
          `<span class="text-muted">${rate.toFixed(2)}%</span>` +
          "</div>" +
          '<div class="progress" style="height: 8px;">' +
          `<div class="progress-bar ${barColor}" role="progressbar" style="width:${Math.min(100, rate)}%;" aria-valuenow="${rate}" aria-valuemin="0" aria-valuemax="100"></div>` +
          "</div>" +
          `<div class="d-flex justify-content-between small text-muted mt-1">` +
          `<span>Due ${this.formatCurrency(t.total_due || 0)}</span>` +
          `<span>Paid ${this.formatCurrency(t.total_paid || 0)}</span>` +
          `<span>Balance ${this.formatCurrency(t.total_balance || 0)}</span>` +
          "</div>" +
          "</div>"
        );
      })
      .join("");
    const updated = document.getElementById("termProgressUpdated");
    if (updated && annual.total_due !== undefined) {
      updated.textContent =
        `Whole year: due ${this.formatCurrency(annual.total_due || 0)} · ` +
        `paid ${this.formatCurrency(annual.total_paid || 0)} · ` +
        `balance ${this.formatCurrency(annual.total_balance || 0)}`;
    }
  },

  renderTable: function () {
    if (!this.ui.tableBody) {
      return;
    }

    if (!this.data.rows.length) {
      this.ui.tableBody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted">No fee records found.</td></tr>';
      return;
    }

    const canManageAwards = this.canManageAwards();

    this.ui.tableBody.innerHTML = this.data.rows
      .map((row) => {
        const status = this.formatPaymentStatus(row);
        const safeName = (row.student_name || "").replace(/'/g, "\\'");
        const studentId = Number(row.id);
        const admissionNo = row.admission_no || "-";
        const studentName = row.student_name || "-";
        const className = row.class_name || row.level_name || "-";
        const balance = Number(row.current_balance || 0);

        // Build dropdown menu items
        let dropdownItems = `
          <li><button class="dropdown-item" data-action="view" data-student-id="${studentId}"><i class="bi bi-eye me-2"></i>View Details</button></li>
          <li><button class="dropdown-item" data-action="record-payment" data-student-id="${studentId}"><i class="bi bi-cash-coin me-2"></i>Record Payment</button></li>
          <li><button class="dropdown-item" data-action="prompt-parent" data-student-id="${studentId}"><i class="bi bi-phone me-2"></i>Prompt Parent (STK Push)</button></li>
          <li><button class="dropdown-item" data-action="payment-trends" data-student-id="${studentId}"><i class="bi bi-graph-up me-2"></i>View Payment Trends</button></li>
          <li><button class="dropdown-item" data-action="history" data-student-id="${studentId}" data-student-name="${safeName}"><i class="bi bi-clock-history me-2"></i>Full History</button></li>
        `;
        if (canManageAwards) {
          dropdownItems += `
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" data-action="scholarship" data-student-id="${studentId}"><i class="bi bi-award me-2"></i>Sponsor (Scholarship)</button></li>
            <li><button class="dropdown-item" data-action="waive" data-student-id="${studentId}"><i class="bi bi-shield-check me-2"></i>Waive Fees</button></li>
          `;
        }

        return `
          <tr>
            <td class="text-center"><input type="checkbox" class="fee-student-select" value="${studentId}" ${this.data.selectedStudentIds.has(studentId) ? "checked" : ""} onchange="StudentFeesController.toggleStudent(${studentId}, this.checked)" aria-label="Select ${this.escapeHtml(studentName)}"></td>
            <td>${admissionNo}</td>
            <td>${studentName}</td>
            <td>${className}</td>
            <td>${this.formatCurrency(row.total_due || 0)}</td>
            <td>${this.formatCurrency(row.total_paid || 0)}</td>
            <td>${this.formatCurrency(balance)}</td>
            <td><span class="badge ${status.badge}">${status.label}</span></td>
            <td>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  ${dropdownItems}
                </ul>
              </div>
            </td>
          </tr>
        `;
      })
      .join("");

    // Attach event listeners for dropdown actions
    this.ui.tableBody.querySelectorAll("[data-action]").forEach((btn) => {
      btn.addEventListener("click", (event) => {
        const action = event.currentTarget.getAttribute("data-action");
        const studentId = event.currentTarget.getAttribute("data-student-id");
        const studentName = event.currentTarget.getAttribute("data-student-name");

        switch (action) {
          case "view":
            this.openFeeDetails(studentId);
            break;
          case "record-payment":
            this.openPaymentModalForStudent(studentId);
            break;
          case "prompt-parent":
            this.openPromptParentModal(studentId);
            break;
          case "payment-trends":
            this.openPaymentTrendsModal(studentId);
            break;
          case "history":
            this.openBillingHistory(studentId, studentName);
            break;
          case "scholarship":
            this.openAssistanceModal(studentId);
            break;
          case "waive":
            this.data.selectedStudentIds.clear();
            this.data.selectedStudentIds.add(Number(studentId));
            this.openWaiverModal();
            break;
        }
      });
    });
    this.updateSelectionUi();
  },

  toggleStudent: function (studentId, checked) {
    const id = Number(studentId);
    if (checked) this.data.selectedStudentIds.add(id);
    else this.data.selectedStudentIds.delete(id);
    this.updateSelectionUi();
  },

  toggleAllStudents: function (checked) {
    this.data.rows.forEach((row) => {
      const id = Number(row.id);
      if (checked) this.data.selectedStudentIds.add(id);
      else this.data.selectedStudentIds.delete(id);
    });
    this.renderTable();
  },

  updateSelectionUi: function () {
    const count = this.data.selectedStudentIds.size;
    const suffix = count ? ` (${count})` : "";
    if (this.ui.awardScholarshipBtn) this.ui.awardScholarshipBtn.innerHTML = `<i class="bi bi-award"></i> Sponsor (Scholarship)${suffix}`;
    if (this.ui.waiveFeesBtn) this.ui.waiveFeesBtn.innerHTML = `<i class="bi bi-shield-check"></i> Waive Off Fees${suffix}`;
  },

  renderPagination: function () {
    if (!this.ui.pagination) {
      return;
    }

    // Same shape as the staff table: Showing from–to of total, a rows-per-page
    // selector, and page N of totalPages. The page count derives from the data
    // size and the rows the user chooses to display; the values change with
    // the filters and stay fast.
    const { page, limit, total } = this.data.pagination;
    const totalPages = Math.max(1, Math.ceil(total / limit));
    const from = total ? (page - 1) * limit + 1 : 0;
    const to = Math.min(page * limit, total);
    const currentLimit = Number(limit || this.filters.limit || 25);

    const pageSizeOptions = [10, 25, 50, 100, 250];

    this.ui.pagination.innerHTML = `
      <div class="small text-muted">Showing ${from}–${to} of ${total}</div>
      <div class="d-flex align-items-center gap-2">
        <label class="small text-muted" for="feePageSize">Rows</label>
        <select id="feePageSize" class="form-select form-select-sm" style="width:auto" aria-label="Rows per page">
          ${pageSizeOptions.map((n) => `<option value="${n}" ${currentLimit === n ? "selected" : ""}>${n}</option>`).join("")}
        </select>
        <div class="btn-group btn-group-sm" role="group" aria-label="Fee accounts pages">
          <button class="btn btn-outline-secondary" type="button" data-fee-page="${Math.max(1, page - 1)}" ${page <= 1 ? "disabled" : ""}>Previous</button>
          <span class="btn btn-outline-secondary disabled">${page} / ${totalPages}</span>
          <button class="btn btn-outline-secondary" type="button" data-fee-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? "disabled" : ""}>Next</button>
        </div>
      </div>`;

    this.ui.pagination.querySelectorAll("[data-fee-page]").forEach((btn) => {
      btn.addEventListener("click", () => {
        this.filters.page = Number(btn.dataset.feePage);
        this.loadPaymentStatus();
      });
    });
    const sizeSelect = this.ui.pagination.querySelector("#feePageSize");
    if (sizeSelect) {
      sizeSelect.addEventListener("change", () => {
        this.filters.limit = Number(sizeSelect.value);
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }
  },

  openFeeDetails: async function (studentId) {
    if (!studentId) {
      return;
    }

    try {
      this.currentStudentId = Number(studentId);
      const statementResp = await window.API.finance.getStudentFeeStatement(
        studentId,
        {
          academic_year: this.filters.academic_year || undefined,
        },
      );
      const payload = statementResp?.data ?? statementResp;
      const student = payload?.student || {};
      const summary = payload?.summary || {};
      const obligations = payload?.obligations || [];
      const payments = payload?.payments || [];
      const balance = payload?.balance || {};

      this.ui.studentName.textContent = student.student_name || "-";
      this.ui.admNo.textContent = student.admission_no || "-";
      this.ui.modalTotalFee.textContent = this.formatCurrency(
        summary.total_due ?? balance.total_fee ?? 0,
      );
      this.ui.modalTotalPaid.textContent = this.formatCurrency(
        summary.total_paid ?? balance.amount_paid ?? 0,
      );
      this.ui.modalBalance.textContent = this.formatCurrency(
        summary.balance ?? balance.balance ?? 0,
      );

      this.ui.feeBreakdownBody.innerHTML = obligations
        .map((item) => {
          const status = this.formatPaymentStatus(
            item.payment_status || "pending",
          );
          return `
            <tr>
              <td>${item.fee_structure_name || item.fee_type_name || "-"}</td>
              <td>${this.formatCurrency(item.amount_due || 0)}</td>
              <td><span class="badge ${status.badge}">${status.label}</span></td>
            </tr>
          `;
        })
        .join("");

      this.ui.paymentHistoryBody.innerHTML =
        payments
          .map((payment) => {
            return `
            <tr>
              <td>${this.formatDate(payment.payment_date)}</td>
              <td>${payment.receipt_no || payment.reference_no || "-"}</td>
              <td>${this.formatCurrency(payment.amount_paid || payment.amount || 0)}</td>
              <td>${payment.payment_method || payment.method || "-"}</td>
              <td>${payment.received_by_name || payment.received_by || "-"}</td>
            </tr>
          `;
          })
          .join("") ||
        '<tr><td colspan="5" class="text-muted text-center">No payments recorded.</td></tr>';

      const modal = new bootstrap.Modal(this.ui.feeDetailsModal);
      modal.show();
    } catch (error) {
      console.error("Failed to load fee statement:", error);
    }
  },

  updateAssistanceCoverageFields: function () {
    const type = this.ui.assistanceCoverage?.value || "full";
    this.ui.assistancePercentageWrap?.classList.toggle("d-none", type !== "percentage");
    this.ui.assistanceAmountWrap?.classList.toggle("d-none", type !== "fixed_amount");
    if (type === "full") this.ui.assistancePercentage.value = 100;
  },

  updateAssistancePeriodFields: function () {
    const type = this.ui.assistancePeriodType?.value || "academic_year";
    this.ui.assistanceTermWrap?.classList.toggle("d-none", type !== "term");
    this.ui.assistanceStartsWrap?.classList.toggle("d-none", type !== "custom");
    this.ui.assistanceEndsWrap?.classList.toggle("d-none", type !== "custom");
    if (this.ui.assistanceTerm) this.ui.assistanceTerm.required = type === "term";
    if (this.ui.assistanceStartsOn) this.ui.assistanceStartsOn.required = type === "custom";
    if (this.ui.assistanceEndsOn) this.ui.assistanceEndsOn.required = type === "custom";
  },

  openAssistanceModal: async function (studentId) {
    const ids = studentId ? [Number(studentId)] : Array.from(this.data.selectedStudentIds);
    if (!ids.length) { this.notify("Select at least one student first.", "warning"); return; }
    const rows = ids.map((id) => this.data.rows.find((item) => Number(item.id) === id)).filter(Boolean);
    this.currentStudentId = ids[0];
    this.ui.assistanceStudentId.value = ids.join(",");
    this.ui.assistanceStudentLabel.textContent = ids.length === 1
      ? `${rows[0]?.student_name || "Student"} · ${rows[0]?.admission_no || ""}`
      : `${ids.length} selected students`;
    this.ui.assistanceForm.reset();
    this.ui.assistanceStudentId.value = ids.join(",");
    this.ui.assistanceCoverage.value = "full";
    this.ui.assistancePeriodType.value = "academic_year";
    this.ui.assistanceTerm.innerHTML = (this.data.academicYearTerms || []).map((term) => `<option value="${term.id}">${term.name || term.code || `Term ${term.term_id}`}</option>`).join("");
    this.updateAssistancePeriodFields();
    this.ui.assistancePercentage.value = 100;
    this.updateAssistanceCoverageFields();
    const years = this.data.years || [];
    this.ui.assistanceYear.innerHTML = years.map((year) => `<option value="${year.id}">${year.year_code || year.year_name || year.id}</option>`).join("");
    const current = years.find((year) => year.is_current == 1 || year.is_current === "1");
    if (current) this.ui.assistanceYear.value = String(current.id);
    const programsResp = await window.API.finance.getScholarshipPrograms();
    const programs = programsResp?.data ?? programsResp ?? [];
    this.ui.assistanceProgram.innerHTML = (Array.isArray(programs) ? programs : []).map((p) => `<option value="${p.id}" data-type="${p.coverage_type}" data-pct="${p.default_percentage || ""}">${p.name}</option>`).join("");
    this.ui.assistanceProgram.onchange = () => {
      const option = this.ui.assistanceProgram.selectedOptions[0];
      if (option?.dataset.type) this.ui.assistanceCoverage.value = option.dataset.type;
      if (option?.dataset.pct) this.ui.assistancePercentage.value = option.dataset.pct;
      this.updateAssistanceCoverageFields();
    };
    if (ids.length === 1) await this.loadAssistanceAwards(ids[0]);
    new bootstrap.Modal(this.ui.assistanceModal).show();
  },

  loadAssistanceAwards: async function (studentId) {
    const response = await window.API.finance.getStudentScholarships({ student_id: studentId });
    const awards = response?.data ?? response ?? [];
    this.ui.assistanceAwardsBody.innerHTML = (Array.isArray(awards) ? awards : []).map((award) => {
      const coverage = award.coverage_type === "full" ? "100%" : award.coverage_type === "percentage" ? `${award.coverage_percentage}%` : this.formatCurrency(award.coverage_amount);
      const period = award.period_type === "term" ? "Term" : award.period_type === "custom" ? `${award.starts_on || ""} – ${award.ends_on || ""}` : "Academic year";
      const action = award.status === "active" ? `<button class="btn btn-sm btn-outline-danger" data-revoke-award="${award.id}">Terminate</button>` : "";
      return `<tr><td>${award.year_code || award.academic_year_id}</td><td>${award.programme_name}</td><td>${coverage}</td><td>${period}</td><td>${award.status}</td><td>${action}</td></tr>`;
    }).join("") || '<tr><td colspan="5" class="text-muted">No annual awards recorded.</td></tr>';
    this.ui.assistanceAwardsBody.querySelectorAll("[data-revoke-award]").forEach((button) => button.addEventListener("click", async () => {
      if (!window.confirm("Terminate this sponsorship prospectively? Approved fee waivers are not affected.")) return;
      await window.API.finance.revokeStudentScholarship(button.dataset.revokeAward);
      await this.loadAssistanceAwards(studentId);
      await this.loadPaymentStatus();
    }));
  },

  saveAssistance: async function () {
    const type = this.ui.assistanceCoverage.value;
    const payload = {
      student_id: Number(String(this.ui.assistanceStudentId.value).split(",")[0]),
      academic_year_id: Number(this.ui.assistanceYear.value),
      scholarship_program_id: Number(this.ui.assistanceProgram.value),
      period_type: this.ui.assistancePeriodType.value,
      academic_year_term_id: this.ui.assistancePeriodType.value === "term" ? Number(this.ui.assistanceTerm.value) : null,
      starts_on: this.ui.assistancePeriodType.value === "custom" ? this.ui.assistanceStartsOn.value : null,
      ends_on: this.ui.assistancePeriodType.value === "custom" ? this.ui.assistanceEndsOn.value : null,
      coverage_type: type,
      coverage_percentage: type === "percentage" ? Number(this.ui.assistancePercentage.value) : null,
      coverage_amount: type === "fixed_amount" ? Number(this.ui.assistanceAmount.value) : null,
      reason: this.ui.assistanceReason.value.trim(),
      notes: this.ui.assistanceNotes.value.trim(),
    };
    if (!payload.student_id || !payload.academic_year_id || !payload.scholarship_program_id || !payload.reason) {
      this.notify("Select the year and programme, then enter the approval reason.", "warning"); return;
    }
    const studentIds = String(this.ui.assistanceStudentId.value).split(",").map(Number).filter(Boolean);
    if (!studentIds.length) { this.notify("Select at least one student.", "warning"); return; }
    try {
      for (const studentId of studentIds) await window.API.finance.saveStudentScholarship({ ...payload, student_id: studentId });
      this.notify(`Scholarship awarded to ${studentIds.length} student(s).`, "success");
      if (studentIds.length === 1) await this.loadAssistanceAwards(studentIds[0]);
      await this.loadPaymentStatus();
    } catch (error) { this.notify(error.message || "Unable to save scholarship.", "danger"); }
  },

  openWaiverModal: function () {
    const ids = Array.from(this.data.selectedStudentIds);
    if (!ids.length) { this.notify("Select at least one student first.", "warning"); return; }
    const years = this.data.years || [];
    this.ui.waiverYear.innerHTML = years.map((year) => `<option value="${year.year_code || year.year || year.id}">${year.year_code || year.year_name || year.id}</option>`).join("");
    const current = years.find((year) => year.is_current == 1 || year.is_current === "1");
    if (current) this.ui.waiverYear.value = String(current.year_code || current.year || current.id);
    this.ui.waiverStudentLabel.textContent = `${ids.length} selected students`;
    this.ui.waiverReason.value = "";
    this.ui.waiverNotes.value = "";
    this.ui.waiverScope.value = "full";
    this.ui.waiverAmount.value = "";
    this.ui.waiverAmountWrap.classList.add("d-none");
    new bootstrap.Modal(this.ui.waiverModal).show();
  },

  saveWaiver: async function () {
    if (!this.canManageAwards()) { this.notify("Only the director or school administrator can waive fees.", "danger"); return; }
    const ids = Array.from(this.data.selectedStudentIds);
    const reason = this.ui.waiverReason.value.trim();
    if (!ids.length || !reason) { this.notify("Select students and enter the waiver reason.", "warning"); return; }
    const rows = ids.map((id) => this.data.rows.find((row) => Number(row.id) === id)).filter(Boolean);
    try {
      for (const row of rows) {
        const scope = this.ui.waiverScope.value;
        const amount = scope === "full" ? Number(row.current_balance || 0) : Number(this.ui.waiverAmount.value || 0);
        if (amount <= 0) continue;
        if (scope === "percentage" && amount > 100) throw new Error("Fee-waiver percentage must be between 0 and 100.");
        await window.API.finance.saveFeeWaiver({
          student_id: Number(row.id), discount_type: scope === "full" ? "full_waiver" : scope === "percentage" ? "percentage" : "fixed_amount", discount_value: amount,
          academic_year: this.ui.waiverYear.value, reason, notes: this.ui.waiverNotes.value.trim(),
        });
      }
      this.notify(`Fee waiver applied to ${rows.length} student(s).`, "success");
      bootstrap.Modal.getInstance(this.ui.waiverModal)?.hide();
      await this.loadPaymentStatus();
    } catch (error) { this.notify(error.message || "Unable to apply fee waiver.", "danger"); }
  },

  printSelectedFeeAccounts: function () {
    const rows = this.data.rows.filter((row) => this.data.selectedStudentIds.has(Number(row.id)));
    if (!rows.length) { this.notify("Select at least one student to print.", "warning"); return; }
    if (!window.PrintManager?.printTable) { this.notify("Print service is unavailable.", "danger"); return; }
    const periodLabel = (this.data.summary || {}).period_label || "Whole Year";
    return window.PrintManager.printTable({
      title: "Selected Student Fee Accounts", subtitle: new Date().toLocaleDateString("en-KE") + " · " + periodLabel,
      filename: `selected_student_fee_accounts_${new Date().toISOString().slice(0, 10)}`,
      columns: [
        { key: "admission_no", label: "Admission No" }, { key: "student_name", label: "Student Name" },
        { key: "class_name", label: "Class" },
        { key: "total_due", label: `Expected (${periodLabel})`, type: "currency" },
        { key: "total_paid", label: `Paid (${periodLabel})`, type: "currency" },
        { key: "current_balance", label: `Balance (${periodLabel})`, type: "currency" },
        { key: "payment_status", label: "Status" },
      ], rows,
    });
  },

  openPaymentModal: function () {
    if (!this.ui.paymentModal) {
      return;
    }

    this.resetPaymentForm();
    const modal = new bootstrap.Modal(this.ui.paymentModal);
    modal.show();
  },

  /**
   * Academic years come from academic_years. The option value is the canonical
   * year_code because that is what the fee ledger stores, and the year picker
   * is also what scopes the term list.
   */
  populateYearFilter: function (years) {
    if (!this.ui.yearFilter) return;
    const list = Array.isArray(years) ? years : [];
    this.ui.yearFilter.innerHTML =
      '<option value="">All years</option>' +
      list
        .map((year) => {
          const value =
            year.year_code || year.year || year.name || year.id || "";
          const isCurrent =
            year.is_current == 1 || year.is_current === "1";
          const label = `${value}${isCurrent ? " (current)" : ""}`;
          return `<option value="${this.esc(String(value))}">${this.esc(label)}</option>`;
        })
        .join("");

    // Default the selection to the current year so the first open shows a
    // defined period instead of an unfiltered multi-year total.
    const current = this.currentYearOptionValue(list);
    if (current) {
      this.ui.yearFilter.value = current;
    }
  },

  currentYearOptionValue: function (years) {
    const list = Array.isArray(years) ? years : [];
    const current = list.find(
      (year) => year.is_current == 1 || year.is_current === "1",
    );
    if (!current) return "";
    return String(
      current.year_code || current.year || current.name || current.id || "",
    );
  },

  /**
   * Classes come from the classes table, so the option carries the class id
   * and the label carries the display name. The fee ledger matches on class_id
   * where it exists; sending the name only worked when a learner happened to
   * sit in a stream-less "Grade 8" and silently returned zero for the rest.
   */
  populateClassFilter: function (classes) {
    if (!this.ui.classFilter) {
      return;
    }

    const firstOption = this.ui.classFilter.options[0];
    this.ui.classFilter.innerHTML = "";
    if (firstOption) {
      this.ui.classFilter.appendChild(firstOption);
    }

    const list = Array.isArray(classes) ? classes : [];
    list.forEach((cls) => {
      const name = cls.name || cls.class_name || "";
      if (!name) {
        return;
      }
      const option = document.createElement("option");
      option.value = cls.id != null && cls.id !== "" ? String(cls.id) : name;
      option.dataset.className = name;
      option.textContent = name;
      this.ui.classFilter.appendChild(option);
    });
  },

  /**
   * Term options come from academic_year_terms for the selected year — never
   * from a hardcoded 1/2/3 list, because a year can be configured with a
   * different term set and an unopened year may not be the current one.
   *
   * The default scope stays "Whole Year (All Terms)": the annual position and
   * the per-term breakdown are both returned, so the first open answers the
   * question the page is actually asked — what does this year owe, what has
   * been paid, and what is left.
   */
  populateTermFilter: function (terms) {
    if (!this.ui.termFilter) {
      return;
    }

    const previous = this.ui.termFilter.value || this.filters.term_number || "";

    this.ui.termFilter.innerHTML =
      '<option value="">Whole Year (All Terms)</option>';

    if (!Array.isArray(terms) || terms.length === 0) {
      this.ui.termFilter.value = "";
      return;
    }

    // Scoped to the selected year: the endpoint is year-filtered, but drop any
    // straggler row from another year so labels can never collide.
    const selectedYear = this.filters.academic_year || "";
    const scoped = terms.filter((term) => {
      if (!selectedYear) {
        return true;
      }
      const code = String(term.year_code || term.year_name || "");
      const yearId = String(term.year ?? "");
      return (
        code === selectedYear ||
        yearId === selectedYear ||
        yearId === String(this.currentYearId(selectedYear))
      );
    });

    const unique = new Map();
    (scoped.length ? scoped : terms).forEach((term) => {
      const termNumber = term.term_number ?? term.code ?? null;
      if (!termNumber) {
        return;
      }
      unique.set(String(termNumber), term);
    });

    const sorted = Array.from(unique.values()).sort(
      (a, b) =>
        this.termNumberOf(a.term_number ?? a.code) -
        this.termNumberOf(b.term_number ?? b.code),
    );

    sorted.forEach((term) => {
      const option = document.createElement("option");
      const raw = term.term_number ?? term.code;
      option.value = String(raw);
      const n = this.termNumberOf(raw);
      const dates =
        term.start_date && term.end_date
          ? ` (${this.formatDate(term.start_date)} – ${this.formatDate(
              term.end_date,
            )})`
          : "";
      option.textContent = `Term ${n}${dates}`;
      this.ui.termFilter.appendChild(option);
    });

    const currentTerm = sorted.find(
      (term) =>
        term.status === "current" ||
        term.status === "active" ||
        term.is_current == 1 ||
        term.is_current === "1",
    );
    if (currentTerm && (currentTerm.term_number || currentTerm.code)) {
      // The current term badges the progress strip; the default scope stays
      // the whole year so the first open shows the complete position.
      this.data.currentTermNumber = this.termNumberOf(
        currentTerm.term_number || currentTerm.code,
      );
    }

    // Keep the operator's term choice when the year changes, but only if that
    // term still exists in the newly selected year.
    const optionValues = Array.from(this.ui.termFilter.options).map((o) =>
      o.value,
    );
    const keep = previous && optionValues.includes(previous) ? previous : "";
    this.ui.termFilter.value = keep;
    this.filters.term_number = keep;
  },

  /** 'T3' and 3 both mean Term 3. */
  termNumberOf: function (value) {
    const match = String(value ?? "").match(/(\d+)/);
    return match ? Number(match[1]) : 0;
  },

  currentYearId: function (yearValue) {
    const list = Array.isArray(this.data.years) ? this.data.years : [];
    const match = list.find(
      (year) =>
        String(year.year_code || year.year || year.name || year.id || "") ===
        String(yearValue),
    );
    return match ? match.id : "";
  },

  /**
   * Load the term list for one academic year. Every year has its own
   * academic_year_terms rows, so the term picker is always rebuilt for the
   * year the ledger is showing.
   */
  loadTermsForYear: async function (academicYear) {
    const cache = this.state.referenceCache;
    const params = {};
    if (academicYear) {
      params.academic_year = academicYear;
    }
    const termsResp = cache?.terms !== undefined ? cache.terms : await window.API.academic.listTerms(params);
    const terms = this.unwrapList(termsResp);
    this.data.academicYearTerms = terms;
    this.populateTermFilter(terms);
    return terms;
  },

  populatePaymentStudents: function () {
    if (!this.ui.paymentStudent) {
      return;
    }

    const firstOption =
      this.ui.paymentStudent.options[0] || new Option("Select student", "");
    this.ui.paymentStudent.innerHTML = "";
    this.ui.paymentStudent.appendChild(firstOption);

    const unique = new Map();
    this.data.rows.forEach((row) => {
      if (row.id && !unique.has(row.id)) {
        unique.set(row.id, row);
      }
    });

    unique.forEach((row) => {
      const option = document.createElement("option");
      option.value = row.id;
      option.textContent =
        `${row.admission_no || ""} - ${row.student_name || ""}`.trim();
      this.ui.paymentStudent.appendChild(option);
    });
  },

  updateOutstandingAmount: async function (studentId) {
    if (!studentId) {
      this.ui.outstandingAmount.textContent = this.formatCurrency(0);
      return;
    }

    try {
      const balanceResp = await window.API.finance.getStudentBalance(studentId);
      const payload = balanceResp?.data ?? balanceResp;
      const balances = payload?.balances || [];
      const latest = balances[0] || {};
      const balanceValue =
        latest.balance || latest.term_balance || latest.year_balance || 0;
      this.ui.outstandingAmount.textContent = this.formatCurrency(balanceValue);
    } catch (error) {
      console.warn("Failed to load student balance:", error);
      this.ui.outstandingAmount.textContent = this.formatCurrency(0);
    }
  },

  resetPaymentForm: function () {
    if (!this.ui.paymentForm) {
      return;
    }

    this.ui.paymentForm.reset();
    this.ui.paymentStudentId.value = "";
    this.ui.outstandingAmount.textContent = this.formatCurrency(0);
    if (this.ui.paymentDate) {
      const today = new Date().toISOString().split("T")[0];
      this.ui.paymentDate.value = today;
    }
  },

  savePayment: async function () {
    const studentId = this.ui.paymentStudent.value;
    const amount = parseFloat(this.ui.paymentAmount.value || "0");
    const paymentMethod = this.ui.paymentMethod.value;
    const paymentDate = this.ui.paymentDate.value;

    if (!studentId || !amount || amount <= 0 || !paymentDate) {
      showNotification(
        "Please provide student, amount, and payment date.",
        NOTIFICATION_TYPES.WARNING,
      );
      return;
    }

    const payload = {
      type: "payment",
      student_id: studentId,
      amount: amount,
      payment_method: paymentMethod === "bank" ? "bank_transfer" : paymentMethod,
      reference_no: this.ui.paymentReference.value || null,
      payment_date: paymentDate,
      notes: this.ui.paymentNotes.value || null,
    };

    try {
      await window.API.finance.recordPayment(payload);
      showNotification(
        "Payment recorded successfully.",
        NOTIFICATION_TYPES.SUCCESS,
      );
      const modal = bootstrap.Modal.getInstance(this.ui.paymentModal);
      if (modal) {
        modal.hide();
      }
      await this.loadPaymentStatus();
    } catch (error) {
      console.error("Failed to record payment:", error);
      showNotification("Failed to record payment.", NOTIFICATION_TYPES.ERROR);
    }
  },

  exportTable: function () {
    if (!this.data.rows.length) {
      showNotification("No data available to export.", NOTIFICATION_TYPES.INFO);
      return;
    }

    const periodLabel = (this.data.summary || {}).period_label || "Whole Year";
    const headers = [
      "Admission No",
      "Student Name",
      "Class",
      `Expected (${periodLabel})`,
      `Paid (${periodLabel})`,
      `Balance (${periodLabel})`,
      "Status",
    ];

    const rows = this.data.rows.map((row) => [
      row.admission_no || "",
      row.student_name || "",
      row.class_name || row.level_name || "",
      row.total_due || 0,
      row.total_paid || 0,
      row.current_balance || 0,
      row.payment_status || "",
    ]);

    const csv = [headers, ...rows]
      .map((line) =>
        line
          .map((value) => {
            const text = String(value ?? "");
            return `"${text.replace(/"/g, '""')}"`;
          })
          .join(","),
      )
      .join("\n");

    KingswayFileLifecycle.exportText(csv, `student_fees_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8;");
  },

  formatCurrency: function (value) {
    const number = Number(value || 0);
    return `KES ${number.toLocaleString("en-KE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  },

  formatPaymentStatus: function (statusOrRow) {
    const row = statusOrRow && typeof statusOrRow === "object" ? statusOrRow : {};
    const status = typeof statusOrRow === "object" ? row.payment_status : statusOrRow;
    if (Number(row.is_sponsored || 0) === 1 || row.sponsorship_type) {
      return { label: `Sponsored – ${row.sponsorship_type || "Sponsorship"}`, badge: "bg-info text-dark" };
    }
    const waived = Number(row.total_waived || row.amount_waived || 0);
    if (waived > 0) {
      return { label: `Waived – ${this.formatCurrency(waived)}`, badge: "bg-primary" };
    }
    const normalized = String(status || "").toLowerCase();
    if (normalized === "paid" || normalized === "fully_paid") {
      return { label: "Paid", badge: "bg-success" };
    }
    if (normalized === "partial") {
      return { label: "Partial", badge: "bg-warning text-dark" };
    }
    if (normalized === "overpaid" || normalized === "credit") {
      // The balances view marks an overpay (negative balance) as 'credit'.
      // Falling through to "Pending" made fully-paid credit rows look owed.
      return { label: "Overpaid (Credit)", badge: "bg-info text-dark" };
    }
    if (normalized === "arrears") {
      return { label: "Arrears", badge: "bg-danger" };
    }
    if (normalized === "waived") {
      return { label: "Waived", badge: "bg-primary" };
    }
    return { label: "Pending", badge: "bg-secondary" };
  },

  formatDate: function (value) {
    if (!value) {
      return "-";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleDateString();
  },

  unwrapList: function (resp) {
    if (!resp) return [];
    if (Array.isArray(resp)) return resp;
    if (Array.isArray(resp.data)) return resp.data;
    if (Array.isArray(resp.items)) return resp.items;
    if (Array.isArray(resp.data?.items)) return resp.data.items;
    if (Array.isArray(resp.data?.data)) return resp.data.data;
    if (Array.isArray(resp.data?.data?.items)) return resp.data.data.items;
    return [];
  },

  openBillingHistory: function(studentId, studentName) {
    document.getElementById('historyStudentName').textContent = studentName;
    document.getElementById('billingHistoryContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('studentBillingHistoryModal'));
    modal.show();

    window.API.apiCall('/finance/students-billing-history/' + studentId, 'GET')
      .then(function(resp) {
        var data = resp.data || resp;
        StudentFeesController.renderBillingHistory(data, studentId);
      })
      .catch(function() {
        document.getElementById('billingHistoryContent').innerHTML = '<div class="alert alert-danger">Failed to load billing history.</div>';
      });
  },

  renderBillingHistory: function(data, studentId) {
    // data.academic_years is array of { year, terms: [{ term_id, term_name, obligations: [...], payments: [...], total_due, total_paid, balance }] }
    var years = data.academic_years || data || [];
    var relief = data.financial_relief;
    var reliefHtml = relief ? '<div class="alert alert-warning small"><i class="bi bi-shield-check me-1"></i><strong>Approved financial relief:</strong> ' +
      (relief.registration_fee_waived ? 'Registration fee waived. ' : '') +
      (relief.school_fee_waived ? 'School-fee relief: ' + (relief.school_fee_waiver_type || 'approved') + '. ' : '') +
      (Number(relief.school_fee_waived_amount || 0) > 0 ? 'Waiver applied: KES ' + Number(relief.school_fee_waived_amount).toLocaleString() + '. ' : '') +
      (relief.reason ? String(relief.reason).replace(/[<>]/g, '') : '') + '</div>' : '';
    if (!years.length) {
      document.getElementById('billingHistoryContent').innerHTML = reliefHtml + '<div class="alert alert-info">No billing history found.</div>';
      return;
    }

    var html = reliefHtml;
    years.forEach(function(yr) {
      html += '<div class="card mb-3">';
      html += '<div class="card-header fw-bold bg-light">Academic Year ' + yr.year + '</div>';
      html += '<div class="card-body p-0">';

      // Tabs for terms
      html += '<ul class="nav nav-tabs px-3 pt-2" id="tabs-' + yr.year + '">';
      (yr.terms || []).forEach(function(term, i) {
        html += '<li class="nav-item"><a class="nav-link' + (i === 0 ? ' active' : '') + '" data-bs-toggle="tab" href="#term-' + yr.year + '-' + term.term_id + '">' + term.term_name + '</a></li>';
      });
      html += '</ul>';

      html += '<div class="tab-content p-3">';
      (yr.terms || []).forEach(function(term, i) {
        html += '<div class="tab-pane fade' + (i === 0 ? ' show active' : '') + '" id="term-' + yr.year + '-' + term.term_id + '">';

        // Obligations table
        html += '<h6 class="text-muted mb-2">Fee Obligations</h6>';
        html += '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Waived</th><th>Balance</th><th>Status</th></tr></thead><tbody>';
        (term.obligations || []).forEach(function(o) {
          var statusClass = ['paid', 'credit'].includes(o.payment_status) ? 'success' : o.payment_status === 'partial' ? 'warning' : 'danger';
          html += '<tr><td>' + (o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_waived || 0).toLocaleString() + '</td><td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td><td><span class="badge bg-' + statusClass + '">' + (o.payment_status || 'pending') + '</span></td></tr>';
        });
        html += '<tr class="table-light fw-bold"><td>TOTAL</td><td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td><td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td><td>—</td><td>KES ' + Number(term.balance || 0).toLocaleString() + (Number(term.balance || 0) < 0 ? ' credit' : '') + '</td><td></td></tr>';
        html += '</tbody></table>';

        // Payments table
        if ((term.payments || []).length > 0) {
          html += '<h6 class="text-muted mb-2">Payments Received</h6>';
          html += '<table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th></tr></thead><tbody>';
          (term.payments || []).forEach(function(p) {
            html += '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td>' + (p.payment_method || '') + '</td><td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + (p.receipt_no || '—') + '</td><td>' + (p.reference_no || '—') + '</td></tr>';
          });
          html += '</tbody></table>';
        }

        html += '</div>'; // tab-pane
      });
      html += '</div></div></div>';
    });

    document.getElementById('billingHistoryContent').innerHTML = html;
  },

  printFeeStatement: function () {
    if (!this.data.selectedStudent) {
      this.notify("No student selected", "warning");
      return;
    }

    const student = this.data.selectedStudent;
    const billingHistory = this.data.billingHistory || [];

    // The server prepares the canonical statement from obligations, waivers,
    // balances and confirmed payments. Do not print a browser-reconstructed
    // table that can drift from the accounting database.
    if (window.PrintManager && typeof window.PrintManager.printFeeStatement === 'function') {
      return window.PrintManager.printFeeStatement({
        student_id: student.student_id || student.id,
        academic_year: this.filters.academic_year || undefined,
        download: false,
      });
    }

    // Build fee statement rows
    const feeRows = [];
    billingHistory.forEach(term => {
      (term.fee_items || []).forEach(item => {
        feeRows.push({
          term: term.term_name || term.academic_year || '—',
          fee_type: item.fee_type_name || '—',
          amount_due: item.amount_due || 0,
          amount_paid: item.amount_paid || 0,
          amount_waived: item.amount_waived || 0,
          balance: item.balance || 0,
          status: item.payment_status || 'pending'
        });
      });
    });

    const money = (value) => `KSh ${Number(value || 0).toLocaleString("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;

    const columns = [
      { key: "term", label: "Term", width: "12%" },
      { key: "fee_type", label: "Fee Item", width: "22%" },
      { key: "amount_due", label: "Amount Due", type: "currency", width: "15%", formatter: money },
      { key: "amount_paid", label: "Amount Paid", type: "currency", width: "15%", formatter: money },
      { key: "amount_waived", label: "Waived", type: "currency", width: "12%", formatter: money },
      { key: "balance", label: "Balance", type: "currency", width: "15%", formatter: money },
      { key: "status", label: "Status", width: "9%" },
    ];

    // Calculate totals
    const totalDue = feeRows.reduce((sum, row) => sum + Number(row.amount_due || 0), 0);
    const totalPaid = feeRows.reduce((sum, row) => sum + Number(row.amount_paid || 0), 0);
    const totalWaived = feeRows.reduce((sum, row) => sum + Number(row.amount_waived || 0), 0);
    const totalBalance = feeRows.reduce((sum, row) => sum + Number(row.balance || 0), 0);

    window.PrintManager.printTable({
      title: 'Student Fee Statement',
      subtitle: `${student.first_name || ''} ${student.last_name || ''} (${student.admission_no || '—'})`,
      columns: columns,
      rows: feeRows,
      summary: {
        'Student Name': `${student.first_name || ''} ${student.last_name || ''}`,
        'Admission No': student.admission_no || '—',
        'Class': student.class_name || '—',
        'Total Due': money(totalDue),
        'Total Paid': money(totalPaid),
        'Total Waived': money(totalWaived),
        'Outstanding Balance': money(totalBalance),
              },
      orientation: 'landscape',
      paperSize: 'A4',
      reportCode: 'FEE-' + (student.student_id || student.id || '0'),
      signatureSection: [
        { label: 'Accountant', dateLine: true },
        { label: 'Headteacher', dateLine: true }
      ]
    });
  },

  /**
   * Patch specific table rows in place without full reload
   */
  patchTableRows: async function (studentIds) {
    if (!studentIds || !studentIds.length) return;

    try {
      // Fetch fresh data for just these students
      for (const studentId of studentIds) {
        const response = await window.API.finance.getStudentBalance(studentId);
        const payload = response?.data ?? response;
        const balances = payload?.balances || [];
        const latest = balances[0] || {};
        const balanceValue = latest.balance || latest.term_balance || latest.year_balance || 0;
        const totalPaid = latest.total_paid || latest.amount_paid || 0;
        const totalDue = latest.total_due || latest.amount_due || 0;
        const paymentStatus = latest.payment_status || (balanceValue <= 0 ? 'paid' : balanceValue >= totalDue ? 'pending' : 'partial');

        // Update the row in data
        const rowIndex = this.data.rows.findIndex(r => Number(r.id) === Number(studentId));
        if (rowIndex !== -1) {
          this.data.rows[rowIndex].current_balance = balanceValue;
          this.data.rows[rowIndex].total_paid = totalPaid;
          this.data.rows[rowIndex].total_due = totalDue;
          this.data.rows[rowIndex].payment_status = paymentStatus;

          // Update just that row in the DOM
          this.updateTableRow(rowIndex, this.data.rows[rowIndex]);
        }
      }
    } catch (error) {
      console.warn("Failed to patch table rows:", error);
      // Don't trigger full refresh - let user manually refresh if needed
    }
  },

  /**
   * Update a single table row in place
   */
  updateTableRow: function (rowIndex, row) {
    if (!this.ui.tableBody) return;
    const tr = this.ui.tableBody.querySelector(`tr:nth-child(${rowIndex + 1})`);
    if (!tr) return;

    const status = this.formatPaymentStatus(row);
    tr.innerHTML = `
      <td class="text-center"><input type="checkbox" class="fee-student-select" value="${Number(row.id)}" ${this.data.selectedStudentIds.has(Number(row.id)) ? "checked" : ""} onchange="StudentFeesController.toggleStudent(${Number(row.id)}, this.checked)" aria-label="Select ${this.escapeHtml(row.student_name || "student")}"></td>
      <td>${row.admission_no || "-"}</td>
      <td>${row.student_name || "-"}</td>
      <td>${row.class_name || row.level_name || "-"}</td>
      <td>${this.formatCurrency(row.total_due || 0)}</td>
      <td>${this.formatCurrency(row.total_paid || 0)}</td>
      <td>${this.formatCurrency(row.current_balance || 0)}</td>
      <td><span class="badge ${status.badge}">${status.label}</span></td>
      <td>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><button class="dropdown-item" data-action="view" data-student-id="${row.id}"><i class="bi bi-eye me-2"></i>View Details</button></li>
            <li><button class="dropdown-item" data-action="record-payment" data-student-id="${row.id}"><i class="bi bi-cash-coin me-2"></i>Record Payment</button></li>
            <li><button class="dropdown-item" data-action="prompt-parent" data-student-id="${row.id}"><i class="bi bi-phone me-2"></i>Prompt Parent (STK Push)</button></li>
            <li><button class="dropdown-item" data-action="payment-trends" data-student-id="${row.id}"><i class="bi bi-graph-up me-2"></i>View Payment Trends</button></li>
            <li><button class="dropdown-item" data-action="history" data-student-id="${row.id}" data-student-name="${(row.student_name || "").replace(/'/g, "\\'")}"><i class="bi bi-clock-history me-2"></i>Full History</button></li>
            ${this.canManageAwards() ? `
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item" data-action="scholarship" data-student-id="${row.id}"><i class="bi bi-award me-2"></i>Sponsor (Scholarship)</button></li>
              <li><button class="dropdown-item" data-action="waive" data-student-id="${row.id}"><i class="bi bi-shield-check me-2"></i>Waive Fees</button></li>
            ` : ''}
          </ul>
        </div>
      </td>
    `;

    // Re-attach event listeners for the new buttons
    tr.querySelectorAll("[data-action]").forEach((btn) => {
      btn.addEventListener("click", (event) => {
        const action = event.currentTarget.getAttribute("data-action");
        const studentId = event.currentTarget.getAttribute("data-student-id");
        const studentName = event.currentTarget.getAttribute("data-student-name");

        switch (action) {
          case "view":
            this.openFeeDetails(studentId);
            break;
          case "record-payment":
            this.openPaymentModalForStudent(studentId);
            break;
          case "prompt-parent":
            this.openPromptParentModal(studentId);
            break;
          case "payment-trends":
            this.openPaymentTrendsModal(studentId);
            break;
          case "history":
            this.openBillingHistory(studentId, studentName);
            break;
          case "scholarship":
            this.openAssistanceModal(studentId);
            break;
          case "waive":
            this.data.selectedStudentIds.clear();
            this.data.selectedStudentIds.add(Number(studentId));
            this.openWaiverModal();
            break;
        }
      });
    });

    // Restore checkbox selection
    const checkbox = tr.querySelector(".fee-student-select");
    if (checkbox && this.data.selectedStudentIds.has(Number(row.id))) {
      checkbox.checked = true;
    }
  },

  /**
   * Open payment modal for a specific student
   */
  openPaymentModalForStudent: function (studentId) {
    this.resetPaymentForm();
    this.ui.paymentStudent.value = studentId;
    this.ui.paymentStudentId.value = studentId;
    this.updateOutstandingAmount(studentId);
    const modal = new bootstrap.Modal(this.ui.paymentModal);
    modal.show();
  },

  /**
   * Open Prompt Parent modal for STK push
   */
  openPromptParentModal: async function (studentId) {
    if (!studentId) return;

    try {
      const row = this.data.rows.find((r) => Number(r.id) === Number(studentId));
      if (!row) {
        this.notify("Student not found.", "warning");
        return;
      }

      const studentResp = await window.API.finance.getStudentFeeStatement(studentId, {
        academic_year: this.filters.academic_year || undefined,
      });
      const payload = studentResp?.data ?? studentResp;
      const student = payload?.student || {};
      const balance = payload?.balance || {};

      this.ui.promptStudentId.value = studentId;
      this.ui.promptStudentName.value = student.student_name || row.student_name || "";
      this.ui.promptAdmNo.value = student.admission_no || row.admission_no || "";
      this.ui.promptClass.value = student.class_name || row.class_name || row.level_name || "";
      this.ui.promptBalance.value = this.formatCurrency(balance.balance || balance.total_balance || row.current_balance || 0);
      this.ui.promptOutstanding.textContent = this.formatCurrency(balance.balance || balance.total_balance || row.current_balance || 0);

      const parentPhone = student.parent_phone || student.guardian_phone || "";
      const parentName = student.parent_name || student.guardian_name || "";
      this.ui.promptPhone.value = parentPhone;
      this.ui.promptParentName.value = parentName;

      this.ui.promptAmount.value = balance.balance || balance.total_balance || row.current_balance || 0;

      const modal = new bootstrap.Modal(this.ui.promptParentModal);
      modal.show();
    } catch (error) {
      console.error("Failed to load student details for STK push:", error);
      this.notify("Could not load student details.", "danger");
    }
  },

  /**
   * Send STK Push via PayHero
   */
  sendStkPush: async function () {
    const studentId = this.ui.promptStudentId.value;
    const phone = this.ui.promptPhone.value.trim();
    const amount = parseFloat(this.ui.promptAmount.value || "0");
    const parentName = this.ui.promptParentName.value.trim();
    const purpose = this.ui.promptPurpose.value;
    const description = this.ui.promptDescription.value.trim() || `School fees payment for ${this.ui.promptStudentName.value}`;

    if (!studentId || !phone || !amount || amount <= 0) {
      this.notify("Please provide phone number and amount.", "warning");
      return;
    }

    if (!/^254[0-9]{9}$/.test(phone)) {
      this.notify("Phone number must be in format 2547XXXXXXXX.", "warning");
      return;
    }

    this.ui.sendStkPushBtn.disabled = true;
    this.ui.sendStkPushBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    try {
      const payload = {
        student_id: Number(studentId),
        purpose: purpose,
        amount: amount,
        phone: phone,
        customer_name: parentName || this.ui.promptStudentName.value,
        description: description,
      };

      const response = await window.API.payments.initiateCollection(payload);
      const result = response?.data ?? response;

      if (result?.accepted) {
        const rail = result?.fallback_used ? " (fallback rail)" : "";
        this.notify(`Payment prompt sent to ${phone}${rail}. Parent should check their phone and enter PIN.`, "success");
        const modal = bootstrap.Modal.getInstance(this.ui.promptParentModal);
        if (modal) modal.hide();
        setTimeout(() => this.loadPaymentStatus(), 3000);
      } else {
        this.notify(result?.message || "Failed to send STK push.", "danger");
      }
    } catch (error) {
      console.error("STK push error:", error);
      this.notify(error.message || "Failed to send STK push.", "danger");
    } finally {
      this.ui.sendStkPushBtn.disabled = false;
      this.ui.sendStkPushBtn.innerHTML = '<i class="bi bi-send me-1"></i>Send STK Push';
    }
  },

  /**
   * Open Payment Trends modal
   */
  openPaymentTrendsModal: async function (studentId) {
    if (!studentId) return;

    try {
      const row = this.data.rows.find((r) => Number(r.id) === Number(studentId));
      if (!row) {
        this.notify("Student not found.", "warning");
        return;
      }

      const modal = new bootstrap.Modal(this.ui.paymentTrendsModal);
      this.ui.paymentTrendsContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading payment trends...</p></div>';
      modal.show();

      const response = await window.API.apiCall('/finance/students-billing-history/' + studentId, 'GET');
      const data = response?.data ?? response;

      this.renderPaymentTrends(data, row);
    } catch (error) {
      console.error("Failed to load payment trends:", error);
      this.ui.paymentTrendsContent.innerHTML = '<div class="alert alert-danger">Failed to load payment trends.</div>';
    }
  },

  /**
   * Render payment trends in modal
   */
  renderPaymentTrends: function (data, studentRow) {
    const years = data.academic_years || data || [];
    const studentName = studentRow.student_name || "Student";
    const admNo = studentRow.admission_no || "";

    let html = `
      <div class="mb-3">
        <h6>${this.esc(studentName)} (${this.esc(admNo)})</h6>
        <small class="text-muted">Class: ${this.esc(studentRow.class_name || studentRow.level_name || "—")}</small>
      </div>
    `;

    if (!years.length) {
      html += '<div class="alert alert-info">No payment history found.</div>';
      this.ui.paymentTrendsContent.innerHTML = html;
      return;
    }

    let totalPaidAll = 0;
    let totalDueAll = 0;

    years.forEach((yr) => {
      (yr.terms || []).forEach((term) => {
        totalPaidAll += Number(term.total_paid || 0);
        totalDueAll += Number(term.total_due || 0);
      });
    });

    html += `
      <div class="row mb-4">
        <div class="col-md-4"><div class="card border-success"><div class="card-body text-center"><h6>Total Paid</h6><h4 class="text-success">${this.formatCurrency(totalPaidAll)}</h4></div></div></div>
        <div class="col-md-4"><div class="card border-primary"><div class="card-body text-center"><h6>Total Expected</h6><h4 class="text-primary">${this.formatCurrency(totalDueAll)}</h4></div></div></div>
        <div class="col-md-4"><div class="card border-info"><div class="card-body text-center"><h6>Collection Rate</h6><h4 class="text-info">${totalDueAll > 0 ? ((totalPaidAll / totalDueAll) * 100).toFixed(1) : 0}%</h4></div></div></div>
      </div>
    `;

    const timelineLabels = [];
    const timelinePaid = [];
    const timelineDue = [];

    years.forEach((yr) => {
      (yr.terms || []).forEach((term) => {
        timelineLabels.push(`${yr.year} - ${term.term_name}`);
        timelinePaid.push(Number(term.total_paid || 0));
        timelineDue.push(Number(term.total_due || 0));
      });
    });

    let methodBreakdown = {};
    years.forEach((yr) => {
      (yr.terms || []).forEach((term) => {
        (term.payments || []).forEach((p) => {
          const method = p.payment_method || "Unknown";
          methodBreakdown[method] = (methodBreakdown[method] || 0) + Number(p.amount_paid || 0);
        });
      });
    });

    html += `
      <div class="row mb-4">
        <div class="col-md-6">
          <h6>Payment Timeline</h6>
          <canvas id="trendsTimelineChart" height="120"></canvas>
        </div>
        <div class="col-md-6">
          <h6>Payment Methods</h6>
          <canvas id="trendsMethodChart" height="120"></canvas>
        </div>
      </div>

      <h6 class="mb-3">Term-wise Breakdown</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th>Academic Year</th>
              <th>Term</th>
              <th>Expected (KES)</th>
              <th>Paid (KES)</th>
              <th>Balance (KES)</th>
              <th>Rate</th>
              <th>Payments Count</th>
            </tr>
          </thead>
          <tbody>
    `;

    years.forEach((yr) => {
      (yr.terms || []).forEach((term) => {
        const rate = term.total_due > 0 ? ((term.total_paid / term.total_due) * 100).toFixed(1) : 0;
        const paymentsCount = (term.payments || []).length;
        html += `
          <tr>
            <td>${this.esc(yr.year)}</td>
            <td>${this.esc(term.term_name)}</td>
            <td>${this.formatCurrency(term.total_due || 0)}</td>
            <td>${this.formatCurrency(term.total_paid || 0)}</td>
            <td>${this.formatCurrency(term.balance || 0)}</td>
            <td><span class="badge ${rate >= 90 ? 'bg-success' : rate >= 50 ? 'bg-warning' : 'bg-danger'}">${rate}%</span></td>
            <td>${paymentsCount}</td>
          </tr>
        `;
      });
    });

    html += `
          </tbody>
        </table>
      </div>
    `;

    this.ui.paymentTrendsContent.innerHTML = html;

    if (typeof Chart !== "undefined") {
      new Chart(document.getElementById("trendsTimelineChart"), {
        type: "bar",
        data: {
          labels: timelineLabels,
          datasets: [
            { label: "Paid", data: timelinePaid, backgroundColor: "rgba(25, 135, 84, 0.8)" },
            { label: "Expected", data: timelineDue, backgroundColor: "rgba(13, 110, 253, 0.8)" },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } },
      });

      const methodLabels = Object.keys(methodBreakdown);
      const methodData = Object.values(methodBreakdown);
      new Chart(document.getElementById("trendsMethodChart"), {
        type: "doughnut",
        data: {
          labels: methodLabels,
          datasets: [{ data: methodData, backgroundColor: ["#0d6efd", "#198754", "#ffc107", "#dc3545", "#6c757d", "#0dcaf0", "#6f42c1"] }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } },
      });
    }
  },

  /**
   * Export payment trends to CSV
   */
  exportPaymentTrends: function () {
    const content = this.ui.paymentTrendsContent.querySelector("table");
    if (!content) {
      this.notify("No data to export.", "warning");
      return;
    }
    KingswayFileLifecycle.exportText(
      this.tableToCSV(content),
      `payment_trends_${new Date().toISOString().slice(0, 10)}.csv`,
      "text/csv;charset=utf-8;"
    );
  },

  tableToCSV: function (table) {
    const rows = Array.from(table.querySelectorAll("tr"));
    return rows.map((row) =>
      Array.from(row.querySelectorAll("th, td")).map((cell) => `"${cell.textContent.trim().replace(/"/g, '""')}"`).join(",")
    ).join("\n");
  },

  debounce: function (fn, delay) {
    let timer = null;
    return function (...args) {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  /**
   * The canonical academic-year value that every API in this workspace
   * accepts (id, "2026/2027" or "2026").
   *
   * This used to reduce "2026/2027" to "2026", which silently dropped the
   * year code: the fee ledger then matched on a prefix guess and the summary
   * echoed a year the user never selected. The full code is now passed
   * through unchanged.
   */
  normalizeAcademicYearValue: function (value) {
    if (value === null || value === undefined) {
      return "";
    }

    const text = String(value).trim();
    if (!text) {
      return "";
    }

    // A full year code is already canonical.
    if (/^\d{4}\s*[/-]\s*\d{4}$/.test(text)) {
      return text.replace(/\s+/g, "");
    }

    // A bare year still identifies the academic year that opens in it.
    if (/^\d{4}$/.test(text)) {
      return text;
    }

    return text;
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentFeesController.init(),
);

window.StudentFeesController = StudentFeesController;
