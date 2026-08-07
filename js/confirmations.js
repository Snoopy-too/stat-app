/**
 * Confirmation Dialog Handler
 * Shows confirmation dialogs before destructive actions (delete, deactivate)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle all delete links and buttons automatically with theme-consistent modal
    const deleteElements = document.querySelectorAll('[data-confirm], .delete-btn, a[href*="delete"], a[href*="remove"]');

    deleteElements.forEach(element => {
        if (element.getAttribute('data-confirm') === 'false') return;
        if (element.getAttribute('data-has-custom-modal') === 'true') return;

        element.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            const form = this.closest('form');
            const message = this.getAttribute('data-confirm-message') ||
                          (this.getAttribute('data-confirm') && this.getAttribute('data-confirm') !== 'true' ? this.getAttribute('data-confirm') : null) ||
                          'Are you sure you want to delete this item?';

            const itemName = this.getAttribute('data-item-name');
            const fullMessage = itemName ? `${message} (<strong>${itemName}</strong>)` : message;

            showConfirmDialog(e, {
                title: '⚠️ Confirm Action',
                message: fullMessage,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger',
                onConfirm: () => {
                    if (href && href !== '#' && !href.startsWith('javascript:')) {
                        window.location.href = href;
                    } else if (form) {
                        form.submit();
                    }
                }
            });
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
        requirePassword: false,
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
    
    const rawTitle = settings.title || 'Confirm Action';
    const cleanTitle = rawTitle.replace(/^⚠️\s*/, '');
    const displayTitle = (settings.type === 'danger' || settings.type === 'warning') ? `⚠️ ${cleanTitle}` : cleanTitle;

    dialog.innerHTML = `
        <div class="confirm-modal__content">
            <div style="margin-bottom: var(--spacing-3);">
                <h2 class="confirm-modal__title" style="margin: 0; color: var(--color-heading); font-size: 1.25rem; font-weight: 600;">${displayTitle}</h2>
            </div>
            <p class="confirm-modal__message" style="margin-top: 0.5rem; margin-bottom: 0;">${settings.message}</p>
            ${settings.type === 'danger' ? `
                <div class="message message--error" style="margin-top: 1.25rem; margin-bottom: 0;">
                    <strong>Warning:</strong> ${settings.warningMessage || 'This action is permanent and cannot be undone.'}
                </div>
            ` : ''}
            ${settings.requirePassword ? `
                <div style="margin-top: 1.25rem;">
                    <label for="confirm-modal-password" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.35rem; color: var(--color-heading);">
                        Admin Password Required: <span style="color: var(--color-danger, #ef4444); font-weight: bold;">*</span>
                    </label>
                    <input type="password" id="confirm-modal-password" class="form-control" placeholder="Enter your password" autocomplete="current-password" style="width: 100%; box-sizing: border-box;">
                    <div id="confirm-modal-password-error" style="color: var(--color-danger, #ef4444); font-size: 0.8rem; margin-top: 0.25rem; display: none;">Password is required.</div>
                </div>
            ` : ''}
            <div class="confirm-modal__actions" style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-3); justify-content: flex-start;">
                <button class="btn ${btnClass} confirm-modal__confirm">${settings.confirmText}</button>
                <button class="btn btn--subtle confirm-modal__cancel">${settings.cancelText}</button>
            </div>
        </div>
    `;

    modal.appendChild(overlay);
    modal.appendChild(dialog);
    document.body.appendChild(modal);

    // Handle confirm button
    const confirmBtn = dialog.querySelector('.confirm-modal__confirm');
    confirmBtn.addEventListener('click', () => {
        let passwordVal = '';
        if (settings.requirePassword) {
            const passInput = dialog.querySelector('#confirm-modal-password');
            const passErr = dialog.querySelector('#confirm-modal-password-error');
            passwordVal = passInput ? passInput.value.trim() : '';
            if (!passwordVal) {
                if (passErr) passErr.style.display = 'block';
                if (passInput) passInput.focus();
                return;
            }
        }
        modal.classList.add('is-closing');
        setTimeout(() => {
            modal.remove();
            if (typeof settings.onConfirm === 'function') {
                settings.onConfirm(passwordVal);
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

    // Focus input or confirm button
    if (settings.requirePassword) {
        const passInput = dialog.querySelector('#confirm-modal-password');
        if (passInput) {
            passInput.focus();
            passInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });
        }
    } else {
        confirmBtn.focus();
    }

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
