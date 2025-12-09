/**
 * Transport Controller
 * Handles transport management: routes, stops, vehicles, drivers, student assignments, payments
 * Integrates with /api/transport endpoints
 */

const transportController = {
    routes: [],
    stops: [],
    vehicles: [],
    drivers: [],
    assignments: [],
    filteredData: [],
    currentFilters: {},

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            showNotification('Loading transport data...', 'info');
            await Promise.all([
                this.loadRoutes(),
                this.loadStops(),
                this.loadDrivers()
            ]);
            this.checkUserPermissions();
            showNotification('Transport management loaded successfully', 'success');
        } catch (error) {
            console.error('Error initializing transport controller:', error);
            showNotification('Failed to load transport management', 'error');
        }
    },

    // ============================================================================
    // ROUTES MANAGEMENT
    // ============================================================================

    /**
     * Load all routes
     */
    loadRoutes: async function() {
        try {
            const response = await API.transport.getAllRoutes();
            this.routes = response.data || response || [];
            this.filteredData = [...this.routes];
            this.renderRoutesTable();
        } catch (error) {
            console.error('Error loading routes:', error);
            const container = document.getElementById('routesContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load routes</div>';
            }
        }
    },

    /**
     * Render routes table
     */
    renderRoutesTable: function() {
        const container = document.getElementById('routesContainer');
        if (!container) return;

        if (this.filteredData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No routes found. Click "Add Route" to create one.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-info">
                        <tr>
                            <th>Route Name</th>
                            <th>Code</th>
                            <th>Stops</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredData.forEach(route => {
            const statusBadge = this.getStatusBadge(route.status);
            
            html += `
                <tr>
                    <td><strong>${route.route_name}</strong></td>
                    <td>${route.route_code || 'N/A'}</td>
                    <td><span class="badge bg-primary">${route.stop_count || 0} stops</span></td>
                    <td>${route.vehicle_number || 'Not Assigned'}</td>
                    <td>${route.driver_name || 'Not Assigned'}</td>
                    <td>${route.students_count || 0} / ${route.capacity || 0}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="transportController.viewRoute(${route.route_id || route.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="transportController.editRoute(${route.route_id || route.id})" title="Edit" data-permission="transport_update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-success" onclick="transportController.viewStudents(${route.route_id || route.id})" title="View Students">
                                <i class="bi bi-people"></i>
                            </button>
                            <button class="btn btn-danger" onclick="transportController.deleteRoute(${route.route_id || route.id})" title="Delete" data-permission="transport_delete">
                                <i class="bi bi-trash"></i>
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
            'suspended': '<span class="badge bg-danger">Suspended</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * Create route
     */
    createRoute: async function() {
        try {
            const data = {
                route_name: prompt('Route Name:'),
                route_code: prompt('Route Code:'),
                capacity: parseInt(prompt('Capacity:')),
                description: prompt('Description (optional):')
            };

            await API.transport.createRoute(data);
            showNotification('Route created successfully', 'success');
            await this.loadRoutes();
        } catch (error) {
            console.error('Error creating route:', error);
            showNotification('Failed to create route', 'error');
        }
    },

    /**
     * View route details
     */
    viewRoute: async function(routeId) {
        try {
            const route = await API.transport.getRoute(routeId);
            const students = await API.transport.getStudentsByRoute(routeId);
            
            alert(`Route Details:\n\nName: ${route.route_name}\nCode: ${route.route_code}\nStops: ${route.stop_count || 0}\nStudents: ${students.length || 0}\nCapacity: ${route.capacity}\nStatus: ${route.status}`);
        } catch (error) {
            console.error('Error loading route:', error);
            showNotification('Failed to load route details', 'error');
        }
    },

    /**
     * Edit route
     */
    editRoute: function(routeId) {
        console.log('Edit route feature coming soon');
    },

    /**
     * Delete route
     */
    deleteRoute: async function(routeId) {
        if (!confirm('Are you sure you want to delete this route?')) return;

        try {
            await API.transport.deleteRoute(routeId);
            showNotification('Route deleted successfully', 'success');
            await this.loadRoutes();
        } catch (error) {
            console.error('Error deleting route:', error);
            showNotification('Failed to delete route', 'error');
        }
    },

    /**
     * View students on route
     */
    viewStudents: async function(routeId) {
        try {
            const students = await API.transport.getStudentsByRoute(routeId);
            
            let message = 'Students on Route:\n\n';
            if (students.length > 0) {
                students.forEach(student => {
                    message += `- ${student.first_name} ${student.last_name} (${student.student_id})\n`;
                });
            } else {
                message += 'No students assigned to this route.';
            }
            
            alert(message);
        } catch (error) {
            console.error('Error loading students:', error);
            showNotification('Failed to load students', 'error');
        }
    },

    // ============================================================================
    // STOPS MANAGEMENT
    // ============================================================================

    /**
     * Load stops
     */
    loadStops: async function() {
        try {
            const response = await API.transport.getAllStops();
            this.stops = response.data || response || [];
        } catch (error) {
            console.error('Error loading stops:', error);
            this.stops = [];
        }
    },

    /**
     * Create stop
     */
    createStop: async function() {
        try {
            const data = {
                stop_name: prompt('Stop Name:'),
                location: prompt('Location:'),
                coordinates: prompt('Coordinates (optional):'),
                route_id: prompt('Route ID:')
            };

            await API.transport.createStop(data);
            showNotification('Stop created successfully', 'success');
            await this.loadStops();
        } catch (error) {
            console.error('Error creating stop:', error);
            showNotification('Failed to create stop', 'error');
        }
    },

    /**
     * Delete stop
     */
    deleteStop: async function(stopId) {
        if (!confirm('Delete this stop?')) return;

        try {
            await API.transport.deleteStop(stopId);
            showNotification('Stop deleted successfully', 'success');
            await this.loadStops();
        } catch (error) {
            console.error('Error deleting stop:', error);
            showNotification('Failed to delete stop', 'error');
        }
    },

    // ============================================================================
    // DRIVERS MANAGEMENT
    // ============================================================================

    /**
     * Load drivers
     */
    loadDrivers: async function() {
        try {
            const response = await API.transport.getAllDrivers();
            this.drivers = response.data || response || [];
        } catch (error) {
            console.error('Error loading drivers:', error);
            this.drivers = [];
        }
    },

    /**
     * Create driver
     */
    createDriver: async function() {
        try {
            const data = {
                first_name: prompt('First Name:'),
                last_name: prompt('Last Name:'),
                license_number: prompt('License Number:'),
                phone: prompt('Phone:'),
                email: prompt('Email (optional):')
            };

            await API.transport.createDriver(data);
            showNotification('Driver created successfully', 'success');
            await this.loadDrivers();
        } catch (error) {
            console.error('Error creating driver:', error);
            showNotification('Failed to create driver', 'error');
        }
    },

    /**
     * Assign driver to route
     */
    assignDriver: async function() {
        const routeId = prompt('Route ID:');
        const driverId = prompt('Driver ID:');
        
        if (!routeId || !driverId) return;

        try {
            await API.transport.assignDriver({
                route_id: routeId,
                driver_id: driverId
            });
            showNotification('Driver assigned successfully', 'success');
            await this.loadRoutes();
        } catch (error) {
            console.error('Error assigning driver:', error);
            showNotification('Failed to assign driver', 'error');
        }
    },

    /**
     * Delete driver
     */
    deleteDriver: async function(driverId) {
        if (!confirm('Delete this driver?')) return;

        try {
            await API.transport.deleteDriver(driverId);
            showNotification('Driver deleted successfully', 'success');
            await this.loadDrivers();
        } catch (error) {
            console.error('Error deleting driver:', error);
            showNotification('Failed to delete driver', 'error');
        }
    },

    // ============================================================================
    // STUDENT ASSIGNMENTS
    // ============================================================================

    /**
     * Assign student to route
     */
    assignStudent: async function() {
        try {
            const data = {
                student_id: prompt('Student ID:'),
                route_id: prompt('Route ID:'),
                stop_id: prompt('Stop ID:'),
                pickup_time: prompt('Pickup Time (HH:MM):')
            };

            await API.transport.assignStudent(data);
            showNotification('Student assigned to route successfully', 'success');
        } catch (error) {
            console.error('Error assigning student:', error);
            showNotification('Failed to assign student', 'error');
        }
    },

    /**
     * Withdraw student assignment
     */
    withdrawAssignment: async function(studentId) {
        if (!confirm('Withdraw this student from transport?')) return;

        try {
            await API.transport.withdrawAssignment({ student_id: studentId });
            showNotification('Student withdrawn from transport successfully', 'success');
        } catch (error) {
            console.error('Error withdrawing student:', error);
            showNotification('Failed to withdraw student', 'error');
        }
    },

    /**
     * Verify student
     */
    verifyStudent: async function() {
        const studentId = prompt('Enter Student ID or scan QR code:');
        if (!studentId) return;

        try {
            const response = await API.transport.verifyStudent({ student_id: studentId });
            
            if (response.verified) {
                showNotification(`Student verified: ${response.student_name}`, 'success');
            } else {
                showNotification('Student not verified for transport', 'warning');
            }
        } catch (error) {
            console.error('Error verifying student:', error);
            showNotification('Failed to verify student', 'error');
        }
    },

    // ============================================================================
    // PAYMENTS
    // ============================================================================

    /**
     * Record transport payment
     */
    recordPayment: async function() {
        try {
            const data = {
                student_id: prompt('Student ID:'),
                amount: parseFloat(prompt('Amount:')),
                payment_method: prompt('Payment Method (Cash/M-Pesa/Bank):'),
                reference_no: prompt('Reference Number:'),
                period: prompt('Period (e.g., Term 1 2025):')
            };

            await API.transport.recordPayment(data);
            showNotification('Payment recorded successfully', 'success');
        } catch (error) {
            console.error('Error recording payment:', error);
            showNotification('Failed to record payment', 'error');
        }
    },

    /**
     * View payment summary
     */
    viewPaymentSummary: async function(studentId) {
        try {
            const summary = await API.transport.getPaymentSummary(studentId);
            
            let message = 'Transport Payment Summary:\n\n';
            message += `Total Paid: KES ${summary.total_paid || 0}\n`;
            message += `Outstanding: KES ${summary.outstanding || 0}\n`;
            message += `Last Payment: ${summary.last_payment_date || 'N/A'}\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading payment summary:', error);
            showNotification('Failed to load payment summary', 'error');
        }
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
     * Show quick actions
     */
    showQuickActions: function() {
        alert('Quick Actions:\n1. Add Route\n2. Add Stop\n3. Add Driver\n4. Assign Student\n5. Verify Student\n6. Record Payment');
    },

    /**
     * Search routes
     */
    searchRoutes: function(query) {
        const term = query.toLowerCase();
        this.filteredData = this.routes.filter(r =>
            (r.route_name || '').toLowerCase().includes(term) ||
            (r.route_code || '').toLowerCase().includes(term)
        );
        this.renderRoutesTable();
    },

    /**
     * Filter by status
     */
    filterByStatus: function(status) {
        if (status) {
            this.filteredData = this.routes.filter(r => r.status === status);
        } else {
            this.filteredData = [...this.routes];
        }
        this.renderRoutesTable();
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('routesContainer') || document.getElementById('transportContainer')) {
        transportController.init();
    }
});
