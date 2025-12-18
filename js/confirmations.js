/**
 * Confirmation Dialog Handler
 * Shows confirmation dialogs before destructive actions (delete, deactivate)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle all delete links and buttons
    const deleteElements = document.querySelectorAll('[data-confirm], .delete-btn, a[href*="delete"], a[href*="remove"]');

    deleteElements.forEach(element => {
        // Skip if already has confirmation
        if (element.getAttribute('data-confirm') === 'false') return;

        element.addEventListener('click', function(e) {
            // Get confirmation message from data attribute or use default
            const message = this.getAttribute('data-confirm-message') ||
                          this.getAttribute('data-confirm') ||
                          'Are you sure you want to delete this?';

            const itemName = this.getAttribute('data-item-name');
            const fullMessage = itemName ? `${message}\n\nItem: ${itemName}` : message;

            if (!confirm(fullMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
});

/**
 * Modal-based confirmation dialog (more polished alternative)
 * Usage: <a href="#" onclick="showConfirmDialog(event, 'Delete member?', onConfirm)">Delete</a>
 */
/**
 * Modal-based confirmation dialog (more polished alternative)
 * Usage: showConfirmDialog(event, {
 *     title: 'Delete Result?',
 *     message: 'Are you sure you want to delete this game result? This action cannot be undone.',
 *     confirmText: 'Delete Result',
 *     onConfirm: () => { ... }
 * })
 */
function showConfirmDialog(event, options) {
    if (event) event.preventDefault();

    const settings = Object.assign({
        title: 'Confirm Action',
        message: 'Are you sure you want to proceed?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        type: 'danger', // danger, primary, warning
        onConfirm: null,
        onCancel: null
    }, options);

    // Create modal dialog
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');

    const overlay = document.createElement('div');
    overlay.className = 'confirm-modal__overlay';

    const dialog = document.createElement('div');
    dialog.className = 'confirm-modal__dialog';

    const btnClass = settings.type === 'danger' ? 'btn--danger' : (settings.type === 'primary' ? 'btn--primary' : 'btn--warning');
    
    // Icon for the dialog
    const icon = settings.type === 'danger' ? 
        `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-error-text);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>` :
        `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-primary);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

    dialog.innerHTML = `
        <div class="confirm-modal__content">
            <div style="display: flex; align-items: flex-start; gap: var(--spacing-4);">
                <div style="background: ${settings.type === 'danger' ? 'var(--color-error-bg)' : 'var(--color-primary-soft)'}; padding: var(--spacing-2); border-radius: var(--radius-md); flex-shrink: 0;">
                    ${icon}
                </div>
                <div>
                    <h2 class="confirm-modal__title" style="margin-top: 2px;">${settings.title}</h2>
                    <p class="confirm-modal__message">${settings.message}</p>
                </div>
            </div>
            <div class="confirm-modal__actions" style="margin-top: var(--spacing-6);">
                <button class="btn btn--subtle confirm-modal__cancel">${settings.cancelText}</button>
                <button class="btn ${btnClass} confirm-modal__confirm">${settings.confirmText}</button>
            </div>
        </div>
    `;

    modal.appendChild(overlay);
    modal.appendChild(dialog);
    document.body.appendChild(modal);

    // Handle confirm button
    const confirmBtn = dialog.querySelector('.confirm-modal__confirm');
    confirmBtn.addEventListener('click', () => {
        modal.classList.add('is-closing');
        setTimeout(() => {
            modal.remove();
            if (typeof settings.onConfirm === 'function') {
                settings.onConfirm();
            }
        }, 200);
    });

    // Handle cancel button
    const cancelBtn = dialog.querySelector('.confirm-modal__cancel');
    cancelBtn.addEventListener('click', () => {
        modal.classList.add('is-closing');
        setTimeout(() => {
            modal.remove();
            if (typeof settings.onCancel === 'function') {
                settings.onCancel();
            }
        }, 200);
    });

    // Close on overlay click
    overlay.addEventListener('click', () => {
        cancelBtn.click();
    });

    // Close on escape key
    function handleEscape(e) {
        if (e.key === 'Escape') {
            cancelBtn.click();
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);

    // Focus confirm button
    confirmBtn.focus();
}

/**
 * Helper to create a delete link with confirmation
 * Usage: createDeleteLink(url, 'Delete This Member', 'member_name')
 */
function createDeleteLink(href, confirmMessage, itemName = null) {
    const link = document.createElement('a');
    link.href = href;
    link.className = 'btn btn--danger btn--small';
    link.textContent = 'Delete';
    link.setAttribute('data-confirm-message', confirmMessage);
    if (itemName) {
        link.setAttribute('data-item-name', itemName);
    }
    return link;
}
