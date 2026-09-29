(function () {
  "use strict";
  const state = { invitations: [], loading: false };
  const el = (id) => document.getElementById(id);
  const rows = (response) => response?.data || response || {};
  const escapeHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[c]);
  const notify = (message, type = "info") => {
    const box = el("bootstrapAlert");
    box.className = `alert alert-${type}`;
    box.textContent = message;
    box.classList.remove("d-none");
  };

  const dateTime = (value) => {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  };

  function renderInvitations(items) {
    state.invitations = Array.isArray(items) ? items : [];
    el("schoolAdminInvitationCount").textContent = `${state.invitations.length} account/invitation record(s)`;
    if (!state.invitations.length) {
      el("schoolAdminInvitationsBody").innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No School Administrator accounts or invitations yet.</td></tr>';
      return;
    }
    const badge = (status, palette) => `<span class="badge text-bg-${palette}">${escapeHtml(status)}</span>`;
    const invitePalette = { pending: "warning", accepted: "success", expired: "secondary", revoked: "danger", not_sent: "dark" };
    const accountPalette = { active: "success", pending: "warning", inactive: "secondary", suspended: "danger", locked: "danger" };
    el("schoolAdminInvitationsBody").innerHTML = state.invitations.map((item) => {
      const invite = item.invitation_status || "not_sent";
      const profileComplete = Number(item.profile_completed) === 1;
      const account = profileComplete
        ? (item.user_status || "active")
        : invite === "accepted" && Number(item.setup_required) !== 1
          ? "password set · verification/profile pending"
          : "user created · setup incomplete";
      const inviteLabel = invite.replaceAll("_", " ");
      const accountLabel = account.replaceAll("_", " ");
      const delivery = item.email_delivery_status || "not queued";
      const canManage = Number(item.setup_required) === 1 && !profileComplete;
      const canResendOtp = invite === "accepted" && Number(item.setup_required) !== 1 && !profileComplete;
      const actions = canManage || canResendOtp ? `<div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for ${escapeHtml(item.full_name || "administrator")}"><i class="bi bi-three-dots-vertical"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          ${canManage ? `<li><button class="dropdown-item" type="button" data-invitation-action="resend" data-user-id="${Number(item.user_id)}"><i class="bi bi-envelope-arrow-up me-2"></i>Resend invitation</button></li>` : ""}
          ${canResendOtp ? `<li><button class="dropdown-item" type="button" data-invitation-action="resend_otp" data-user-id="${Number(item.user_id)}"><i class="bi bi-shield-check me-2"></i>Resend verification code</button></li>` : ""}
          ${invite === "pending" ? `<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-invitation-action="cancel" data-user-id="${Number(item.user_id)}"><i class="bi bi-x-circle me-2"></i>Cancel invitation</button></li>` : ""}
        </ul>
      </div>` : "—";
      return `<tr>
        <td><strong>${escapeHtml(item.full_name || "Unnamed account")}</strong><br><span class="small text-muted">${escapeHtml(item.email || "—")}</span></td>
        <td>${escapeHtml(item.username || "—")}</td>
        <td>${escapeHtml(item.department_name || "Not assigned")}</td>
        <td>${badge(inviteLabel, invitePalette[invite] || "secondary")}<br><span class="small text-muted">Email: ${escapeHtml(delivery)}</span></td>
        <td>${badge(accountLabel, profileComplete ? (accountPalette[item.user_status] || "secondary") : "warning")}</td>
        <td>${escapeHtml(dateTime(item.invitation_sent_at))}</td>
        <td>${escapeHtml(dateTime(item.expires_at))}</td>
        <td>${escapeHtml(dateTime(item.account_created_at))}</td>
        <td class="no-print">${actions}</td>
      </tr>`;
    }).join("");
  }

  function populateEmploymentReferences(response) {
    const supervisorSelect = el("bootstrapSupervisor");
    const previousSupervisor = supervisorSelect.value;
    supervisorSelect.innerHTML = '<option value="">No supervisor assigned</option>' +
      (response.supervisors || []).map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name)} — ${escapeHtml(item.staff_no)}</option>`).join("");
    if ([...supervisorSelect.options].some((option) => option.value === previousSupervisor)) supervisorSelect.value = previousSupervisor;

    const typeSelect = el("bootstrapStaffType");
    const previousType = typeSelect.value;
    const adminTypes = (response.staff_types || []).filter((item) => String(item.name || "").trim().toLowerCase() === "administration");
    typeSelect.innerHTML = '<option value="">Select staff type</option>' + adminTypes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name)}</option>`).join("");
    if ([...typeSelect.options].some((option) => option.value === previousType)) typeSelect.value = previousType;
    const categorySelect = el("bootstrapStaffCategory");
    const previousCategory = categorySelect.value;
    const populateCategories = () => {
      const typeId = Number(typeSelect.value || 0);
      const categories = (response.staff_categories || []).filter((item) => Number(item.staff_type_id) === typeId);
      categorySelect.innerHTML = '<option value="">Select category</option>' + categories.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name)}</option>`).join("");
      if ([...categorySelect.options].some((option) => option.value === previousCategory)) categorySelect.value = previousCategory;
    };
    populateCategories();
    typeSelect.onchange = populateCategories;
  }

  async function refreshInvitations(showMessage = false) {
    if (state.loading) return false;
    state.loading = true;
    el("refreshSchoolAdminInvitations").disabled = true;
    try {
      const response = rows(await window.API.staff.getSchoolAdministratorBootstrap());
      el("bootstrapDepartment").innerHTML = '<option value="">Select department</option>' +
        (response.departments || []).map((department) => `<option value="${Number(department.id)}">${escapeHtml(department.name)}</option>`).join("");
      populateEmploymentReferences(response);
      el("bootstrapAvailability").textContent = `${Number(response.administrator_count || 0)} administrators`;
      renderInvitations(response.invitations || []);
      if (showMessage) notify("Invitation history refreshed.", "success");
      return true;
    } catch (error) {
      el("schoolAdminInvitationsBody").innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Invitation history could not be loaded.</td></tr>';
      if (showMessage) notify(error?.message || "Could not refresh invitation history.", "danger");
      return false;
    } finally {
      state.loading = false;
      el("refreshSchoolAdminInvitations").disabled = false;
    }
  }

  function exportInvitations() {
    if (typeof window.AuthContext?.canExport === "function" && !window.AuthContext.canExport("users")) return;
    const header = ["Name", "Email", "Username", "Department", "Invitation status", "Account status", "Invitation sent", "Expires", "Account created"];
    const rows = state.invitations.map((item) => [
      item.full_name, item.email, item.username, item.department_name,
      item.invitation_status || "not_sent", Number(item.profile_completed) === 1 ? item.user_status : "user created / setup incomplete",
      item.invitation_sent_at, item.expires_at, item.account_created_at,
    ]);
    const csv = [header, ...rows].map((row) => row.map((value) => `"${String(value ?? "").replace(/"/g, '""')}"`).join(",")).join("\r\n");
    const filename = `school_administrator_invitations_${new Date().toISOString().slice(0, 10)}.csv`;
    if (window.KingswayFileLifecycle?.exportText) {
      window.KingswayFileLifecycle.exportText(csv, filename, "text/csv");
      return;
    }
    const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  async function load() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.hasRole?.("System Administrator")) {
      notify("Only a System Administrator may invite School Administrators.", "danger");
      return;
    }
    el("schoolAdminBootstrapForm").classList.remove("d-none");
    el("bootstrapAvailability").className = "badge text-bg-primary px-3 py-2";
    el("bootstrapAvailability").textContent = "Invitations available";
    if (await refreshInvitations()) notify("Create another School Administrator invitation at any time.", "info");
  }

  async function submit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = el("bootstrapSubmit");
    if (!form.reportValidity() || button.disabled) return;
    const data = Object.fromEntries(new FormData(form).entries());
    const confirmed = await window.confirmAction(
      "Invite School Administrator",
      `Create a School Administrator account and send a 72-hour setup link to ${data.email}?`,
      { confirmText: "Create account and send invitation" },
    );
    if (!confirmed) return;
    button.disabled = true;
    notify("Creating the account and sending the secure invitation…", "info");
    try {
      const result = rows(await window.API.staff.bootstrapSchoolAdministrator(data));
      form.reset();
      notify(result.email_sent
        ? `Invitation email sent to ${result.email}. The administrator must set a password and complete their profile before accessing the dashboard.`
        : `The account was created, but its invitation email is queued for delivery to ${result.email}. Check the invitation status below.`, result.email_sent ? "success" : "warning");
      await refreshInvitations();
    } catch (error) {
      notify(error?.message || "The invitation could not be created.", "danger");
    } finally {
      button.disabled = false;
    }
  }

  async function handleInvitationAction(event) {
    const button = event.target.closest("[data-invitation-action]");
    if (!button || button.disabled) return;
    const userId = Number(button.dataset.userId);
    const action = button.dataset.invitationAction;
    if (!Number.isInteger(userId) || userId < 1) return;
    if (action === "cancel") {
      const confirmed = await window.confirmAction(
        "Cancel invitation",
        "Revoke this setup link? The invited administrator will no longer be able to use it.",
        { confirmText: "Cancel invitation", danger: true },
      );
      if (!confirmed) return;
    }
    button.disabled = true;
    try {
      const response = rows(await window.API.staff.manageSchoolAdministratorInvitation(userId, action));
      notify(response.email_sent
        ? action === "resend_otp" ? "A new setup verification code was sent." : "A new invitation email was sent."
        : action === "cancel" ? "The invitation was cancelled." : action === "resend_otp" ? "The verification code could not be sent." : "A new invitation was queued for delivery.", response.email_sent === false && action === "resend_otp" ? "danger" : "success");
      await refreshInvitations();
    } catch (error) {
      notify(error?.message || `Could not ${action} the invitation.`, "danger");
      button.disabled = false;
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    el("schoolAdminBootstrapForm")?.addEventListener("submit", submit);
    el("refreshSchoolAdminInvitations")?.addEventListener("click", () => refreshInvitations(true));
    el("exportSchoolAdminInvitations")?.addEventListener("click", exportInvitations);
    el("printSchoolAdminInvitations")?.addEventListener("click", () => {
      if (typeof window.AuthContext?.canPrint === "function" && !window.AuthContext.canPrint("users")) return;
      window.print();
    });
    el("schoolAdminInvitationsBody")?.addEventListener("click", handleInvitationAction);
    if (typeof window.AuthContext?.canExport === "function") el("exportSchoolAdminInvitations").hidden = !window.AuthContext.canExport("users");
    if (typeof window.AuthContext?.canPrint === "function") el("printSchoolAdminInvitations").hidden = !window.AuthContext.canPrint("users");
    load();
  });
})();
