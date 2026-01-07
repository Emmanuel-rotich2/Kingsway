document.addEventListener('DOMContentLoaded', () => {
    const sessionsTableBody = document.querySelector('#sessionsTable tbody');
    const searchBox = document.getElementById('searchBox');
    const statusFilter = document.getElementById('statusFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const dateFilter = document.getElementById('dateFilter');
    const pagination = document.getElementById('pagination');

    let currentPage = 1;
    const limit = 10;

    // Load summary cards
    function loadSummary() {
        fetch('api/get_summary.php')
            .then(res => res.json())
            .then(data => {
                document.getElementById('totalSessions').textContent = data.total;
                document.getElementById('scheduledSessions').textContent = data.scheduled;
                document.getElementById('completedSessions').textContent = data.completed;
                document.getElementById('activeCases').textContent = data.active;
            });
    }

    // Load sessions
    function loadSessions(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            search: searchBox.value,
            status: statusFilter.value,
            category: categoryFilter.value,
            date: dateFilter.value,
            page: currentPage,
            limit: limit
        });

        fetch(`api/get_sessions.php?${params}`)
            .then(res => res.json())
            .then(data => {
                sessionsTableBody.innerHTML = '';
                if (data.sessions.length === 0) {
                    sessionsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">No sessions found</td></tr>`;
                } else {
                    data.sessions.forEach(s => {
                        sessionsTableBody.innerHTML += `
                            <tr>
                                <td>${new Date(s.session_datetime).toLocaleString()}</td>
                                <td>${s.first_name} ${s.last_name}</td>
                                <td>${s.class_name}</td>
                                <td>${s.category}</td>
                                <td>${s.issue_summary}</td>
                                <td>${s.status}</td>
                                <td>
                                    <button class="btn btn-sm btn-info viewSession" data-id="${s.id}">View</button>
                                    <button class="btn btn-sm btn-warning editSession" data-id="${s.id}">Edit</button>
                                </td>
                            </tr>`;
                    });
                }

                // Pagination
                pagination.innerHTML = '';
                for (let i = 1; i <= data.pages; i++) {
                    pagination.innerHTML += `<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" href="#">${i}</a></li>`;
                }
            });
    }

    // Event listeners
    [searchBox, statusFilter, categoryFilter, dateFilter].forEach(el => {
        el.addEventListener('input', () => loadSessions(1));
        el.addEventListener('change', () => loadSessions(1));
    });

    pagination.addEventListener('click', e => {
        if (e.target.classList.contains('page-link')) {
            loadSessions(parseInt(e.target.textContent));
        }
    });

    // Export CSV
    document.getElementById('exportBtn').addEventListener('click', () => {
        const rows = [['Date/Time','Student','Class','Category','Issue Summary','Status']];
        sessionsTableBody.querySelectorAll('tr').forEach(tr => {
            const cells = Array.from(tr.querySelectorAll('td')).map(td => td.textContent);
            if(cells.length===7) rows.push(cells.slice(0,6));
        });
        const csvContent = "data:text/csv;charset=utf-8," + rows.map(e=>e.join(",")).join("\n");
        const link=document.createElement('a'); link.href=encodeURI(csvContent); link.download="counseling_sessions.csv"; link.click();
    });

    // Initial load
    loadSummary();
    loadSessions();
});
