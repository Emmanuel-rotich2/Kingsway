/**
 * Frontend Validation Library
 * 
 * Provides client-side validation matching backend ValidationHelper
 * Gives immediate feedback to users before API calls
 */

const FormValidation = {
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
     * Rules: Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
     */
    validatePassword(password) {
        if (!password || password === '') {
            return { valid: false, error: 'Password is required' };
        }

        if (password.length < 8) {
            return { valid: false, error: 'Password must be at least 8 characters long' };
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
    }
};

// Make available globally
window.FormValidation = FormValidation;
