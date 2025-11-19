/**
 * Mobile Navigation Menu Handler
 * Toggles the mobile menu visibility on small screens
 */

document.addEventListener('DOMContentLoaded', function() {
    const headerActions = document.querySelector('.header-actions');

    if (!headerActions) {
        return; // No header-actions on this page
    }

    // Create hamburger menu button if it doesn't exist
    let menuToggle = document.querySelector('.mobile-menu-toggle');
    if (!menuToggle) {
        menuToggle = document.createElement('button');
        menuToggle.className = 'mobile-menu-toggle';
        menuToggle.setAttribute('aria-label', 'Toggle mobile menu');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.innerHTML = '<span>☰</span>';

        // Insert after header-title-group
        const header = document.querySelector('.header');
        if (header) {
            header.appendChild(menuToggle);
        }
    }

    // Toggle menu visibility
    menuToggle.addEventListener('click', function() {
        headerActions.classList.toggle('mobile-menu-open');
        // Update aria-expanded for accessibility
        const isOpen = headerActions.classList.contains('mobile-menu-open');
        menuToggle.setAttribute('aria-expanded', isOpen);
    });

    // Close menu when a link is clicked
    const menuLinks = headerActions.querySelectorAll('a, button');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Don't close if it's a form submission or if it opens in new tab
            if (!this.hasAttribute('target')) {
                headerActions.classList.remove('mobile-menu-open');
                menuToggle.setAttribute('aria-expanded', false);
            }
        });
    });

    // Close menu when clicking outside of header
    document.addEventListener('click', function(event) {
        const header = document.querySelector('.header');
        const isClickInsideHeader = header.contains(event.target);

        if (!isClickInsideHeader && headerActions.classList.contains('mobile-menu-open')) {
            headerActions.classList.remove('mobile-menu-open');
            menuToggle.setAttribute('aria-expanded', false);
        }
    });

    // Close menu on resize back to larger screens
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            headerActions.classList.remove('mobile-menu-open');
            menuToggle.setAttribute('aria-expanded', false);
        }
    });
});
