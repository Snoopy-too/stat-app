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
function showConfirmDialog(event, message, onConfirm, onCancel = null) {
    event.preventDefault();

    // Create modal dialog
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');

    const overlay = document.createElement('div');
    overlay.className = 'confirm-modal__overlay';

    const dialog = document.createElement('div');
    dialog.className = 'confirm-modal__dialog';

    const content = document.createElement('div');
    content.className = 'confirm-modal__content';
    content.innerHTML = `
        <h2 class="confirm-modal__title">Confirm Action</h2>
        <p class="confirm-modal__message">${message}</p>
        <div class="confirm-modal__actions">
            <button class="btn btn--danger confirm-modal__confirm">Delete</button>
            <button class="btn btn--subtle confirm-modal__cancel">Cancel</button>
        </div>
    `;

    dialog.appendChild(content);
    modal.appendChild(overlay);
    modal.appendChild(dialog);
    document.body.appendChild(modal);

    // Handle confirm button
    const confirmBtn = dialog.querySelector('.confirm-modal__confirm');
    confirmBtn.addEventListener('click', () => {
        modal.remove();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });

    // Handle cancel button
    const cancelBtn = dialog.querySelector('.confirm-modal__cancel');
    cancelBtn.addEventListener('click', () => {
        modal.remove();
        if (typeof onCancel === 'function') {
            onCancel();
        }
    });

    // Close on overlay click
    overlay.addEventListener('click', () => {
        modal.remove();
        if (typeof onCancel === 'function') {
            onCancel();
        }
    });

    // Close on escape key
    function handleEscape(e) {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', handleEscape);
            if (typeof onCancel === 'function') {
                onCancel();
            }
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
