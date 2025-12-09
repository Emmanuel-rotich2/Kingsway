/**
 * Boarding Controller
 * Handles boarding/dormitory management
 * NOTE: Currently using static data - backend API integration pending
 */

const boardingController = {
    houses: [],
    rooms: [],
    students: [],
    filteredData: [],

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            showNotification('Loading boarding data...', 'info');
            this.loadStaticData();
            this.renderHousesTable();
            this.checkUserPermissions();
            showNotification('Boarding management loaded successfully', 'success');
        } catch (error) {
            console.error('Error initializing boarding controller:', error);
            showNotification('Failed to load boarding management', 'error');
        }
    },

    /**
     * Load static data (temporary until backend API is created)
     */
    loadStaticData: function() {
        this.houses = [
            {
                id: 1,
                name: 'Boys Dormitory A',
                capacity: 50,
                occupied: 45,
                available: 5,
                status: 'active',
                matron: 'Mrs. Smith',
                gender: 'male'
            },
            {
                id: 2,
                name: 'Girls Dormitory A',
                capacity: 50,
                occupied: 48,
                available: 2,
                status: 'active',
                matron: 'Mrs. Johnson',
                gender: 'female'
            },
            {
                id: 3,
                name: 'Boys Dormitory B',
                capacity: 40,
                occupied: 35,
                available: 5,
                status: 'active',
                matron: 'Mr. Brown',
                gender: 'male'
            },
            {
                id: 4,
                name: 'Girls Dormitory B',
                capacity: 40,
                occupied: 38,
                available: 2,
                status: 'active',
                matron: 'Ms. Davis',
                gender: 'female'
            }
        ];
        this.filteredData = [...this.houses];
    },

    // ============================================================================
    // BOARDING HOUSES
    // ============================================================================

    /**
     * Render houses table
     */
    renderHousesTable: function() {
        const container = document.getElementById('boardingContainer');
        if (!container) return;

        if (this.filteredData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No boarding houses found.</div>';
            return;
        }

        let html = `
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Capacity</h6>
                            <h3>${this.getTotalCapacity()}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Occupied</h6>
                            <h3>${this.getTotalOccupied()}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Available</h6>
                            <h3>${this.getTotalAvailable()}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Occupancy Rate</h6>
                            <h3>${this.getOccupancyRate()}%</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-info">
                        <tr>
                            <th>Boarding House</th>
                            <th>Gender</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Available</th>
                            <th>Occupancy</th>
                            <th>Matron/Patron</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredData.forEach(house => {
            const occupancy = Math.round((house.occupied / house.capacity) * 100);
            const statusBadge = this.getStatusBadge(house.status);
            const genderBadge = house.gender === 'male' ? '<span class="badge bg-primary">Boys</span>' : '<span class="badge bg-danger">Girls</span>';
            
            html += `
                <tr>
                    <td><strong>${house.name}</strong></td>
                    <td>${genderBadge}</td>
                    <td>${house.capacity}</td>
                    <td>${house.occupied}</td>
                    <td><span class="badge ${house.available < 5 ? 'bg-danger' : 'bg-success'}">${house.available}</span></td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${occupancy > 90 ? 'bg-danger' : 'bg-success'}" 
                                 role="progressbar" 
                                 style="width: ${occupancy}%" 
                                 aria-valuenow="${occupancy}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">${occupancy}%</div>
                        </div>
                    </td>
                    <td>${house.matron}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="boardingController.viewHouse(${house.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="boardingController.editHouse(${house.id})" title="Edit" data-permission="boarding_update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-success" onclick="boardingController.manageStudents(${house.id})" title="Manage Students">
                                <i class="bi bi-people"></i>
                            </button>
                            <button class="btn btn-primary" onclick="boardingController.viewReports(${house.id})" title="Reports">
                                <i class="bi bi-file-text"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        this.checkUserPermissions();
    },

    /**
     * Get status badge
     */
    getStatusBadge: function(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'maintenance': '<span class="badge bg-warning">Maintenance</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * View house details
     */
    viewHouse: function(houseId) {
        const house = this.houses.find(h => h.id === houseId);
        if (!house) {
            showNotification('House not found', 'error');
            return;
        }

        const occupancy = Math.round((house.occupied / house.capacity) * 100);
        alert(`Boarding House Details:\n\nName: ${house.name}\nGender: ${house.gender === 'male' ? 'Boys' : 'Girls'}\nCapacity: ${house.capacity}\nOccupied: ${house.occupied}\nAvailable: ${house.available}\nOccupancy: ${occupancy}%\nMatron/Patron: ${house.matron}\nStatus: ${house.status}`);
    },

    /**
     * Edit house
     */
    editHouse: function(houseId) {
        console.log('Edit house feature coming soon (awaiting backend API)');
    },

    /**
     * Manage students in house
     */
    manageStudents: function(houseId) {
        const house = this.houses.find(h => h.id === houseId);
        if (!house) {
            showNotification('House not found', 'error');
            return;
        }

        console.log(`Manage students in ${house.name} (awaiting backend API integration)`);
    },

    /**
     * View reports
     */
    viewReports: function(houseId) {
        console.log('Boarding reports feature coming soon');
    },

    // ============================================================================
    // STATISTICS
    // ============================================================================

    /**
     * Get total capacity
     */
    getTotalCapacity: function() {
        return this.houses.reduce((sum, house) => sum + house.capacity, 0);
    },

    /**
     * Get total occupied
     */
    getTotalOccupied: function() {
        return this.houses.reduce((sum, house) => sum + house.occupied, 0);
    },

    /**
     * Get total available
     */
    getTotalAvailable: function() {
        return this.houses.reduce((sum, house) => sum + house.available, 0);
    },

    /**
     * Get occupancy rate
     */
    getOccupancyRate: function() {
        const total = this.getTotalCapacity();
        const occupied = this.getTotalOccupied();
        return total > 0 ? Math.round((occupied / total) * 100) : 0;
    },

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Check user permissions
     */
    checkUserPermissions: function() {
        const currentUser = AuthContext.getUser();
        if (!currentUser || !currentUser.permissions) return;

        document.querySelectorAll('[data-permission]').forEach(btn => {
            const requiredPerm = btn.getAttribute('data-permission');
            if (!currentUser.permissions.includes(requiredPerm)) {
                btn.style.display = 'none';
            }
        });
    },

    /**
     * Search houses
     */
    searchHouses: function(query) {
        const term = query.toLowerCase();
        this.filteredData = this.houses.filter(h =>
            (h.name || '').toLowerCase().includes(term) ||
            (h.matron || '').toLowerCase().includes(term)
        );
        this.renderHousesTable();
    },

    /**
     * Filter by gender
     */
    filterByGender: function(gender) {
        if (gender) {
            this.filteredData = this.houses.filter(h => h.gender === gender);
        } else {
            this.filteredData = [...this.houses];
        }
        this.renderHousesTable();
    },

    /**
     * Filter by status
     */
    filterByStatus: function(status) {
        if (status) {
            this.filteredData = this.houses.filter(h => h.status === status);
        } else {
            this.filteredData = [...this.houses];
        }
        this.renderHousesTable();
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('boardingContainer')) {
        boardingController.init();
    }
});
