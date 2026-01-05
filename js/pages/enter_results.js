/**
 * Enter Results Page Controller
 * Manages Enter Results workflow using api.js
 */

const EnterResultsController = {
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
            const response = await window.API.apiCall('/api/enter_results', 'GET');
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

document.addEventListener('DOMContentLoaded', () => EnterResultsController.init());
