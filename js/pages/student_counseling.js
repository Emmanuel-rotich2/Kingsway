document.addEventListener('DOMContentLoaded', () => {
  const sessionsTableBody = document.querySelector("#sessionsTable tbody");
  const searchBox = document.getElementById("searchBox");
  const statusFilter = document.getElementById("statusFilter");
  const categoryFilter = document.getElementById("categoryFilter");
  const dateFilter = document.getElementById("dateFilter");
  const pagination = document.getElementById("pagination");

  let currentPage = 1;
  const limit = 10;

  // Load summary cards using proper API
  async function loadSummary() {
    try {
      const response = await window.API.counseling.getSummary();
      if (response && response.data) {
        const data = response.data;
        document.getElementById("totalSessions").textContent = data.total || 0;
        document.getElementById("scheduledSessions").textContent =
          data.scheduled || 0;
        document.getElementById("completedSessions").textContent =
          data.completed || 0;
        document.getElementById("activeCases").textContent = data.active || 0;
      }
    } catch (error) {
      console.error("Error loading counseling summary:", error);
    }
  }

  // Load sessions using proper API
  async function loadSessions(page = 1) {
    currentPage = page;
    const params = {
      search: searchBox.value,
      status: statusFilter.value,
      category: categoryFilter.value,
      date: dateFilter.value,
      page: currentPage,
      limit: limit,
    };

    try {
      const response = await window.API.counseling.list(params);
      sessionsTableBody.innerHTML = "";

      if (response && response.data) {
        const data = response.data;
        const sessions = data.sessions || [];
        const paginationInfo = data.pagination || {};

        if (sessions.length === 0) {
          sessionsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">No sessions found</td></tr>`;
        } else {
          sessions.forEach((s) => {
            sessionsTableBody.innerHTML += `
                            <tr>
                                <td>${new Date(
                                  s.session_datetime
                                ).toLocaleString()}</td>
                                <td>${s.first_name} ${s.last_name}</td>
                                <td>${s.class_name || "N/A"}</td>
                                <td>${s.category}</td>
                                <td>${s.issue_summary}</td>
                                <td>${s.status}</td>
                                <td>
                                    <button class="btn btn-sm btn-info viewSession" data-id="${
                                      s.id
                                    }">View</button>
                                    <button class="btn btn-sm btn-warning editSession" data-id="${
                                      s.id
                                    }">Edit</button>
                                </td>
                            </tr>`;
          });
        }

        // Pagination
        const pages = paginationInfo.pages || 1;
        pagination.innerHTML = "";
        for (let i = 1; i <= pages; i++) {
          pagination.innerHTML += `<li class="page-item ${
            i === currentPage ? "active" : ""
          }"><a class="page-link" href="#">${i}</a></li>`;
        }
      }
    } catch (error) {
      console.error("Error loading counseling sessions:", error);
      sessionsTableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading sessions</td></tr>`;
    }
  }

  // Event listeners
  [searchBox, statusFilter, categoryFilter, dateFilter].forEach((el) => {
    el.addEventListener("input", () => loadSessions(1));
    el.addEventListener("change", () => loadSessions(1));
  });

  pagination.addEventListener("click", (e) => {
    if (e.target.classList.contains("page-link")) {
      loadSessions(parseInt(e.target.textContent));
    }
  });

  // Export CSV
  document.getElementById("exportBtn").addEventListener("click", () => {
    const rows = [
      ["Date/Time", "Student", "Class", "Category", "Issue Summary", "Status"],
    ];
    sessionsTableBody.querySelectorAll("tr").forEach((tr) => {
      const cells = Array.from(tr.querySelectorAll("td")).map(
        (td) => td.textContent
      );
      if (cells.length === 7) rows.push(cells.slice(0, 6));
    });
    const csvContent =
      "data:text/csv;charset=utf-8," + rows.map((e) => e.join(",")).join("\n");
    const link = document.createElement("a");
    link.href = encodeURI(csvContent);
    link.download = "counseling_sessions.csv";
    link.click();
  });

  // Initial load
  loadSummary();
  loadSessions();
});
