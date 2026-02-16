const ParentFeedbackController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall(
              "/communications/parent-feedback",
              "GET",
            ).catch(() => null);
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statTotal", items.length);
        const ratings = items
          .map((d) => parseFloat(d.rating || d.score) || 0)
          .filter((v) => v > 0);
        const avgRating =
          ratings.length > 0
            ? (ratings.reduce((s, v) => s + v, 0) / ratings.length).toFixed(1)
            : "0.0";
        el("statAvg", avgRating + "/5");
        el(
          "statPositive",
          items.filter((d) => {
            const r = parseFloat(d.rating || d.score) || 0;
            const s = (d.sentiment || "").toLowerCase();
            return (
              r >= 4 || s === "positive" || s === "good" || s === "excellent"
            );
          }).length,
        );
        el(
          "statNegative",
          items.filter((d) => {
            const r = parseFloat(d.rating || d.score) || 0;
            const s = (d.sentiment || "").toLowerCase();
            return r <= 2 || s === "negative" || s === "poor" || s === "bad";
          }).length,
        );
    }
    function renderTable(items) {
      const tbody = document.querySelector("#dataTable tbody");
      if (!tbody) return;
      if (!items.length) {
        tbody.innerHTML =
          '<tr><td colspan="9" class="text-center text-muted py-4">No parent feedback found</td></tr>';
        return;
      }
      tbody.innerHTML = items
        .map((d, i) => {
          const rating = parseFloat(d.rating || d.score) || 0;
          const stars =
            "★".repeat(Math.round(rating)) + "☆".repeat(5 - Math.round(rating));
          const ratingColor =
            rating >= 4 ? "success" : rating >= 3 ? "warning" : "danger";
          const cat = d.category || d.feedback_type || d.type || "General";
          const status = d.status || "received";
          const statusColor =
            status.toLowerCase() === "responded"
              ? "success"
              : status.toLowerCase() === "pending"
                ? "warning"
                : "secondary";
          return `<tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(d.parent_name || d.parent || "--")}</td>
                <td>${escapeHtml(d.student_name || d.student || "--")}</td>
                <td><span class="badge bg-info">${escapeHtml(cat)}</span></td>
                <td><span class="text-${ratingColor}">${stars}</span> <small>(${rating})</small></td>
                <td>${escapeHtml((d.message || d.feedback || d.comment || "").substring(0, 60))}${(d.message || d.feedback || d.comment || "").length > 60 ? "..." : ""}</td>
                <td>${d.date || d.created_at || d.submitted_at || "--"}</td>
                <td><span class="badge bg-${statusColor}">${status}</span></td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="ParentFeedbackController.viewFeedback(${i})" title="View"><i class="fas fa-eye"></i></button></td>
            </tr>`;
        })
        .join("");
    }
    function viewFeedback(index) {
      const d = allData[index];
      if (!d) return;
      const rating = parseFloat(d.rating || d.score) || 0;
      const html = [
        `<strong>Parent:</strong> ${escapeHtml(d.parent_name || d.parent || "")}`,
        `<strong>Student:</strong> ${escapeHtml(d.student_name || d.student || "")}`,
        `<strong>Category:</strong> ${d.category || d.feedback_type || "General"}`,
        `<strong>Rating:</strong> ${"★".repeat(Math.round(rating))}${"☆".repeat(5 - Math.round(rating))} (${rating}/5)`,
        `<strong>Date:</strong> ${d.date || d.created_at || ""}`,
        `<hr><strong>Message:</strong><br>${escapeHtml(d.message || d.feedback || d.comment || "No message")}`,
        d.response
          ? `<hr><strong>Response:</strong><br>${escapeHtml(d.response)}`
          : "",
      ]
        .filter(Boolean)
        .join("<br>");
      const modal = document.getElementById("notificationModal");
      if (modal) {
        const m =
          modal.querySelector(".notification-message") ||
          modal.querySelector(".modal-body");
        if (m) m.innerHTML = html;
        bootstrap.Modal.getOrCreateInstance(modal).show();
      }
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f)
          filtered = filtered.filter((item) => {
            const r = parseFloat(item.rating || item.score) || 0;
            if (f === "positive") return r >= 4;
            if (f === "neutral") return r >= 3 && r < 4;
            if (f === "negative") return r < 3;
            return (
              (item.category || item.feedback_type || "").toLowerCase() ===
              f.toLowerCase()
            );
          });
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Parent",
          "Student",
          "Category",
          "Rating",
          "Message",
          "Date",
          "Status",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.parent_name || d.parent,
          d.student_name || d.student,
          d.category || d.feedback_type,
          d.rating || d.score,
          d.message || d.feedback || d.comment,
          d.date || d.created_at,
          d.status || "received",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "parent_feedback.csv";
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
        if (c) c.className = "modal-content notification-" + (type || "info");
        const b = bootstrap.Modal.getOrCreateInstance(modal);
        b.show();
        setTimeout(() => b.hide(), 3000);
      }
    }
    return { init, refresh: loadData, exportCSV, viewFeedback };
})();
document.addEventListener('DOMContentLoaded', () => ParentFeedbackController.init());
