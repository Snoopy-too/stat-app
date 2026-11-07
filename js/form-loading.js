/**
 * Form Submission Loading States Handler
 * Adds visual feedback and prevents double-submission when forms are submitted
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get all forms on the page
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;

            // Find the submit button(s)
            const submitButtons = form.querySelectorAll('button[type="submit"]');

            if (submitButtons.length === 0) return;

            submitButtons.forEach(button => {
                // Store original button state
                const originalText = button.textContent;
                const originalHTML = button.innerHTML;

                // Disable button and show loading state
                button.disabled = true;
                button.classList.add('is-loading');

                // Add loading text or spinner
                const loadingText = button.getAttribute('data-loading-text') || 'Saving...';
                button.textContent = loadingText;

                // Optional: Add spinner if Font Awesome is available
                if (window.location.href.includes('admin')) {
                    button.style.pointerEvents = 'none';
                    button.style.opacity = '0.7';
                }
            });
        });
    });

    // Also handle AJAX forms if any
    const ajaxForms = document.querySelectorAll('form[data-ajax="true"]');

    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) return;

            // Store original state
            const originalText = submitButton.textContent;

            // Disable and show loading
            submitButton.disabled = true;
            submitButton.classList.add('is-loading');
            submitButton.textContent = submitButton.getAttribute('data-loading-text') || 'Loading...';

            // Submit form via AJAX
            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action');

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage(data.message || 'Success!', 'success');

                    // Optionally redirect or reset form
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 500);
                    } else {
                        form.reset();
                    }
                } else {
                    showMessage(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                // Re-enable button and restore original state
                submitButton.disabled = false;
                submitButton.classList.remove('is-loading');
                submitButton.textContent = originalText;
            });
        });
    });
});

/**
 * Helper function to show overlay banners / toast messages
 */
function showMessage(message, type = 'info', options = {}) {
    if (typeof window.showBanner === 'function') {
        return window.showBanner(message, type, options);
    }

    // Fallback if dark-mode.js is not loaded on this specific page
    let container = document.querySelector('.toast-container, .banner-overlay-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container banner-overlay-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
    }

    const normType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    const messageEl = document.createElement('div');
    messageEl.className = `banner-overlay banner-overlay--${normType} toast toast--${normType}`;
    messageEl.setAttribute('role', normType === 'error' ? 'alert' : 'status');

    const iconDiv = document.createElement('div');
    iconDiv.className = 'banner-overlay__icon toast__icon';
    if (normType === 'success') {
        iconDiv.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
    } else if (normType === 'error') {
        iconDiv.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
    } else {
        iconDiv.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
    }
    messageEl.appendChild(iconDiv);

    const contentDiv = document.createElement('div');
    contentDiv.className = 'banner-overlay__content toast__content';
    contentDiv.innerHTML = message;
    messageEl.appendChild(contentDiv);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'banner-overlay__close toast__close';
    closeBtn.setAttribute('aria-label', `Close ${normType} notification`);
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', function() {
        messageEl.classList.add('banner-overlay--dismissing', 'toast--dismissing');
        setTimeout(() => messageEl.remove(), 260);
    });
    messageEl.appendChild(closeBtn);

    container.appendChild(messageEl);

    if (normType === 'success' || (normType === 'info' && !options.persist)) {
        setTimeout(() => {
            messageEl.classList.add('banner-overlay--dismissing', 'toast--dismissing');
            setTimeout(() => messageEl.remove(), 260);
        }, options.duration || (normType === 'success' ? 4000 : 5000));
    }

    return messageEl;
}
