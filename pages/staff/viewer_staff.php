<?php
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

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/pages/dashboard.php" class="back-link">← Dashboard</a>
        <h1 class="page-title">📞 School Contacts</h1>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
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
    </main>
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

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadContacts();
    });

    async function loadContacts() {
        try {
            // Load admin contacts
            const adminResponse = await fetch('/api/?route=staff&action=key-contacts');
            const adminData = await adminResponse.json();

            if (adminData.success) {
                renderContacts('adminContacts', adminData.data);
            }

            // Determine if user is student or parent
            const user = typeof AuthContext !== 'undefined' ? AuthContext.getUser() : null;

            if (user && user.role === 'Student') {
                // Show teachers section, hide class teacher section
                document.getElementById('classTeacherSection').style.display = 'none';

                // Load my teachers
                const teachersResponse = await fetch('/api/?route=students&action=my-teachers');
                const teachersData = await teachersResponse.json();

                if (teachersData.success) {
                    renderContacts('teacherContacts', teachersData.data);
                }
            } else if (user && ['Parent', 'Guardian'].includes(user.role)) {
                // Show class teacher section, hide teachers section
                document.getElementById('teachersSection').style.display = 'none';

                // Load class teacher(s) for children
                const classTeacherResponse = await fetch('/api/?route=parents&action=class-teachers');
                const classTeacherData = await classTeacherResponse.json();

                if (classTeacherData.success) {
                    renderContacts('classTeacherContact', classTeacherData.data);
                }
            } else {
                document.getElementById('teachersSection').style.display = 'none';
                document.getElementById('classTeacherSection').style.display = 'none';
            }
        } catch (error) {
            console.error('Error loading contacts:', error);
        }
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