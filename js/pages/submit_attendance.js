/**
 * Submit Attendance Page Controller
 * Manages Submit Attendance workflow using api.js
 */

const SubmitAttendanceController = {
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
            const response = await window.API.apiCall('/api/submit_attendance', 'GET');
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

document.addEventListener('DOMContentLoaded', () => SubmitAttendanceController.init());
