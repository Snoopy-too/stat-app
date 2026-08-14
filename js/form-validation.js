/**
 * Enhanced Form Validation Handler
 * Provides better error messaging, persistence, and auto-scroll to errors
 */

class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = new Map();
        this.setupValidation();
    }

    setupValidation() {
        const form = this.form;

        // Validate on blur for better UX
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('blur', () => this.validateField(field));
            field.addEventListener('change', () => this.validateField(field));
        });

        // Validate entire form on submit
        form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.scrollToFirstError();
                this.displayErrorSummary();
            }
        });
    }

    validateField(field) {
        const fieldName = field.name || field.id;
        let error = null;

        // Check required fields
        if (field.hasAttribute('required') && !field.value.trim()) {
            error = `${this.getFieldLabel(field)} is required`;
        }

        // Check email fields
        if (!error && field.type === 'email' && field.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                error = `${this.getFieldLabel(field)} must be a valid email`;
            }
        }

        // Check min length
        if (!error && field.hasAttribute('minlength')) {
            const minLength = parseInt(field.getAttribute('minlength'));
            if (field.value.length < minLength && field.value.trim()) {
                error = `${this.getFieldLabel(field)} must be at least ${minLength} characters`;
            }
        }

        // Check max length
        if (!error && field.hasAttribute('maxlength')) {
            const maxLength = parseInt(field.getAttribute('maxlength'));
            if (field.value.length > maxLength) {
                error = `${this.getFieldLabel(field)} must not exceed ${maxLength} characters`;
            }
        }

        // Check pattern
        if (!error && field.hasAttribute('pattern') && field.value.trim()) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (!pattern.test(field.value)) {
                error = field.getAttribute('data-error-message') ||
                        `${this.getFieldLabel(field)} format is invalid`;
            }
        }

        // Update field state
        this.updateFieldState(field, error);

        if (error) {
            this.errors.set(fieldName, error);
        } else {
            this.errors.delete(fieldName);
        }

        return !error;
    }

    validateForm() {
        this.errors.clear();
        let isValid = true;

        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    updateFieldState(field, error) {
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;

        const fieldName = field.name || field.id;
        let errorMsg = formGroup.querySelector('.error-message');

        if (error) {
            // Add error class
            formGroup.classList.add('has-error');
            field.setAttribute('aria-invalid', 'true');

            // Create or update error message
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                formGroup.appendChild(errorMsg);
            }
            errorMsg.textContent = error;
            errorMsg.setAttribute('id', `error-${fieldName}`);
            field.setAttribute('aria-describedby', `error-${fieldName}`);
        } else {
            // Remove error class
            formGroup.classList.remove('has-error');
            field.removeAttribute('aria-invalid');
            if (errorMsg) {
                errorMsg.remove();
            }
            field.removeAttribute('aria-describedby');
        }
    }

    displayErrorSummary() {
        // Remove existing summary
        const existingSummary = this.form.querySelector('.form-error-summary');
        if (existingSummary) existingSummary.remove();

        if (this.errors.size === 0) return;

        // Create error summary
        const summary = document.createElement('div');
        summary.className = 'form-error-summary';
        summary.setAttribute('role', 'alert');

        const title = document.createElement('h3');
        title.textContent = `Please fix ${this.errors.size} error${this.errors.size > 1 ? 's' : ''}`;
        summary.appendChild(title);

        const list = document.createElement('ul');
        this.errors.forEach((error, fieldName) => {
            const li = document.createElement('li');

            // Create link to focus field
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = error;
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const field = this.form.querySelector(`[name="${fieldName}"], [id="${fieldName}"]`);
                if (field) {
                    field.focus();
                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            li.appendChild(link);
            list.appendChild(li);
        });

        summary.appendChild(list);

        // Insert at top of form
        this.form.insertBefore(summary, this.form.firstChild);
    }

    scrollToFirstError() {
        const firstErrorField = this.form.querySelector('.has-error input, .has-error select, .has-error textarea');
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErrorField.focus();
        }
    }

    getFieldLabel(field) {
        const label = this.form.querySelector(`label[for="${field.id}"]`);
        if (label) {
            return label.textContent.replace(':', '').trim();
        }
        return field.name || field.id || 'This field';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        new FormValidator(form);
    });
});

/**
 * Helper function to add field validation attributes
 * Usage: addFieldValidation(fieldElement, { required: true, email: true, minLength: 5 })
 */
function addFieldValidation(field, rules) {
    if (rules.required) field.setAttribute('required', 'required');
    if (rules.email) field.setAttribute('type', 'email');
    if (rules.minLength) field.setAttribute('minlength', rules.minLength);
    if (rules.maxLength) field.setAttribute('maxlength', rules.maxLength);
    if (rules.pattern) {
        field.setAttribute('pattern', rules.pattern);
        if (rules.errorMessage) {
            field.setAttribute('data-error-message', rules.errorMessage);
        }
    }
}
