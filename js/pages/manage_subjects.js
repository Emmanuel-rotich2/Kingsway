/**
 * Manage Subjects Page Controller
 * Manages Manage Subjects workflow using api.js
 */

const ManageSubjectsController = {
    data: {},
    init: function() {
        if (!AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.loadData();
    },
    loadData: async function() {
        try {
            const response = await window.API.apiCall('/academics/subjects', 'GET');
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

document.addEventListener('DOMContentLoaded', () => ManageSubjectsController.init());
