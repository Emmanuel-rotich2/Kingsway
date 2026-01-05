const studentsManagementController = {

    loadStats() {
        fetch('/Kingsway/api/students.php?action=stats')
            .then(r => r.json())
            .then(d => {
                totalStudentsCount.innerText = d.total;
                activeStudentsCount.innerText = d.active;
                inactiveStudentsCount.innerText = d.inactive;
                newStudentsCount.innerText = d.new;
            });
    },

    loadStudents(search = '') {
        fetch(`/Kingsway/api/students.php?action=list&search=${search}`)
            .then(r => r.json())
            .then(data => {
                let html = '';
                data.forEach((s, i) => {
                    html += `
                    <tr>
                        <td>${i+1}</td>
                        <td>${s.admission_no}</td>
                        <td>${s.first_name} ${s.last_name}</td>
                        <td>${s.stream_id ?? '-'}</td>
                        <td>${s.gender}</td>
                        <td>-</td>
                        <td><span class="badge bg-success">${s.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="studentsManagementController.viewStudent(${s.id})">
                                View
                            </button>
                        </td>
                    </tr>`;
                });
                studentsTableBody.innerHTML = html;
            });
    },

    searchStudents(val) {
        this.loadStudents(val);
    },

    showStudentModal() {
        studentForm.reset();
        new bootstrap.Modal(studentModal).show();
    },

    saveStudent(e) {
        e.preventDefault();
        const payload = {
            admission_no: admissionNumber.value,
            first_name: firstName.value,
            middle_name: middleName.value,
            last_name: lastName.value,
            date_of_birth: dateOfBirth.value,
            gender: gender.value,
            admission_date: admissionDate.value,
            status: studentStatus.value
        };

        fetch('/Kingsway/api/students.php?action=save', {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(() => {
            bootstrap.Modal.getInstance(studentModal).hide();
            this.loadStudents();
            this.loadStats();
        });
    },

    viewStudent(id) {
        fetch(`/Kingsway/api/students.php?action=view&id=${id}`)
            .then(r => r.json())
            .then(s => {
                viewStudentContent.innerHTML = `
                    <p><strong>Name:</strong> ${s.first_name} ${s.last_name}</p>
                    <p><strong>Admission No:</strong> ${s.admission_no}</p>
                    <p><strong>Gender:</strong> ${s.gender}</p>
                    <p><strong>Status:</strong> ${s.status}</p>
                `;
                new bootstrap.Modal(viewStudentModal).show();
            });
    }
};

document.addEventListener("DOMContentLoaded", () => {
    studentsManagementController.loadStats();
    studentsManagementController.loadStudents();
});
