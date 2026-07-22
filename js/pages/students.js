const studentsManagementController = {

    loadStats() {
        API.students.getStatistics()
            .then(d => {
                totalStudentsCount.innerText = d.total;
                activeStudentsCount.innerText = d.active;
                inactiveStudentsCount.innerText = d.inactive;
                newStudentsCount.innerText = d.new;
            });
    },

    loadStudents: async function(search = '') {
        try {
            let data;
            
            // Try DataStore first for caching
            if (typeof DataStore !== 'undefined') {
                try {
                    const params = search ? { search: search } : {};
                    data = await DataStore.get('students', {
                        strategy: 'stale-while-revalidate',
                        ttl: 300000, // 5 minutes
                        storeName: 'student_directory_cache',
                        endpoint: '/students',
                        params: params
                    });
                    console.log("[Students] Data from DataStore:", data);
                } catch (dataStoreError) {
                    console.warn("[Students] DataStore failed, falling back to API:", dataStoreError);
                }
            }
            
            // Fallback to direct API call
            if (!data) {
                const request = search
                    ? apiCall(`/students/student?search=${encodeURIComponent(search)}`, 'GET')
                    : API.students.get();
                data = await request;
                
                // Cache in DataStore
                if (typeof DataStore !== 'undefined') {
                    const params = search ? { search: search } : {};
                    await DataStore.set('students', data, {
                        ttl: 300000,
                        storeName: 'student_directory_cache'
                    });
                }
            }
            
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
        } catch (error) {
            console.error("[Students] Failed to load students:", error);
        }
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

        API.students.create(payload)
            .then(() => {
                bootstrap.Modal.getInstance(studentModal).hide();
                Promise.all([this.loadStudents(), this.loadStats()]);
            });
    },

    viewStudent(id) {
        API.students.get(id)
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
