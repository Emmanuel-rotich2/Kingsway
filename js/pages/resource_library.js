/**
 * Learning resource library — teaching materials or past papers.
 * The server decides what each viewer sees (published items plus their own;
 * everything for the School Administrator) and who may publish. Files open
 * through short-lived signed links from academic/resources-open.
 */
(() => {
  "use strict";
  const root = document.getElementById("resourceLibrary");
  if (!root) return;
  const kind = root.dataset.kind === "past_paper" ? "past_paper" : "material";
  const isPaper = kind === "past_paper";
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const notify = (m, t = "info") => window.showNotification?.(m, t);
  const GRADES = ["PlayGroup", "PP1", "PP2", ...Array.from({ length: 9 }, (_, i) => `Grade ${i + 1}`)];
  const ICONS = { document: "bi-file-earmark-pdf", presentation: "bi-file-earmark-slides", spreadsheet: "bi-file-earmark-spreadsheet", text: "bi-file-earmark-text", image: "bi-file-earmark-image", video: "bi-camera-video", audio: "bi-music-note-beamed", link: "bi-link-45deg", archive: "bi-file-earmark-zip" };
  const EXAM = { opener: "Opener", mid_term: "Mid-term", end_term: "End-term", mock: "Mock", kpsea: "KPSEA", kjsea: "KJSEA", keya: "KEYA", internal: "Internal", other: "Other", kcpe: "KCPE", kcse: "KCSE" };
  const state = { items: [], canPublish: false, areas: [], timer: null };

  const size = (b) => (!b ? "" : b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);
  const statusBadge = (r) => r.status === "published"
    ? `<span class="badge bg-success">Published</span> <span class="badge ${r.access_scope === "public" ? "bg-info text-dark" : "bg-light text-dark border"}">${r.access_scope === "public" ? "Public" : "Internal"}</span>`
    : r.status === "rejected" ? '<span class="badge bg-danger">Returned</span>' : '<span class="badge bg-warning text-dark">Awaiting publishing</span>';

  function params() {
    const p = { kind };
    const v = (id) => $(id)?.value || "";
    if (v("rlStatus")) p.status = v("rlStatus");
    if (v("rlScope")) p.access_scope = v("rlScope");
    if (v("rlGrade")) p.grade_level = v("rlGrade");
    if (v("rlArea")) p.learning_area_id = v("rlArea");
    if (v("rlExamYear")) p.exam_year = v("rlExamYear");
    if (v("rlMedia")) p.media_kind = v("rlMedia");
    if (v("rlSearch").trim()) p.q = v("rlSearch").trim();
    return p;
  }

  async function load() {
    $("rlBody").innerHTML = '<tr><td class="text-center text-muted py-4">Loading…</td></tr>';
    try {
      const res = await window.API.apiCall("/academic/resources", "GET", null, params(), { checkPermission: false });
      const d = res?.data || res || {};
      state.items = Array.isArray(d.items) ? d.items : [];
      state.canPublish = !!d.can_publish;
      render();
    } catch (e) {
      $("rlBody").innerHTML = `<tr><td class="text-center text-danger py-4">${esc(e.message || "Resources could not be loaded.")}</td></tr>`;
    }
  }

  function render() {
    $("rlHead").innerHTML = isPaper
      ? "<th>Paper</th><th>Grade</th><th>Learning area</th><th>Year</th><th>Exam</th><th>Status</th><th>By</th><th class='d-print-none'>Actions</th>"
      : "<th>Material</th><th>Grade</th><th>Learning area</th><th>Type</th><th>Status</th><th>By</th><th>Opened</th><th class='d-print-none'>Actions</th>";
    if (!state.items.length) {
      $("rlBody").innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">Nothing here yet for these filters.</td></tr>`;
      return;
    }
    $("rlBody").innerHTML = state.items.map((r) => {
      const icon = ICONS[r.media_kind] || "bi-file-earmark";
      const title = `<i class="bi ${icon} me-1 text-primary"></i><strong>${esc(r.title)}</strong>${r.description ? `<div class="small text-muted">${esc(r.description).slice(0, 160)}</div>` : ""}${r.review_note ? `<div class="small text-danger-emphasis">Note: ${esc(r.review_note)}</div>` : ""}`;
      const actions = [
        `<button class="btn btn-outline-primary" data-act="open" data-id="${r.id}" title="Open"><i class="bi bi-box-arrow-up-right"></i></button>`,
        r.has_marking_scheme ? `<button class="btn btn-outline-secondary" data-act="scheme" data-id="${r.id}" title="Marking scheme"><i class="bi bi-check2-square"></i></button>` : "",
        r.can_publish ? `<button class="btn btn-outline-success" data-act="publish" data-id="${r.id}" title="Publish / return"><i class="bi bi-megaphone"></i></button>` : "",
        r.can_publish && r.status === "published" ? `<button class="btn btn-outline-warning" data-act="unpublish" data-id="${r.id}" title="Unpublish"><i class="bi bi-eye-slash"></i></button>` : "",
        r.can_edit ? `<button class="btn btn-outline-secondary" data-act="edit" data-id="${r.id}" title="Edit" data-year-write><i class="bi bi-pencil"></i></button>` : "",
        r.can_edit ? `<button class="btn btn-outline-danger" data-act="delete" data-id="${r.id}" title="Delete" data-year-write><i class="bi bi-trash"></i></button>` : "",
      ].join("");
      return isPaper
        ? `<tr><td>${title}</td><td>${esc(r.grade_level || "All")}</td><td>${esc(r.learning_area_name || "General")}</td><td>${esc(r.exam_year || "")}${r.term_number ? ` · T${r.term_number}` : ""}</td><td>${esc(EXAM[r.exam_type] || r.exam_type || "")}</td><td>${statusBadge(r)}</td><td class="small">${esc(r.uploaded_by_name || "")}</td><td class="d-print-none"><div class="btn-group btn-group-sm">${actions}</div></td></tr>`
        : `<tr><td>${title}</td><td>${esc(r.grade_level || "All")}</td><td>${esc(r.learning_area_name || "General")}</td><td class="small">${esc(r.media_kind || "")}${r.file_size ? `<div class="text-muted">${size(r.file_size)}</div>` : ""}</td><td>${statusBadge(r)}</td><td class="small">${esc(r.uploaded_by_name || "")}</td><td>${r.download_count}</td><td class="d-print-none"><div class="btn-group btn-group-sm">${actions}</div></td></tr>`;
    }).join("");
  }

  async function loadReferences() {
    const gradeOpts = GRADES.map((g) => `<option value="${esc(g)}">${esc(g)}</option>`).join("");
    $("rlGrade").innerHTML = '<option value="">All grades</option>' + gradeOpts;
    $("rlFormGrade").innerHTML = '<option value="">All grades</option>' + gradeOpts;
    if ($("rlExamYear")) {
      const now = new Date().getFullYear();
      $("rlExamYear").innerHTML = '<option value="">All years</option>' + Array.from({ length: 12 }, (_, i) => now - i).map((y) => `<option>${y}</option>`).join("");
    }
    try {
      // Server-scoped: a teacher receives only the learning areas they teach.
      const res = await window.API.apiCall("/academic/learning-areas", "GET", null, {}, { checkPermission: false });
      const rows = Array.isArray(res) ? res : (res?.data || []);
      const seen = new Map();
      rows.forEach((a) => seen.set(String(a.id), `${a.name}${a.levels ? ` (${a.levels})` : ""}`));
      state.areas = [...seen].sort((a, b) => a[1].localeCompare(b[1]));
      const opts = state.areas.map(([id, n]) => `<option value="${esc(id)}">${esc(n)}</option>`).join("");
      $("rlArea").innerHTML = '<option value="">All learning areas</option>' + opts;
      $("rlFormArea").innerHTML = '<option value="">General (no learning area)</option>' + opts;
    } catch (_) { /* filters stay generic */ }
  }

  function setSource(src) {
    $("rlFileWrap").classList.toggle("d-none", src !== "file");
    $("rlLinkWrap").classList.toggle("d-none", src !== "link");
  }

  function openForm(item = null) {
    $("rlForm").reset();
    $("rlId").value = item ? item.id : "";
    $("rlFormState").textContent = "";
    $("rlModalTitle").textContent = item ? "Edit details" : (isPaper ? "Add past paper" : "Add teaching material");
    $("rlSourceWrap").classList.toggle("d-none", !!item);
    $("rlSchemeWrap")?.classList.toggle("d-none", !!item);
    setSource(item ? "none" : "file");
    if (item) {
      $("rlTitle").value = item.title || "";
      $("rlDescription").value = item.description || "";
      $("rlFormGrade").value = item.grade_level || "";
      $("rlFormArea").value = item.learning_area_id ? String(item.learning_area_id) : "";
      if (isPaper) {
        $("rlFormExamYear").value = item.exam_year || "";
        $("rlFormTerm").value = item.term_number || "";
        $("rlFormExamType").value = item.exam_type || "end_term";
      } else if (item.resource_type) {
        $("rlFormType").value = item.resource_type;
      }
    }
    bootstrap.Modal.getOrCreateInstance($("rlModal")).show();
  }

  async function save() {
    const id = Number($("rlId").value) || 0;
    const title = $("rlTitle").value.trim();
    if (!title) { notify("Give the resource a title.", "warning"); return; }
    const fields = {
      kind, title,
      description: $("rlDescription").value.trim(),
      grade_level: $("rlFormGrade").value,
      learning_area_id: $("rlFormArea").value,
    };
    if (isPaper) Object.assign(fields, { exam_year: $("rlFormExamYear").value, term_number: $("rlFormTerm").value, exam_type: $("rlFormExamType").value });
    else fields.resource_type = $("rlFormType").value;
    $("rlSave").disabled = true;
    try {
      if (id) {
        await window.API.apiCall(`/academic/resources/${id}`, "PUT", fields, {}, { checkPermission: false });
      } else {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v ?? ""));
        fd.append("academic_year_id", String(window.AcademicContext?.getAcademicYearId?.() || ""));
        if (document.querySelector('input[name="rlSource"]:checked')?.value === "link") {
          const link = $("rlLink").value.trim();
          if (!/^https?:\/\//i.test(link)) { notify("Enter a full link starting with http:// or https://.", "warning"); return; }
          fd.append("external_url", link);
        } else {
          const file = $("rlFile").files?.[0];
          if (!file) { notify("Choose the file to upload.", "warning"); return; }
          if (file.size > 40 * 1024 * 1024) { notify("The file is larger than 40 MB. For long videos add a YouTube or Drive link.", "warning"); return; }
          fd.append("file", file);
        }
        const scheme = $("rlScheme")?.files?.[0];
        if (scheme) fd.append("scheme_file", scheme);
        $("rlFormState").innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
        await window.API.apiCall("/academic/resources", "POST", fd, {}, { isFile: true, checkPermission: false });
      }
      bootstrap.Modal.getInstance($("rlModal"))?.hide();
      notify(id ? "Details updated." : "Uploaded. It will be visible once the School Administrator publishes it.", "success");
      await load();
    } catch (e) {
      $("rlFormState").textContent = "";
      notify(e.message || "The resource could not be saved.", "danger");
    } finally {
      $("rlSave").disabled = false;
    }
  }

  async function open(id, scheme = false) {
    try {
      const res = await window.API.apiCall("/academic/resources-open", "GET", null, { kind, id, scheme: scheme ? 1 : "" }, { checkPermission: false });
      const d = res?.data || res || {};
      if (d.url) window.open(d.url, "_blank", "noopener,noreferrer");
      setTimeout(load, 800);
    } catch (e) {
      notify(e.message || "This resource could not be opened.", "danger");
    }
  }

  async function decide(id, action, scope = "internal", note = "") {
    try {
      await window.API.apiCall("/academic/resources-decision", "POST", { kind, id, action, access_scope: scope, note }, {}, { checkPermission: false });
      notify(action === "publish" ? (scope === "public" ? "Published for staff and parents." : "Published for staff.") : action === "reject" ? "Returned to the uploader." : "Unpublished.", "success");
      bootstrap.Modal.getInstance($("rlPublishModal"))?.hide();
      await load();
    } catch (e) {
      notify(e.message || "The decision could not be saved.", "danger");
    }
  }

  async function remove(id) {
    if (!(await window.confirmAction("Delete resource", "Delete this resource and its file?", { confirmText: "Delete", danger: true }))) return;
    try {
      await window.API.apiCall(`/academic/resources/${id}`, "DELETE", null, { kind }, { checkPermission: false });
      notify("Deleted.", "success");
      await load();
    } catch (e) {
      notify(e.message || "The resource could not be deleted.", "danger");
    }
  }

  async function exportCsv() {
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const head = isPaper ? ["Title", "Grade", "Learning area", "Year", "Term", "Exam", "Status", "Audience", "Uploaded by"] : ["Title", "Grade", "Learning area", "Type", "Status", "Audience", "Uploaded by", "Times opened"];
    const lines = [head.join(",")].concat(state.items.map((r) => (isPaper
      ? [r.title, r.grade_level || "All", r.learning_area_name || "General", r.exam_year, r.term_number, EXAM[r.exam_type] || r.exam_type, r.status, r.access_scope, r.uploaded_by_name]
      : [r.title, r.grade_level || "All", r.learning_area_name || "General", r.media_kind, r.status, r.access_scope, r.uploaded_by_name, r.download_count]).map(cell).join(",")));
    try {
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, `${isPaper ? "past_papers" : "teaching_materials"}_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
    } catch (e) { notify(e.message || "Export failed", "danger"); }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    const roles = (window.AuthContext?.getRoles?.() || []).map((r) => String(r?.name || r).toLowerCase());
    const canUpload = roles.some((r) => /teacher|intern|administrator|headteacher|deputy head - academic|director/.test(r));
    $("rlAddBtn").classList.toggle("d-none", !canUpload);
    await loadReferences();
    ["rlStatus", "rlScope", "rlGrade", "rlArea", "rlExamYear", "rlMedia"].forEach((id) => $(id)?.addEventListener("change", load));
    $("rlSearch").addEventListener("input", () => { clearTimeout(state.timer); state.timer = setTimeout(load, 300); });
    document.querySelectorAll('input[name="rlSource"]').forEach((r) => r.addEventListener("change", () => setSource(r.value)));
    $("rlAddBtn").addEventListener("click", () => openForm());
    $("rlSave").addEventListener("click", save);
    $("rlExport").addEventListener("click", exportCsv);
    $("rlPrint").addEventListener("click", () => window.print());
    $("rlPublish").addEventListener("click", () => decide(Number($("rlPubId").value), "publish", document.querySelector('input[name="rlPubScope"]:checked')?.value || "internal", $("rlPubNote").value));
    $("rlReject").addEventListener("click", () => {
      const note = $("rlPubNote").value.trim();
      if (!note) { notify("Say what needs to change before returning it.", "warning"); return; }
      decide(Number($("rlPubId").value), "reject", "internal", note);
    });
    $("rlBody").addEventListener("click", (e) => {
      const b = e.target.closest("[data-act]");
      if (!b) return;
      const id = Number(b.dataset.id);
      const item = state.items.find((x) => x.id === id);
      if (b.dataset.act === "open") open(id);
      if (b.dataset.act === "scheme") open(id, true);
      if (b.dataset.act === "edit") openForm(item);
      if (b.dataset.act === "delete") remove(id);
      if (b.dataset.act === "unpublish") decide(id, "unpublish");
      if (b.dataset.act === "publish") {
        $("rlPubId").value = String(id);
        $("rlPubTitle").textContent = item?.title || "";
        $("rlPubNote").value = "";
        const scope = item?.access_scope === "public" ? "public" : "internal";
        document.querySelector(`input[name="rlPubScope"][value="${scope}"]`).checked = true;
        bootstrap.Modal.getOrCreateInstance($("rlPublishModal")).show();
      }
    });
    await load();
    if (root.dataset.openUpload === "1" && canUpload) openForm();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
