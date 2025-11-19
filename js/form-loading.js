/**
 * Form Submission Loading States Handler
 * Adds visual feedback and prevents double-submission when forms are submitted
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get all forms on the page
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
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
 * Helper function to show toast-style messages
 */
function showMessage(message, type = 'info') {
    // Check if message container exists, if not create one
    let messageContainer = document.querySelector('.toast-container');
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.className = 'toast-container';
        document.body.appendChild(messageContainer);
    }

    // Create message element
    const messageEl = document.createElement('div');
    messageEl.className = `toast toast--${type}`;
    messageEl.textContent = message;
    messageEl.setAttribute('role', 'status');
    messageEl.setAttribute('aria-live', 'polite');

    messageContainer.appendChild(messageEl);

    // Auto-remove after 4 seconds
    setTimeout(() => {
        messageEl.classList.add('toast--dismissing');
        setTimeout(() => {
            messageEl.remove();
        }, 300);
    }, 4000);
}
