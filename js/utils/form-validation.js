/**
 * Frontend Validation Library
 * 
 * Provides client-side validation matching backend ValidationHelper
 * Gives immediate feedback to users before API calls
 */

// Use a reusable global so page fragments loaded by the authenticated shell
// cannot throw a second-declaration error when they include this utility.
var FormValidation = window.FormValidation || {
    /**
     * Validate email format
     */
    validateEmail(email) {
        if (!email || email.trim() === '') {
            return { valid: false, error: 'Email is required' };
        }

        email = email.trim();

        // RFC 5322 simplified regex
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        if (!emailRegex.test(email)) {
            return { valid: false, error: 'Invalid email format (e.g., user@example.com)' };
        }

        return { valid: true, value: email };
    },

    /**
     * Validate username format
     * Rules: 3-30 chars, alphanumeric + underscore/hyphen, must start with letter
     */
    validateUsername(username) {
        if (!username || username.trim() === '') {
            return { valid: false, error: 'Username is required' };
        }

        username = username.trim();

        if (username.length < 3 || username.length > 30) {
            return { valid: false, error: 'Username must be 3-30 characters' };
        }

        if (!/^[a-zA-Z][a-zA-Z0-9_-]*$/.test(username)) {
            return { valid: false, error: 'Username must start with a letter and contain only letters, numbers, underscore, or hyphen' };
        }

        return { valid: true, value: username };
    },

    /**
     * Validate password strength
     * Rules: Min 6 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
     * Must stay aligned with ValidationHelper::validatePassword() in
     * api/includes/ValidationHelper.php and the minlength="6" markup.
     */
    validatePassword(password) {
        if (!password || password === '') {
            return { valid: false, error: 'Password is required' };
        }

        if (password.length < 6) {
            return { valid: false, error: 'Password must be at least 6 characters long' };
        }

        if (password.length > 128) {
            return { valid: false, error: 'Password must not exceed 128 characters' };
        }

        if (!/[A-Z]/.test(password)) {
            return { valid: false, error: 'Password must contain at least one uppercase letter' };
        }

        if (!/[a-z]/.test(password)) {
            return { valid: false, error: 'Password must contain at least one lowercase letter' };
        }

        if (!/[0-9]/.test(password)) {
            return { valid: false, error: 'Password must contain at least one number' };
        }

        if (!/[^a-zA-Z0-9]/.test(password)) {
            return { valid: false, error: 'Password must contain at least one special character (!@#$%^&*etc)' };
        }

        // Check for common weak passwords
        const weakPasswords = [
            'password', 'password1!', '12345678', 'qwerty123', 'admin123',
            'welcome1!', 'password123!', 'admin@123', 'test@123'
        ];
        
        if (weakPasswords.includes(password.toLowerCase())) {
            return { valid: false, error: 'This password is too common. Please choose a stronger password' };
        }

        return { valid: true, value: password };
    },

    /**
     * Calculate password strength score (0-100)
     */
    getPasswordStrength(password) {
        let score = 0;
        
        if (!password) return 0;

        // Length bonus
        if (password.length >= 8) score += 20;
        if (password.length >= 12) score += 10;
        if (password.length >= 16) score += 10;

        // Character variety bonuses
        if (/[a-z]/.test(password)) score += 10;
        if (/[A-Z]/.test(password)) score += 10;
        if (/[0-9]/.test(password)) score += 10;
        if (/[^a-zA-Z0-9]/.test(password)) score += 10;

        // Multiple special chars
        const specialChars = password.match(/[^a-zA-Z0-9]/g);
        if (specialChars && specialChars.length > 1) score += 10;

        // Multiple numbers
        const numbers = password.match(/[0-9]/g);
        if (numbers && numbers.length > 1) score += 5;

        // Mixed case
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 5;

        return Math.min(score, 100);
    },

    /**
     * Get password strength label and color
     */
    getPasswordStrengthLabel(score) {
        if (score < 40) {
            return { label: 'Weak', color: 'danger', class: 'bg-danger' };
        } else if (score < 70) {
            return { label: 'Fair', color: 'warning', class: 'bg-warning' };
        } else if (score < 90) {
            return { label: 'Good', color: 'info', class: 'bg-info' };
        } else {
            return { label: 'Strong', color: 'success', class: 'bg-success' };
        }
    },

    /**
     * Validate name (first_name, last_name)
     */
    validateName(name, fieldName = 'Name') {
        if (!name || name.trim() === '') {
            return { valid: false, error: `${fieldName} is required` };
        }

        name = name.trim();

        if (name.length < 1 || name.length > 50) {
            return { valid: false, error: `${fieldName} must be 1-50 characters` };
        }

        if (!/^[a-zA-Z\s'-]+$/.test(name)) {
            return { valid: false, error: `${fieldName} can only contain letters, spaces, hyphens, and apostrophes` };
        }

        return { valid: true, value: name };
    },

    /**
     * Validate status
     */
    validateStatus(status) {
        const validStatuses = ['active', 'inactive', 'suspended', 'pending'];
        
        if (!validStatuses.includes(status)) {
            return { valid: false, error: 'Invalid status. Must be: active, inactive, suspended, or pending' };
        }

        return { valid: true, value: status };
    },

    /**
     * Comprehensive user form validation
     */
    validateUserForm(formData, isUpdate = false) {
      const errors = [];

      // Username validation
      if (!isUpdate || formData.username !== undefined) {
        const result = this.validateUsername(formData.username);
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // Email validation
      if (!isUpdate || formData.email !== undefined) {
        const result = this.validateEmail(formData.email);
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // Password validation (required for create, optional for update)
      if (!isUpdate) {
        const result = this.validatePassword(formData.password);
        if (!result.valid) {
          errors.push(result.error);
        }
      } else if (formData.password && formData.password !== "") {
        const result = this.validatePassword(formData.password);
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // First name validation
      if (!isUpdate || formData.first_name !== undefined) {
        const result = this.validateName(formData.first_name, "First name");
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // Last name validation
      if (!isUpdate || formData.last_name !== undefined) {
        const result = this.validateName(formData.last_name, "Last name");
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // Status validation
      if (formData.status !== undefined) {
        const result = this.validateStatus(formData.status);
        if (!result.valid) {
          errors.push(result.error);
        }
      }

      // Role(s) validation - ensure at least one role selected on create
      if (!isUpdate) {
        const roleIds = formData.role_ids || formData.role_id || [];
        const count = Array.isArray(roleIds) ? roleIds.length : roleIds ? 1 : 0;
        if (count === 0) {
          errors.push("At least one role must be selected");
        }
      }

      return {
        valid: errors.length === 0,
        errors: errors,
      };
    },

    /**
     * Show validation error on input field
     */
    showFieldError(fieldId, errorMessage) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.classList.add('is-invalid');
        
        // Remove existing error message
        const existingError = field.parentElement.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }

        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = errorMessage;
        field.parentElement.appendChild(errorDiv);
    },

    /**
     * Clear validation error from field
     */
    clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        const errorDiv = field.parentElement.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    },

    /**
     * Clear all field errors in a form
     */
    clearAllErrors(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });

        form.querySelectorAll('.invalid-feedback').forEach(error => {
            error.remove();
        });

        form.querySelectorAll('.is-valid').forEach(field => {
            field.classList.remove('is-valid');
        });
    },

    /**
     * Setup real-time validation for a field
     */
    setupRealTimeValidation(fieldId, validationFunc, ...args) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        field.addEventListener('blur', () => {
            const result = validationFunc(field.value, ...args);
            
            if (result.valid) {
                this.clearFieldError(fieldId);
            } else {
                this.showFieldError(fieldId, result.error);
            }
        });

        field.addEventListener('input', () => {
            // Clear error on input to give immediate feedback
            if (field.classList.contains('is-invalid')) {
                field.classList.remove('is-invalid');
            }
        });
    },

    /**
     * Setup password strength meter
     */
    setupPasswordStrengthMeter(passwordFieldId, meterContainerId) {
        const passwordField = document.getElementById(passwordFieldId);
        const meterContainer = document.getElementById(meterContainerId);
        
        if (!passwordField || !meterContainer) return;

        passwordField.addEventListener('input', () => {
            const password = passwordField.value;
            const score = this.getPasswordStrength(password);
            const strength = this.getPasswordStrengthLabel(score);

            meterContainer.innerHTML = `
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar ${strength.class}" 
                         role="progressbar" 
                         style="width: ${score}%"
                         aria-valuenow="${score}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
                <small class="text-${strength.color} mt-1">
                    Password Strength: <strong>${strength.label}</strong>
                </small>
            `;
        });
    },

    // ---- data-kw-validate self-binding kernel (shared with backend FieldCleaner) ----

    /**
     * Validate a personal name (letters/spaces/hyphens/apostrophes only).
     *
     * Mirrors backend FieldCleaner::cleanName: collapses whitespace, rejects
     * digits/symbols, and returns the title-cased canonical value.
     *
     * @param {string} value raw input
     * @param {string} [fieldName='Name']
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validatePersonName(value, fieldName = 'Name') {
        if (!value || value.trim() === '') {
            return { valid: false, error: `${fieldName} is required` };
        }

        const collapsed = value.trim().replace(/\s{2,}/g, ' ');

        if (!/^[a-zA-Z'’\s\-]+$/.test(collapsed)) {
            return { valid: false, error: `${fieldName} can only contain letters, spaces, hyphens, and apostrophes (no digits or symbols)` };
        }

        const titleCased = collapsed.replace(/(^|[\s'’\-])([a-z])/g, (m, sep, letter) => sep + letter.toUpperCase());

        return { valid: true, value: titleCased };
    },

    /**
     * Validate a date of birth: parseable and STRICTLY in the past.
     *
     * Rejects today and future dates because a date of birth can never be today.
     * Also rejects implausibly old dates (before 1900) to catch typo'd years.
     *
     * @param {string} value
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validateDob(value) {
        if (!value || value.trim() === '') {
            return { valid: false, error: 'Date of birth is required' };
        }

        const d = new Date(value);
        if (isNaN(d.getTime())) {
            return { valid: false, error: 'Enter a valid date of birth' };
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        d.setHours(0, 0, 0, 0);

        if (d >= today) {
            return { valid: false, error: 'Date of birth must be in the past (not today or future)' };
        }

        if (d.getFullYear() < 1900) {
            return { valid: false, error: 'Enter a valid date of birth' };
        }

        return { valid: true, value };
    },

    /**
     * Validate any date that must not be today or in the future.
     *
     * @param {string} value
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validateNotFuture(value) {
        if (!value || value.trim() === '') {
            return { valid: false, error: 'Date is required' };
        }

        const d = new Date(value);
        if (isNaN(d.getTime())) {
            return { valid: false, error: 'Enter a valid date' };
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        d.setHours(0, 0, 0, 0);

        if (d > today) {
            return { valid: false, error: 'Date must not be in the future' };
        }

        return { valid: true, value };
    },

    /**
     * Canonicalize a Kenyan phone number to 254XXXXXXXXX.
     *
     * Mirrors PhoneNumberNormalizer. The prefix is used for normalization
     * only and does not identify an operator:
     *   07XXXXXXXX -> 2547XXXXXXXX
     *   01XXXXXXXX -> 2541XXXXXXXX
     * Accepts 07/01/7/1, 254..., +254..., with spaces/dashes/plus. Returns null
     * when the value is not a Kenyan mobile number.
     *
     * @param {string} value
     * @returns {string|null}
     */
    canonicalizePhone(value) {
        if (!value) return null;
        let digits = String(value).replace(/[^0-9]/g, '');
        if (digits.length === 9 && (digits.startsWith('7') || digits.startsWith('1'))) {
            digits = '254' + digits;
        }
        if (digits.startsWith('0')) {
            digits = '254' + digits.slice(1);
        }
        return /^254[71][0-9]{8}$/.test(digits) ? digits : null;
    },

    /**
     * Validate a Kenyan national ID / passport number.
     *
     * Mirrors backend FieldCleaner::cleanNationalId: digits only (separators
     * stripped), 5-12 characters; a value containing letters is rejected
     * rather than silently rewritten.
     *
     * @param {string} value
     * @param {string} [fieldName='National ID / Passport']
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validateNationalId(value, fieldName = 'National ID / Passport') {
        if (!value || value.trim() === '') {
            return { valid: false, error: `${fieldName} is required` };
        }
        const cleaned = String(value).replace(/[\s\-.\/]+/g, '').trim();
        if (!/^[0-9]+$/.test(cleaned)) {
            return { valid: false, error: `${fieldName} must be digits only (5-12 characters)` };
        }
        if (cleaned.length < 5 || cleaned.length > 12) {
            return { valid: false, error: `${fieldName} must contain 5-12 digits` };
        }
        return { valid: true, value: cleaned };
    },

    /**
     * Validate a free-text address.
     *
     * Mirrors backend FieldCleaner::cleanAddress: trims, collapses internal
     * whitespace, allows letters/digits and common address punctuation.
     *
     * @param {string} value
     * @param {string} [fieldName='Address']
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validateAddress(value, fieldName = 'Address') {
        if (!value || value.trim() === '') {
            return { valid: false, error: `${fieldName} is required` };
        }
        const collapsed = String(value).trim().replace(/\s{2,}/g, ' ');
        if (collapsed.length > 120) {
            return { valid: false, error: `${fieldName} must not exceed 120 characters` };
        }
        if (!/^[a-zA-Z0-9'’&@\/.,#()\s\-]+$/.test(collapsed)) {
            return { valid: false, error: `${fieldName} contains characters that are not allowed` };
        }
        return { valid: true, value: collapsed };
    },

    /**
     * Validate a Kenyan phone number.
     *
     * @param {string} value
     * @param {string} [fieldName='Phone number']
     * @returns {{valid: boolean, error?: string, value?: string}}
     */
    validatePhone(value, fieldName = 'Phone number') {
        if (!value || value.trim() === '') {
            return { valid: false, error: `${fieldName} is required` };
        }
        const canonical = this.canonicalizePhone(value);
        if (!canonical) {
            return { valid: false, error: 'Enter a valid Kenyan phone number (e.g., 0712 345 678 or 0112 345 678)' };
        }
        return { valid: true, value: canonical };
    },

    /**
     * Resolve the validator(s) declared by a field's data-kw-validate attribute.
     *
     * @param {string} attrValue e.g. "name" or "dob,phone"
     * @returns {string[]}
     */
    validatorsFromAttr(attrValue) {
        return String(attrValue || '')
            .split(',')
            .map(s => s.trim())
            .filter(Boolean);
    },

    /**
     * Run all declared validators for one field.
     *
     * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
     * @returns {{valid: boolean, error?: string, value?: any}}
     */
    runFieldValidators(field) {
        const validators = this.validatorsFromAttr(field.dataset.kwValidate);
        const required = field.required;

        // Optional (unrequired) fields may be left blank.
        if (!required && !String(field.value).trim()) {
            return { valid: true, value: field.value };
        }

        for (const name of validators) {
            let result;
            if (name === 'name') {
                result = this.validatePersonName(field.value, this.fieldLabel(field));
            } else if (name === 'email') {
                result = this.validateEmail(field.value);
            } else if (name === 'phone') {
                result = this.validatePhone(field.value, this.fieldLabel(field));
            } else if (name === 'national_id') {
                result = this.validateNationalId(field.value, this.fieldLabel(field));
            } else if (name === 'address') {
                result = this.validateAddress(field.value, this.fieldLabel(field));
            } else if (name === 'dob') {
                result = this.validateDob(field.value);
            } else if (name === 'not_future') {
                result = this.validateNotFuture(field.value);
            } else {
                continue;
            }

            if (!result.valid) {
                return result;
            }
            // Apply the canonical value from any passing validator in place.
            if (result.value !== undefined && String(field.value) !== result.value) {
                field.value = result.value;
            }
        }

        return { valid: true, value: field.value };
    },

    /**
     * Human label for a field (from name attribute, best effort).
     */
    fieldLabel(field) {
        const name = field.name || field.id || '';
        return name
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/^\w/, c => c.toUpperCase()) || 'Field';
    },

    /**
     * Show an inline error on a field element (Bootstrap invalid-feedback).
     */
    showElementError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');

        const parent = field.closest('.form-group, .mb-3, .form-field') || field.parentElement;
        let error = parent ? parent.querySelector('.invalid-feedback') : null;
        if (!error) {
            error = document.createElement('div');
            error.className = 'invalid-feedback';
            if (parent) {
                parent.appendChild(error);
            } else {
                field.after(error);
            }
        }
        error.textContent = message;
    },

    /**
     * Clear an inline error on a field element.
     */
    clearElementError(field) {
        field.classList.remove('is-invalid');
        const parent = field.closest('.form-group, .mb-3, .form-field') || field.parentElement;
        if (parent) {
            const error = parent.querySelector('.invalid-feedback');
            if (error) {
                error.remove();
            }
        }
    },

    /**
     * Validate an entire form by scanning data-kw-validate fields.
     *
     * @param {HTMLFormElement} form
     * @returns {boolean} true when the form is valid
     */
    validateForm(form) {
        const fields = form.querySelectorAll('[data-kw-validate]');
        let firstInvalid = null;
        let isValid = true;

        fields.forEach(field => {
            if (field.disabled || field.readOnly) return;
            const result = this.runFieldValidators(field);
            if (result.valid) {
                this.clearElementError(field);
            } else {
                this.showElementError(field, result.error);
                isValid = false;
                if (!firstInvalid) firstInvalid = field;
            }
        });

        if (!isValid && firstInvalid) {
            firstInvalid.focus();
        }
        return isValid;
    },

    /** @type {Set<HTMLFormElement>} forms opted into self-binding */
    _boundForms: null,

    /**
     * Bind the self-loading kernel: intercept submit and auto-validate.
     *
     * Forms opt in by carrying at least one
     * data-kw-validate field. Valid forms are not blocked; the regular submit
     * handler proceeds. Invalid forms are stopped with errors shown inline.
     *
     * Uses a single document-level capture listener (once) so validation runs
     * BEFORE any inline onsubmit/controller handler and can block it.
     *
     * @param {HTMLFormElement} form
     */
    bindForm(form) {
        if (!form) return;
        if (!this._boundForms) {
            this._boundForms = new Set();
            this._installGlobalSubmitInterceptor();
            this._installGlobalBlurFeedback();
        }
        this._boundForms.add(form);
    },

    _installGlobalSubmitInterceptor() {
        const self = this;
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!form || typeof form.matches !== 'function' || !form.matches('form')) return;
            if (!self._boundForms || !self._boundForms.has(form)) return;
            if (!form.querySelector('[data-kw-validate]')) return;
            if (!self.validateForm(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    },

    _installGlobalBlurFeedback() {
        const self = this;
        document.addEventListener('focusout', (event) => {
            const field = event.target;
            if (!field || typeof field.matches !== 'function') return;
            if (!field.matches('[data-kw-validate]')) return;
            if (!self._boundForms) return;
            const form = field.form;
            if (!form || !self._boundForms.has(form)) return;

            if (!String(field.value).trim()) {
                if (field.required) {
                    self.showElementError(field, `${self.fieldLabel(field)} is required`);
                } else {
                    self.clearElementError(field);
                }
                return;
            }
            const result = self.runFieldValidators(field);
            if (result.valid) {
                self.clearElementError(field);
            } else {
                self.showElementError(field, result.error);
            }
        }, true);
    },

    /**
     * Bind every form on the page carrying data-kw-validate.
     *
     * Safe to call once from a page controller; dynamically injected forms are
     * still covered because bindForm also delegates blur handling globally.
     */
    bindAllForms(scope = document) {
        const forms = scope.querySelectorAll('form');
        for (const form of forms) {
            if (form.querySelector('[data-kw-validate]')) {
                this.bindForm(form);
            }
        }
    },

    /**
     * Bind date-picker constraints so the calendar cannot even offer invalid
     * choices:
     *
     *   - data-kw-validate contains "dob" (date of birth)  -> max = yesterday
     *   - data-kw-validate contains "not_future"           -> max = today
     *   - explicit data-kw-min-date / data-kw-max-date     -> always honoured
     *
     * Only sets a max when a stricter one is not already on the input.
     *
     * @param {ParentNode} [scope=document]
     */
    bindDateConstraints(scope = document) {
        const iso = (offsetDays) => {
            const d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + offsetDays);
            return d.toISOString().slice(0, 10);
        };

        scope.querySelectorAll('input[type="date"]').forEach((field) => {
            const validators = this.validatorsFromAttr(field.dataset.kwValidate);

            const explicitMax = field.dataset.kwMaxDate;
            const explicitMin = field.dataset.kwMinDate;

            if (explicitMax) {
                field.max = explicitMax;
            } else if (validators.includes('dob')) {
                if (!field.max || field.max > iso(-1)) field.max = iso(-1);
            } else if (validators.includes('not_future')) {
                if (!field.max || field.max > iso(0)) field.max = iso(0);
            }

            if (explicitMin) {
                field.min = explicitMin;
            }
        });
    }
};

// Make available globally
window.FormValidation = FormValidation;

// Self-activate on load: any page that includes this file gets date-picker
// constraints and data-kw-validate/kw sweep automatically (idempotent).
(function () {
    const install = () => {
        if (!window.FormValidation) return;
        window.FormValidation.bindDateConstraints(document);
        window.FormValidation.bindAllForms(document);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', install);
    } else {
        install();
    }
})();
