/**
 * Specialized Components for common UI patterns
 * - Status badges with colors
 * - Progress indicators
 * - Form helpers
 * - Document upload
 * - QR code display
 */

class UIComponents {
    /**
     * Create a progress indicator for multi-step workflows
     */
    static createProgressIndicator(steps, currentStep) {
        return `
            <div class="progress mb-4" style="height: 30px;">
                ${steps.map((step, idx) => {
                    const isActive = idx === currentStep;
                    const isCompleted = idx < currentStep;
                    const width = (100 / steps.length);
                    
                    return `
                        <div class="progress-bar ${isCompleted ? 'bg-success' : isActive ? 'bg-primary' : 'bg-secondary'}" 
                             role="progressbar" 
                             style="width: ${width}%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;"
                             title="${step}">
                            <span style="font-size: 0.8rem;">${idx + 1}. ${step}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    /**
     * Create status timeline for workflows
     */
    static createTimeline(statuses) {
        return `
            <div class="timeline">
                ${statuses.map((status, idx) => `
                    <div class="timeline-item ${status.completed ? 'completed' : ''}">
                        <div class="timeline-marker ${status.completed ? 'bg-success' : 'bg-secondary'}">
                            ${status.completed ? '<i class="bi bi-check"></i>' : '<i class="bi bi-circle-fill"></i>'}
                        </div>
                        <div class="timeline-content">
                            <h6>${status.title}</h6>
                            ${status.date ? `<small class="text-muted">${new Date(status.date).toLocaleString()}</small>` : ''}
                            ${status.description ? `<p class="mb-0">${status.description}</p>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    /**
     * Create file upload dropzone
     */
    static createDropzone(fileInputId, acceptedTypes = '*') {
        return `
            <div class="dropzone-wrapper" data-input-id="${fileInputId}">
                <div class="dropzone-area p-5 border-2 border-dashed rounded text-center" style="background-color: #f8f9fa; cursor: pointer;">
                    <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #6c757d;"></i>
                    <p class="mt-3 mb-0">
                        <strong>Drop files here</strong><br>
                        <small class="text-muted">or click to browse</small>
                    </p>
                    <input type="file" id="${fileInputId}" accept="${acceptedTypes}" style="display: none;" multiple>
                </div>
                <div class="dropzone-files mt-3"></div>
            </div>
        `;
    }

    /**
     * Create document card with preview
     */
    static createDocumentCard(document) {
        const getDocumentIcon = (type) => {
            const icons = {
                'pdf': 'bi-file-pdf',
                'doc': 'bi-file-word',
                'docx': 'bi-file-word',
                'xls': 'bi-file-excel',
                'xlsx': 'bi-file-excel',
                'ppt': 'bi-file-pptx',
                'pptx': 'bi-file-pptx',
                'jpg': 'bi-image',
                'jpeg': 'bi-image',
                'png': 'bi-image',
                'gif': 'bi-image'
            };
            return icons[type?.toLowerCase()] || 'bi-file';
        };

        return `
            <div class="card document-card mb-2">
                <div class="card-body d-flex align-items-center">
                    <i class="bi ${getDocumentIcon(document.type)} me-3" style="font-size: 1.5rem;"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${document.name}</h6>
                        <small class="text-muted">${(document.size / 1024).toFixed(2)} KB • Uploaded ${new Date(document.uploadedAt).toLocaleDateString()}</small>
                    </div>
                    <div class="document-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument('${document.id}')">
                            <i class="bi bi-download"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument('${document.id}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create QR code display
     */
    static createQRCodeDisplay(qrData, studentName = '') {
        return `
            <div class="qr-code-wrapper text-center p-4 border rounded">
                <img src="${qrData}" alt="Student QR Code" class="img-fluid mb-3" style="max-width: 300px;">
                <p class="mb-0"><strong>${studentName}</strong></p>
                <small class="text-muted">Scan this code to view student information</small>
                <div class="mt-3">
                    <button class="btn btn-sm btn-primary" onclick="downloadQRCode('${qrData}')">
                        <i class="bi bi-download"></i> Download
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="printQRCode('${qrData}')">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Create payment status indicator
     */
    static createPaymentStatus(status, amount, dueDate = null) {
        const statusConfig = {
            'paid': { badge: 'success', icon: 'bi-check-circle', label: 'Paid' },
            'pending': { badge: 'warning', icon: 'bi-clock', label: 'Pending' },
            'overdue': { badge: 'danger', icon: 'bi-exclamation-circle', label: 'Overdue' },
            'partial': { badge: 'info', icon: 'bi-dash-circle', label: 'Partial' },
            'cancelled': { badge: 'secondary', icon: 'bi-x-circle', label: 'Cancelled' }
        };

        const config = statusConfig[status] || statusConfig['pending'];

        return `
            <div class="payment-status p-3 border rounded">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi ${config.icon} me-2"></i>
                    <span class="badge bg-${config.badge}">${config.label}</span>
                </div>
                <div class="amount mb-2">
                    <strong>KES ${parseFloat(amount).toFixed(2)}</strong>
                </div>
                ${dueDate ? `
                    <small class="text-muted">
                        Due: ${new Date(dueDate).toLocaleDateString()}
                    </small>
                ` : ''}
            </div>
        `;
    }

    /**
     * Create attendance summary card
     */
    static createAttendanceSummary(attendance) {
        const total = attendance.present + attendance.absent + attendance.late;
        const presentPercentage = total > 0 ? ((attendance.present / total) * 100).toFixed(1) : 0;

        return `
            <div class="attendance-summary card">
                <div class="card-body">
                    <h6 class="card-title">Attendance</h6>
                    <div class="row text-center">
                        <div class="col">
                            <div class="attendance-stat">
                                <strong style="color: #28a745;">${attendance.present}</strong>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="attendance-stat">
                                <strong style="color: #dc3545;">${attendance.absent}</strong>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="attendance-stat">
                                <strong style="color: #ffc107;">${attendance.late}</strong>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: ${presentPercentage}%"></div>
                        </div>
                        <small class="text-muted">${presentPercentage}% attendance rate</small>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create grade badge
     */
    static createGradeBadge(grade, marks = null) {
        const gradeConfig = {
            'A': { color: '#28a745', range: '80-100' },
            'B': { color: '#17a2b8', range: '70-79' },
            'C': { color: '#ffc107', range: '60-69' },
            'D': { color: '#fd7e14', range: '50-59' },
            'E': { color: '#dc3545', range: '0-49' }
        };

        const config = gradeConfig[grade?.toUpperCase()] || { color: '#6c757d', range: 'N/A' };

        return `
            <div class="grade-badge" style="display: inline-block; padding: 0.5rem 1rem; background-color: ${config.color}; color: white; border-radius: 0.25rem; font-weight: bold;">
                ${grade || 'N/A'}
                ${marks !== null && marks !== undefined ? `<small style="opacity: 0.8;">(${marks}%)</small>` : ''}
            </div>
        `;
    }

    /**
     * Create role/permission badge
     */
    static createRoleBadge(role) {
        const roleColors = {
            'admin': 'danger',
            'teacher': 'primary',
            'student': 'success',
            'parent': 'info',
            'staff': 'warning'
        };

        const variant = roleColors[role?.toLowerCase()] || 'secondary';
        return `<span class="badge bg-${variant}">${role}</span>`;
    }

    /**
     * Create stat card
     */
    static createStatCard(title, value, icon = '', trend = null, color = 'primary') {
        return `
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">${title}</h6>
                            <h3 class="mb-0">${value}</h3>
                            ${trend ? `
                                <small class="text-${trend.value > 0 ? 'success' : 'danger'}">
                                    <i class="bi bi-arrow-${trend.value > 0 ? 'up' : 'down'}"></i>
                                    ${Math.abs(trend.value)}% ${trend.label || 'from last month'}
                                </small>
                            ` : ''}
                        </div>
                        ${icon ? `<i class="bi ${icon}" style="font-size: 2rem; opacity: 0.3;"></i>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create empty state message
     */
    static createEmptyState(icon, title, message, action = null) {
        return `
            <div class="empty-state text-center py-5">
                <i class="bi ${icon}" style="font-size: 3rem; color: #ccc;"></i>
                <h5 class="mt-3">${title}</h5>
                <p class="text-muted">${message}</p>
                ${action ? `
                    <button class="btn btn-primary">${action.label}</button>
                ` : ''}
            </div>
        `;
    }

    /**
     * Create alert message
     */
    static createAlert(type, title, message, dismissible = true) {
        const alertId = 'alert-' + Math.random().toString(36).substr(2, 9);
        return `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                ${title ? `<strong>${title}:</strong> ` : ''}
                ${message}
                ${dismissible ? `<button type="button" class="btn-close" data-bs-dismiss="alert"></button>` : ''}
            </div>
        `;
    }

    /**
     * Create loading spinner
     */
    static createSpinner(text = 'Loading...') {
        return `
            <div class="text-center py-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">${text}</p>
            </div>
        `;
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UIComponents;
}
