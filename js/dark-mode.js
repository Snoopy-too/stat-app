/**
 * Dark Mode Toggle Handler
 * Manages theme switching with localStorage persistence and smooth transitions
 */

class DarkModeHandler {
    constructor(options = {}) {
        this.storageKey = options.storageKey || 'app-theme-preference';
        this.transitionClass = options.transitionClass || 'theme-transitioning';
        this.dataAttribute = 'data-theme';

        // Theme values
        this.THEME_DARK = 'dark';
        this.THEME_LIGHT = 'light';
        this.THEME_SYSTEM = 'system';

        // DOM elements
        this.html = document.documentElement;
        this.toggleButtons = [];
        this.initialized = false;

        this.init();
    }

    /**
     * Initialize dark mode handler
     */
    init() {
        if (this.initialized) return;
        this.initialized = true;

        // Apply saved or system preference on page load
        this.applyInitialTheme();

        // Listen for system theme changes
        this.watchSystemPreference();

        // Look for and setup toggle buttons (there may be multiple on a page)
        this.setupToggleButtons();

        // Listen for storage changes from other tabs
        this.syncAcrossTabs();
    }

    /**
     * Apply theme on page load based on preference and system settings
     */
    applyInitialTheme() {
        if (this.html.hasAttribute('data-theme-locked')) return;
        const savedTheme = this.getSavedTheme();

        if (savedTheme && savedTheme !== this.THEME_SYSTEM) {
            // Use saved preference
            this.setTheme(savedTheme, false);
        } else {
            // Use system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.setTheme(prefersDark ? this.THEME_DARK : this.THEME_LIGHT, false);
        }
    }

    /**
     * Get saved theme preference from localStorage
     * @returns {string|null} Saved theme or null if not set
     */
    getSavedTheme() {
        try {
            return localStorage.getItem(this.storageKey);
        } catch (e) {
            // localStorage may be disabled in private browsing
            return null;
        }
    }

    /**
     * Save theme preference to localStorage
     * @param {string} theme - Theme to save ('dark', 'light', or 'system')
     */
    saveTheme(theme) {
        try {
            localStorage.setItem(this.storageKey, theme);
            // Notify other tabs of theme change
            window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
        } catch (e) {
            // localStorage may be disabled in private browsing
            console.warn('Unable to save theme preference:', e);
        }
    }

    /**
     * Set the theme and update DOM
     * @param {string} theme - Theme to apply ('dark' or 'light')
     * @param {boolean} animate - Whether to show transition animation
     */
    setTheme(theme, animate = true) {
        if (![this.THEME_DARK, this.THEME_LIGHT].includes(theme)) {
            return;
        }

        // Add transition class if animating
        if (animate) {
            this.html.classList.add(this.transitionClass);
        }

        // Set data attribute on html element
        this.html.setAttribute(this.dataAttribute, theme);

        // Update all toggle buttons
        this.updateAllToggleButtons(theme);

        // Remove transition class after animation completes
        if (animate) {
            this.html.addEventListener('transitionend', () => {
                this.html.classList.remove(this.transitionClass);
            }, { once: true });

            // Fallback timeout in case transitionend doesn't fire
            setTimeout(() => {
                this.html.classList.remove(this.transitionClass);
            }, 350);
        }
    }

