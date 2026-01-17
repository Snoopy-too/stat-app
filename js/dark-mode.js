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
