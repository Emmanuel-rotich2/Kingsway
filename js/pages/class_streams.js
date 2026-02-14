const ClassStreamsController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document
          .getElementById("filterSelect")
          ?.addEventListener("change", filterData);
    }
    async function loadData() {
        try {
            const r =
              (await window.API.apiCall("/academic/class-streams", "GET").catch(
                () => null,
              )) ||
              (await window.API.academic?.listStreams?.().catch(() => null)) ||
              (await window.API.academic?.listClasses?.().catch(() => null));
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
        const uniqueClasses = new Set(
          items.map((d) => d.class_name || d.grade || d.class_id),
        ).size;
        const totalStudents = items.reduce(
          (s, d) =>
            s + (parseInt(d.students || d.student_count || d.enrolled) || 0),
          0,
        );
        const totalCapacity = items.reduce(
          (s, d) => s + (parseInt(d.capacity || d.max_students) || 0),
          0,
        );
        const avgSize =
          items.length > 0 ? Math.round(totalStudents / items.length) : 0;

        el("statClasses", uniqueClasses);
        el("statStreams", items.length);
        el("statAvg", avgSize + " students");
        el(
          "statCapacity",
          totalCapacity > 0
            ? Math.round((totalStudents / totalCapacity) * 100) + "%"
            : "--",
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">No class streams found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const students =
              parseInt(d.students || d.student_count || d.enrolled) || 0;
            const capacity = parseInt(d.capacity || d.max_students) || 0;
            const utilization =
              capacity > 0 ? Math.round((students / capacity) * 100) : 0;
            const utilColor =
              utilization >= 90
                ? "danger"
                : utilization >= 70
                  ? "warning"
                  : "success";
            const statusColor =
              (d.status || "active") === "active" ? "success" : "secondary";
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.class_name || d.grade || "--")}</strong></td>
                <td>${escapeHtml(d.stream_name || d.name || "--")}</td>
                <td>${escapeHtml(d.class_teacher || d.teacher_name || "--")}</td>
                <td>${students}</td>
                <td>${capacity || "--"}</td>
                <td><div class="progress" style="height:16px;"><div class="progress-bar bg-${utilColor}" style="width:${utilization}%">${utilization}%</div></div></td>
                <td><span class="badge bg-${statusColor}">${(d.status || "Active").charAt(0).toUpperCase() + (d.status || "active").slice(1)}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ClassStreamsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ClassStreamsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
          })
          .join("");
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
            if (f === "active" || f === "inactive")
              return (item.status || "active").toLowerCase() === f;
            return (item.class_name || item.grade || "")
              .toLowerCase()
              .includes(f.toLowerCase());
          });
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-stream me-2"></i>Add Stream';
        document.getElementById("recordForm").reset();
        document.getElementById("recordId").value = "";
        // Customize form labels
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Stream Name";
        if (nameEl) nameEl.placeholder = "e.g., East, West, Red";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Class / Grade";
        if (descEl) descEl.placeholder = "e.g., Grade 7, Form 2";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Stream';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.stream_name || item.name || "";
        document.getElementById("recordDescription").value =
          item.class_name || item.grade || "";
        document.getElementById("recordDate").value = "";
        document.getElementById('recordStatus').value = item.status || 'active';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          stream_name: document.getElementById("recordName").value,
          class_name: document.getElementById("recordDescription").value,
          status: document.getElementById("recordStatus").value,
        };
        if (!data.stream_name) {
          showNotification("Stream name is required", "warning");
          return;
        }
        try {
            await window.API.apiCall(id ? '/academic/class-streams/' + id : '/academic/class-streams', id ? 'PUT' : 'POST', data);
            showNotification("Stream saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (
          !confirm("Delete this stream? This may affect student assignments.")
        )
          return;
        try {
          await window.API.apiCall("/academic/class-streams/" + id, "DELETE");
          showNotification("Stream deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Class (Grade)",
          "Stream Name",
          "Class Teacher",
          "Students",
          "Capacity",
          "Utilization %",
          "Status",
        ];
        const rows = allData.map((d, i) => {
          const students =
            parseInt(d.students || d.student_count || d.enrolled) || 0;
          const capacity = parseInt(d.capacity || d.max_students) || 0;
          return [
            i + 1,
            d.class_name || d.grade,
            d.stream_name || d.name,
            d.class_teacher || d.teacher_name,
            students,
            capacity,
            capacity > 0 ? Math.round((students / capacity) * 100) : 0,
            d.status || "active",
          ];
        });
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "class_streams.csv";
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
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => ClassStreamsController.init());
