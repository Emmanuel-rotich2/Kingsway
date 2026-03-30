/**
 * System Settings Controller
 * Loads and saves system-wide configuration via /system/school-config
 */
(function () {
    "use strict";

    function showToast(msg, type = "success") {
        const el = document.createElement("div");
        el.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible position-fixed top-0 end-0 m-3`;
        el.style.zIndex = "9999";
        el.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    // Config key → element ID mappings
    const FIELD_MAP = {
        school_name:         "sysSchoolName",
        school_code:         "sysSchoolCode",
        email:               "sysEmail",
        phone:               "sysPhone",
        address:             "sysAddress",
        headteacher_name:    "sysHeadteacher",
        logo_url:            "sysLogoUrl",
        academic_year:       "sysAcademicYear",
        terms_per_year:      "sysTermsPerYear",
        pass_mark:           "sysPassMark",
        curriculum:          "sysCurriculum",
        grading_scale:       "sysGradingScale",
        currency:            "sysCurrency",
        tax_rate:            "sysTaxRate",
        late_fee_penalty:    "sysLateFee",
        bank_account:        "sysBankAccount",
        bank_name:           "sysBankName",
        mpesa_paybill:       "sysMpesaPaybill",
        mpesa_account:       "sysMpesaAccount",
        admin_email:         "sysAdminEmail",
        session_timeout:     "sysSessionTimeout",
        max_login_attempts:  "sysMaxLoginAttempts",
        lockout_duration:    "sysLockoutDuration",
        password_min_length: "sysPasswordMinLen",
    };
    const CHECKBOX_MAP = {
        enable_sms:       "sysEnableSMS",
        sms_on_payment:   "sysSmsOnPayment",
        sms_on_absence:   "sysSmsOnAbsence",
        enable_email:     "sysEnableEmail",
        require_2fa:      "sysRequire2FA",
        log_all_activity: "sysLogAllActivity",
    };

    const Controller = {
        init: async function () {
            if (!AuthContext.isAuthenticated()) {
                window.location.href = "/Kingsway/index.php";
                return;
            }
            await this.loadSettings();
            this.bindForms();
        },

        loadSettings: async function () {
            try {
                const res = await window.API.system.getSchoolConfig();
                const data = res?.data || res?.config || res || {};
                this.populateFields(data);
                const ts = document.getElementById("settingsLastSaved");
                if (ts && data.updated_at) {
                    ts.textContent = "Last saved: " + new Date(data.updated_at).toLocaleString();
                }
            } catch (e) {
                console.error("Failed to load settings:", e);
            }
        },

        populateFields: function (data) {
            for (const [key, elId] of Object.entries(FIELD_MAP)) {
                const el = document.getElementById(elId);
                if (el && data[key] != null) el.value = data[key];
            }
            for (const [key, elId] of Object.entries(CHECKBOX_MAP)) {
                const el = document.getElementById(elId);
                if (el && data[key] != null) el.checked = !!data[key];
            }
        },

        collectFields: function (fieldIds, checkboxIds = []) {
            const payload = {};
            for (const elId of fieldIds) {
                const el = document.getElementById(elId);
                if (!el) continue;
                const key = Object.keys(FIELD_MAP).find(k => FIELD_MAP[k] === elId);
                if (key) payload[key] = el.value;
            }
            for (const elId of checkboxIds) {
                const el = document.getElementById(elId);
                if (!el) continue;
                const key = Object.keys(CHECKBOX_MAP).find(k => CHECKBOX_MAP[k] === elId);
                if (key) payload[key] = el.checked ? 1 : 0;
            }
            return payload;
        },

        save: async function (payload, btnId) {
            const btn = document.getElementById(btnId);
            const origText = btn ? btn.innerHTML : "";
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
            }
            try {
                const res = await window.API.system.updateSchoolConfig(payload);
                if (res?.status === "success" || res?.success) {
                    showToast("Settings saved.", "success");
                    const ts = document.getElementById("settingsLastSaved");
                    if (ts) ts.textContent = "Last saved: " + new Date().toLocaleString();
                } else {
                    showToast(res?.message || "Failed to save.", "error");
                }
            } catch (e) {
                showToast("Error saving settings.", "error");
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        },

        bindForms: function () {
            const self = this;

            const bindings = [
                {
                    formId: "sysGeneralForm", btnId: "saveGeneralBtn",
                    fields: ["sysSchoolName","sysSchoolCode","sysEmail","sysPhone","sysAddress","sysHeadteacher","sysLogoUrl"],
                    checkboxes: []
                },
                {
                    formId: "sysAcademicForm", btnId: "saveAcademicBtn",
                    fields: ["sysAcademicYear","sysTermsPerYear","sysPassMark","sysCurriculum","sysGradingScale"],
                    checkboxes: []
                },
                {
                    formId: "sysFinanceForm", btnId: "saveFinanceBtn",
                    fields: ["sysCurrency","sysTaxRate","sysLateFee","sysBankAccount","sysBankName","sysMpesaPaybill","sysMpesaAccount"],
                    checkboxes: []
                },
                {
                    formId: "sysNotificationsForm", btnId: "saveNotificationsBtn",
                    fields: ["sysAdminEmail"],
                    checkboxes: ["sysEnableSMS","sysSmsOnPayment","sysSmsOnAbsence","sysEnableEmail"]
                },
                {
                    formId: "sysSecurityForm", btnId: "saveSecurityBtn",
                    fields: ["sysSessionTimeout","sysMaxLoginAttempts","sysLockoutDuration","sysPasswordMinLen"],
                    checkboxes: ["sysRequire2FA","sysLogAllActivity"]
                },
            ];

            for (const { formId, btnId, fields, checkboxes } of bindings) {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener("submit", function (e) {
                        e.preventDefault();
                        self.save(self.collectFields(fields, checkboxes), btnId);
                    });
                }
            }
        }
    };

    document.addEventListener("DOMContentLoaded", () => Controller.init());
    window.SystemSettingsController = Controller;
})();
