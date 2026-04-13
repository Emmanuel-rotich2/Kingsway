<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
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

<!-- Profile Cards Container -->
<div class="viewer-profile-container" id="profileContainer">
    <!-- Loaded dynamically based on user type -->
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.RoleBasedUI?.applyLayout) {
            RoleBasedUI.applyLayout();
        }
        loadProfile();
    });

    async function loadProfile() {
        const container = document.getElementById('profileContainer');
        const user = window.AuthContext?.getUser?.();

        if (!user) {
            container.innerHTML = '<div class="error-card">Please log in to view profile</div>';
            return;
        }

        container.innerHTML = '<div class="state-card">Loading linked student profile...</div>';

        try {
            let ownProfile = null;

            try {
                ownProfile = await API.students.getMyProfile();
            } catch (error) {
                ownProfile = null;
            }

            if (ownProfile && ownProfile.id) {
                container.innerHTML = renderProfiles([ownProfile], "Your learner profile");
                return;
            }

            const children = await API.students.getMyChildren();
            if (Array.isArray(children) && children.length > 0) {
                container.innerHTML = renderProfiles(children, "Learner profiles linked to your account");
                return;
            }

            container.innerHTML = '<div class="empty-card">No student profiles are linked to this account yet.</div>';
        } catch (error) {
            console.error('Error loading profile:', error);
            container.innerHTML = '<div class="error-card">Unable to load profile</div>';
        }
    }

    function renderProfiles(students, title) {
        return `
        <section class="viewer-section">
            <div class="viewer-section-header">
                <h2>${escapeHtml(title)}</h2>
                <span class="viewer-count">${students.length} profile${students.length === 1 ? '' : 's'}</span>
            </div>
            <div class="viewer-grid">
                ${students.map(renderStudentCard).join('')}
            </div>
        </section>
    `;
    }

    function renderStudentCard(student) {
        const studentType = student.student_type || student.student_type_name || student.boarding_status || 'day';
        const effectiveStudentType = String(studentType).toLowerCase();
        const studentTypeLabel =
            effectiveStudentType.includes('board') ? 'Boarder' :
            effectiveStudentType.includes('weekly') ? 'Weekly Boarder' :
            'Day Scholar';
        const photo = student.photo_url || student.photo || (window.APP_BASE || '') + '/images/default-avatar.png';

        return `
        <div class="viewer-profile-card">
            <div class="profile-header">
                <img src="${photo}" alt="Photo" class="profile-photo">
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
                            <span class="info-value">${escapeHtml(studentTypeLabel)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value status-badge status-${escapeHtml(student.status || 'active')}">${escapeHtml(student.status || 'active')}</span>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h4>Personal Info</h4>
                    <div class="profile-info-grid">
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value">${formatGender(student.gender)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value">${formatDate(student.date_of_birth || student.dob)}</span>
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

    function formatGender(gender) {
        switch (String(gender || '').toLowerCase()) {
            case 'male':
                return 'Male';
            case 'female':
                return 'Female';
            case 'other':
                return 'Other';
            default:
                return '-';
        }
    }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'; }
    function formatNumber(n) { return n?.toLocaleString() || '0'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>

<style>
    /* Viewer-specific styles for profile cards */
    .viewer-profile-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 1rem;
    }

    .viewer-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .viewer-section-header h2 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--green-700);
    }

    .viewer-count {
        font-size: 0.875rem;
        color: #64748b;
    }

    .viewer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.25rem;
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

    .state-card,
    .error-card,
    .empty-card,
    .info-card {
        background: #fff;
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        padding: 1.25rem;
        text-align: center;
        color: #334155;
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
