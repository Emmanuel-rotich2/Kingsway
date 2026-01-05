/**
 * Import Existing Students Page Controller
 * Manages Import Existing Students workflow using api.js
 */

const ImportExistingStudentsController = {
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
            const response = await window.API.apiCall('/api/import_existing_students', 'GET');
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

document.addEventListener('DOMContentLoaded', () => ImportExistingStudentsController.init());
