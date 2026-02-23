const token = localStorage.getItem('jwt'); // assume JWT is stored in localStorage

async function fetchActivities(){
    const res = await fetch('/api/activities/list.php', {
        headers: { 'Authorization': 'Bearer ' + token }
    });
    const data = await res.json();
    populateTable(data.activities);
    populateChart(data.activities);
}

function populateTable(activities){
    const tbody = document.querySelector('#activitiesTable tbody');
    tbody.innerHTML = '';
    activities.forEach(act => {
        tbody.innerHTML += `
            <tr>
                <td>${act.id}</td>
                <td>${act.title}</td>
                <td>${act.category_name}</td>
                <td>${act.start_date} → ${act.end_date}</td>
                <td>${act.max_participants || 0}</td>
                <td>${act.status}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editActivity(${act.id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteActivity(${act.id})">Delete</button>
                </td>
            </tr>
        `;
    });
}
async function fetchCategories() {
    const res = await fetch('/api/categories/list.php', {
        headers: { 'Authorization': 'Bearer ' + token }
    });
    const data = await res.json();
    const sel = document.getElementById('activityCategory');
    sel.innerHTML = '';
    data.categories.forEach(c => sel.innerHTML += `<option value="${c.id}">${c.name}</option>`);
}

async function fetchActivities() {
    const res = await fetch('/api/activities/list.php', {
        headers: { 'Authorization': 'Bearer ' + token }
    });
    const data = await res.json();
    populateTable(data.activities);
    populateChart(data.activities);
}

let categoryChart;
function populateChart(activities){
    const ctx = document.getElementById('categoryChart').getContext('2d');
    const counts = {};
    activities.forEach(a => counts[a.category_name] = (counts[a.category_name] || 0) + 1);

    if(categoryChart) categoryChart.destroy();
    categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(counts),
            datasets: [{
                label: 'Activities by Category',
                data: Object.values(counts),
                backgroundColor: ['#0d6efd','#198754','#ffc107','#0dcaf0']
            }]
        },
        options: { responsive:true }
    });
}

// Add/Edit activity
document.getElementById('saveActivityBtn').addEventListener('click', async()=>{
    const id = document.getElementById('activityId').value || null;
    const payload = {
        id,
        title: document.getElementById('activityName').value,
        category_id: document.getElementById('activityCategory').value,
        description: document.getElementById('activityDescription').value,
        start_date: document.getElementById('activityDate').value,
        end_date: document.getElementById('activityDate').value,
        status: document.getElementById('activityStatus').value,
        max_participants: document.getElementById('activityParticipants').value,
        target_audience: 'students'
    };
    await fetch('/api/activities/save.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token 
        },
        body: JSON.stringify(payload)
    });
    fetchActivities();
    new bootstrap.Modal(document.getElementById('addActivityModal')).hide();
});

// Delete activity
async function deleteActivity(id){
    if(confirm('Delete this activity?')){
        await fetch('/api/activities/delete.php?id=' + id, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        fetchActivities();
    }
}

fetchActivities();