    /**
     * Update all toggle buttons to reflect current theme
     * @param {string} theme - Current theme
     */
    updateAllToggleButtons(theme) {
        // Update all registered toggle buttons
        this.toggleButtons.forEach(btn => {
            btn.setAttribute('aria-pressed', theme === this.THEME_DARK);
            const icon = btn.querySelector('[data-theme-icon]');
            if (icon) {
                this.updateToggleIcon(icon, theme);
            }
        });

        // Also update any buttons we haven't registered yet (dynamically added)
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            btn.setAttribute('aria-pressed', theme === this.THEME_DARK);
            const icon = btn.querySelector('[data-theme-icon]');
            if (icon) {
                this.updateToggleIcon(icon, theme);
            }
        });
    }

    /**
     * Toggle between dark and light themes
     */
    toggle() {
        const currentTheme = this.html.getAttribute(this.dataAttribute);
        const newTheme = currentTheme === this.THEME_DARK ? this.THEME_LIGHT : this.THEME_DARK;

        this.setTheme(newTheme, true);
        this.saveTheme(newTheme);
    }

    /**
     * Watch for system preference changes
     */
    watchSystemPreference() {
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

        // Modern browsers support addEventListener
        if (darkModeQuery.addEventListener) {
            darkModeQuery.addEventListener('change', (e) => {
                const savedTheme = this.getSavedTheme();

                // Only apply system preference if user hasn't set explicit preference
                if (!savedTheme || savedTheme === this.THEME_SYSTEM) {
                    this.setTheme(e.matches ? this.THEME_DARK : this.THEME_LIGHT, true);
                }
            });
        }
    }

    /**
     * Setup all toggle buttons on the page
     */
    setupToggleButtons() {
        // Find all existing toggle buttons
        const buttons = document.querySelectorAll('[data-theme-toggle]');
        const currentTheme = this.html.getAttribute(this.dataAttribute);

        buttons.forEach(button => {
            this.initializeToggleButton(button, currentTheme);
        });
    }

    /**
     * Initialize a single toggle button
     * @param {HTMLElement} button - The button element
     * @param {string} currentTheme - Current theme
     */
    initializeToggleButton(button, currentTheme) {
        // Skip if already initialized
        if (button.dataset.themeInitialized) return;
        button.dataset.themeInitialized = 'true';

        // Setup button attributes
        button.setAttribute('aria-label', 'Toggle dark mode');
        button.setAttribute('aria-pressed', currentTheme === this.THEME_DARK);

        // Find or create icon element
        let icon = button.querySelector('[data-theme-icon]');
        if (!icon) {
            icon = document.createElement('span');
            icon.setAttribute('data-theme-icon', '');
            icon.className = 'theme-toggle__icon';
            button.appendChild(icon);
        }

        // Update icon
        this.updateToggleIcon(icon, currentTheme);

        // Attach click handler
        button.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });

        // Track this button
        this.toggleButtons.push(button);
    }

    /**
     * Update toggle button icon based on current theme
     * @param {HTMLElement} icon - The icon element
     * @param {string} theme - Current theme
     */
    updateToggleIcon(icon, theme) {
        if (!icon) return;

        // Show appropriate icon based on current theme
        // (icon represents the theme you can switch TO, not current)
        if (theme === this.THEME_DARK) {
            icon.textContent = '☀️'; // Show sun icon (can switch to light)
            icon.setAttribute('aria-label', 'Switch to light mode');
        } else {
            icon.textContent = '🌙'; // Show moon icon (can switch to dark)
            icon.setAttribute('aria-label', 'Switch to dark mode');
        }
    }

    /**
     * Sync theme across browser tabs using storage events
     */
    syncAcrossTabs() {
        window.addEventListener('storage', (e) => {
            if (e.key === this.storageKey && e.newValue) {
                this.setTheme(e.newValue, false);
            }
        });
    }
}

