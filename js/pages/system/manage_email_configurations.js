(function () {
    "use strict";

    const EmailConfigController = {
        profiles: [],
        auditBcc: { email: "", enabled: true },
        editingId: null,
        modal: null,
        initialized: false,

        async init() {
            if (this.initialized) return;
            this.initialized = true;
            try {
                await window.AuthContext?.ready?.();
                if (!window.AuthContext?.isAuthenticated?.()) {
                    window.location.href = (window.APP_BASE || "") + "/index.php";
                    return;
                }
            } catch (e) {
                console.warn("[EmailConfig] Auth init failed", e);
            }
            this.bind();
            this.applyExportGates();
            await this.load();
        },

        bind() {
            this.on("emailRefresh", "click", () => this.load());
            this.on("emailNewProfile", "click", () => this.openModal());
            this.on("emailExportCsv", "click", () => this.exportCsv());
            this.on("emailPrint", "click", () => window.print());
            this.on("emailTestBtn", "click", () => this.testGlobalSmtp());
            this.on("auditBccSaveBtn", "click", () => this.saveAuditBcc());
            this.on("emailProfileTestSmtpBtn", "click", () => this.testProfileSmtp(this.editingId));
            this.on("emailProfileTestImapBtn", "click", () => this.testProfileImap(this.editingId));

            const form = document.getElementById("emailProfileForm");
            if (form) {
                form.addEventListener("submit", (event) => {
                    event.preventDefault();
                    this.saveProfile(false);
                });
            }

            const body = document.getElementById("emailProfilesBody");
            if (body) {
                body.addEventListener("click", (event) => this.onRowAction(event));
            }
        },

        on(id, evt, fn) {
            const el = document.getElementById(id);
            if (el) el.addEventListener(evt, fn);
        },

        applyExportGates() {
            if (!window.AuthContext?.canExport) return;
            const csv = document.getElementById("emailExportCsv");
            if (csv && AuthContext.canExport("system") === false) csv.disabled = true;
            const print = document.getElementById("emailPrint");
            if (print && AuthContext.canPrint?.("system") === false) print.disabled = true;
        },

        onRowAction(event) {
            const button = event.target.closest("[data-action]");
            if (!button) return;
            const id = Number(button.dataset.id);
            if (!id) return;
            const action = button.dataset.action;
            if (action === "view") this.openInbox(id);
            else if (action === "edit") this.openModal(id);
            else if (action === "set_default") this.setDefaultProfile(id);
            else if (action === "test_smtp") this.testProfileSmtp(id);
            else if (action === "test_imap") this.testProfileImap(id);
            else if (action === "delete") this.deleteProfile(id);
        },

        async load() {
            this.setState("Loading email profiles...", "info");
            this.setBody('<tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr>');
            try {
                const res = await window.API.system.getEmailProfiles();
                this.profiles = Array.isArray(res?.profiles) ? res.profiles : [];
                const bcc = res?.audit_bcc || {};
                this.auditBcc = { email: bcc.email || "", enabled: bcc.enabled !== false };
                this.render();
                this.setAuditBccForm();
                this.setState("Email profiles loaded.", "success");
            } catch (e) {
                console.error("[EmailConfig] load failed", e);
                this.setState("Failed to load email profiles.", "danger");
                this.setBody('<tr><td colspan="7" class="text-danger text-center py-3">Load failed.</td></tr>');
            }
        },

        render() {
            const body = document.getElementById("emailProfilesBody");
            if (!body) return;
            if (!this.profiles.length) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No email mailbox profiles configured yet.</td></tr>';
                return;
            }
            body.innerHTML = this.profiles.map((p) => this.rowHtml(p)).join("");
        },

        rowHtml(p) {
            const id = Number(p.id);
            const active = Number(p.is_active) === 1;
            const def = Number(p.is_default) === 1;
            const roleIds = Array.isArray(p.assigned_role_ids) ? p.assigned_role_ids : [];
            const rolesBadge = roleIds.length
                ? `<span class="badge bg-primary" title="${this.esc(roleIds.join(", "))}">${roleIds.length} role${roleIds.length > 1 ? "s" : ""}</span>`
                : '<span class="badge bg-secondary">All staff</span>';
            const statusBadge = active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            const defaultBadge = def
                ? '<span class="badge bg-warning text-dark">★ Default</span>'
                : '<span class="text-muted">—</span>';
            const defReset = def ? "" : `<button class="btn btn-sm btn-outline-warning" data-action="set_default" data-id="${id}" title="Set as default"><i class="bi bi-star"></i></button>`;
            const actions = [
                `<button class="btn btn-sm btn-outline-primary" data-action="view" data-id="${id}" title="View inbox"><i class="bi bi-envelope-open"></i></button>`,
                `<button class="btn btn-sm btn-outline-success" data-action="edit" data-id="${id}" title="Edit mailbox"><i class="bi bi-pencil"></i></button>`,
                defReset,
                `<button class="btn btn-sm btn-outline-info" data-action="test_smtp" data-id="${id}" title="Test SMTP"><i class="bi bi-lightning"></i></button>`,
                `<button class="btn btn-sm btn-outline-info" data-action="test_imap" data-id="${id}" title="Test IMAP"><i class="bi bi-inbox"></i></button>`,
                `<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${id}" title="Delete mailbox"><i class="bi bi-trash"></i></button>`,
            ].join(" ");
            return `<tr data-id="${id}">
                <td class="align-middle"><strong>${this.esc(p.label)}</strong></td>
                <td class="align-middle"><a href="mailto:${this.esc(p.email_address)}">${this.esc(p.email_address)}</a></td>
                <td class="align-middle">${this.esc(p.display_name)}</td>
                <td class="align-middle">${statusBadge}</td>
                <td class="align-middle">${defaultBadge}</td>
                <td class="align-middle">${rolesBadge}</td>
                <td class="align-middle"><div class="d-flex flex-wrap gap-1">${actions}</div></td>
            </tr>`;
        },

        async openModal(id) {
            let profile = null;
            if (id) {
                try {
                    profile = await window.API.system.getEmailProfile(id);
                } catch (e) {
                    this.notify(e?.message || "Failed to load the email mailbox.", "error");
                    return;
                }
            }
            this.editingId = profile ? Number(profile.id) : null;
            this.populateModal(profile);
        },

        populateModal(profile) {
            const form = document.getElementById("emailProfileForm");
            if (form) form.reset();
            const editing = Boolean(profile);
            document.getElementById("emailProfileId").value = editing ? profile.id : "";
            document.getElementById("emailProfileModalTitle").textContent = editing ? "Edit Email Mailbox" : "New Email Mailbox";
            document.getElementById("emailProfileLabel").value = editing ? profile.label || "" : "";
            document.getElementById("emailProfileAddress").value = editing ? profile.email_address || "" : "";
            document.getElementById("emailProfileDisplayName").value = editing ? profile.display_name || "" : "";
            document.getElementById("emailProfileSmtpHost").value = editing ? profile.smtp_host || "" : "";
            document.getElementById("emailProfileSmtpPort").value = editing ? profile.smtp_port || 587 : 587;
            document.getElementById("emailProfileSmtpUsername").value = editing ? profile.smtp_username || "" : "";
            document.getElementById("emailProfileImapHost").value = editing ? profile.imap_host || "" : "";
            document.getElementById("emailProfileImapPort").value = editing ? profile.imap_port || 993 : 993;
            document.getElementById("emailProfileImapUsername").value = editing ? profile.imap_username || "" : "";
            document.getElementById("emailProfileActive").value = editing ? (Number(profile.is_active) === 1 ? "1" : "0") : "1";
            document.getElementById("emailProfileDefault").value = editing ? (Number(profile.is_default) === 1 ? "1" : "0") : "0";
            const roles = Array.isArray(profile?.assigned_role_ids) ? profile.assigned_role_ids : [];
            document.getElementById("emailProfileRoles").value = roles.join(", ");

            const smtpPassword = document.getElementById("emailProfileSmtpPassword");
            const imapPassword = document.getElementById("emailProfileImapPassword");
            smtpPassword.value = "";
            imapPassword.value = "";
            smtpPassword.placeholder = editing ? "Leave blank to keep current" : "Mailbox password";
            imapPassword.placeholder = editing ? "Leave blank to keep current" : "Leave blank = use SMTP password";
            smtpPassword.required = !editing;
            imapPassword.required = false;

            const testResult = document.getElementById("emailProfileTestResult");
            if (testResult) testResult.innerHTML = "";

            const modalEl = document.getElementById("emailProfileModal");
            if (!this.modal || !this.modal._isShown) {
                this.modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            this.modal.show();
        },

        async saveProfile(silent) {
            const form = document.getElementById("emailProfileForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return null;
            }
            const id = this.editingId;
            const val = (el) => document.getElementById(el)?.value ?? "";
            const smtpPassword = val("emailProfileSmtpPassword");
            const imapPassword = val("emailProfileImapPassword");

            const data = {
                label: val("emailProfileLabel").trim(),
                email_address: val("emailProfileAddress").trim(),
                display_name: val("emailProfileDisplayName").trim(),
                smtp_host: val("emailProfileSmtpHost").trim(),
                smtp_port: parseInt(val("emailProfileSmtpPort"), 10) || 587,
                smtp_username: val("emailProfileSmtpUsername").trim(),
                imap_host: val("emailProfileImapHost").trim(),
                imap_port: parseInt(val("emailProfileImapPort"), 10) || 993,
                imap_username: val("emailProfileImapUsername").trim(),
                is_active: Number(val("emailProfileActive")) || 0,
                is_default: Number(val("emailProfileDefault")) || 0,
            };

            if (smtpPassword !== "") data.smtp_password = smtpPassword;
            if (imapPassword !== "") data.imap_password = imapPassword;

            const rolesRaw = val("emailProfileRoles");
            const roleIds = rolesRaw.trim()
                ? rolesRaw.split(",").map((v) => parseInt(v.trim(), 10)).filter((v) => Number.isInteger(v) && v > 0)
                : null;
            data.assigned_role_ids = roleIds;

            try {
                const saved = id
                    ? await window.API.system.updateEmailProfile(id, data)
                    : await window.API.system.createEmailProfile(data);
                if (silent) {
                    this.editingId = saved && saved.id ? Number(saved.id) : null;
                    document.getElementById("emailProfileId").value = this.editingId || "";
                    return saved;
                }
                this.editingId = null;
                document.getElementById("emailProfileId").value = "";
                this.modal?.hide();
                this.notify(id ? "Email mailbox updated." : "Email mailbox created.", "success");
                await this.load();
                return saved;
            } catch (e) {
                console.error("[EmailConfig] saveProfile failed", e);
                if (!silent) this.notify(e?.message || "Failed to save email mailbox.", "error");
                return null;
            }
        },

        async deleteProfile(id) {
            const confirmFn = window.confirmAction || window.confirm;
            const confirmed = typeof window.confirmAction === "function"
                ? await window.confirmAction("Delete email mailbox", "Delete this mailbox profile? Credentials will be removed and outbound email will no longer use it.", { confirmText: "Delete", danger: true })
                : window.confirm("Delete this mailbox profile? This action cannot be undone.");
            if (!confirmed) return;
            try {
                await window.API.system.deleteEmailProfile(id);
                this.notify("Email mailbox deleted.", "success");
                await this.load();
            } catch (e) {
                console.error("[EmailConfig] deleteProfile failed", e);
                this.notify(e?.message || "Failed to delete email mailbox.", "error");
            }
        },

        async setDefaultProfile(id) {
            try {
                await window.API.system.setDefaultEmailProfile(id);
                this.notify("Default email mailbox updated.", "success");
                await this.load();
            } catch (e) {
                console.error("[EmailConfig] setDefaultProfile failed", e);
                this.notify(e?.message || "Failed to set the default email mailbox.", "error");
            }
        },

        openInbox(id) {
            const url = (window.APP_BASE || "") + "/home.php?route=manage_system_inbox&profile_id=" + encodeURIComponent(id);
            window.open(url, "_blank", "noopener");
        },

        async testProfileSmtp(id) {
            await this.runProfileTest(id, "smtp");
        },

        async testProfileImap(id) {
            await this.runProfileTest(id, "imap");
        },

        async runProfileTest(id, kind) {
            const result = document.getElementById("emailProfileTestResult");
            const label = kind.toUpperCase();
            if (!id) {
                const saved = await this.saveProfile(true);
                if (!saved || !saved.id) {
                    if (result) result.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Save the mailbox before testing.</span>';
                    return;
                }
                id = saved.id;
            }
            if (result) result.innerHTML = `<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Testing ${label}...</span>`;
            try {
                const res = kind === "smtp"
                    ? await window.API.system.testSmtpProfile(id)
                    : await window.API.system.testImapProfile(id);
                if (!result) return;
                if (res?.success) {
                    let extra = "";
                    if (kind === "imap" && Array.isArray(res.folders) && res.folders.length) {
                        extra = " Folders: " + this.esc(res.folders.slice(0, 8).join(", ")) + (res.folders.length > 8 ? "…" : "");
                    }
                    result.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${this.esc(res.message || "Connection successful.")}${extra}</span>`;
                } else {
                    result.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(res?.message || `${label} test failed.`)}</span>`;
                }
            } catch (e) {
                if (result) result.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(e?.message || `${label} test failed.`)}</span>`;
            }
        },

        async testGlobalSmtp() {
            const result = document.getElementById("emailTestResult");
            if (result) result.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Testing global SMTP...</span>';
            try {
                const res = await window.API.system.testCommunicationConfig("email");
                if (!result) return;
                result.innerHTML = res?.success
                    ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${this.esc(res.message || "Test passed.")}</span>`
                    : `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(res.message || "Test failed.")}</span>`;
            } catch (e) {
                if (result) result.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(e?.message || "Test failed.")}</span>`;
            }
        },

        async saveAuditBcc() {
            const result = document.getElementById("auditBccResult");
            const email = document.getElementById("auditBccEmail")?.value.trim() || "";
            const enabled = document.getElementById("auditBccEnabled")?.checked === true;
            if (!email) {
                if (result) result.textContent = "Audit BCC email is required.";
                return;
            }
            try {
                const saved = await window.API.system.saveAuditBcc(email, enabled);
                this.auditBcc = { email: saved?.email || email, enabled: saved?.enabled !== false };
                if (result) result.textContent = "Audit BCC saved.";
                this.notify("Audit BCC updated.", "success");
            } catch (e) {
                console.error("[EmailConfig] saveAuditBcc failed", e);
                if (result) result.textContent = "Failed to save Audit BCC.";
                this.notify(e?.message || "Failed to save Audit BCC.", "error");
            }
        },

        setAuditBccForm() {
            const email = document.getElementById("auditBccEmail");
            const enabled = document.getElementById("auditBccEnabled");
            if (email) email.value = this.auditBcc.email || "";
            if (enabled) enabled.checked = this.auditBcc.enabled !== false;
        },

        exportCsv() {
            if (!window.KingswayFileLifecycle?.exportText) {
                this.notify("CSV export is unavailable.", "error");
                return;
            }
            const headers = ["Label", "Email Address", "Display Name", "Status", "Default", "Role IDs"];
            const rows = this.profiles.map((p) => [
                p.label,
                p.email_address,
                p.display_name,
                Number(p.is_active) === 1 ? "Active" : "Inactive",
                Number(p.is_default) === 1 ? "Yes" : "No",
                (Array.isArray(p.assigned_role_ids) ? p.assigned_role_ids : []).join(", "),
            ]);
            const csv = [headers, ...rows]
                .map((line) => line.map((v) => {
                    const text = String(v ?? "");
                    return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
                }).join(","))
                .join("\r\n");
            window.KingswayFileLifecycle.exportText(csv, "email-profiles.csv", "text/csv");
        },

        setState(message, type) {
            const state = document.getElementById("emailState");
            if (!state) return;
            state.className = "alert alert-" + (type || "info");
            const icon = type === "danger"
                ? "exclamation-triangle"
                : type === "success"
                    ? "check-circle"
                    : "hourglass-split";
            state.innerHTML = `<i class="bi bi-${icon} me-1"></i>${this.esc(message)}`;
        },

        setBody(html) {
            const body = document.getElementById("emailProfilesBody");
            if (body) body.innerHTML = html;
        },

        notify(message, type) {
            if (typeof window.showNotification === "function") {
                window.showNotification(message, type);
            } else {
                console.warn("[EmailConfig]", type, message);
            }
        },

        esc(value) {
            const div = document.createElement("div");
            div.textContent = String(value ?? "");
            return div.innerHTML;
        },
    };

    window.EmailConfigController = EmailConfigController;
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => EmailConfigController.init());
    } else {
        EmailConfigController.init();
    }
})();