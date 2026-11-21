<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
/**
 * Staff - Viewer Layout
 * For Students, Parents (no access to full staff list)
 *
 * Features:
 * - No sidebar
 * - Key contacts only (Admin, Teachers assigned to student)
 * - Emergency contacts
 * - No personal details
 */
?>

<!-- Emergency Contacts -->
<div class="viewer-section">
    <h3>🚨 Emergency Contacts</h3>
    <div class="contact-list" id="emergencyContacts">
        <div class="contact-card emergency">
            <div class="contact-icon">🏥</div>
            <div class="contact-info">
                <div class="contact-name">School Nurse</div>
                <div class="contact-phone">+254 XXX XXX XXX</div>
            </div>
        </div>
        <div class="contact-card emergency">
            <div class="contact-icon">🚔</div>
            <div class="contact-info">
                <div class="contact-name">Security Office</div>
                <div class="contact-phone">+254 XXX XXX XXX</div>
            </div>
        </div>
    </div>
</div>

<!-- Administration -->
<div class="viewer-section">
    <h3>🏫 Administration</h3>
    <div class="contact-list" id="adminContacts">
        <div class="loading-item">Loading contacts...</div>
    </div>
</div>

<!-- My Teachers (for students) -->
<div class="viewer-section" id="teachersSection">
    <h3>👩‍🏫 My Teachers</h3>
    <div class="contact-list" id="teacherContacts">
        <div class="loading-item">Loading teachers...</div>
    </div>
</div>

<!-- Class Teacher (for parents) -->
<div class="viewer-section" id="classTeacherSection">
    <h3>👩‍🏫 Class Teacher</h3>
    <div class="contact-list" id="classTeacherContact">
        <div class="loading-item">Loading...</div>
    </div>
</div>

<style>
    .contact-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    .contact-card {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4);
        background: var(--white);
        border-radius: var(--radius-md);
        border: 1px solid var(--gray-200);
    }

    .contact-card.emergency {
        background: var(--danger-50);
        border-color: var(--danger-200);
    }

    .contact-icon {
        font-size: 2rem;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .contact-info {
        flex: 1;
    }

    .contact-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--space-1);
    }

    .contact-role {
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }

    .contact-phone {
        font-size: var(--text-sm);
        color: var(--primary-600);
        font-weight: 500;
    }

    .contact-action {
        padding: var(--space-2) var(--space-3);
        background: var(--primary-100);
        color: var(--primary-700);
        border-radius: var(--radius-full);
        font-size: var(--text-sm);
        text-decoration: none;
        transition: background 0.2s;
    }

    .contact-action:hover {
        background: var(--primary-200);
    }
</style>

<script nonce="<?= csp_nonce() ?>">
    (function () {
        function boot() {
            RoleBasedUI.applyLayout();
            loadContacts();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();

    async function loadContacts() {
        const user = typeof AuthContext !== 'undefined' ? AuthContext.getUser() : null;
        const roleNames = userRoleNames(user);

        const isStudent = roleNames.includes('student');
        const isParent = roleNames.includes('parent') || roleNames.includes('guardian');

        // Administration — key contacts for every viewer.
        try {
            const adminResponse = await window.API.staff.keyContacts();
            renderContacts('adminContacts', adminResponse?.data || adminResponse);
        } catch (error) {
            console.error('Error loading admin contacts:', error);
            const el = document.getElementById('adminContacts');
            if (el) el.innerHTML = '<div class="error-item">Unable to load contacts</div>';
        }

        const teachersSection = document.getElementById('teachersSection');
        const classTeacherSection = document.getElementById('classTeacherSection');

        if (isParent) {
            if (teachersSection) teachersSection.style.display = 'none';
            if (classTeacherSection) classTeacherSection.style.display = '';

            // Class teachers for the current user's children.
            try {
                const classTeacherResponse = await window.API.academic.parentClassTeachers();
                renderContacts('classTeacherContact', classTeacherResponse?.data || classTeacherResponse);
            } catch (error) {
                console.error('Error loading class teachers:', error);
                const el = document.getElementById('classTeacherContact');
                if (el) el.innerHTML = '<div class="error-item">Unable to load class teachers</div>';
            }
        } else if (isStudent) {
            if (classTeacherSection) classTeacherSection.style.display = 'none';
            if (teachersSection) teachersSection.style.display = '';

            // Teachers assigned to the current student's class.
            try {
                const teachersResponse = await window.API.academic.myTeachers();
                renderContacts('teacherContacts', teachersResponse?.data || teachersResponse);
            } catch (error) {
                console.error('Error loading my teachers:', error);
                const el = document.getElementById('teacherContacts');
                if (el) el.innerHTML = '<div class="error-item">Unable to load teachers</div>';
            }
        } else {
            if (teachersSection) teachersSection.style.display = 'none';
            if (classTeacherSection) classTeacherSection.style.display = 'none';
        }
    }

    function userRoleNames(user) {
        const names = [];
        const push = (value) => {
            if (typeof value === 'string' && value) names.push(value.toLowerCase());
        };
        if (user) {
            push(user.role);
            push(user.role_name);
            if (Array.isArray(user.roles)) {
                user.roles.forEach((role) => push(typeof role === 'string' ? role : role && role.name));
            }
        }
        return names;
    }

    function renderContacts(containerId, contacts) {
        const container = document.getElementById(containerId);

        if (!contacts || contacts.length === 0) {
            container.innerHTML = '<div class="empty-item">No contacts available</div>';
            return;
        }

        container.innerHTML = contacts.map(c => `
            <div class="contact-card">
                <div class="contact-icon">${c.icon || '👤'}</div>
                <div class="contact-info">
                    <div class="contact-name">${escapeHtml(c.name)}</div>
                    <div class="contact-role">${escapeHtml(c.role || '')}</div>
                    ${c.context ? `<div class="contact-role">${escapeHtml(c.context)}</div>` : ''}
                    <div class="contact-phone">${escapeHtml(c.phone || '-')}</div>
                </div>
                ${c.phone ? `<a href="tel:${c.phone}" class="contact-action">📞 Call</a>` : ''}
            </div>
        `).join('');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
</script>
