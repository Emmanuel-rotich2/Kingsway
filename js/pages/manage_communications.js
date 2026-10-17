/**
 * Manage Communications Controller
 * Administrative interface for managing communications including templates.
 */
const manageCommunicationsController = {
    state: {
        referenceCache: null,
        templates: [],
        lists: [],
        channels: [],
        currentTemplatePage: 1,
        templateFilters: { channel: '', status: '', search: '' },
        editingCatalogId: null,
        editingVersionId: null,
        channelVariants: [],
        editingChannelIndex: null,
    },

    API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

    esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

    async loadReferencesComposed() {
        try {
            const response = await window.API.nodeViews.read("communications", "manage-communications-references");
            this.state.referenceCache = response?.data?.sources || response?.sources || {};
        } catch (error) {
            console.warn("Manage Communications Node view failed; using direct reads:", error);
            this.state.referenceCache = null;
        }
    },

    async load() {
        try {
            const cache = this.state.referenceCache;
            const [templates, lists, channels] = await Promise.all([
                cache?.templates !== undefined ? cache.templates : this.API('GET', '/api/communications/templates'),
                cache?.lists !== undefined ? cache.lists : this.API('GET', '/api/communications/lists'),
                cache?.channels !== undefined ? cache.channels : this.API('GET', '/api/communications/channels'),
            ]);
            this.state.templates = Array.isArray(templates?.templates) ? templates.templates : (Array.isArray(templates) ? templates : []);
            this.state.lists = Array.isArray(lists?.lists) ? lists.lists : (Array.isArray(lists) ? lists : []);
            this.state.channels = Array.isArray(channels?.channels) ? channels.channels : (Array.isArray(channels) ? channels : []);
            this.renderTemplates();
            this.renderLists();
            this.renderChannels();
        } catch (e) { console.error(e); }
    },

    getCache(key) { return this.state.referenceCache?.[key]; },

    // ========== TEMPLATES ==========

    async loadTemplates() {
        const { currentTemplatePage, templateFilters } = this.state;
        const params = new URLSearchParams({
            page: currentTemplatePage,
            ...templateFilters
        });
        try {
            const res = await this.API('GET', `/api/communications/templates?${params}`);
            if (res.success) {
                this.renderTemplatesTable(res.templates);
                this.renderTemplatePagination(res.pagination || { current_page: currentTemplatePage, total_pages: 1 });
            }
        } catch (err) {
            console.error('Failed to load templates:', err);
        }
    },

    renderTemplates() {
        // Initial render - load from API
        this.loadTemplates();
    },

    renderTemplatesTable(templates) {
        const tbody = document.getElementById('templateManagerBody');
        if (!tbody) return;

        if (!templates.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No templates found</td></tr>';
            return;
        }

        tbody.innerHTML = templates.map(tpl => {
            const latestVersion = tpl.versions?.[0];
            const channels = latestVersion?.channels || [];
            const channelBadges = channels.map(c => {
                const colors = { sms: 'success', whatsapp: 'info', email: 'primary', portal: 'secondary', in_app: 'dark' };
                return `<span class="badge bg-${colors[c.channel] || 'secondary'} me-1">${c.channel}</span>`;
            }).join(' ');

            return `
                <tr data-catalog-id="${tpl.catalog_id}">
                    <td><code>${this.esc(tpl.code)}</code></td>
                    <td>${this.esc(tpl.name)}</td>
                    <td><span class="badge bg-light text-dark">${this.esc(tpl.purpose)}</span></td>
                    <td>${channelBadges}</td>
                    <td><span class="badge bg-${this.getStatusColor(tpl.status)}">${this.esc(tpl.status)}</span></td>
                    <td>${latestVersion ? `v${latestVersion.version_no} (${latestVersion.status})` : '-'}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary edit-template" data-catalog-id="${tpl.catalog_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            ${latestVersion ? `
                                <button class="btn btn-outline-secondary edit-channel" data-channel-id="${channels[0]?.channel_id}" title="Edit Channels" ${channels.length ? '' : 'disabled'}>
                                    <i class="bi bi-sliders"></i>
                                </button>
                                ${latestVersion.status === 'draft' ? `
                                    <button class="btn btn-outline-success approve-version" data-version-id="${latestVersion.version_id}" title="Approve">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                ` : ''}
                            ` : ''}
                            <button class="btn btn-outline-danger delete-template" data-catalog-id="${tpl.catalog_id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        // Bind events for template actions
        tbody.querySelectorAll('.edit-template').forEach(btn => {
            btn.onclick = () => this.editTemplate(btn.dataset.catalogId);
        });
        tbody.querySelectorAll('.edit-channel').forEach(btn => {
            btn.onclick = () => this.editChannel(btn.dataset.channelId);
        });
        tbody.querySelectorAll('.delete-template').forEach(btn => {
            btn.onclick = () => this.deleteTemplate(btn.dataset.catalogId);
        });
        tbody.querySelectorAll('.approve-version').forEach(btn => {
            btn.onclick = () => this.approveVersion(btn.dataset.versionId);
        });
    },

    getStatusColor(status) {
        const colors = { active: 'success', draft: 'warning', inactive: 'secondary', archived: 'dark' };
        return colors[status] || 'secondary';
    },

    renderTemplatePagination(pagination) {
        const $pagination = document.getElementById('templatePagination');
        if (!$pagination) return;

        const current = pagination.current_page || 1;
        const total = pagination.total_pages || 1;

        if (total <= 1) {
            $pagination.innerHTML = '';
            return;
        }

        let html = '';
        html += `<li class="page-item ${current === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">&laquo;</a></li>`;

        const start = Math.max(1, current - 2);
        const end = Math.min(total, current + 2);

        for (let i = start; i <= end; i++) {
            html += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        html += `<li class="page-item ${current === total ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">&raquo;</a></li>`;

        $pagination.innerHTML = html;

        $pagination.querySelectorAll('.page-link').forEach(link => {
            link.onclick = (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (page && page !== current) {
                    this.state.currentTemplatePage = page;
                    this.loadTemplates();
                }
            };
        });
    },

    // ========== LISTS & CHANNELS (existing) ==========

    renderLists() {
        const lists = this.state.lists;
        const body = document.getElementById('mcomListsBody');
        if (!body) return;
        if (!lists.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No contact lists.</td></tr>'; return; }
        body.innerHTML = lists.map(l => `<tr>
            <td class="small">${this.esc(l.name || '—')}</td>
            <td class="small">${l.contacts_count || 0}</td>
            <td class="small">${this.fmtDate(l.updated_at)}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="manageCommunicationsController.editList(${l.id})"><i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
    },

    renderChannels() {
        const channels = this.state.channels;
        const body = document.getElementById('mcomChannelsBody');
        if (!body) return;
        if (!channels.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No channels configured.</td></tr>'; return; }
        body.innerHTML = channels.map(c => `<tr>
            <td class="small">${this.esc(c.name || '—')}</td>
            <td class="small">${this.esc(c.type || '—')}</td>
            <td class="small"><span class="badge bg-${c.is_active ? 'success' : 'secondary'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="manageCommunicationsController.editChannel(${c.id})"><i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
    },

    fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

    // ========== TEMPLATE MODAL LOGIC ==========

    tokenList: [
        { token: '@first_name', label: 'First Name' }, { token: '@last_name', label: 'Last Name' },
        { token: '@student_name', label: 'Student Name' }, { token: '@parent_name', label: 'Parent Name' },
        { token: '@class_name', label: 'Class/Stream' }, { token: '@amount_due', label: 'Amount Due' },
        { token: '@due_date', label: 'Due Date' }, { token: '@event_title', label: 'Event Title' },
        { token: '@event_date', label: 'Event Date' }, { token: '@event_time', label: 'Event Time' },
        { token: '@event_venue', label: 'Event Venue' }, { token: '@school_name', label: 'School Name' },
        { token: '@school_address', label: 'School Address' }, { token: '@school_phone', label: 'School Phone' },
        { token: '@school_email', label: 'School Email' }, { token: '@school_motto', label: 'School Motto' },
        { token: '@school_logo', label: 'School Logo URL' },
    ],

    openTemplateModal(catalog = null) {
        this.state.editingCatalogId = catalog?.catalog_id || null;
        this.state.editingVersionId = null;
        this.state.channelVariants = [];

        document.getElementById('templateForm').reset();
        document.getElementById('inputCatalogId').value = '';
        document.getElementById('inputVersionId').value = '';
        document.getElementById('channelVariantsContainer').innerHTML = '';

        if (catalog) {
            document.getElementById('templateModalTitle').textContent = 'Edit Template';
            document.getElementById('inputCatalogId').value = catalog.catalog_id;
            document.getElementById('inputCode').value = catalog.code;
            document.getElementById('inputCode').readOnly = true;
            document.getElementById('inputName').value = catalog.name;
            document.getElementById('inputPurpose').value = catalog.purpose;

            if (catalog.versions?.length) {
                const latest = catalog.versions[0];
                this.state.editingVersionId = latest.version_id;
                document.getElementById('inputVersionId').value = latest.version_id;
                this.state.channelVariants = latest.channels || [];
                this.renderChannelVariantsList();
            }
        } else {
            document.getElementById('templateModalTitle').textContent = 'New Template';
            document.getElementById('inputCode').readOnly = false;
            this.addDefaultChannelVariant();
        }

        new bootstrap.Modal(document.getElementById('templateManagerModal')).show();
    },

    addDefaultChannelVariant() {
        const channels = ['sms', 'whatsapp', 'email'];
        channels.forEach(ch => {
            this.state.channelVariants.push({
                channel: ch,
                language_code: 'en',
                body_format: ch === 'email' ? 'html' : 'plain',
                subject: '',
                body: '',
                header: ch === 'email' ? this.getDefaultHeader() : '',
                footer: ch === 'email' ? this.getDefaultFooter() : '',
                media_json: null,
            });
        });
        this.renderChannelVariantsList();
    },

    getDefaultHeader() {
        return "<div style=\"text-align:center;padding:20px;background:#f8f9fa;border-bottom:1px solid #dee2e6;\"><img src=\"{{school_logo}}\" alt=\"{{school_name}}\" style=\"max-height:80px;\"></div>";
    },

    getDefaultFooter() {
        return "<div style=\"padding:20px;background:#f8f9fa;border-top:1px solid #dee2e6;text-align:center;font-size:12px;color:#6c757d;\"><p>{{school_name}}<br>{{school_address}}<br>{{school_phone}} | {{school_email}}</p><p style=\"margin-top:10px;\">{{school_motto}}</p></div>";
    },

    renderChannelVariantsList() {
        const $container = document.getElementById('channelVariantsContainer');
        $container.innerHTML = '';

        this.state.channelVariants.forEach((variant, index) => {
            const colors = { sms: 'success', whatsapp: 'info', email: 'primary', portal: 'secondary', in_app: 'dark' };
            const badgeClass = colors[variant.channel] || 'secondary';

            const card = document.createElement('div');
            card.className = 'card mb-2 channel-variant-card';
            card.dataset.index = index;
            card.innerHTML = `
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-${badgeClass} me-2">${variant.channel.toUpperCase()}</span>
                            <strong>${this.esc(variant.subject || '(no subject)')}</strong>
                            <span class="text-muted ms-2">${variant.body_format === 'html' ? 'HTML' : 'Plain Text'}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary edit-variant" data-index="${index}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button type="button" class="btn btn-outline-danger remove-variant" data-index="${index}" ${this.state.channelVariants.length <= 1 ? 'disabled' : ''}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $container.appendChild(card);
        });

        $container.querySelectorAll('.edit-variant').forEach(btn => {
            btn.onclick = () => this.openChannelModal(parseInt(btn.dataset.index));
        });
        $container.querySelectorAll('.remove-variant').forEach(btn => {
            btn.onclick = () => {
                const index = parseInt(btn.dataset.index);
                this.state.channelVariants.splice(index, 1);
                this.renderChannelVariantsList();
            };
        });
    },

    openChannelModal(index = null) {
        this.state.editingChannelIndex = index !== null ? index : null;
        const variant = index !== null ? this.state.channelVariants[index] : {
            channel: 'sms', language_code: 'en', body_format: 'plain',
            subject: '', body: '', header: '', footer: '',
            media_json: null, provider_template_id: '',
        };

        document.getElementById('channelForm').reset();
        document.getElementById('inputChannelId').value = variant.channel_id || '';
        document.getElementById('channelCatalogId').value = this.state.editingCatalogId || '';
        document.getElementById('channelVersionId').value = this.state.editingVersionId || '';
        document.getElementById('inputChannel').value = variant.channel;
        document.getElementById('inputLanguageCode').value = variant.language_code;
        document.getElementById('inputBodyFormat').value = variant.body_format;
        document.getElementById('inputSubject').value = variant.subject;
        document.getElementById('inputBody').value = variant.body;
        document.getElementById('inputHeader').value = variant.header;
        document.getElementById('inputFooter').value = variant.footer;
        document.getElementById('inputProviderTemplateId').value = variant.provider_template_id || '';

        this.renderMediaList(variant.media_json || []);
        this.toggleChannelSpecificFields();
        this.updateCharCount();
        this.updateSegmentEstimate();

        new bootstrap.Modal(document.getElementById('channelModal')).show();
    },

    renderMediaList(media) {
        const $list = document.getElementById('mediaList');
        $list.innerHTML = '';
        (media || []).forEach((m, i) => {
            const div = document.createElement('div');
            div.className = 'input-group mb-2 media-row';
            div.innerHTML = `
                <input type="url" class="form-control" name="media_url[]" value="${this.esc(m.url || '')}" placeholder="https://example.com/image.jpg" required>
                <select class="form-select" name="media_type[]" style="width:auto;">
                    <option value="image" ${m.type === 'image' ? 'selected' : ''}>Image</option>
                    <option value="document" ${m.type === 'document' ? 'selected' : ''}>Document</option>
                </select>
                <button type="button" class="btn btn-outline-danger remove-media" data-index="${i}"><i class="bi bi-x"></i></button>
            `;
            $list.appendChild(div);
        });

        $list.querySelectorAll('.remove-media').forEach(btn => {
            btn.onclick = () => btn.closest('.media-row').remove();
        });
    },

    addMediaRow() {
        const $list = document.getElementById('mediaList');
        const index = $list.querySelectorAll('.media-row').length;
        const div = document.createElement('div');
        div.className = 'input-group mb-2 media-row';
        div.innerHTML = `
            <input type="url" class="form-control" name="media_url[]" placeholder="https://example.com/image.jpg" required>
            <select class="form-select" name="media_type[]" style="width:auto;">
                <option value="image">Image</option>
                <option value="document">Document</option>
            </select>
            <button type="button" class="btn btn-outline-danger remove-media" data-index="${index}"><i class="bi bi-x"></i></button>
        `;
        $list.appendChild(div);
        div.querySelector('.remove-media').onclick = () => div.remove();
    },

    toggleChannelSpecificFields() {
        const channel = document.getElementById('inputChannel').value;
        const isEmail = channel === 'email';
        const isWhatsApp = channel === 'whatsapp';

        document.getElementById('subjectGroup').style.display = isEmail || isWhatsApp ? 'block' : 'none';
        document.getElementById('headerGroup').style.display = isEmail ? 'block' : 'none';
        document.getElementById('footerGroup').style.display = isEmail ? 'block' : 'none';
        document.getElementById('mediaGroup').style.display = isWhatsApp ? 'block' : 'none';

        const format = isEmail ? 'html' : 'plain';
        document.getElementById('inputBodyFormat').value = format;
    },

    saveChannel() {
        const formData = {
            channel_id: document.getElementById('inputChannelId').value || null,
            catalog_id: document.getElementById('channelCatalogId').value || this.state.editingCatalogId,
            version_id: document.getElementById('channelVersionId').value || this.state.editingVersionId,
            channel: document.getElementById('inputChannel').value,
            language_code: document.getElementById('inputLanguageCode').value,
            body_format: document.getElementById('inputBodyFormat').value,
            subject: document.getElementById('inputSubject').value,
            body: document.getElementById('inputBody').value,
            header: document.getElementById('inputHeader').value,
            footer: document.getElementById('inputFooter').value,
            provider_template_id: document.getElementById('inputProviderTemplateId').value || null,
        };

        const media = [];
        document.querySelectorAll('#mediaList .media-row').forEach(row => {
            const url = row.querySelector('input[name="media_url[]"]').value;
            const type = row.querySelector('select[name="media_type[]"]').value;
            if (url) media.push({ url, type });
        });
        if (media.length) formData.media_json = media;

        const index = this.state.editingChannelIndex;
        if (index !== null && index >= 0 && index < this.state.channelVariants.length) {
            this.state.channelVariants[index] = { ...this.state.channelVariants[index], ...formData };
        } else {
            this.state.channelVariants.push(formData);
        }

        this.renderChannelVariantsList();
        bootstrap.Modal.getInstance(document.getElementById('channelModal')).hide();
    },

    async saveTemplate() {
        const channels = this.state.channelVariants.map(v => ({
            channel: v.channel,
            language_code: v.language_code,
            body_format: v.body_format,
            subject: v.subject,
            body: v.body,
            header: v.header,
            footer: v.footer,
            media_json: v.media_json,
            provider_template_id: v.provider_template_id,
        }));

        const payload = {
            code: document.getElementById('inputCode').value,
            name: document.getElementById('inputName').value,
            purpose: document.getElementById('inputPurpose').value,
            channels,
        };

        try {
            let res;
            if (this.state.editingCatalogId) {
                const id = this.state.editingCatalogId;
                res = await this.API('PUT', `/api/communications/templates/${id}`, {
                    name: payload.name,
                    purpose: payload.purpose,
                });
                if (!res.success) throw new Error(res.message || 'Failed to update template');

                const existing = [];
                const added = [];
                this.state.channelVariants.forEach(v => {
                    if (v.channel_id) {
                        existing.push(v);
                    } else {
                        added.push({
                            channel: v.channel,
                            language_code: v.language_code,
                            body_format: v.body_format,
                            subject: v.subject,
                            body: v.body,
                            header: v.header,
                            footer: v.footer,
                            media_json: v.media_json,
                            provider_template_id: v.provider_template_id,
                        });
                    }
                });
                for (const v of existing) {
                    await this.API('PUT', `/api/communications/templates/channels/${v.channel_id}`, {
                        body_format: v.body_format,
                        subject: v.subject,
                        body: v.body,
                        header: v.header,
                        footer: v.footer,
                        media_json: v.media_json,
                        provider_template_id: v.provider_template_id,
                    });
                }
                if (added.length) {
                    await this.API('POST', `/api/communications/templates/${id}/versions`, { channels: added });
                }
            } else {
                res = await this.API('POST', '/api/communications/templates', payload);
            }
            if (res.success) {
                showNotification('Template saved successfully', 'success');
                bootstrap.Modal.getInstance(document.getElementById('templateManagerModal')).hide();
                this.loadTemplates();
            } else {
                showNotification(res.message || 'Failed to save template', 'error');
            }
        } catch (err) {
            showNotification('Error saving template: ' + err.message, 'error');
        }
    },

    async editTemplate(catalogId) {
        try {
            const res = await this.API('GET', `/api/communications/templates/${catalogId}`);
            if (res.success && res.template) {
                this.openTemplateModal(res.template);
            } else {
                showNotification(res.message || 'Failed to load template', 'error');
            }
        } catch (err) {
            showNotification('Error loading template: ' + err.message, 'error');
        }
    },

    async editChannel(channelId) {
        try {
            const res = await this.API('GET', `/api/communications/templates/channels/${channelId}`);
            if (res.success && res.channel) {
                const variant = res.channel;
                this.state.channelVariants = [{
                    channel: variant.channel,
                    language_code: variant.language_code,
                    body_format: variant.body_format,
                    subject: variant.subject,
                    body: variant.body,
                    header: variant.header,
                    footer: variant.footer,
                    media_json: variant.media_json,
                    provider_template_id: variant.provider_template_id,
                }];

                this.state.editingCatalogId = variant.catalog_id;
                this.state.editingVersionId = variant.version_id;

                document.getElementById('inputCatalogId').value = variant.catalog_id;
                document.getElementById('inputVersionId').value = variant.version_id;
                document.getElementById('inputCode').value = variant.code;
                document.getElementById('inputCode').readOnly = true;
                document.getElementById('inputName').value = variant.name;
                document.getElementById('inputPurpose').value = variant.purpose;
                document.getElementById('templateModalTitle').textContent = 'Edit Template';

                this.renderChannelVariantsList();

                new bootstrap.Modal(document.getElementById('templateManagerModal')).show();
            } else {
                showNotification(res.message || 'Failed to load channel', 'error');
            }
        } catch (err) {
            showNotification('Error loading channel: ' + err.message, 'error');
        }
    },

    async deleteTemplate(catalogId) {
        if (!confirm('Delete this template and all its versions?')) return;
        try {
            const res = await this.API('DELETE', `/api/communications/templates/${catalogId}`);
            if (res.success) {
                showNotification('Template deleted', 'success');
                this.loadTemplates();
            } else {
                showNotification(res.message || 'Failed to delete template', 'error');
            }
        } catch (err) {
            showNotification('Error deleting template: ' + err.message, 'error');
        }
    },

    async approveVersion(versionId) {
        if (!confirm('Approve this version? It will become the active version.')) return;
        try {
            const res = await this.API('POST', `/api/communications/templates/versions/${versionId}/approve`);
            if (res.success) {
                showNotification('Version approved and activated', 'success');
                this.loadTemplates();
            } else {
                showNotification(res.message || 'Failed to approve version', 'error');
            }
        } catch (err) {
            showNotification('Error approving version: ' + err.message, 'error');
        }
    },

    // ========== PREVIEW & TOKENS ==========

    populateTokenDropdown() {
        const $dropdown = document.getElementById('tokenDropdown');
        $dropdown.innerHTML = '';
        this.tokenList.forEach(t => {
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item insert-token" href="#" data-token="${t.token}">${t.token} <span class="text-muted small">(${t.label})</span></a>`;
            $dropdown.appendChild(li);
        });

        $dropdown.querySelectorAll('.insert-token').forEach(a => {
            a.onclick = (e) => {
                e.preventDefault();
                this.insertTokenAtCursor(a.dataset.token);
            };
        });
    },

    insertTokenAtCursor(token) {
        const textarea = document.getElementById('inputBody');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const val = textarea.value;
        textarea.value = val.substring(0, start) + token + val.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + token.length;
        textarea.focus();
        this.updateCharCount();
        this.updateSegmentEstimate();
    },

    updateCharCount() {
        const len = document.getElementById('inputBody').value.length;
        document.getElementById('charCount').textContent = len;
    },

    updateSegmentEstimate() {
        const body = document.getElementById('inputBody').value;
        const vars = this.getTestVariables();
        const rendered = this.simulateRender(body, vars, 'sms');
        if (rendered) {
            const segments = this.estimateSegments(rendered);
            document.getElementById('segmentEstimate').textContent = `Segments: ${segments.segments}`;
            document.getElementById('segmentInfo').textContent = `${segments.segments} segment${segments.segments > 1 ? 's' : ''} (${segments.encoding})`;
        }
    },

    getTestVariables() {
        try { return JSON.parse(document.getElementById('previewVariables').value || '{}'); }
        catch { return {}; }
    },

    simulateRender(body, vars, channel) {
        let rendered = body;
        for (const [key, value] of Object.entries(vars)) {
            rendered = rendered.replace(new RegExp('@' + key, 'g'), value);
            rendered = rendered.replace(new RegExp('\\{\\{\\s*' + key + '\\s*\\}\\}', 'g'), value);
        }
        if (channel === 'whatsapp') {
            rendered = rendered.replace(/<b>/gi, '').replace(/<\/b>/gi, '').replace(/<br\s*\/?>/gi, '\n');
        }
        return rendered;
    },

    estimateSegments(text) {
        const unicode = /[^\x00-\x7F]/.test(text);
        const length = unicode ? text.length : this.countGsm7(text);
        const single = unicode ? 70 : 160;
        const multi = unicode ? 67 : 153;
        const segments = length <= single ? 1 : Math.ceil(length / multi);
        return { segments, encoding: unicode ? 'UCS-2' : 'GSM-7', length, unicode };
    },

    countGsm7(text) {
        const ext = "^{}\\[~]|€";
        let len = 0;
        for (const ch of text) { len += ext.includes(ch) ? 2 : 1; }
        return len;
    },

    openPreviewModal() {
        const activeVariant = this.state.channelVariants.find(v => v.channel === document.getElementById('inputChannel').value) || this.state.channelVariants[0];
        if (!activeVariant) { showNotification('No channel variant to preview', 'warning'); return; }

        document.getElementById('previewVariables').value = JSON.stringify({
            first_name: 'John', last_name: 'Doe', student_name: 'John Doe', parent_name: 'Jane Doe',
            class_name: '6A', amount_due: 'KES 5,000', due_date: '2025-12-31',
            event_title: 'Prize Giving Day', event_date: '2025-11-15', event_time: '10:00 AM', event_venue: 'School Hall',
            school_name: 'Kingsway Preparatory School', school_address: 'Simotwet, Londiani, Kericho',
            school_phone: '+254 700 000 000', school_email: 'info@kingsway.ac.ke',
            school_motto: 'Education for Life', school_logo: 'https://kingsway.ac.ke/assets/logo.png',
        }, null, 2);

        this.renderPreview();
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    },

    renderPreview() {
        const vars = this.getTestVariables();
        const output = document.getElementById('previewOutput');
        let html = '';
        const channelColors = { sms: 'success', whatsapp: 'info', email: 'primary', portal: 'secondary', in_app: 'dark' };

        this.state.channelVariants.forEach(variant => {
            const channel = variant.channel;
            const rendered = this.simulateRender(variant.body, vars, channel);
            const subject = variant.subject ? this.simulateRender(variant.subject, vars, channel) : '';

            if (channel === 'email') {
                const header = variant.header ? this.simulateRender(variant.header, vars, channel) : '';
                const footer = variant.footer ? this.simulateRender(variant.footer, vars, channel) : '';
                html += `
                    <div class="card mb-3">
                        <div class="card-header bg-${channelColors[channel]} text-white">${channel.toUpperCase()}</div>
                        <div class="card-body">
                            ${subject ? `<div class="mb-3"><strong>Subject:</strong> ${this.esc(subject)}</div>` : ''}
                            ${header ? `<div class="border-bottom mb-3 pb-3">${header}</div>` : ''}
                            <div class="preview-body" style="white-space: pre-wrap;">${this.esc(rendered)}</div>
                            ${footer ? `<div class="border-top mt-3 pt-3">${footer}</div>` : ''}
                        </div>
                    </div>
                `;
            } else {
                const segments = this.estimateSegments(rendered);
                html += `
                    <div class="card mb-3">
                        <div class="card-header bg-${channelColors[channel]} text-white d-flex justify-content-between">
                            ${channel.toUpperCase()}
                            <span class="badge bg-light text-dark">${segments.segments} segment(s)</span>
                        </div>
                        <div class="card-body">
                            ${subject ? `<div class="mb-3"><strong>Subject:</strong> ${this.esc(subject)}</div>` : ''}
                            <div class="preview-body font-monospace" style="white-space: pre-wrap; background:#f8f9fa; padding:15px; border-radius:4px;">${this.esc(rendered)}</div>
                        </div>
                    </div>
                `;
            }
        });

        output.innerHTML = html;
    },

    async testSend() {
        const vars = this.getTestVariables();
        if (!Object.keys(vars).length) { showNotification('Please provide test variables', 'warning'); return; }

        const activeVariant = this.state.channelVariants.find(v => v.channel === document.getElementById('inputChannel').value) || this.state.channelVariants[0];
        if (!activeVariant) return;

        try {
            const res = await this.API('POST', '/api/communications/templates/preview', {
                channel_id: activeVariant.channel_id,
                variables: vars,
                channel: activeVariant.channel,
            });
            if (res.success) showNotification('Preview rendered successfully', 'success');
            else showNotification(res.message || 'Preview failed', 'error');
        } catch (err) { showNotification('Error: ' + err.message, 'error'); }
    },

    // ========== FILTERS ==========

    bindTemplateFilters() {
        document.getElementById('templateFilterChannel').onchange = () => {
            this.state.templateFilters.channel = document.getElementById('templateFilterChannel').value;
            this.state.currentTemplatePage = 1;
            this.loadTemplates();
        };
        document.getElementById('templateFilterStatus').onchange = () => {
            this.state.templateFilters.status = document.getElementById('templateFilterStatus').value;
            this.state.currentTemplatePage = 1;
            this.loadTemplates();
        };
        document.getElementById('templateFilterSearch').oninput = debounce(() => {
            this.state.templateFilters.search = document.getElementById('templateFilterSearch').value;
            this.state.currentTemplatePage = 1;
            this.loadTemplates();
        }, 300);
        document.getElementById('templateClearFilters').onclick = () => {
            document.getElementById('templateFilterChannel').value = '';
            document.getElementById('templateFilterStatus').value = '';
            document.getElementById('templateFilterSearch').value = '';
            this.state.templateFilters = { channel: '', status: '', search: '' };
            this.state.currentTemplatePage = 1;
            this.loadTemplates();
        };
    },

    async init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        await this.loadReferencesComposed();
        await this.load();

        // Template UI bindings
        this.bindTemplateFilters();
        this.populateTokenDropdown();

        document.getElementById('newTemplateBtn').onclick = () => this.openTemplateModal();
        document.getElementById('templateForm').onsubmit = (e) => { e.preventDefault(); this.saveTemplate(); };
        document.getElementById('addChannelBtn').onclick = () => this.openChannelModal();
        document.getElementById('channelForm').onsubmit = (e) => { e.preventDefault(); this.saveChannel(); };
        document.getElementById('previewBtn').onclick = () => this.openPreviewModal();
        document.getElementById('channelPreviewBtn').onclick = () => this.openPreviewModal();
        document.getElementById('previewVariables').oninput = debounce(() => this.renderPreview(), 300);
        document.getElementById('testSendBtn').onclick = () => this.testSend();
        document.getElementById('inputBody').oninput = () => { this.updateCharCount(); this.updateSegmentEstimate(); };
        document.getElementById('inputChannel').onchange = () => this.toggleChannelSpecificFields();
        document.getElementById('addMediaBtn').onclick = () => this.addMediaRow();

        window.APIRealtime?.register?.("manage_communications", {
            refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
        }, ["communications"]);
    },
};

function debounce(fn, delay) { let timer; return function(...args) { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, args), delay); }; }

window.manageCommunicationsController = manageCommunicationsController;
document.addEventListener('DOMContentLoaded', () => manageCommunicationsController.init().catch(() => {}));