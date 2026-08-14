/**
 * Multi-Step Form Handler
 * Manages navigation through multi-step forms with validation
 */

class MultiStepForm {
    constructor(formElement) {
        this.form = formElement;
        this.currentStep = 1;
        this.totalSteps = this.form.querySelectorAll('[data-step]').length;
        this.steps = Array.from(this.form.querySelectorAll('[data-step]'));

        this.init();
    }

    init() {
        // Show first step
        this.goToStep(1);

        // Setup navigation buttons
        const prevBtn = this.form.querySelector('[data-action="prev"]');
        const nextBtn = this.form.querySelector('[data-action="next"]');
        const submitBtn = this.form.querySelector('[data-action="submit"]');

        if (prevBtn) prevBtn.addEventListener('click', () => this.previousStep());
        if (nextBtn) nextBtn.addEventListener('click', () => this.nextStep());
        if (submitBtn) submitBtn.addEventListener('click', (e) => this.submitForm(e));

        // Setup step indicator clicks
        const stepIndicators = this.form.querySelectorAll('.form-step');
        stepIndicators.forEach((indicator, index) => {
            // Only allow clicking on completed or current steps
            indicator.addEventListener('click', () => {
                if (index < this.currentStep) {
                    this.goToStep(index + 1);
                }
            });
        });
    }

    goToStep(stepNumber) {
        // Validate current step before moving
        if (stepNumber > this.currentStep) {
            if (!this.validateStep(this.currentStep)) {
                return false;
            }
        }

        // Hide all steps
        this.steps.forEach(step => {
            const stepNum = parseInt(step.getAttribute('data-step'));
            const content = this.form.querySelector(`[data-step-content="${stepNum}"]`);

            if (content) {
                content.classList.remove('is-active');
            }

            const indicator = this.form.querySelector(`.form-step:nth-child(${stepNum})`);
            if (indicator) {
                indicator.classList.remove('is-active', 'is-complete');
            }
        });

        // Show current step
        const currentContent = this.form.querySelector(`[data-step-content="${stepNumber}"]`);
        if (currentContent) {
            currentContent.classList.add('is-active');
        }

        // Update indicators
        for (let i = 1; i <= this.totalSteps; i++) {
            const indicator = this.form.querySelector(`.form-step:nth-child(${i})`);
            if (!indicator) continue;

            if (i < stepNumber) {
                indicator.classList.add('is-complete');
                indicator.classList.remove('is-active', 'is-disabled');
            } else if (i === stepNumber) {
                indicator.classList.add('is-active');
                indicator.classList.remove('is-complete', 'is-disabled');
            } else {
                indicator.classList.add('is-disabled');
                indicator.classList.remove('is-active', 'is-complete');
            }
        }

        // Update button visibility
        this.updateNavigation();

        this.currentStep = stepNumber;

        // Scroll to top of form
        this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });

        return true;
    }

    nextStep() {
        if (this.currentStep < this.totalSteps) {
            this.goToStep(this.currentStep + 1);
        }
    }

    previousStep() {
        if (this.currentStep > 1) {
            this.goToStep(this.currentStep - 1);
        }
    }

    validateStep(stepNumber) {
        const content = this.form.querySelector(`[data-step-content="${stepNumber}"]`);
        if (!content) return true;

        // Get all required fields in this step
        const fields = content.querySelectorAll('[required], [data-validate]');
        let isValid = true;

        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            // Show validation error message
            const errorMsg = content.querySelector('.form-error-summary');
            if (!errorMsg) {
                const msg = document.createElement('div');
                msg.className = 'message message--error';
                msg.textContent = `Please complete all required fields in Step ${stepNumber}`;
                content.insertBefore(msg, content.firstChild);

                // Auto-remove after 5 seconds
                setTimeout(() => msg.remove(), 5000);
            }
        }

        return isValid;
    }

    validateField(field) {
        let isValid = true;

        // Check required
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }

        // Check email
        if (field.type === 'email' && field.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
            }
        }

        return isValid;
    }

    updateNavigation() {
        const prevBtn = this.form.querySelector('[data-action="prev"]');
        const nextBtn = this.form.querySelector('[data-action="next"]');
        const submitBtn = this.form.querySelector('[data-action="submit"]');

        // Hide/show prev button
        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'flex' : 'none';
        }

        // Hide/show next button
        if (nextBtn) {
            nextBtn.style.display = this.currentStep < this.totalSteps ? 'flex' : 'none';
        }

        // Hide/show submit button
        if (submitBtn) {
            submitBtn.style.display = this.currentStep === this.totalSteps ? 'flex' : 'none';
        }
    }

    submitForm(e) {
        e.preventDefault();

        // Validate final step
        if (!this.validateStep(this.currentStep)) {
            return false;
        }

        // Submit the form
        this.form.submit();
    }

    getCurrentStep() {
        return this.currentStep;
    }

    getTotalSteps() {
        return this.totalSteps;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const multiStepForms = document.querySelectorAll('[data-multi-step]');
    multiStepForms.forEach(form => {
        new MultiStepForm(form);
    });
});
