/**
 * Breadcrumb Navigation Helper
 * Creates and manages breadcrumb navigation
 */

/**
 * Create a breadcrumb navigation element
 * @param {array} items - Array of breadcrumb items: [{label: 'Home', url: '/'}, ...]
 * @param {string} containerId - ID of container to insert breadcrumb into
 */
function createBreadcrumb(items, containerId = 'breadcrumb-container') {
    const container = document.getElementById(containerId);
    if (!container) return;

    const nav = document.createElement('nav');
    nav.setAttribute('aria-label', 'Breadcrumb');

    const list = document.createElement('ol');
    list.className = 'breadcrumb';

    items.forEach((item, index) => {
        const li = document.createElement('li');
        li.className = 'breadcrumb__item';

        if (index === items.length - 1) {
            // Current page (no link)
            const span = document.createElement('span');
            span.className = 'breadcrumb__current';
            span.textContent = item.label;
            span.setAttribute('aria-current', 'page');
            li.appendChild(span);
        } else {
            // Link to previous pages
            const link = document.createElement('a');
            link.className = 'breadcrumb__link';
            link.href = item.url;
            link.textContent = item.label;

            if (item.icon) {
                const icon = document.createElement('span');
                icon.className = 'breadcrumb__icon';
                icon.textContent = item.icon;
                link.insertBefore(icon, link.firstChild);
            }

            li.appendChild(link);
        }

        list.appendChild(li);
    });

    // Check if we need to hide items on mobile
    if (items.length > 3) {
        list.classList.add('has-hidden-items');
    }

    nav.appendChild(list);
    container.appendChild(nav);
}

/**
 * Update breadcrumb based on current URL
 * Automatically generates breadcrumb from URL path
 */
function updateBreadcrumbFromURL(baseItems = []) {
    const pathname = window.location.pathname;
    const pathParts = pathname.split('/').filter(part => part);

    // Start with home/base items
    let breadcrumbs = baseItems.length ? baseItems : [{label: 'Home', url: '/'}];

    // Add path parts as breadcrumbs
    let currentPath = '';
    pathParts.forEach((part, index) => {
        currentPath += '/' + part;
        const isLast = index === pathParts.length - 1;

        // Convert URL part to readable label
        const label = part
            .replace(/[_-]/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());

        if (isLast) {
            // Current page
            breadcrumbs.push({label: label, url: null});
        } else {
            // Link to ancestor page
            breadcrumbs.push({label: label, url: currentPath});
        }
    });

    return breadcrumbs;
}

/**
 * Breadcrumb configurations for common pages
 */
const breadcrumbConfigs = {
    adminDashboard: [
        {label: 'Home', url: '/', icon: '🏠'},
        {label: 'Admin', url: '/admin/dashboard.php', icon: '⚙️'},
        {label: 'Dashboard', url: null}
    ],
    adminClub: (clubId, clubName) => [
        {label: 'Home', url: '/', icon: '🏠'},
        {label: 'Admin', url: '/admin/dashboard.php', icon: '⚙️'},
        {label: 'Clubs', url: '/admin/manage_clubs.php'},
        {label: clubName || 'Club', url: null}
    ],
    adminMembers: (clubId, clubName) => [
        {label: 'Home', url: '/', icon: '🏠'},
        {label: 'Admin', url: '/admin/dashboard.php', icon: '⚙️'},
        {label: clubName || 'Club', url: `/admin/view_club.php?club_id=${clubId}`},
        {label: 'Members', url: null}
    ],
    gameResult: (gameId, gameName) => [
        {label: 'Home', url: '/', icon: '🏠'},
        {label: 'Results', url: '/club_game_results.php'},
        {label: gameName || 'Game', url: `/game_details.php?id=${gameId}`},
        {label: 'Add Result', url: null}
    ],
    clubStats: (clubId, clubName) => [
        {label: 'Home', url: '/', icon: '🏠'},
        {label: 'Clubs', url: '/index.php'},
        {label: clubName || 'Club', url: null}
    ]
};

// Auto-initialize breadcrumbs on page load
document.addEventListener('DOMContentLoaded', function() {
    // Look for element with data-breadcrumb attribute
    const breadcrumbElement = document.querySelector('[data-breadcrumb]');
    if (breadcrumbElement) {
        const config = breadcrumbElement.getAttribute('data-breadcrumb');
        const container = breadcrumbElement.getAttribute('data-breadcrumb-container') || 'breadcrumb-container';

        // Use predefined config or generate from URL
        if (breadcrumbConfigs[config]) {
            const items = typeof breadcrumbConfigs[config] === 'function'
                ? breadcrumbConfigs[config]()
                : breadcrumbConfigs[config];
            createBreadcrumb(items, container);
        } else {
            const items = updateBreadcrumbFromURL();
            createBreadcrumb(items, container);
        }
    }
});

/**
 * Helper to create breadcrumb HTML string (for server-side rendering)
 */
function breadcrumbHTML(items) {
    let html = '<nav aria-label="Breadcrumb"><ol class="breadcrumb">';

    items.forEach((item, index) => {
        const isLast = index === items.length - 1;
        html += '<li class="breadcrumb__item">';

        if (isLast) {
            html += `<span class="breadcrumb__current" aria-current="page">${item.label}</span>`;
        } else {
            html += `<a class="breadcrumb__link" href="${item.url}">${item.label}</a>`;
        }

        html += '</li>';
    });

    html += '</ol></nav>';
    return html;
}
