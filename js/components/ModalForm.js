/**
 * ModalForm Component - Reusable modal form builder for CRUD and workflow actions
 * Integrates with API and handles validation/submission
 */

class ModalForm {
    constructor(modalId, options = {}) {
        this.modalId = modalId;
        this.modal = null;
        this.bsModal = null;
        
        // Configuration
        this.title = options.title || 'Form';
        this.apiEndpoint = options.apiEndpoint;
        this.method = options.method || 'POST';
        this.fields = options.fields || [];
        this.onSubmit = options.onSubmit || null;
        this.submitButtonLabel = options.submitButtonLabel || 'Save';
        this.size = options.size || 'lg'; // sm, lg, xl
        this.validators = options.validators || {};
        this.formatters = options.formatters || {};
        
        // State
        this.formData = {};
        this.isLoading = false;
        this.isEditing = false;
        this.currentId = null;
        
        // Initialize
        this.init();
    }

    init() {
        this.createModalHTML();
        this.modal = document.getElementById(this.modalId);
        this.bsModal = new bootstrap.Modal(this.modal);
        this.attachEventListeners();
    }

    createModalHTML() {
        const existingModal = document.getElementById(this.modalId);
        if (existingModal) {
            existingModal.remove();
        }

        const html = `
            <div class="modal fade" id="${this.modalId}" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-${this.size}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="${this.modalId}-title">${this.title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="${this.modalId}-form" novalidate>
                            <div class="modal-body" id="${this.modalId}-body">
                                ${this.renderFormFields()}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="${this.modalId}-submit">
                                    ${this.submitButtonLabel}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
    }

    renderFormFields() {
        return this.fields.map(field => this.renderField(field)).join('');
    }

    renderField(field) {
        const fieldId = `${this.modalId}-field-${field.name}`;
        
        // Skip rendering if field is hidden
        if (field.type === 'hidden') {
            return `<input type="hidden" id="${fieldId}" name="${field.name}">`;
        }

        let fieldHTML = `<div class="mb-3">`;

        if (field.type !== 'checkbox') {
            fieldHTML += `<label for="${fieldId}" class="form-label">${field.label}`;
            if (field.required) {
                fieldHTML += `<span class="text-danger">*</span>`;
            }
            fieldHTML += `</label>`;
        }

        const inputAttrs = `
            id="${fieldId}" 
            name="${field.name}"
            class="form-control"
            ${field.required ? 'required' : ''}
            ${field.disabled ? 'disabled' : ''}
            ${field.placeholder ? `placeholder="${field.placeholder}"` : ''}
            ${field.attributes ? Object.entries(field.attributes).map(([k, v]) => `${k}="${v}"`).join(' ') : ''}
        `;

        switch (field.type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'number':
                fieldHTML += `<input type="${field.type}" ${inputAttrs} />`;
                break;

            case 'textarea':
                fieldHTML += `<textarea ${inputAttrs} rows="${field.rows || 3}"></textarea>`;
                break;

            case 'select':
                fieldHTML += `
                    <select ${inputAttrs}>
                        <option value="">-- Select ${field.label} --</option>
                        ${(field.options || []).map(opt => `
                            <option value="${opt.value || opt.id}">${opt.label || opt.name}</option>
                        `).join('')}
                    </select>
                `;
                break;

            case 'multiselect':
                fieldHTML += `
                    <select ${inputAttrs} multiple>
                        ${(field.options || []).map(opt => `
                            <option value="${opt.value || opt.id}">${opt.label || opt.name}</option>
                        `).join('')}
                    </select>
                `;
                break;

            case 'checkbox':
                fieldHTML = `
                    <div class="form-check mb-3">
                        <input type="checkbox" ${inputAttrs} />
                        <label class="form-check-label" for="${fieldId}">${field.label}</label>
                    </div>
                `;
                break;

            case 'date':
            case 'datetime-local':
            case 'time':
                fieldHTML += `<input type="${field.type}" ${inputAttrs} />`;
                break;

            case 'file':
                fieldHTML += `
                    <input type="file" ${inputAttrs} 
                        ${field.accept ? `accept="${field.accept}"` : ''} />
                    ${field.helpText ? `<small class="form-text text-muted">${field.helpText}</small>` : ''}
                `;
                break;

            case 'radio':
                fieldHTML = `<div class="mb-3"><label class="form-label">${field.label}</label>`;
                (field.options || []).forEach(opt => {
                    const optId = `${fieldId}-${opt.value || opt.id}`;
                    fieldHTML += `
                        <div class="form-check">
                            <input type="radio" id="${optId}" name="${field.name}" value="${opt.value || opt.id}" class="form-check-input" />
                            <label class="form-check-label" for="${optId}">${opt.label || opt.name}</label>
                        </div>
                    `;
                });
                fieldHTML += `</div>`;
                break;

            case 'custom':
                if (field.customRenderer) {
                    fieldHTML += field.customRenderer(fieldId);
                }
                break;
        }

        if (field.type !== 'checkbox' && field.type !== 'radio' && field.type !== 'custom') {
            if (field.helpText) {
                fieldHTML += `<small class="form-text text-muted">${field.helpText}</small>`;
            }
        }

        fieldHTML += `</div>`;
        return fieldHTML;
    }

    attachEventListeners() {
        const form = document.getElementById(`${this.modalId}-form`);
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
    }

    async handleSubmit(e) {
        e.preventDefault();

        const form = document.getElementById(`${this.modalId}-form`);
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        // Collect form data
        this.formData = this.getFormData();

        // Validate custom validators
        for (const [field, validator] of Object.entries(this.validators)) {
            const value = this.formData[field];
            const error = validator(value, this.formData);
            if (error) {
                window.API.showNotification(error, NOTIFICATION_TYPES.ERROR);
                return;
            }
        }

        this.isLoading = true;
        this.setSubmitButtonLoading(true);

        try {
            // Call custom onSubmit if provided
            if (this.onSubmit) {
                const result = await this.onSubmit(this.formData, this.isEditing);
                if (result === false) {
                    // onSubmit handled the submission, don't call API
                    this.setSubmitButtonLoading(false);
                    return;
                }
            }

            // Default API call
            if (this.apiEndpoint) {
                const method = this.isEditing && this.currentId ? 'PUT' : this.method;
                const endpoint = this.isEditing && this.currentId 
                    ? `${this.apiEndpoint}/${this.currentId}`
                    : this.apiEndpoint;

                const response = await window.API.apiCall(endpoint, method, this.formData);
                
                window.API.showNotification(
                    response.message || `${this.isEditing ? 'Updated' : 'Created'} successfully`,
                    NOTIFICATION_TYPES.SUCCESS
                );

                this.bsModal.hide();
                
                // Trigger refresh if callback provided
                if (window.onModalSuccess) {
                    window.onModalSuccess();
                }
            }
        } catch (error) {
            console.error('Form submission error:', error);
            window.API.showNotification(
                error.message || 'An error occurred while saving',
                NOTIFICATION_TYPES.ERROR
            );
        } finally {
            this.isLoading = false;
            this.setSubmitButtonLoading(false);
        }
    }

    getFormData() {
        const form = document.getElementById(`${this.modalId}-form`);
        const formData = new FormData(form);
        const data = {};

        for (const [key, value] of formData.entries()) {
            // Handle file uploads
            if (value instanceof File) {
                data[key] = value;
            }
            // Handle checkboxes
            else if (form.elements[key].type === 'checkbox') {
                data[key] = form.elements[key].checked ? 1 : 0;
            }
            // Handle multiselect
            else if (form.elements[key].multiple) {
                if (!data[key]) data[key] = [];
                data[key].push(value);
            }
            else {
                data[key] = value;
            }
        }

        return data;
    }

    setSubmitButtonLoading(loading) {
        const submitBtn = document.getElementById(`${this.modalId}-submit`);
        if (submitBtn) {
            submitBtn.disabled = loading;
            submitBtn.innerHTML = loading 
                ? `<span class="spinner-border spinner-border-sm me-2"></span>Saving...` 
                : this.submitButtonLabel;
        }
    }

    // Public methods
    open(data = null, title = null) {
        this.isEditing = !!data;
        this.currentId = data?.id || data?.ID || null;
        
        if (title) {
            document.getElementById(`${this.modalId}-title`).textContent = title;
        }

        this.formData = data || {};
        this.populateForm(data);
        this.bsModal.show();
    }

    populateForm(data) {
        if (!data) return;

        Object.entries(data).forEach(([key, value]) => {
            const element = document.querySelector(`#${this.modalId}-form [name="${key}"]`);
            if (!element) return;

            if (element.type === 'checkbox') {
                element.checked = !!value;
            } else if (element.multiple) {
                // Multiselect
                if (Array.isArray(value)) {
                    Array.from(element.options).forEach(opt => {
                        opt.selected = value.includes(opt.value);
                    });
                }
            } else {
                element.value = value;
            }
        });
    }

    close() {
        this.bsModal.hide();
    }

    reset() {
        const form = document.getElementById(`${this.modalId}-form`);
        form.reset();
        form.classList.remove('was-validated');
        this.formData = {};
        this.isEditing = false;
        this.currentId = null;
    }

    updateFields(fields) {
        this.fields = fields;
        const body = document.getElementById(`${this.modalId}-body`);
        if (body) {
            body.innerHTML = this.renderFormFields();
        }
    }

    setFieldValue(fieldName, value) {
        const element = document.querySelector(`#${this.modalId}-form [name="${fieldName}"]`);
        if (element) {
            element.value = value;
            this.formData[fieldName] = value;
        }
    }

    getFieldValue(fieldName) {
        return this.formData[fieldName] || '';
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalForm;
}
