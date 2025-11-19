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
        this.toggleButton = null;
        this.themeIcon = null;

        this.init();
    }

    /**
     * Initialize dark mode handler
     */
    init() {
        // Apply saved or system preference on page load
        this.applyInitialTheme();

        // Listen for system theme changes
        this.watchSystemPreference();

        // Look for and setup toggle button
        this.setupToggleButton();
    }

    /**
     * Apply theme on page load based on preference and system settings
     */
    applyInitialTheme() {
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

        // Update toggle button state if present
        if (this.toggleButton) {
            this.toggleButton.setAttribute('aria-pressed', theme === this.THEME_DARK);
            this.updateToggleIcon(theme);
        }

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
     * Setup toggle button if it exists or create one
     */
    setupToggleButton() {
        // Look for existing toggle button
        this.toggleButton = document.querySelector('[data-theme-toggle]');

        if (!this.toggleButton) {
            // Look for a preferred location to insert button
            const headerActions = document.querySelector('.header-actions');
            if (headerActions) {
                this.toggleButton = this.createToggleButton();
                headerActions.insertBefore(this.toggleButton, headerActions.firstChild);
            }
        }

        if (this.toggleButton) {
            // Setup button attributes
            this.toggleButton.setAttribute('data-theme-toggle', '');
            this.toggleButton.setAttribute('aria-label', 'Toggle dark mode');
            this.toggleButton.setAttribute('aria-pressed',
                this.html.getAttribute(this.dataAttribute) === this.THEME_DARK
            );

            // Find icon element or create it
            this.themeIcon = this.toggleButton.querySelector('[data-theme-icon]');
            if (!this.themeIcon) {
                this.themeIcon = document.createElement('span');
                this.themeIcon.setAttribute('data-theme-icon', '');
                this.toggleButton.appendChild(this.themeIcon);
            }

            // Update initial icon
            const currentTheme = this.html.getAttribute(this.dataAttribute);
            this.updateToggleIcon(currentTheme);

            // Attach click handler
            this.toggleButton.addEventListener('click', () => this.toggle());
        }
    }

    /**
     * Create toggle button element
     * @returns {HTMLElement} Button element
     */
    createToggleButton() {
        const button = document.createElement('button');
        button.className = 'btn btn--icon theme-toggle';
        button.type = 'button';
        button.title = 'Toggle dark mode';

        const icon = document.createElement('span');
        icon.setAttribute('data-theme-icon', '');
        icon.className = 'theme-toggle__icon';

        button.appendChild(icon);
        return button;
    }

    /**
     * Update toggle button icon based on current theme
     * @param {string} theme - Current theme
     */
    updateToggleIcon(theme) {
        if (!this.themeIcon) return;

        // Show appropriate icon based on current theme
        // (icon represents the theme you can switch TO, not current)
        if (theme === this.THEME_DARK) {
            this.themeIcon.textContent = '☀️'; // Show sun icon (can switch to light)
            this.themeIcon.setAttribute('aria-label', 'Switch to light mode');
        } else {
            this.themeIcon.textContent = '🌙'; // Show moon icon (can switch to dark)
            this.themeIcon.setAttribute('aria-label', 'Switch to dark mode');
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

        // Also listen for custom theme change events from other tabs
        window.addEventListener('themechange', (e) => {
            this.setTheme(e.detail.theme, false);
        });
    }
}

// Initialize dark mode handler when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.darkModeHandler = new DarkModeHandler({
        storageKey: 'stat-app-theme',
        transitionClass: 'theme-transitioning'
    });

    // Enable cross-tab syncing
    window.darkModeHandler.syncAcrossTabs();
});

// Also support early initialization (before DOMContentLoaded)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.darkModeHandler) {
            window.darkModeHandler = new DarkModeHandler();
            window.darkModeHandler.syncAcrossTabs();
        }
    });
} else {
    // DOM is already loaded
    if (!window.darkModeHandler) {
        window.darkModeHandler = new DarkModeHandler();
        window.darkModeHandler.syncAcrossTabs();
    }
}
