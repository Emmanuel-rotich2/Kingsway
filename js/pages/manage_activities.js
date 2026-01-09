document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.querySelector('#activitiesTable tbody');

  async function loadActivities() {
    try {
      const response = await window.API.activities.list();
      tbody.innerHTML = "";

      let total = 0,
        active = 0,
        upcoming = 0,
        participants = 0;
      const today = new Date();

      // Handle the response data structure
      const activities =
        response?.data?.activities || response?.activities || response || [];

      activities.forEach((a) => {
        total++;
        if (["Scheduled", "In Progress"].includes(a.status)) active++;
        if (new Date(a.activity_date) > today) upcoming++;
        participants += parseInt(a.participants) || 0;

        tbody.innerHTML += `
          <tr data-id="${a.id}">
            <td>${a.id}</td>
            <td>${a.name}</td>
            <td>${a.category}</td>
            <td>${a.activity_date}</td>
            <td>${a.participants || 0}</td>
            <td><span class="badge bg-${badgeColor(a.status)}">${
          a.status
        }</span></td>
            <td>
              <button class="btn btn-sm btn-info edit">Edit</button>
              <button class="btn btn-sm btn-danger delete">Delete</button>
            </td>
          </tr>`;
      });

      document.getElementById("totalActivities").textContent = total;
      document.getElementById("activeActivities").textContent = active;
      document.getElementById("upcomingActivities").textContent = upcoming;
      document.getElementById("totalParticipants").textContent = participants;
    } catch (error) {
      console.error("Error loading activities:", error);
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading activities</td></tr>`;
    }
  }

  function badgeColor(status) {
    return (
      {
        Planning: "secondary",
        Scheduled: "primary",
        "In Progress": "success",
        Completed: "dark",
        Cancelled: "danger",
      }[status] || "secondary"
    );
  }

  loadActivities();

  // Save using proper API
  document
    .getElementById("saveActivityBtn")
    .addEventListener("click", async () => {
      const form = document.getElementById("activityForm");
      const activityId = document.getElementById("activityId").value;

      const data = {
        name: document.getElementById("activityName").value,
        category: document.getElementById("activityCategory").value,
        activity_date: document.getElementById("activityDate").value,
        participants:
          parseInt(document.getElementById("activityParticipants").value) || 0,
        status: document.getElementById("activityStatus").value,
        description:
          document.getElementById("activityDescription")?.value || "",
      };

      try {
        let response;
        if (activityId) {
          // Update existing activity
          response = await window.API.activities.update(activityId, data);
        } else {
          // Create new activity
          response = await window.API.activities.create(data);
        }

        if (response && (response.status === "success" || response.success)) {
          alert(response.message || "Activity saved successfully");
          form.reset();
          document.getElementById("activityId").value = "";
          bootstrap.Modal.getInstance(
            document.getElementById("addActivityModal")
          ).hide();
          loadActivities();
        } else {
          alert(response?.message || "Error saving activity");
        }
      } catch (error) {
        console.error("Error saving activity:", error);
        alert("Error saving activity: " + error.message);
      }
    });

  // Edit / Delete
  tbody.addEventListener("click", async (e) => {
    const row = e.target.closest("tr");
    if (!row) return;
    const id = row.dataset.id;

    if (e.target.classList.contains("edit")) {
      document.getElementById("activityId").value = id;
      document.getElementById("activityName").value = row.cells[1].textContent;
      document.getElementById("activityCategory").value =
        row.cells[2].textContent;
      document.getElementById("activityDate").value = row.cells[3].textContent;
      document.getElementById("activityParticipants").value =
        row.cells[4].textContent;
      document.getElementById("activityStatus").value = row.cells[5].innerText;
      new bootstrap.Modal("#addActivityModal").show();
    }

    if (e.target.classList.contains("delete")) {
      if (!confirm("Delete this activity?")) return;

      try {
        const response = await window.API.activities.delete(id);
        if (response && (response.status === "success" || response.success)) {
          loadActivities();
        } else {
          alert(response?.message || "Error deleting activity");
        }
      } catch (error) {
        console.error("Error deleting activity:", error);
        alert("Error deleting activity: " + error.message);
      }
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
