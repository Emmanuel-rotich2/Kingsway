const studentsManagementController = (() => {
    let currentStep = 1;
    const totalSteps = 4; // 1: Personal, 2: Academic, 3: Parent/Sponsor, 4: Payment

    /* ------------------------------
       Step Navigation for Wizard
    -------------------------------*/
    function showStep(step) {
        currentStep = step;
        document.querySelectorAll('#studentForm .wizard-step').forEach((el, i) => {
            el.style.display = (i + 1 === step) ? 'block' : 'none';
        });
        // Update modal title with step
        const titles = ['Personal Info', 'Academic Info', 'Parent/Sponsor', 'Initial Payment'];
        document.getElementById('studentModalLabel').textContent = `Add Student - ${titles[step - 1]}`;
    }

    function nextStep() {
        if (validateStep(currentStep)) {
            if (currentStep < totalSteps) showStep(currentStep + 1);
            else saveStudent(); // last step: submit
        }
    }

    function prevStep() {
        if (currentStep > 1) showStep(currentStep - 1);
    }

    function validateStep(step) {
        let valid = true;
        const stepFields = document.querySelectorAll(`#studentForm .wizard-step[data-step="${step}"] [required]`);
        stepFields.forEach(f => {
            if (!f.value) {
                f.classList.add('is-invalid');
                valid = false;
            } else f.classList.remove('is-invalid');
        });
        return valid;
    }

    /* ------------------------------
       Save Student Function
    -------------------------------*/
    async function saveStudent(event) {
        if (event) event.preventDefault();

        const formData = new FormData();
        // --- Personal Info ---
        formData.append('studentId', document.getElementById('studentId').value);
        formData.append('firstName', document.getElementById('firstName').value);
        formData.append('middleName', document.getElementById('middleName').value);
        formData.append('lastName', document.getElementById('lastName').value);
        formData.append('dateOfBirth', document.getElementById('dateOfBirth').value);
        formData.append('gender', document.getElementById('gender').value);
        formData.append('bloodGroup', document.getElementById('bloodGroup').value);
        formData.append('studentEmail', document.getElementById('studentEmail').value);
        formData.append('studentPhone', document.getElementById('studentPhone').value);
        formData.append('studentAddress', document.getElementById('studentAddress').value);

        // --- Files ---
        const profilePic = document.getElementById('studentProfilePic').files[0];
        const nationalId = document.getElementById('nationalId').files[0];
        if (profilePic) formData.append('profile_pic', profilePic);
        if (nationalId) formData.append('nationalId', nationalId);

        // --- Academic Info ---
        formData.append('admissionNumber', document.getElementById('admissionNumber').value);
        formData.append('studentClass', document.getElementById('studentClass').value);
        formData.append('studentStream', document.getElementById('studentStream').value);
        formData.append('studentTypeId', document.getElementById('studentTypeId').value);
        formData.append('admissionDate', document.getElementById('admissionDate').value);
        formData.append('studentStatus', document.getElementById('studentStatus').value);
        formData.append('boardingStatus', document.getElementById('boardingStatus').value);
        formData.append('assessmentNumber', document.getElementById('assessmentNumber').value);
        formData.append('assessmentStatus', document.getElementById('assessmentStatus').value);

        // --- Sponsorship Info ---
        const isSponsored = document.getElementById('isSponsored').checked;
        formData.append('isSponsored', isSponsored);
        if (isSponsored) {
            formData.append('sponsorName', document.getElementById('sponsorName').value);
            formData.append('sponsorType', document.getElementById('sponsorType').value);
            formData.append('sponsorWaiverPercentage', document.getElementById('sponsorWaiverPercentage').value);
        }

        // --- Initial Payment ---
        formData.append('initialPaymentAmount', document.getElementById('initialPaymentAmount').value);
        formData.append('paymentMethod', document.getElementById('paymentMethod').value);
        formData.append('paymentReference', document.getElementById('paymentReference').value);
        formData.append('receiptNo', document.getElementById('receiptNo').value);

        // --- Parent Info ---
        const isNewParent = document.getElementById('isNewParent').checked;
        formData.append('isNewParent', isNewParent);
        if (isNewParent) {
            formData.append('parentFirstName', document.getElementById('parentFirstName').value);
            formData.append('parentLastName', document.getElementById('parentLastName').value);
            formData.append('parentGender', document.getElementById('parentGender').value);
            formData.append('parentPhone1', document.getElementById('parentPhone1').value);
            formData.append('parentPhone2', document.getElementById('parentPhone2').value);
            formData.append('parentEmail', document.getElementById('parentEmail').value);
            formData.append('parentOccupation', document.getElementById('parentOccupation').value);
            formData.append('parentAddress', document.getElementById('parentAddress').value);
        } else {
            formData.append('existingParentId', document.getElementById('existingParentId').value);
        }
        formData.append('guardianRelationship', document.getElementById('guardianRelationship').value);

        // --- API Call ---
        const apiUrl = '/api/save_student.php';
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showAlert('success', result.message);
                loadStudentsTable(); // refresh table dynamically
                document.getElementById('studentForm').reset();
                $('#studentModal').modal('hide');
            } else {
                showAlert('danger', result.message);
            }
        } catch (err) {
            console.error(err);
            showAlert('danger', 'Error saving student. Please try again.');
        }
    }

    /* ------------------------------
       Load Students Table
    -------------------------------*/
    async function loadStudentsTable() {
        try {
            const response = await fetch('/api/get_students.php');
            const students = await response.json();
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = '';
            students.forEach((s, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><img src="${s.photo || '/Kingsway/images/default-avatar.png'}" width="40" height="40" class="rounded-circle" /></td>
                    <td>${idx + 1}</td>
                    <td>${s.admissionNumber}</td>
                    <td>${s.firstName} ${s.lastName}</td>
                    <td>${s.className} / ${s.streamName}</td>
                    <td>${s.gender}</td>
                    <td>${s.phone}</td>
                    <td><span class="badge bg-${s.status === 'active' ? 'success' : s.status === 'inactive' ? 'secondary' : 'warning'}">${s.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="studentsManagementController.viewStudent(${s.id})">View</button>
                        <button class="btn btn-sm btn-primary" onclick="studentsManagementController.editStudent(${s.id})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="studentsManagementController.deleteStudent(${s.id})">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
        }
    }

    /* ------------------------------
       Show Alerts
    -------------------------------*/
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.prepend(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.remove();
        }, 5000);
    }

    /* ------------------------------
       Init Function
    -------------------------------*/
    function init() {
        loadStudentsTable();
        showStep(1); // start at step 1
    }

    return {
        init,
        saveStudent,
        loadStudentsTable,
        showStep,
        nextStep,
        prevStep,
        showAlert
    };
})();

// Initialize controller
document.addEventListener('DOMContentLoaded', () => studentsManagementController.init());
