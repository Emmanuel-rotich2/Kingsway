/**
 * Manage Non Teaching Staff Page Controller
 * Manages Manage Non Teaching Staff workflow using api.js
 */

const ManageNonTeachingStaffController = {
    data: {},
    init: function() {
        if (!AuthContext.isAuthenticated()) {
            window.location.href = '/Kingsway/index.php';
            return;
        }
        this.loadData();
    },
    loadData: async function() {
        try {
            const response = await window.API.apiCall('/api/manage_non_teaching_staff', 'GET');
            if (response) {
                this.data = response;
                this.render();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    },
    render: function() {
        console.log('Rendering data:', this.data);
    }
};

document.addEventListener('DOMContentLoaded', () => ManageNonTeachingStaffController.init());
