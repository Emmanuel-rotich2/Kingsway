<?php
/**
 * Transport - Viewer Layout
 * Read-only for Students and Parents
 * 
 * Features:
 * - No sidebar
 * - Transport info card
 * - Route and pickup details
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/pages/dashboard.php" class="back-link">← Dashboard</a>
        <h1 class="page-title">🚌 Transport Info</h1>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Transport Cards -->
        <div class="viewer-transport-container" id="transportContainer">
            <!-- Loaded dynamically -->
        </div>
    </main>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadTransportInfo();
    });

    async function loadTransportInfo() {
        const container = document.getElementById('transportContainer');
        const user = AuthContext.getUser();

        if (!user) {
            container.innerHTML = '<div class="info-card">Please log in to view transport info</div>';
            return;
        }

        try {
            let response;
            if (user.role === 'Student') {
                response = await API.transport.getMyTransport();
            } else if (['Parent', 'Guardian'].includes(user.role)) {
                response = await API.transport.getChildrenTransport();
            } else {
                container.innerHTML = '<div class="info-card">Access restricted to students and parents</div>';
                return;
            }

            if (response.success && response.data) {
                if (Array.isArray(response.data)) {
                    container.innerHTML = response.data.map(t => renderTransportCard(t)).join('');
                } else {
                    container.innerHTML = renderTransportCard(response.data);
                }
            } else {
                container.innerHTML = '<div class="no-transport-card">🚶 Not using school transport</div>';
            }
        } catch (error) {
            console.error('Error loading transport info:', error);
            container.innerHTML = '<div class="error-card">Unable to load transport info</div>';
        }
    }

    function renderTransportCard(transport) {
        return `
        <div class="viewer-transport-card">
            <div class="transport-header">
                <div class="transport-icon">🚌</div>
                <div class="transport-title">
                    <h2>${escapeHtml(transport.route_name)}</h2>
                    ${transport.student_name ? `<span class="student-name">${escapeHtml(transport.student_name)}</span>` : ''}
                </div>
            </div>
            
            <div class="transport-body">
                <div class="transport-section">
                    <h4>Schedule</h4>
                    <div class="schedule-grid">
                        <div class="schedule-item">
                            <span class="schedule-icon">🌅</span>
                            <div class="schedule-details">
                                <span class="schedule-label">Morning Pickup</span>
                                <span class="schedule-time">${transport.am_pickup || '--:--'}</span>
                            </div>
                        </div>
                        <div class="schedule-item">
                            <span class="schedule-icon">🌇</span>
                            <div class="schedule-details">
                                <span class="schedule-label">Afternoon Dropoff</span>
                                <span class="schedule-time">${transport.pm_dropoff || '--:--'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="transport-section">
                    <h4>Pickup Point</h4>
                    <div class="pickup-info">
                        <span class="pickup-icon">📍</span>
                        <span class="pickup-name">${escapeHtml(transport.stop_name || 'Not assigned')}</span>
                    </div>
                </div>
                
                <div class="transport-section">
                    <h4>Vehicle & Driver</h4>
                    <div class="vehicle-info">
                        <div class="info-row">
                            <span class="info-label">Vehicle</span>
                            <span class="info-value">${escapeHtml(transport.vehicle_reg || '-')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Driver</span>
                            <span class="info-value">${escapeHtml(transport.driver_name || '-')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Contact</span>
                            <span class="info-value">${transport.driver_phone ? `<a href="tel:${transport.driver_phone}">${transport.driver_phone}</a>` : '-'}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>

<style>
    /* Viewer-specific transport styles */
    .viewer-transport-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 1rem;
    }

    .viewer-transport-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .transport-header {
        background: linear-gradient(135deg, var(--green-600), var(--green-700));
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: white;
    }

    .transport-icon {
        font-size: 2.5rem;
    }

    .transport-title h2 {
        margin: 0;
        font-size: 1.25rem;
    }

    .transport-title .student-name {
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .transport-body {
        padding: 1.5rem;
    }

    .transport-section {
        margin-bottom: 1.5rem;
    }

    .transport-section:last-child {
        margin-bottom: 0;
    }

    .transport-section h4 {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--green-700);
        margin: 0 0 0.75rem 0;
        letter-spacing: 0.5px;
    }

    .schedule-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .schedule-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 8px;
    }

    .schedule-icon {
        font-size: 1.5rem;
    }

    .schedule-details {
        display: flex;
        flex-direction: column;
    }

    .schedule-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #666;
    }

    .schedule-time {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--green-700);
    }

    .pickup-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--gold-50);
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid var(--gold-200);
    }

    .pickup-icon {
        font-size: 1.5rem;
    }

    .pickup-name {
        font-weight: 500;
    }

    .vehicle-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #666;
        font-size: 0.85rem;
    }

    .info-value {
        font-weight: 500;
    }

    .info-value a {
        color: var(--green-600);
        text-decoration: none;
    }

    .no-transport-card {
        text-align: center;
        padding: 3rem 2rem;
        background: white;
        border-radius: 12px;
        color: #666;
        font-size: 1.25rem;
    }

    .info-card,
    .error-card {
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        color: #666;
    }

    .error-card {
        color: #dc2626;
        background: #fef2f2;
    }
</style>