// Initialize dark mode handler - single initialization point
(function() {
    function initDarkMode() {
        if (window.darkModeHandler) return; // Already initialized

        window.darkModeHandler = new DarkModeHandler({
            storageKey: 'stat-app-theme',
            transitionClass: 'theme-transitioning'
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }
})();

// ==========================================================================
// Global Overlay Banner & Toast Notification Manager
// ==========================================================================
(function() {
    function getOrCreateContainer() {
        let container = document.querySelector('.toast-container, .banner-overlay-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container banner-overlay-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }
        return container;
    }

    function getIconSvg(type) {
        if (type === 'success') {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
        } else if (type === 'error') {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
        } else if (type === 'warning') {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        } else {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
        }
    }

    function dismissBanner(bannerEl) {
        if (!bannerEl || bannerEl.dataset.dismissing === 'true') return;
        bannerEl.dataset.dismissing = 'true';
        bannerEl.classList.add('banner-overlay--dismissing', 'toast--dismissing');
        
        setTimeout(() => {
            if (bannerEl.parentNode) {
                bannerEl.parentNode.removeChild(bannerEl);
            }
        }, 260);
    }

    function showBanner(content, type = 'info', options = {}) {
        const container = getOrCreateContainer();
        const banner = document.createElement('div');
        
        // Normalize type
        let normType = 'info';
        if (typeof type === 'string') {
            const lower = type.toLowerCase();
            if (lower.includes('error') || lower.includes('danger')) normType = 'error';
            else if (lower.includes('success')) normType = 'success';
            else if (lower.includes('warn')) normType = 'warning';
            else if (lower.includes('info')) normType = 'info';
        }

        banner.className = `banner-overlay banner-overlay--${normType} toast toast--${normType}`;
        banner.setAttribute('role', normType === 'error' ? 'alert' : 'status');

        // Icon
        const iconDiv = document.createElement('div');
        iconDiv.className = 'banner-overlay__icon toast__icon';
        iconDiv.innerHTML = getIconSvg(normType);
        banner.appendChild(iconDiv);

        // Content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'banner-overlay__content toast__content';
        if (typeof content === 'string') {
            contentDiv.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            contentDiv.appendChild(content);
        }
        banner.appendChild(contentDiv);

        // Close button (always provided for all banners)
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'banner-overlay__close toast__close';
        closeBtn.setAttribute('aria-label', `Close ${normType} notification`);
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dismissBanner(banner);
        });
        banner.appendChild(closeBtn);

        container.appendChild(banner);

        // Success auto-dismisses after 4 seconds (pauses on hover)
        // Error PERSISTS indefinitely until user clicks the close button
        // Warning persists by default; info auto-dismisses after 5s
        const isError = (normType === 'error');
        const isWarning = (normType === 'warning');
        const shouldAutoDismiss = !isError && (!isWarning || options.autoDismiss === true) && (options.persist !== true);
        const timeoutDuration = options.duration || (normType === 'success' ? 4000 : 5000);

        if (shouldAutoDismiss) {
            let dismissTimer = null;
            let remainingTime = timeoutDuration;
            let startTime = Date.now();

            const startTimer = (duration) => {
                startTime = Date.now();
                dismissTimer = setTimeout(() => {
                    dismissBanner(banner);
                }, duration);
            };

            const pauseTimer = () => {
                if (dismissTimer) {
                    clearTimeout(dismissTimer);
                    remainingTime -= (Date.now() - startTime);
                    if (remainingTime < 1000) remainingTime = 1000;
                }
            };

            banner.addEventListener('mouseenter', pauseTimer);
            banner.addEventListener('mouseleave', () => startTimer(remainingTime));

            startTimer(timeoutDuration);
        }

        return banner;
    }

    // Expose globally
    window.showBanner = showBanner;
    window.showMessage = showBanner;
    window.dismissBanner = dismissBanner;

    function processExistingBanners() {
        const selectors = '.message, .success, .error, .warning, .info, [class*="message--"], [class*="alert"], .sa-flash';
        const banners = document.querySelectorAll(selectors);
        
        banners.forEach(banner => {
            // Skip inline messages in modals, form groups, confirmation dialogs, etc.
            if (
                banner.closest('.modal') || 
                banner.closest('.form-group') || 
                banner.closest('.confirm-modal') ||
                banner.dataset.inline === 'true' ||
                banner.dataset.noOverlay === 'true' ||
                banner.dataset.processed === 'true' ||
                banner.classList.contains('banner-overlay') ||
                banner.closest('.toast-container') ||
                banner.closest('.banner-overlay-container')
            ) {
                return;
            }

            banner.dataset.processed = 'true';

            // Determine type
            let type = 'info';
            const classList = banner.className;
            if (classList.includes('error') || classList.includes('danger')) {
                type = 'error';
            } else if (classList.includes('success')) {
                type = 'success';
            } else if (classList.includes('warning')) {
                type = 'warning';
            }

            // Extract content and remove the inline placeholder immediately to prevent layout shift
            const content = banner.innerHTML.trim();
            const parent = banner.parentNode;
            if (parent) {
                parent.removeChild(banner);
            }

            if (content) {
                showBanner(content, type);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', processExistingBanners);
    } else {
        processExistingBanners();
    }
})();
