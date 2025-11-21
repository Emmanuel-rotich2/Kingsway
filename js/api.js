// Only define API_BASE_URL if not already defined
if (typeof API_BASE_URL === 'undefined') {
    var API_BASE_URL = '/Kingsway/api';
}

// Notification types
const NOTIFICATION_TYPES = {
    SUCCESS: 'success',
    ERROR: 'error',
    WARNING: 'warning',
    INFO: 'info'
};

// Icons for different notification types
const NOTIFICATION_ICONS = {
    success: 'bi-check-circle',
    error: 'bi-x-circle',
    warning: 'bi-exclamation-triangle',
    info: 'bi-info-circle'
};

// Show notification using Bootstrap modal
function showNotification(message, type = NOTIFICATION_TYPES.INFO) {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const icon = modal.querySelector('.notification-icon i');
    const messageDiv = modal.querySelector('.notification-message');

    // Remove existing notification classes
    modalContent.classList.remove(
        'notification-success',
        'notification-error',
        'notification-warning',
        'notification-info'
    );

    // Add appropriate notification class
    modalContent.classList.add(`notification-${type}`);

    // Set icon
    icon.className = `bi ${NOTIFICATION_ICONS[type]}`;

    // Set message
    messageDiv.textContent = message;

    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Handle API Response
function handleApiResponse(response, showSuccess = true) {
    if (response.status === 'success') {
        if (showSuccess && response.message) {
            showNotification(response.message, NOTIFICATION_TYPES.SUCCESS);
        }
        // For sidebar endpoint, return the entire response
        if (response.data?.sidebar !== undefined) {
            return response;
        }
        // For other endpoints, return just the data
        return response.data !== undefined ? response.data : response;
    } else {
        const error = new Error(response.message || 'API call failed');
        error.response = response;
        throw error;
    }
}

// Handle API Error
function handleApiError(error) {
    console.error('API Error:', error);
    const message = error.response?.message || error.message || 'An unexpected error occurred';
    showNotification(message, NOTIFICATION_TYPES.ERROR);
    throw error;
}

// Download file helper
async function downloadFile(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

// Generic API call function using fetch
async function apiCall(endpoint, method = 'GET', data = null, params = {}, options = {}) {
    try {
        // Construct URL with query parameters
        const url = new URL(API_BASE_URL + endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        // Request options
        const fetchOptions = {
            method: method,
            headers: {
                ...(options.isFile ? {} : { 'Content-Type': 'application/json' }),
                // Add Authorization header if token exists
                ...(localStorage.getItem('token') && {
                    'Authorization': 'Bearer ' + localStorage.getItem('token')
                }),
                ...options.headers
            }
        };

        // Add body for POST/PUT requests
        if (data) {
            if (options.isFile) {
                fetchOptions.body = data;
            } else if (['POST', 'PUT', 'PATCH'].includes(method)) {
                fetchOptions.body = JSON.stringify(data);
            }
        }

        const response = await fetch(url, fetchOptions);

        // If not JSON, throw a clear error
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('API did not return JSON. Response: ' + text.substring(0, 200));
        }

        // Handle file downloads
        if (options.isDownload) {
            if (!response.ok) {
                throw new Error('File download failed');
            }
            const blob = await response.blob();
            const filename = options.filename ||
                response.headers.get('content-disposition')?.split('filename=')[1] ||
                'download';
            await downloadFile(blob, filename);
            return { status: 'success', message: 'File downloaded successfully' };
        }

        // Handle regular JSON responses
        const result = await response.json();
        return handleApiResponse(result, options.showSuccess !== false);
    } catch (error) {
        return handleApiError(error);
    }
}

// File upload helper
function createFormData(data, files = {}) {
    const formData = new FormData();
    Object.keys(data || {}).forEach(key => formData.append(key, data[key]));
    Object.keys(files).forEach(key => {
        if (Array.isArray(files[key])) {
            files[key].forEach(file => formData.append(key + '[]', file));
        } else {
            formData.append(key, files[key]);
        }
    });
    return formData;
}

//attach API to window for global access
window.API = {
    apiCall,
    showNotification,
    
    auth: {
        login: async (username, password) => {
            const response = await apiCall('/auth.php', 'POST', { username, password }, { action: 'login' });
            if (response && response.token) {
                localStorage.setItem('token', response.token);
                localStorage.setItem('user_data', JSON.stringify(response.user || {}));
                const modal = document.getElementById('loginModal');
                if (modal) {
                    const bsModal = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
                    bsModal.hide();
                }
                window.location.href = '/Kingsway/home.php';
            }
            return response;
        },
        resetPassword: async (email) =>
            apiCall('/auth.php', 'POST', { email }, { action: 'reset-password' }),
        changePassword: async (data) =>
            apiCall('/auth.php', 'POST', data, { action: 'change-password' }),
        logout: async () => {
            localStorage.removeItem('token');
            window.location.href = '/KingsWay/index.php';
        }
    },

    users: {
        list: async (params = {}) =>
            apiCall('/users.php', 'GET', null, { action: 'list', ...params }),
        get: async (id) =>
            apiCall('/users.php', 'GET', null, { action: 'view', id }),
        create: async (userData, files = {}) => {
            const formData = createFormData(userData, files);
            return apiCall('/users.php', 'POST', formData, { action: 'add' }, { isFile: true });
        },
        update: async (id, userData, files = {}) => {
            const formData = createFormData(userData, files);
            return apiCall('/users.php', 'POST', formData, { action: 'update', id }, { isFile: true });
        },
        delete: async (id) =>
            apiCall('/users.php', 'POST', null, { action: 'delete', id }),
        getRoles: async () =>
            apiCall('/users.php', 'GET', null, { action: 'roles' }),
        getPermissions: async () =>
            apiCall('/users.php', 'GET', null, { action: 'permissions' }),
        assignRole: async (id, roleData) =>
            apiCall('/users.php', 'POST', roleData, { action: 'assign-role', id }),
        assignPermission: async (id, permissionData) =>
            apiCall('/users.php', 'POST', permissionData, { action: 'assign-permission', id }),
        updatePermissions: async (id, permissions) =>
            apiCall('/users.php', 'POST', { permissions }, { action: 'update-permissions', id }),
        getProfile: async (id) =>
            apiCall('/users.php', 'GET', null, { action: 'profile', id }),
        getSidebar: async (user_id) =>
            apiCall('/users.php', 'GET', null, { action: 'sidebar', user_id: user_id }),
        bulkInsert: async (data) =>
            apiCall('/users.php', 'POST', data, { action: 'bulk_insert' }),
        bulkUpdate: async (data) =>
            apiCall('/users.php', 'POST', data, { action: 'bulk_update' }),
        bulkDelete: async (ids) =>
            apiCall('/users.php', 'POST', { ids }, { action: 'bulk_delete' }),
        export: async (format = 'csv') =>
            window.open(`${API_BASE_URL}/users.php?action=export&format=${format}`, '_blank')
    },

    students: {
        list: async (params = {}) =>
            apiCall('/students.php', 'GET', null, { action: 'list', ...params }),
        get: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'view', id }),
        create: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/students.php', 'POST', formData, { action: 'add' }, { isFile: true });
        },
        update: async (id, data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/students.php', 'POST', formData, { action: 'update', id }, { isFile: true });
        },
        delete: async (id) =>
            apiCall('/students.php', 'POST', null, { action: 'delete', id }),
        getProfile: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'profile', id }),
        getAttendance: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'attendance', id }),
        getPerformance: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'performance', id }),
        getFees: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'fees', id }),
        promote: async (id, data) =>
            apiCall('/students.php', 'POST', data, { action: 'promote', id }),
        transfer: async (id, data) =>
            apiCall('/students.php', 'POST', data, { action: 'transfer', id }),
        generateQRCode: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'generate-qr', id }, { isDownload: true }),
        getQRInfo: async (id) =>
            apiCall('/students.php', 'GET', null, { action: 'qr-info', id }),
        bulkCreate: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/students.php', 'POST', formData, { action: 'bulk-create' }, { isFile: true });
        },
        bulkUpdate: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/students.php', 'POST', formData, { action: 'bulk-update' }, { isFile: true });
        },
        bulkInsert: async (data) =>
            apiCall('/students.php', 'POST', data, { action: 'bulk_insert' }),
        bulkDelete: async (ids) =>
            apiCall('/students.php', 'POST', { ids }, { action: 'bulk_delete' }),
        export: async (format = 'csv') =>
            window.open(`${API_BASE_URL}/students.php?action=export&format=${format}`, '_blank')
    },

    reports: {
        list: async (params = {}) =>
            apiCall('/reports.php', 'GET', null, { action: 'list', ...params }),
        get: async (id) =>
            apiCall('/reports.php', 'GET', null, { action: 'view', id }),
        generate: async (data) =>
            apiCall('/reports.php', 'POST', data, { action: 'generate' }),
        getAcademicReport: async (params = {}) =>
            apiCall('/reports.php', 'GET', null, { action: 'academic', ...params }),
        getSystemReports: async (params = {}) =>
            apiCall('/reports.php', 'GET', null, { action: 'system', ...params }),
        getAuditReports: async (params = {}) =>
            apiCall('/reports.php', 'GET', null, { action: 'audit', ...params }),
        getDashboardStats: async (params = {}) => {
            try {
                const res = await apiCall('/reports.php', 'GET', null, { action: 'dashboard_stats', ...params });
                // Fallback dummy data if no data returned
                if (!res || !res.data) {
                    return {
                        students: { total: 0, growth: 0, by_class: [], by_gender: { male: 0, female: 0 }, by_status: { active: 0, inactive: 0, suspended: 0 } },
                        staff: { total: 0, teaching: 0, non_teaching: 0, growth: 0, present: 0, on_leave: 0, by_department: [], by_role: { teaching: 0, non_teaching: 0, admin: 0 } },
                        attendance: { today: 0, total: 0, rate: 0, by_class: [], trend: [], by_status: { present: 0, absent: 0, late: 0 } },
                        finance: { total: 0, paid: 0, unpaid: 0, growth: 0, by_type: [], by_status: [], trend: [] },
                        activities: { total: 0, upcoming: [] },
                        schedules: { total: 0, today: [] }
                    };
                }
                return res.data;
            } catch (e) {
                // Fallback dummy data on error
                return {
                    students: { total: 0, growth: 0, by_class: [], by_gender: { male: 0, female: 0 }, by_status: { active: 0, inactive: 0, suspended: 0 } },
                    staff: { total: 0, teaching: 0, non_teaching: 0, growth: 0, present: 0, on_leave: 0, by_department: [], by_role: { teaching: 0, non_teaching: 0, admin: 0 } },
                    attendance: { today: 0, total: 0, rate: 0, by_class: [], trend: [], by_status: { present: 0, absent: 0, late: 0 } },
                    finance: { total: 0, paid: 0, unpaid: 0, growth: 0, by_type: [], by_status: [], trend: [] },
                    activities: { total: 0, upcoming: [] },
                    schedules: { total: 0, today: [] }
                };
            }
        },
        getCustomReport: async (params = {}) =>
            apiCall('/reports.php', 'POST', params, { action: 'custom' }),
        export: async (format = 'csv') =>
            apiCall('/reports.php', 'GET', null, { action: 'export', ...params }, { isDownload: true })
    },

    staff: {
        list: async (params = {}) =>
            apiCall('/staff.php', 'GET', null, { action: 'list', ...params }),
        get: async (id) =>
            apiCall('/staff.php', 'GET', null, { action: 'view', id }),
        create: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/staff.php', 'POST', formData, { action: 'add' }, { isFile: true });
        },
        update: async (id, data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/staff.php', 'POST', formData, { action: 'update', id }, { isFile: true });
        },
        delete: async (id) =>
            apiCall('/staff.php', 'POST', null, { action: 'delete', id }),
        getProfile: async (id) =>
            apiCall('/staff.php', 'GET', null, { action: 'profile', id }),
        assignRole: async (id, roleData) =>
            apiCall('/staff.php', 'POST', roleData, { action: 'assign-role', id }),
        updatePermissions: async (id, permissions) =>
            apiCall('/staff.php', 'POST', { permissions }, { action: 'update-permissions', id }),
        export: async (format = 'csv') =>
            apiCall('/staff.php', 'GET', null, { action: 'export', format }, { isDownload: true })
    },

    academic: {
        getClasses: async (params = {}) =>
            apiCall('/academic.php', 'GET', null, { action: 'classes', ...params }),
        getSubjects: async (params = {}) =>
            apiCall('/academic.php', 'GET', null, { action: 'subjects', ...params }),
        getTeacherClasses: async (teacherId) =>
            apiCall('/academic.php', 'GET', null, { action: 'teacher_classes', teacher_id: teacherId }),
        enterResults: async (data) =>
            apiCall('/academic.php', 'POST', data, { action: 'enter_results' }),
        getResults: async (params = {}) =>
            apiCall('/academic.php', 'GET', null, { action: 'results', ...params }),
        exportResults: async (params = {}) =>
            apiCall('/academic.php', 'GET', null, { action: 'export_results', ...params }, { isDownload: true }),
        generateReportCards: async (params = {}) =>
            apiCall('/academic.php', 'GET', null, { action: 'report_cards', ...params }, { isDownload: true })
    },

    attendance: {
        markAttendance: async (data) =>
            apiCall('/attendance.php', 'POST', data, { action: 'mark' }),
        getAttendance: async (params = {}) =>
            apiCall('/attendance.php', 'GET', null, { action: 'view', ...params }),
        getStats: async (params = {}) =>
            apiCall('/attendance.php', 'GET', null, { action: 'stats', ...params }),
        exportReport: async (params = {}) =>
            apiCall('/attendance.php', 'GET', null, { action: 'export', ...params }, { isDownload: true })
    },

    finance: {
        getFees: async (params = {}) =>
            apiCall('/finance.php', 'GET', null, { action: 'fees', ...params }),
        recordPayment: async (data) =>
            apiCall('/finance.php', 'POST', data, { action: 'record_payment' }),
        generateInvoice: async (params = {}) =>
            apiCall('/finance.php', 'GET', null, { action: 'generate_invoice', ...params }, { isDownload: true }),
        getTransactions: async (params = {}) =>
            apiCall('/finance.php', 'GET', null, { action: 'transactions', ...params }),
        getPayments: async (params = {}) =>
            apiCall('/finance_payments.php', 'GET', null, { action: 'list', ...params }),
        getStats: async () =>
            apiCall('/finance.php', 'GET', null, { action: 'stats' }),
        getOutstandingFees: async () =>
            apiCall('/finance.php', 'GET', null, { action: 'outstanding_fees' }),
        getPaymentHistory: async (params = {}) =>
            apiCall('/finance.php', 'GET', null, { action: 'payment_history', ...params }),
        generateReceipt: async (paymentId) =>
            apiCall('/finance.php', 'GET', null, { action: 'generate_receipt', payment_id: paymentId }, { isDownload: true })
    },

    inventory: {
        list: async (params = {}) =>
            apiCall('/inventory.php', 'GET', null, { action: 'list', ...params }),
        addItem: async (data) =>
            apiCall('/inventory.php', 'POST', data, { action: 'add' }),
        updateItem: async (id, data) =>
            apiCall('/inventory.php', 'POST', data, { action: 'update', id }),
        deleteItem: async (id) =>
            apiCall('/inventory.php', 'POST', null, { action: 'delete', id }),
        getStock: async (params = {}) =>
            apiCall('/inventory.php', 'GET', null, { action: 'stock', ...params }),
        adjustStock: async (data) =>
            apiCall('/inventory.php', 'POST', data, { action: 'adjust_stock' }),
        getCategories: async () =>
            apiCall('/inventory.php', 'GET', null, { action: 'categories' }),
        getSuppliers: async () =>
            apiCall('/inventory.php', 'GET', null, { action: 'suppliers' }),
        generateReport: async (params = {}) =>
            apiCall('/inventory.php', 'GET', null, { action: 'report', ...params }, { isDownload: true })
    },

    communications: {
        sendMessage: async (data) =>
            apiCall('/communications.php', 'POST', data, { action: 'send' }),
        getMessages: async (params = {}) =>
            apiCall('/communications.php', 'GET', null, { action: 'messages', ...params }),
        broadcast: async (data) =>
            apiCall('/communications.php', 'POST', data, { action: 'broadcast' }),
        getNotifications: async (params = {}) =>
            apiCall('/communications.php', 'GET', null, { action: 'notifications', ...params }),
        markAsRead: async (messageId) =>
            apiCall('/communications.php', 'POST', null, { action: 'mark_read', message_id: messageId }),
        getUnreadCount: async () =>
            apiCall('/communications.php', 'GET', null, { action: 'unread_count' }),
        getTemplates: async () =>
            apiCall('/communications.php', 'GET', null, { action: 'templates' }),
        saveTemplate: async (data) =>
            apiCall('/communications.php', 'POST', data, { action: 'save_template' })
    },

    transport: {
        getRoutes: async (params = {}) =>
            apiCall('/transport.php', 'GET', null, { action: 'routes', ...params }),
        getVehicles: async (params = {}) =>
            apiCall('/transport.php', 'GET', null, { action: 'vehicles', ...params }),
        assignRoute: async (data) =>
            apiCall('/transport.php', 'POST', data, { action: 'assign_route' }),
        getSchedule: async (params = {}) =>
            apiCall('/transport.php', 'GET', null, { action: 'schedule', ...params }),
        addVehicle: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/transport.php', 'POST', formData, { action: 'add_vehicle' }, { isFile: true });
        },
        updateVehicle: async (id, data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/transport.php', 'POST', formData, { action: 'update_vehicle', id }, { isFile: true });
        },
        deleteVehicle: async (id) =>
            apiCall('/transport.php', 'POST', null, { action: 'delete_vehicle', id }),
        getDrivers: async () =>
            apiCall('/transport.php', 'GET', null, { action: 'drivers' }),
        assignDriver: async (data) =>
            apiCall('/transport.php', 'POST', data, { action: 'assign_driver' }),
        getMaintenanceSchedule: async (params = {}) =>
            apiCall('/transport.php', 'GET', null, { action: 'maintenance_schedule', ...params }),
        recordMaintenance: async (data) =>
            apiCall('/transport.php', 'POST', data, { action: 'record_maintenance' })
    },

    schedules: {
        getTimetable: async (params = {}) =>
            apiCall('/schedules.php', 'GET', null, { action: 'timetable', ...params }),
        getTeacherSchedule: async (teacherId) =>
            apiCall('/schedules.php', 'GET', null, { action: 'teacher_schedule', teacher_id: teacherId }),
        getClassSchedule: async (classId) =>
            apiCall('/schedules.php', 'GET', null, { action: 'class_schedule', class_id: classId }),
        updateSchedule: async (data) =>
            apiCall('/schedules.php', 'POST', data, { action: 'update' }),
        getEvents: async (params = {}) =>
            apiCall('/schedules.php', 'GET', null, { action: 'events', ...params }),
        addEvent: async (data) =>
            apiCall('/schedules.php', 'POST', data, { action: 'add_event' }),
        updateEvent: async (id, data) =>
            apiCall('/schedules.php', 'POST', data, { action: 'update_event', id }),
        deleteEvent: async (id) =>
            apiCall('/schedules.php', 'POST', null, { action: 'delete_event', id }),
        getHolidays: async () =>
            apiCall('/schedules.php', 'GET', null, { action: 'holidays' }),
        setHoliday: async (data) =>
            apiCall('/schedules.php', 'POST', data, { action: 'set_holiday' })
    },

    activities: {
        list: async (params = {}) =>
            apiCall('/activities.php', 'GET', null, { action: 'list', ...params }),
        create: async (data) =>
            apiCall('/activities.php', 'POST', data, { action: 'create' }),
        update: async (id, data) =>
            apiCall('/activities.php', 'POST', data, { action: 'update', id }),
        delete: async (id) =>
            apiCall('/activities.php', 'POST', null, { action: 'delete', id }),
        getParticipants: async (activityId) =>
            apiCall('/activities.php', 'GET', null, { action: 'participants', activity_id: activityId }),
        addParticipant: async (activityId, data) =>
            apiCall('/activities.php', 'POST', data, { action: 'add_participant', activity_id: activityId }),
        removeParticipant: async (activityId, participantId) =>
            apiCall('/activities.php', 'POST', null, { action: 'remove_participant', activity_id: activityId, participant_id: participantId }),
        getCategories: async () =>
            apiCall('/activities.php', 'GET', null, { action: 'categories' }),
        getSchedule: async (activityId) =>
            apiCall('/activities.php', 'GET', null, { action: 'schedule', activity_id: activityId })
    },

    admissions: {
        list: async (params = {}) =>
            apiCall('/admissions.php', 'GET', null, { action: 'list', ...params }),
        get: async (id) =>
            apiCall('/admissions.php', 'GET', null, { action: 'view', id }),
        create: async (data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/admissions.php', 'POST', formData, { action: 'add' }, { isFile: true });
        },
        update: async (id, data, files = {}) => {
            const formData = createFormData(data, files);
            return apiCall('/admissions.php', 'POST', formData, { action: 'update', id }, { isFile: true });
        },
        delete: async (id) =>
            apiCall('/admissions.php', 'POST', null, { action: 'delete', id }),
        approve: async (id, data) =>
            apiCall('/admissions.php', 'POST', data, { action: 'approve', id }),
        reject: async (id, data) =>
            apiCall('/admissions.php', 'POST', data, { action: 'reject', id }),
        getStats: async () =>
            apiCall('/admissions.php', 'GET', null, { action: 'stats' }),
        getAvailableSlots: async () =>
            apiCall('/admissions.php', 'GET', null, { action: 'slots' }),
        export: async (format = 'csv') =>
            apiCall('/admissions.php', 'GET', null, { action: 'export', format }, { isDownload: true })
    },

    sms: {
        send: async (data) =>
            apiCall('/sms.php', 'POST', data, { action: 'send' }),
        sendBulk: async (data) =>
            apiCall('/sms.php', 'POST', data, { action: 'send_bulk' }),
        getBalance: async () =>
            apiCall('/sms.php', 'GET', null, { action: 'balance' }),
        getHistory: async (params = {}) =>
            apiCall('/sms.php', 'GET', null, { action: 'history', ...params }),
        getDeliveryStatus: async (messageId) =>
            apiCall('/sms.php', 'GET', null, { action: 'status', message_id: messageId }),
        getTemplates: async () =>
            apiCall('/sms.php', 'GET', null, { action: 'templates' }),
        saveTemplate: async (data) =>
            apiCall('/sms.php', 'POST', data, { action: 'save_template' }),
        scheduleMessage: async (data) =>
            apiCall('/sms.php', 'POST', data, { action: 'schedule' }),
        cancelScheduled: async (messageId) =>
            apiCall('/sms.php', 'POST', null, { action: 'cancel_scheduled', message_id: messageId })
    },

    studentQR: {
        generate: async (studentId) =>
            apiCall('/student_qr.php', 'GET', null, { action: 'generate', student_id: studentId }, { isDownload: true }),
        scan: async (qrData) =>
            apiCall('/student_qr.php', 'POST', { qr_data: qrData }, { action: 'scan' }),
        verify: async (qrToken) =>
            apiCall('/student_qr.php', 'GET', null, { action: 'verify', token: qrToken })
    },

    resetPassword: {
        request: async (email) =>
            apiCall('/reset_password.php', 'POST', { email }, { action: 'request' }),
        verify: async (token) =>
            apiCall('/reset_password.php', 'GET', null, { action: 'verify', token }),
        complete: async (token, newPassword) =>
            apiCall('/reset_password.php', 'POST', { token, new_password: newPassword }, { action: 'complete' })
    },

    maintenance: {
        getStatus: async () =>
            apiCall('/maintenance.php', 'GET', null, { action: 'status' }),
        setMode: async (enabled) =>
            apiCall('/maintenance.php', 'POST', { enabled }, { action: 'set_mode' }),
        getMessage: async () =>
            apiCall('/maintenance.php', 'GET', null, { action: 'message' })
    }
};