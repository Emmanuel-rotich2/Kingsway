const UnmatchedPaymentsController = (() => {
  let allData = [];
  let selectedPayment = null;

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await loadData();
    setupEventListeners();
  }

  function setupEventListeners() {
    document
      .getElementById("searchInput")
      ?.addEventListener("input", filterData);
    document
      .getElementById("sourceFilter")
      ?.addEventListener("change", filterData);
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", filterData);
    document.getElementById("dateFrom")?.addEventListener("change", filterData);
    document.getElementById("dateTo")?.addEventListener("change", filterData);
    document
      .getElementById("studentSearch")
      ?.addEventListener("input", debounce(searchStudents, 300));
  }

  function debounce(fn, ms) {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  }

  async function loadData() {
    try {
      const response = await window.API.apiCall(
        "/finance/unmatched-payments",
        "GET",
      );
      allData = response?.data || response || [];
      renderStats(allData);
      renderTable(Array.isArray(allData) ? allData : []);
    } catch (e) {
      console.error("Failed to load:", e);
      renderTable([]);
    }
  }

  function renderStats(data) {
    const items = Array.isArray(data) ? data : [];
    const unmatched = items.filter((i) => i.status === "unmatched");
    const pending = items.filter((i) => i.status === "pending");
    const today = new Date().toISOString().split("T")[0];
    const matchedToday = items.filter((i) => i.matched_date === today).length;
    const totalAmount = unmatched.reduce(
      (s, i) => s + (parseFloat(i.amount) || 0),
      0,
    );

    document.getElementById("statTotal").textContent = unmatched.length;
    document.getElementById("statAmount").textContent =
      "KES " + totalAmount.toLocaleString();
    document.getElementById("statMatchedToday").textContent = matchedToday;
    document.getElementById("statPending").textContent = pending.length;
  }

  function renderTable(items) {
    const tbody = document.querySelector("#dataTable tbody");
    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No unmatched payments found</td></tr>';
      return;
    }
    tbody.innerHTML = items
      .map(
        (item, i) => `
            <tr>
                <td>${i + 1}</td>
                <td><code>${escapeHtml(item.transaction_id || item.id)}</code></td>
                <td>${item.transaction_date ? new Date(item.transaction_date).toLocaleDateString() : "--"}</td>
                <td class="fw-bold">KES ${parseFloat(item.amount || 0).toLocaleString()}</td>
                <td><span class="badge bg-${item.source === "mpesa" ? "success" : "primary"}">${escapeHtml(item.source || "Unknown")}</span></td>
                <td>${escapeHtml(item.payer_name || "--")}</td>
                <td>${escapeHtml(item.reference || "--")}</td>
                <td><span class="badge bg-${item.status === "unmatched" ? "danger" : "warning"}">${escapeHtml(item.status || "unmatched")}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="UnmatchedPaymentsController.showMatchModal(${i})" title="Match">
                        <i class="fas fa-link"></i>
                    </button>
                </td>
            </tr>
        `,
      )
      .join("");
  }

  function filterData() {
    const search = (
      document.getElementById("searchInput")?.value || ""
    ).toLowerCase();
    const source = document.getElementById("sourceFilter")?.value || "";
    const status = document.getElementById("statusFilter")?.value || "";
    const dateFrom = document.getElementById("dateFrom")?.value || "";
    const dateTo = document.getElementById("dateTo")?.value || "";

    let filtered = allData.filter((item) => {
      if (search && !JSON.stringify(item).toLowerCase().includes(search))
        return false;
      if (source && item.source !== source) return false;
      if (status && item.status !== status) return false;
      if (dateFrom && item.transaction_date < dateFrom) return false;
      if (dateTo && item.transaction_date > dateTo) return false;
      return true;
    });
    renderTable(filtered);
  }

  function showMatchModal(index) {
    selectedPayment = allData[index];
    if (!selectedPayment) return;
    document.getElementById("matchTransactionInfo").innerHTML = `
            <strong>${escapeHtml(selectedPayment.transaction_id)}</strong> - KES ${parseFloat(selectedPayment.amount).toLocaleString()}<br>
            <small class="text-muted">From: ${escapeHtml(selectedPayment.payer_name || "Unknown")} | Ref: ${escapeHtml(selectedPayment.reference || "--")}</small>
        `;
    document.getElementById("studentSearch").value = "";
    document.getElementById("studentResults").innerHTML = "";
    document.getElementById("selectedStudentId").value = "";
    document.getElementById("matchNotes").value = "";
    const modal = new bootstrap.Modal(document.getElementById("matchModal"));
    modal.show();
  }

  async function searchStudents() {
    const query = document.getElementById("studentSearch")?.value || "";
    if (query.length < 2) {
      document.getElementById("studentResults").innerHTML = "";
      return;
    }
    try {
      const response = await window.API.apiCall(
        `/students?search=${encodeURIComponent(query)}&limit=5`,
        "GET",
      );
      const students = response?.data || response || [];
      document.getElementById("studentResults").innerHTML = students
        .map(
          (s) => `
                <button type="button" class="list-group-item list-group-item-action" onclick="UnmatchedPaymentsController.selectStudent('${s.id}', '${escapeHtml(s.first_name + " " + s.last_name)}')">
                    <strong>${escapeHtml(s.first_name + " " + s.last_name)}</strong>
                    <small class="text-muted ms-2">${escapeHtml(s.admission_number || "")}</small>
                </button>
            `,
        )
        .join("");
    } catch (e) {
      console.error("Student search failed:", e);
    }
  }

  function selectStudent(id, name) {
    document.getElementById("selectedStudentId").value = id;
    document.getElementById("studentSearch").value = name;
    document.getElementById("studentResults").innerHTML = "";
  }

  async function confirmMatch() {
    const studentId = document.getElementById("selectedStudentId").value;
    if (!studentId || !selectedPayment) {
      showNotification("Please select a student", "warning");
      return;
    }
    try {
      await window.API.apiCall("/finance/unmatched-payments/match", "POST", {
        payment_id: selectedPayment.id,
        student_id: studentId,
        notes: document.getElementById("matchNotes").value,
      });
      showNotification("Payment matched successfully", "success");
      bootstrap.Modal.getInstance(
        document.getElementById("matchModal"),
      )?.hide();
      await loadData();
    } catch (e) {
      showNotification(e.message || "Failed to match payment", "danger");
    }
  }

  function exportCSV() {
    if (!allData.length) return;
    const headers = [
      "Transaction ID",
      "Date",
      "Amount",
      "Source",
      "Payer Name",
      "Reference",
      "Status",
    ];
    const rows = allData.map((i) => [
      i.transaction_id,
      i.transaction_date,
      i.amount,
      i.source,
      i.payer_name,
      i.reference,
      i.status,
    ]);
    let csv =
      headers.join(",") +
      "\n" +
      rows.map((r) => r.map((v) => `"${v || ""}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "unmatched_payments.csv";
    a.click();
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function showNotification(msg, type) {
    const modal = document.getElementById("notificationModal");
    if (modal) {
      const m = modal.querySelector(".notification-message"),
        c = modal.querySelector(".modal-content");
      if (m) m.textContent = msg;
      if (c) c.className = `modal-content notification-${type || "info"}`;
      const b = bootstrap.Modal.getOrCreateInstance(modal);
      b.show();
      setTimeout(() => b.hide(), 3000);
    }
  }

  return {
    init,
    refresh: loadData,
    exportCSV,
    showMatchModal,
    searchStudents,
    selectStudent,
    confirmMatch,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  UnmatchedPaymentsController.init(),
);
