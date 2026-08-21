/**
 * Sidebar Navigation Controller (Ponytail Edition)
 * Handles mobile sidebar toggle, overlay, body scroll lock, and keyboard escape.
 * Layout and landscape button scaling are handled natively in CSS.
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.querySelector('.sidebar-overlay');
        var toggleButtons = document.querySelectorAll('.sidebar-toggle');
        var closeButton = document.querySelector('.sidebar__close');

        if (!sidebar) return;

        // Ensure toggle button is attached directly to body on mobile so it is immune to ancestor containing block / filter traps
        if (toggleButtons && toggleButtons.length > 0) {
            toggleButtons.forEach(function(btn) {
                if (btn.parentElement && btn.parentElement !== document.body) {
                    document.body.appendChild(btn);
                }
            });
        }

        var lastScrollY = window.scrollY || window.pageYOffset || 0;

        function onScroll() {
            if (sidebar.classList.contains('sidebar--open')) {
                return;
            }

            var currentScrollY = Math.max(0, window.scrollY || window.pageYOffset || 0);
            var maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            if (currentScrollY > maxScroll) {
                currentScrollY = maxScroll;
            }

            // Always show toggle button near the top of the page
            if (currentScrollY <= 10) {
                toggleButtons.forEach(function(btn) {
                    btn.classList.remove('sidebar-toggle--hidden');
                });
                lastScrollY = currentScrollY;
                return;
            }

            var diff = currentScrollY - lastScrollY;
            if (Math.abs(diff) < 6) {
                return;
            }

            if (diff > 0 && currentScrollY > 60) {
                // Scrolling down past header -> hide toggle
                toggleButtons.forEach(function(btn) {
                    btn.classList.add('sidebar-toggle--hidden');
                });
            } else if (diff < 0) {
                // Scrolling up -> reveal toggle
                toggleButtons.forEach(function(btn) {
                    btn.classList.remove('sidebar-toggle--hidden');
                });
            }

            lastScrollY = currentScrollY;
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        function closeSidebar() {
            if (!sidebar.classList.contains('sidebar--open')) return;
            sidebar.classList.remove('sidebar--open');
            if (overlay) overlay.classList.remove('sidebar-overlay--visible');
            document.documentElement.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
            lastScrollY = window.scrollY || window.pageYOffset || 0;
        }

        function openSidebar() {
            if (sidebar.classList.contains('sidebar--open')) return;
            lastScrollY = window.scrollY || window.pageYOffset || 0;
            toggleButtons.forEach(function(btn) {
                btn.classList.remove('sidebar-toggle--hidden');
            });
            sidebar.classList.add('sidebar--open');
            if (overlay) overlay.classList.add('sidebar-overlay--visible');
            document.documentElement.classList.add('sidebar-open');
            document.body.classList.add('sidebar-open');
        }

        function toggleSidebar() {
            if (sidebar.classList.contains('sidebar--open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        toggleButtons.forEach(function(btn) {
            btn.addEventListener('click', toggleSidebar);
        });

        if (closeButton) closeButton.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Prevent touchmove propagation on overlay and header
        if (overlay) {
            overlay.addEventListener('touchmove', function(e) {
                e.preventDefault();
            }, { passive: false });
        }

        var header = sidebar.querySelector('.sidebar__header');
        if (header) {
            header.addEventListener('touchmove', function(e) {
                e.preventDefault();
            }, { passive: false });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        sidebar.querySelectorAll('.sidebar__link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });
    });
})();
