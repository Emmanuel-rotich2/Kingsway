<?php
/**
 * Students - Viewer Layout
 * Read-only layout for Students, Parents, Guardians
 * 
 * Features:
 * - No sidebar
 * - Single profile card
 * - Student's own profile only (for student)
 * - Children profiles (for parent)
 * - No actions, read-only
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/pages/dashboard.php" class="back-link">← Dashboard</a>
        <h1 class="page-title">👨‍🎓 Student Profile</h1>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Profile Cards Container -->
        <div class="viewer-profile-container" id="profileContainer">
            <!-- Loaded dynamically based on user type -->
        </div>
    </main>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadProfile();
    });

    async function loadProfile() {
        const container = document.getElementById('profileContainer');
        const user = AuthContext.getUser();

        if (!user) {
            container.innerHTML = '<div class="error-card">Please log in to view profile</div>';
            return;
        }

        try {
            // Check if user is a student or parent
            if (user.role === 'Student') {
                // Load own profile
                const response = await API.students.getMyProfile();
                if (response.success) {
                    container.innerHTML = renderStudentCard(response.data);
                } else {
                    container.innerHTML = '<div class="empty-card">Profile not found</div>';
                }
            } else if (['Parent', 'Guardian'].includes(user.role)) {
                // Load children profiles
                const response = await API.students.getMyChildren();
                if (response.success && response.data.length > 0) {
                    container.innerHTML = response.data.map(child => renderStudentCard(child)).join('');
                } else {
                    container.innerHTML = '<div class="empty-card">No student profiles linked to your account</div>';
                }
            } else {
                container.innerHTML = '<div class="info-card">Access restricted to students and parents</div>';
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            container.innerHTML = '<div class="error-card">Unable to load profile</div>';
        }
    }

    function renderStudentCard(student) {
        return `
        <div class="viewer-profile-card">
            <div class="profile-header">
                <img src="${student.photo || '/images/default-avatar.png'}" alt="Photo" class="profile-photo">
                <div class="profile-name-section">
                    <h2 class="profile-name">${escapeHtml(student.full_name)}</h2>
                    <span class="profile-id">${escapeHtml(student.admission_no)}</span>
                </div>
            </div>
            
            <div class="profile-body">
                <div class="profile-section">
                    <h4>Academic Info</h4>
                    <div class="profile-info-grid">
                        <div class="info-item">
                            <span class="info-label">Class</span>
                            <span class="info-value">${escapeHtml(student.class_name || '-')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Stream</span>
                            <span class="info-value">${escapeHtml(student.stream_name || '-')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value">${student.student_type === 'boarder' ? '🏠 Boarder' : '🚌 Day Scholar'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value status-badge status-${student.status}">${student.status}</span>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h4>Personal Info</h4>
                    <div class="profile-info-grid">
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value">${student.gender === 'male' ? '♂ Male' : '♀ Female'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value">${formatDate(student.dob)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Admission Date</span>
                            <span class="info-value">${formatDate(student.admission_date)}</span>
                        </div>
                    </div>
                </div>
                
                ${student.fee_balance !== undefined ? `
                <div class="profile-section">
                    <h4>Fee Status</h4>
                    <div class="fee-card ${student.fee_balance > 0 ? 'fee-pending' : 'fee-clear'}">
                        <span class="fee-label">${student.fee_balance > 0 ? 'Balance Due' : 'All Cleared'}</span>
                        <span class="fee-value">KES ${formatNumber(student.fee_balance || 0)}</span>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'; }
    function formatNumber(n) { return n?.toLocaleString() || '0'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>

<style>
    /* Viewer-specific styles for profile cards */
    .viewer-profile-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 1rem;
    }

    .viewer-profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--green-600), var(--green-700));
        padding: 2rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .profile-photo {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid white;
        object-fit: cover;
    }

    .profile-name-section {
        color: white;
    }

    .profile-name {
        font-size: 1.5rem;
        margin: 0 0 0.25rem 0;
    }

    .profile-id {
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .profile-body {
        padding: 1.5rem;
    }

    .profile-section {
        margin-bottom: 1.5rem;
    }

    .profile-section:last-child {
        margin-bottom: 0;
    }

    .profile-section h4 {
        font-size: 0.85rem;
        text-transform: uppercase;
        color: var(--green-700);
        margin: 0 0 0.75rem 0;
        letter-spacing: 0.5px;
    }

    .profile-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-label {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
    }

    .info-value {
        font-weight: 500;
        color: #333;
    }

    .fee-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-radius: 8px;
    }

    .fee-pending {
        background: #fef2f2;
        border: 1px solid #fecaca;
    }

    .fee-clear {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
    }

    .fee-label {
        font-size: 0.85rem;
        color: #666;
    }

    .fee-value {
        font-size: 1.25rem;
        font-weight: 600;
    }

    .fee-pending .fee-value {
        color: #dc2626;
    }

    .fee-clear .fee-value {
        color: #16a34a;
    }

    .empty-card,
    .error-card,
    .info-card {
        text-align: center;
        padding: 3rem 2rem;
        background: white;
        border-radius: 12px;
        color: #666;
    }

    .error-card {
        color: #dc2626;
        background: #fef2f2;
    }
</style>