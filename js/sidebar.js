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

        var scrollY = 0;

        function closeSidebar() {
            if (!sidebar.classList.contains('sidebar--open')) return;
            sidebar.classList.remove('sidebar--open');
            if (overlay) overlay.classList.remove('sidebar-overlay--visible');
            document.body.classList.remove('sidebar-open');
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            window.scrollTo(0, scrollY);
        }

        function openSidebar() {
            if (sidebar.classList.contains('sidebar--open')) return;
            scrollY = window.scrollY || window.pageYOffset || 0;
            sidebar.classList.add('sidebar--open');
            if (overlay) overlay.classList.add('sidebar-overlay--visible');
            document.body.classList.add('sidebar-open');
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + scrollY + 'px';
            document.body.style.width = '100%';
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
