/**
 * Sidebar Navigation Controller
 * Handles toggle, overlay, keyboard, and resize events
 */
(function() {
    'use strict';

    const MOBILE_BREAKPOINT = 768;

    // DOM Elements
    let sidebar = null;
    let overlay = null;
    let toggleButtons = null;
    let closeButton = null;

    /**
     * Initialize sidebar functionality
     */
    function init() {
        sidebar = document.querySelector('.sidebar');
        overlay = document.querySelector('.sidebar-overlay');
        toggleButtons = document.querySelectorAll('.sidebar-toggle');
        closeButton = document.querySelector('.sidebar__close');

        if (!sidebar) return;

        // Create overlay if it doesn't exist
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(overlay);
        }

        // Bind events
        bindEvents();

        // Check initial state based on screen size
        handleResize();
    }

    /**
     * Bind all event listeners
     */
    function bindEvents() {
        // Toggle button clicks
        toggleButtons.forEach(function(btn) {
            btn.addEventListener('click', toggleSidebar);
        });

        // Close button click
        if (closeButton) {
            closeButton.addEventListener('click', closeSidebar);
        }

        // Overlay click
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Keyboard events
        document.addEventListener('keydown', handleKeydown);

        // Window resize
        window.addEventListener('resize', debounce(handleResize, 150));

        // Close sidebar when clicking a link (mobile)
        sidebar.querySelectorAll('.sidebar__link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        });
    }

    /**
     * Toggle sidebar open/closed
     */
    function toggleSidebar() {
        if (sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    /**
     * Open sidebar
     */
    function openSidebar() {
        sidebar.classList.add('sidebar--open');
        overlay.classList.add('sidebar-overlay--visible');
        document.body.classList.add('sidebar-open');

        // Update toggle button aria
        toggleButtons.forEach(function(btn) {
            btn.setAttribute('aria-expanded', 'true');
        });

        // Focus trap - focus first focusable element in sidebar
        var firstFocusable = sidebar.querySelector('a, button');
        if (firstFocusable && isMobile()) {
            setTimeout(function() {
                firstFocusable.focus();
            }, 100);
        }
    }

    /**
     * Close sidebar
     */
    function closeSidebar() {
        sidebar.classList.remove('sidebar--open');
        overlay.classList.remove('sidebar-overlay--visible');
        document.body.classList.remove('sidebar-open');

        // Update toggle button aria
        toggleButtons.forEach(function(btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    /**
     * Handle keyboard events
     */
    function handleKeydown(e) {
        // Close on Escape
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
            // Return focus to toggle button
            if (toggleButtons.length > 0) {
                toggleButtons[0].focus();
            }
        }
    }

    /**
     * Handle window resize
     */
    function handleResize() {
        // Auto-close sidebar when resizing to desktop
        if (!isMobile() && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
        }
    }

    /**
     * Check if viewport is mobile size
     */
    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    /**
     * Debounce utility function
     */
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
