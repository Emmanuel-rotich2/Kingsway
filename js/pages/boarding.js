
document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.querySelector('#boardingTable tbody');

  // Load boarding houses
  function loadBoarding(){
    fetch('api/boarding/fetch.php')
      .then(res=>res.json())
      .then(data=>{
        tableBody.innerHTML = '';
        let totalCap = 0, totalOcc = 0, totalAvail = 0;

        data.forEach((b,i)=>{
          totalCap += parseInt(b.capacity);
          totalOcc += parseInt(b.occupied);
          totalAvail += parseInt(b.available);

          const tr = document.createElement('tr');
          tr.dataset.id = b.id;
          tr.innerHTML = `
            <td>${i+1}</td>
            <td>${b.name}</td>
            <td>${b.capacity}</td>
            <td>${b.occupied}</td>
            <td>${b.available}</td>
            <td>${b.status}</td>
            <td>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item action-btn" href="#" data-action="view" data-id="${b.id}">View Details</a></li>
                  <li><a class="dropdown-item action-btn" href="#" data-action="edit" data-id="${b.id}">Edit</a></li>
                  <li><a class="dropdown-item action-btn" href="#" data-action="manage-students" data-id="${b.id}">Manage Students</a></li>
                  <li><a class="dropdown-item action-btn text-danger" href="#" data-action="delete" data-id="${b.id}">Delete</a></li>
                </ul>
              </div>
            </td>
          `;
          tableBody.appendChild(tr);
        });

        // Update summary cards
        document.querySelector('#totalCapacity').textContent = totalCap;
        document.querySelector('#occupied').textContent = totalOcc;
        document.querySelector('#available').textContent = totalAvail;
        document.querySelector('#occupancyRate').textContent = Math.round((totalOcc/totalCap)*100) + '%';
      });
  }

  loadBoarding();

  // Add/Edit boarding house
  document.getElementById('saveBoardingBtn').addEventListener('click', () => {
    const form = document.getElementById('boardingForm');
    const fd = new FormData(form);

    fetch('api/boarding/save.php', {method:'POST', body:fd})
      .then(res=>res.json())
      .then(res=>{
        alert(res.message);
        if(res.success){
          form.reset();
          bootstrap.Modal.getInstance(document.getElementById('addBoardingModal')).hide();
          loadBoarding();
        }
      });
  });

  // Delegate actions
  tableBody.addEventListener('click', e=>{
    if(!e.target.classList.contains('action-btn')) return;
    e.preventDefault();
    const id = e.target.dataset.id;
    const action = e.target.dataset.action;

    const row = tableBody.querySelector(`tr[data-id="${id}"]`);

    switch(action){
      case 'view':
        alert(`Boarding House:\nName: ${row.cells[1].textContent}\nCapacity: ${row.cells[2].textContent}\nOccupied: ${row.cells[3].textContent}\nAvailable: ${row.cells[4].textContent}\nStatus: ${row.cells[5].textContent}`);
        break;
      case 'edit':
        document.getElementById('boardingId').value = id;
        document.getElementById('boardingName').value = row.cells[1].textContent;
        document.getElementById('boardingCapacity').value = row.cells[2].textContent;
        document.getElementById('boardingStatus').value = row.cells[5].textContent;
        new bootstrap.Modal(document.getElementById('addBoardingModal')).show();
        break;
      case 'delete':
        if(confirm('Are you sure you want to delete this boarding house?')){
          const fd = new FormData();
          fd.append('id', id);
          fetch('api/boarding/delete.php',{method:'POST', body:fd})
            .then(res=>res.json())
            .then(res=>{
              alert(res.message);
              loadBoarding();
            });
        }
        break;
      case 'manage-students':
        alert('Manage students feature coming soon!');
        break;
    }
  });

});
