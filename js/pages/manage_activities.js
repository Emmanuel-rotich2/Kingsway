document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.querySelector('#activitiesTable tbody');

  function loadActivities() {
    fetch('api/activities/fetch.php')
      .then(res => res.json())
      .then(data => {
        tbody.innerHTML = '';

        let total = 0, active = 0, upcoming = 0, participants = 0;
        const today = new Date();

        data.forEach(a => {
          total++;
          if (['Scheduled','In Progress'].includes(a.status)) active++;
          if (new Date(a.activity_date) > today) upcoming++;
          participants += parseInt(a.participants);

          tbody.innerHTML += `
            <tr data-id="${a.id}">
              <td>${a.id}</td>
              <td>${a.name}</td>
              <td>${a.category}</td>
              <td>${a.activity_date}</td>
              <td>${a.participants}</td>
              <td><span class="badge bg-${badgeColor(a.status)}">${a.status}</span></td>
              <td>
                <button class="btn btn-sm btn-info edit">Edit</button>
                <button class="btn btn-sm btn-danger delete">Delete</button>
              </td>
            </tr>`;
        });

        document.getElementById('totalActivities').textContent = total;
        document.getElementById('activeActivities').textContent = active;
        document.getElementById('upcomingActivities').textContent = upcoming;
        document.getElementById('totalParticipants').textContent = participants;
      });
  }

  function badgeColor(status){
    return {
      'Planning':'secondary',
      'Scheduled':'primary',
      'In Progress':'success',
      'Completed':'dark',
      'Cancelled':'danger'
    }[status];
  }

  loadActivities();

  // Save
  document.getElementById('saveActivityBtn').addEventListener('click', () => {
    const form = document.getElementById('activityForm');
    const fd = new FormData(form);
    fd.append('activityId', document.getElementById('activityId').value);

    fetch('api/activities/save.php', { method:'POST', body: fd })
      .then(res => res.json())
      .then(r => {
        alert(r.message);
        if (r.success) {
          form.reset();
          bootstrap.Modal.getInstance(document.getElementById('addActivityModal')).hide();
          loadActivities();
        }
      });
  });

  // Edit / Delete
  tbody.addEventListener('click', e => {
    const row = e.target.closest('tr');
    const id = row.dataset.id;

    if (e.target.classList.contains('edit')) {
      document.getElementById('activityId').value = id;
      document.getElementById('activityName').value = row.cells[1].textContent;
      document.getElementById('activityCategory').value = row.cells[2].textContent;
      document.getElementById('activityDate').value = row.cells[3].textContent;
      document.getElementById('activityParticipants').value = row.cells[4].textContent;
      document.getElementById('activityStatus').value = row.cells[5].innerText;
      new bootstrap.Modal('#addActivityModal').show();
    }

    if (e.target.classList.contains('delete')) {
      if (!confirm('Delete this activity?')) return;
      const fd = new FormData();
      fd.append('id', id);

      fetch('api/activities/delete.php', { method:'POST', body: fd })
        .then(() => loadActivities());
    }
  });

  // Search
  document.getElementById('searchActivities').addEventListener('input', e => {
    const val = e.target.value.toLowerCase();
    [...tbody.rows].forEach(r => {
      r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
  });
});
