/**
 * Uniform Sales Controller
 * Manages uniform inventory, sales, restocking, and reporting
 */
const UniformSalesController = {
    // State
    uniformItems: [],
    currentSalesPage: 1,
    salesPerPage: 15,
    newSaleModal: null,
    restockModal: null,
    viewSizesModal: null,

    /**
     * Initialize the controller
     */
    init: function() {
        this.newSaleModal = new bootstrap.Modal(document.getElementById('newSaleModal'));
        this.restockModal = new bootstrap.Modal(document.getElementById('restockModal'));
        this.viewSizesModal = new bootstrap.Modal(document.getElementById('viewSizesModal'));

        this.bindEvents();
        this.loadDashboard();
        this.loadUniformItems();
        this.loadStudents();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // New sale form
        document.getElementById('newSaleForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitSale();
        });

        // Restock form
        document.getElementById('restockForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitRestock();
        });

        // Sale item change - load sizes
        document.getElementById('saleItemId').addEventListener('change', (e) => {
            this.loadSizesForSale(e.target.value);
        });

        // Restock item change - load sizes
        document.getElementById('restockItemId').addEventListener('change', (e) => {
            this.loadSizesForRestock(e.target.value);
        });

        // Size change - update price and stock
        document.getElementById('saleSize').addEventListener('change', () => {
            this.updateSalePrice();
        });

        // Quantity change - update total
        document.getElementById('saleQuantity').addEventListener('input', () => {
            this.calculateSaleTotal();
        });

        // Unit price change - update total
        document.getElementById('saleUnitPrice').addEventListener('input', () => {
            this.calculateSaleTotal();
        });

        // Tab change events
        document.getElementById('sales-tab').addEventListener('shown.bs.tab', () => {
            this.loadSales();
        });

        document.getElementById('lowstock-tab').addEventListener('shown.bs.tab', () => {
            this.loadLowStock();
        });

        // Search sales debounce
        let searchTimeout;
        document.getElementById('searchSales').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.loadSales(), 400);
        });
    },

    /**
     * Load dashboard statistics
     */
    loadDashboard: async function() {
        try {
            const [dashboardRes, lowStockRes] = await Promise.all([
                API.inventory.getUniformDashboard(),
                API.inventory.getLowStockUniforms()
            ]);

            if (dashboardRes.success && dashboardRes.data) {
                const metrics = dashboardRes.data.monthly_metrics || {};
                document.getElementById('statMonthlySales').textContent = 
                    this.formatCurrency(metrics.total_revenue || 0);
                document.getElementById('statMonthlySalesCount').textContent = 
                    `${metrics.total_sales || 0} sales`;
                document.getElementById('statPaidAmount').textContent = 
                    this.formatCurrency(metrics.paid_amount || 0);
                document.getElementById('statPendingAmount').textContent = 
                    this.formatCurrency(metrics.pending_amount || 0);

                const stockStatus = dashboardRes.data.inventory_status || {};
                const lowCount = (stockStatus.low_stock || 0) + (stockStatus.out_of_stock || 0);
                document.getElementById('statLowStock').textContent = lowCount;
            }

            if (lowStockRes.success && lowStockRes.data) {
                const summary = lowStockRes.data.summary || {};
                const lowCount = (summary.low || 0) + (summary.critical || 0) + (summary.out_of_stock || 0);
                document.getElementById('lowStockBadge').textContent = lowCount;
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    },

    /**
     * Load uniform items for inventory display
     */
    loadUniformItems: async function() {
        const container = document.getElementById('uniformItemsContainer');
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading uniform inventory...</p>
            </div>
        `;

        try {
            const response = await API.inventory.getUniformItems();
            
            if (response.success && response.data) {
                this.uniformItems = response.data.items || response.data || [];
                this.renderUniformItems();
                this.populateItemDropdowns();
            } else {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load uniform items
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading uniform items:', error);
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading uniform inventory
                    </div>
                </div>
            `;
        }
    },

    /**
     * Render uniform item cards
     */
    renderUniformItems: function() {
        const container = document.getElementById('uniformItemsContainer');

        if (!this.uniformItems || this.uniformItems.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No uniform items found in inventory
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = this.uniformItems.map(item => {
            const totalAvailable = parseInt(item.total_available) || 0;
            const totalSold = parseInt(item.total_sold) || 0;
            const availableSizes = parseInt(item.available_sizes) || 0;
            const reorderLevel = parseInt(item.reorder_level) || 50;
            
            const stockPercentage = Math.min(100, (totalAvailable / reorderLevel) * 100);
            const stockClass = stockPercentage > 50 ? 'bg-success' : stockPercentage > 25 ? 'bg-warning' : 'bg-danger';

            return `
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card uniform-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">${item.name}</h5>
                                    <small class="text-muted"><code>${item.code}</code></small>
                                </div>
                                <span class="badge bg-${item.status === 'active' ? 'success' : 'secondary'}">${item.status}</span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Stock Level</small>
                                    <small class="fw-bold">${totalAvailable} / ${reorderLevel}</small>
                                </div>
                                <div class="progress stock-progress">
                                    <div class="progress-bar ${stockClass}" style="width: ${stockPercentage}%"></div>
                                </div>
                            </div>

                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <h5 class="mb-0 text-primary">${totalAvailable}</h5>
                                    <small class="text-muted">Available</small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-0 text-success">${totalSold}</h5>
                                    <small class="text-muted">Sold</small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-0 text-info">${availableSizes}</h5>
                                    <small class="text-muted">Sizes</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <strong>Unit Price:</strong> ${this.formatCurrency(item.unit_cost)}
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm flex-fill" 
                                        onclick="UniformSalesController.viewSizes(${item.id})">
                                    <i class="fas fa-eye me-1"></i>View Sizes
                                </button>
                                <button class="btn btn-success btn-sm" 
                                        onclick="UniformSalesController.showRestockModal(${item.id})"
                                        title="Restock">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-primary btn-sm" 
                                        onclick="UniformSalesController.showNewSaleModal(${item.id})"
                                        title="Quick Sale">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    /**
     * View sizes for a uniform item
     */
    viewSizes: async function(itemId) {
        const content = document.getElementById('viewSizesContent');
        content.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        `;
        this.viewSizesModal.show();

        try {
            const response = await API.inventory.getUniformSizes(itemId);
            
            if (response.success && response.data) {
                const item = response.data.item || {};
                const sizes = response.data.sizes || [];

                document.getElementById('viewSizesTitle').textContent = item.name || 'Uniform Sizes';

                content.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Size</th>
                                    <th class="text-center">Available</th>
                                    <th class="text-center">Reserved</th>
                                    <th class="text-center">Sold</th>
                                    <th>Unit Price</th>
                                    <th>Last Restocked</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${sizes.map(size => {
                                    const available = parseInt(size.quantity_available) || 0;
                                    const statusClass = available === 0 ? 'table-danger' : 
                                                        available <= 10 ? 'table-warning' : '';
                                    return `
                                        <tr class="${statusClass}">
                                            <td><strong>${size.size}</strong></td>
                                            <td class="text-center">${available}</td>
                                            <td class="text-center">${size.quantity_reserved || 0}</td>
                                            <td class="text-center">${size.quantity_sold || 0}</td>
                                            <td>${this.formatCurrency(size.unit_price)}</td>
                                            <td>${size.last_restocked ? new Date(size.last_restocked).toLocaleDateString() : 'Never'}</td>
                                            <td>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="UniformSalesController.showRestockModal(${itemId}, '${size.size}')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-warning">Failed to load sizes</div>';
            }
        } catch (error) {
            console.error('Error loading sizes:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading sizes</div>';
        }
    },

    /**
     * Load students for dropdown
     */
    loadStudents: async function() {
        try {
            const response = await API.students.get();
            const students = response.data || response || [];
            
            const select = document.getElementById('saleStudentId');
            select.innerHTML = '<option value="">Select Student...</option>' +
                students.map(s => 
                    `<option value="${s.id}">${s.first_name} ${s.last_name} (${s.admission_number || 'N/A'})</option>`
                ).join('');
        } catch (error) {
            console.error('Error loading students:', error);
        }
    },

    /**
     * Populate item dropdowns
     */
    populateItemDropdowns: function() {
        const options = '<option value="">Select Uniform...</option>' +
            this.uniformItems.map(item => 
                `<option value="${item.id}">${item.name} (${item.code})</option>`
            ).join('');

        document.getElementById('saleItemId').innerHTML = options;
        document.getElementById('restockItemId').innerHTML = options;
        document.getElementById('filterItem').innerHTML = '<option value="">All Items</option>' +
            this.uniformItems.map(item => 
                `<option value="${item.id}">${item.name}</option>`
            ).join('');
    },

    /**
     * Load sizes for sale form
     */
    loadSizesForSale: async function(itemId) {
        const select = document.getElementById('saleSize');
        select.innerHTML = '<option value="">Loading...</option>';
        select.disabled = true;
        
        document.getElementById('saleUnitPrice').value = '';
        document.getElementById('saleStockAvailable').value = '';

        if (!itemId) {
            select.innerHTML = '<option value="">Select Size...</option>';
            return;
        }

        try {
            const response = await API.inventory.getUniformSizes(itemId);
            
            if (response.success && response.data) {
                const sizes = response.data.sizes || [];
                select.innerHTML = '<option value="">Select Size...</option>' +
                    sizes.map(s => {
                        const available = parseInt(s.quantity_available) || 0;
                        const disabled = available === 0 ? 'disabled' : '';
                        return `<option value="${s.size}" data-price="${s.unit_price}" data-stock="${available}" ${disabled}>
                            ${s.size} (${available} available) ${available === 0 ? '- OUT OF STOCK' : ''}
                        </option>`;
                    }).join('');
                select.disabled = false;
            }
        } catch (error) {
            console.error('Error loading sizes:', error);
            select.innerHTML = '<option value="">Error loading sizes</option>';
        }
    },

    /**
     * Load sizes for restock form
     */
    loadSizesForRestock: async function(itemId) {
        const select = document.getElementById('restockSize');
        select.innerHTML = '<option value="">Loading...</option>';
        
        if (!itemId) {
            select.innerHTML = '<option value="">Select Size...</option>';
            return;
        }

        try {
            const response = await API.inventory.getUniformSizes(itemId);
            
            if (response.success && response.data) {
                const sizes = response.data.sizes || [];
                select.innerHTML = '<option value="">Select Size...</option>' +
                    sizes.map(s => 
                        `<option value="${s.size}" data-price="${s.unit_price}">
                            ${s.size} (Current: ${s.quantity_available})
                        </option>`
                    ).join('');

                // Set default price
                if (sizes.length > 0) {
                    document.getElementById('restockUnitPrice').value = sizes[0].unit_price;
                }
            }
        } catch (error) {
            console.error('Error loading sizes:', error);
            select.innerHTML = '<option value="">Error loading sizes</option>';
        }
    },

    /**
     * Update sale price based on selected size
     */
    updateSalePrice: function() {
        const sizeSelect = document.getElementById('saleSize');
        const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
        
        if (selectedOption && selectedOption.dataset.price) {
            document.getElementById('saleUnitPrice').value = selectedOption.dataset.price;
            document.getElementById('saleStockAvailable').value = selectedOption.dataset.stock + ' available';
            this.calculateSaleTotal();
        }
    },

    /**
     * Calculate sale total
     */
    calculateSaleTotal: function() {
        const quantity = parseFloat(document.getElementById('saleQuantity').value) || 0;
        const unitPrice = parseFloat(document.getElementById('saleUnitPrice').value) || 0;
        const total = quantity * unitPrice;
        document.getElementById('saleTotalAmount').value = this.formatCurrency(total);
    },

    /**
     * Show new sale modal
     */
    showNewSaleModal: function(itemId = null) {
        document.getElementById('newSaleForm').reset();
        document.getElementById('saleSize').innerHTML = '<option value="">Select Size...</option>';
        document.getElementById('saleTotalAmount').value = '';
        document.getElementById('saleStockAvailable').value = '';

        if (itemId) {
            document.getElementById('saleItemId').value = itemId;
            this.loadSizesForSale(itemId);
        }

        this.newSaleModal.show();
    },

    /**
     * Show restock modal
     */
    showRestockModal: function(itemId = null, size = null) {
        document.getElementById('restockForm').reset();
        this.viewSizesModal.hide();

        if (itemId) {
            document.getElementById('restockItemId').value = itemId;
            this.loadSizesForRestock(itemId).then(() => {
                if (size) {
                    document.getElementById('restockSize').value = size;
                }
            });
        }

        this.restockModal.show();
    },

    /**
     * Submit new sale
     */
    submitSale: async function() {
        const btn = document.getElementById('submitSaleBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        btn.disabled = true;

        try {
            const data = {
                student_id: document.getElementById('saleStudentId').value,
                item_id: document.getElementById('saleItemId').value,
                size: document.getElementById('saleSize').value,
                quantity: parseInt(document.getElementById('saleQuantity').value),
                unit_price: parseFloat(document.getElementById('saleUnitPrice').value),
                notes: document.getElementById('saleNotes').value
            };

            // Validate stock
            const sizeSelect = document.getElementById('saleSize');
            const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
            const availableStock = parseInt(selectedOption?.dataset.stock) || 0;

            if (data.quantity > availableStock) {
                this.showError(`Insufficient stock. Only ${availableStock} available.`);
                return;
            }

            const response = await API.inventory.registerUniformSale(data);

            if (response.success) {
                this.newSaleModal.hide();
                this.showSuccess('Uniform sale recorded successfully');
                this.refresh();
            } else {
                this.showError(response.message || 'Failed to record sale');
            }
        } catch (error) {
            console.error('Error submitting sale:', error);
            this.showError('Error recording sale');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    /**
     * Submit restock
     */
    submitRestock: async function() {
        const btn = document.getElementById('submitRestockBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        btn.disabled = true;

        try {
            const data = {
                item_id: document.getElementById('restockItemId').value,
                size: document.getElementById('restockSize').value,
                quantity: parseInt(document.getElementById('restockQuantity').value),
                unit_price: parseFloat(document.getElementById('restockUnitPrice').value) || null,
                notes: document.getElementById('restockNotes').value
            };

            const response = await API.inventory.restockUniformSize(data);

            if (response.success) {
                this.restockModal.hide();
                this.showSuccess('Stock added successfully');
                this.refresh();
            } else {
                this.showError(response.message || 'Failed to restock');
            }
        } catch (error) {
            console.error('Error restocking:', error);
            this.showError('Error restocking uniform');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    /**
     * Load sales history
     */
    loadSales: async function() {
        const tbody = document.getElementById('salesTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    Loading sales...
                </td>
            </tr>
        `;

        try {
            const params = {
                page: this.currentSalesPage,
                limit: this.salesPerPage,
                item_id: document.getElementById('filterItem').value || null,
                payment_status: document.getElementById('filterPaymentStatus').value || null,
                date_from: document.getElementById('filterDateFrom').value || null,
                date_to: document.getElementById('filterDateTo').value || null
            };

            // Clean up null params
            Object.keys(params).forEach(key => {
                if (!params[key]) delete params[key];
            });

            const response = await API.inventory.listUniformSales(params);

            if (response.success && response.data) {
                const sales = response.data.sales || [];
                const pagination = response.data.pagination || {};

                this.renderSalesTable(sales);
                this.renderSalesPagination(pagination);
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No sales found
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error('Error loading sales:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        Error loading sales
                    </td>
                </tr>
            `;
        }
    },

    /**
     * Render sales table
     */
    renderSalesTable: function(sales) {
        const tbody = document.getElementById('salesTableBody');

        if (!sales || sales.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-receipt fa-2x mb-2"></i><br>
                        No sales found
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = sales.map(sale => {
            const statusBadge = {
                paid: '<span class="badge bg-success payment-badge">Paid</span>',
                pending: '<span class="badge bg-warning text-dark payment-badge">Pending</span>',
                partial: '<span class="badge bg-info payment-badge">Partial</span>'
            };

            return `
                <tr class="sale-row">
                    <td>${new Date(sale.sale_date).toLocaleDateString()}</td>
                    <td>
                        <strong>${sale.student_name}</strong><br>
                        <small class="text-muted">${sale.admission_number || 'N/A'}</small>
                    </td>
                    <td>${sale.item_name}</td>
                    <td><span class="badge bg-secondary">${sale.size}</span></td>
                    <td>${sale.quantity}</td>
                    <td class="fw-bold">${this.formatCurrency(sale.total_amount)}</td>
                    <td>${statusBadge[sale.payment_status] || sale.payment_status}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${sale.payment_status !== 'paid' ? `
                                <button class="btn btn-outline-success" title="Mark as Paid"
                                        onclick="UniformSalesController.updatePaymentStatus(${sale.id}, 'paid')">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-outline-danger" title="Delete"
                                    onclick="UniformSalesController.deleteSale(${sale.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    /**
     * Render sales pagination
     */
    renderSalesPagination: function(pagination) {
        const container = document.getElementById('salesPagination');
        const totalPages = pagination.total_pages || 1;
        const currentPage = pagination.page || 1;

        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="UniformSalesController.goToSalesPage(${currentPage - 1}); return false;">&laquo;</a>
        </li>`;

        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="UniformSalesController.goToSalesPage(${i}); return false;">${i}</a>
            </li>`;
        }

        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="UniformSalesController.goToSalesPage(${currentPage + 1}); return false;">&raquo;</a>
        </li>`;

        container.innerHTML = html;
    },

    /**
     * Go to specific sales page
     */
    goToSalesPage: function(page) {
        this.currentSalesPage = page;
        this.loadSales();
    },

    /**
     * Update payment status
     */
    updatePaymentStatus: async function(saleId, status) {
        if (!confirm('Mark this sale as paid?')) return;

        try {
            const response = await API.inventory.updateUniformPayment(saleId, status);

            if (response.success) {
                this.showSuccess('Payment status updated');
                this.loadSales();
                this.loadDashboard();
            } else {
                this.showError(response.message || 'Failed to update payment');
            }
        } catch (error) {
            console.error('Error updating payment:', error);
            this.showError('Error updating payment status');
        }
    },

    /**
     * Delete a sale
     */
    deleteSale: async function(saleId) {
        if (!confirm('Are you sure you want to delete this sale? Stock will be restored.')) return;

        try {
            const response = await API.inventory.deleteUniformSale ?
                await API.inventory.deleteUniformSale(saleId) :
                await apiCall(`/inventory/uniform-sales/${saleId}`, 'DELETE');

            if (response.success) {
                this.showSuccess('Sale deleted and stock restored');
                this.loadSales();
                this.loadUniformItems();
                this.loadDashboard();
            } else {
                this.showError(response.message || 'Failed to delete sale');
            }
        } catch (error) {
            console.error('Error deleting sale:', error);
            this.showError('Error deleting sale');
        }
    },

    /**
     * Load low stock items
     */
    loadLowStock: async function() {
        const tbody = document.getElementById('lowStockTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading...</td></tr>';

        try {
            const response = await API.inventory.getLowStockUniforms();

            if (response.success && response.data) {
                const items = response.data.items || [];
                
                if (items.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-4 text-success">
                                <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                                All uniforms are well stocked!
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = items.map(item => {
                    const statusBadge = {
                        out_of_stock: '<span class="badge bg-danger">Out of Stock</span>',
                        critical: '<span class="badge bg-warning text-dark">Critical</span>',
                        low: '<span class="badge bg-info">Low</span>'
                    };

                    return `
                        <tr class="${item.stock_status === 'out_of_stock' ? 'table-danger' : item.stock_status === 'critical' ? 'table-warning' : ''}">
                            <td><strong>${item.item_name}</strong></td>
                            <td><span class="badge bg-secondary">${item.size}</span></td>
                            <td class="text-center fw-bold">${item.quantity_available}</td>
                            <td class="text-center">${item.quantity_sold}</td>
                            <td>${this.formatCurrency(item.unit_price)}</td>
                            <td>${statusBadge[item.stock_status] || item.stock_status}</td>
                            <td>
                                <button class="btn btn-success btn-sm"
                                        onclick="UniformSalesController.showRestockModal(${item.item_id}, '${item.size}')">
                                    <i class="fas fa-plus me-1"></i>Restock
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (error) {
            console.error('Error loading low stock:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>';
        }
    },

    /**
     * Load sales report
     */
    loadReport: async function() {
        const container = document.getElementById('reportContainer');
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Generating report...</p>
            </div>
        `;

        try {
            const params = {
                date_from: document.getElementById('reportDateFrom').value,
                date_to: document.getElementById('reportDateTo').value
            };

            const response = await API.inventory.getUniformSalesReport(params);

            if (response.success && response.data) {
                this.renderReport(response.data);
            } else {
                container.innerHTML = '<div class="alert alert-warning">Failed to generate report</div>';
            }
        } catch (error) {
            console.error('Error loading report:', error);
            container.innerHTML = '<div class="alert alert-danger">Error generating report</div>';
        }
    },

    /**
     * Render sales report
     */
    renderReport: function(data) {
        const container = document.getElementById('reportContainer');
        const summary = data.summary || {};
        const byItem = data.by_item || [];
        const bySize = data.by_size || [];

        container.innerHTML = `
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4 class="mb-0">${summary.total_sales || 0}</h4>
                            <small class="text-muted">Total Sales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4 class="mb-0">${summary.total_items || 0}</h4>
                            <small class="text-muted">Items Sold</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4 class="mb-0 text-success">${this.formatCurrency(summary.total_revenue || 0)}</h4>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4 class="mb-0 text-warning">${this.formatCurrency(summary.total_pending || 0)}</h4>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Sales by Item -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Sales by Item</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Paid</th>
                                            <th class="text-end">Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${byItem.map(item => `
                                            <tr>
                                                <td><strong>${item.item_name}</strong></td>
                                                <td class="text-center">${item.total_quantity}</td>
                                                <td class="text-end">${this.formatCurrency(item.total_amount)}</td>
                                                <td class="text-end text-success">${this.formatCurrency(item.paid_amount)}</td>
                                                <td class="text-end text-warning">${this.formatCurrency(item.pending_amount)}</td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="5" class="text-center">No data</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales by Size -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Sales by Size</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Size</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${bySize.map(s => `
                                            <tr>
                                                <td><span class="badge bg-secondary">${s.size}</span></td>
                                                <td class="text-center">${s.total_quantity}</td>
                                                <td class="text-end">${this.formatCurrency(s.total_amount)}</td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="3" class="text-center">No data</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Refresh all data
     */
    refresh: function() {
        this.loadDashboard();
        this.loadUniformItems();
        
        // Refresh active tab
        const activeTab = document.querySelector('#uniformTabs .nav-link.active');
        if (activeTab) {
            if (activeTab.id === 'sales-tab') this.loadSales();
            else if (activeTab.id === 'lowstock-tab') this.loadLowStock();
        }

        this.showSuccess('Data refreshed');
    },

    /**
     * Format currency
     */
    formatCurrency: function(amount) {
        return 'KES ' + parseFloat(amount || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    /**
     * Show success message
     */
    showSuccess: function(message) {
        this.showToast(message, 'success');
    },

    /**
     * Show error message
     */
    showError: function(message) {
        this.showToast(message, 'danger');
    },

    /**
     * Show toast notification
     */
    showToast: function(message, type = 'info') {
        // Remove existing toasts
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = `toast-notification alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 4000);
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    UniformSalesController.init();
});
