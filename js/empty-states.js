/**
 * Empty State Helper Functions
 * Creates consistent empty state cards when no data is available
 */

/**
 * Create an empty state card
 * @param {string} type - Type of empty state (no-members, no-games, no-results, no-teams, no-champions, search, error)
 * @param {string} title - Title text
 * @param {string} description - Description text
 * @param {array} actions - Array of action objects: [{text: 'Add', url: '#', icon: '+'}, ...]
 * @returns {HTMLElement} - The empty state element
 */
function createEmptyState(type = 'default', title = 'No data found', description = '', actions = []) {
    const container = document.createElement('div');
    container.className = `empty-state empty-state--${type}`;

    // Icon
    const icon = document.createElement('div');
    icon.className = 'empty-state__icon';
    container.appendChild(icon);

    // Title
    const titleEl = document.createElement('h3');
    titleEl.className = 'empty-state__title';
    titleEl.textContent = title;
    container.appendChild(titleEl);

    // Description
    if (description) {
        const desc = document.createElement('p');
        desc.className = 'empty-state__description';
        desc.textContent = description;
        container.appendChild(desc);
    }

    // Actions
    if (actions.length > 0) {
        const actionsContainer = document.createElement('div');
        actionsContainer.className = 'empty-state__actions';

        actions.forEach(action => {
            const actionEl = document.createElement('div');
            actionEl.className = 'empty-state__action';

            const btn = document.createElement('a');
            btn.href = action.url;
            btn.className = 'btn';
            if (action.secondary) {
                btn.className += ' btn--subtle';
            }
            btn.textContent = action.text;

            actionEl.appendChild(btn);
            actionsContainer.appendChild(actionEl);
        });

        container.appendChild(actionsContainer);
    }

    return container;
}

/**
 * Replace empty table with empty state
 * @param {HTMLElement} table - The table element
 * @param {string} type - Type of empty state
 * @param {string} title - Title text
 * @param {string} description - Description text
 * @param {array} actions - Array of actions
 */
function replaceTableWithEmptyState(table, type, title, description, actions = []) {
    const emptyState = createEmptyState(type, title, description, actions);
    table.parentNode.replaceChild(emptyState, table);
}

/**
 * Check if table body has rows and show empty state if not
 * @param {string} selector - Table selector
 * @param {string} type - Type of empty state
 * @param {string} title - Title text
 * @param {string} description - Description text
 * @param {array} actions - Array of actions
 */
function checkTableEmpty(selector, type, title, description, actions = []) {
    const table = document.querySelector(selector);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) {
        replaceTableWithEmptyState(table, type, title, description, actions);
    }
}

/**
 * Check if list is empty and show empty state if not
 * @param {string} selector - List selector
 * @param {string} itemSelector - Item selector within list
 * @param {string} type - Type of empty state
 * @param {string} title - Title text
 * @param {string} description - Description text
 * @param {array} actions - Array of actions
 */
function checkListEmpty(selector, itemSelector, type, title, description, actions = []) {
    const list = document.querySelector(selector);
    if (!list) return;

    const items = list.querySelectorAll(itemSelector);
    if (items.length === 0) {
        const emptyState = createEmptyState(type, title, description, actions);
        list.parentNode.replaceChild(emptyState, list);
    }
}

// Auto-check on page load
document.addEventListener('DOMContentLoaded', function() {
    // Uncomment and customize as needed for your pages
    // Example: checkTableEmpty('.members-table', 'no-members', 'No members yet', 'Add your first member to get started', [{text: 'Add Member', url: '#'}]);
});

/**
 * Predefined empty state configurations
 */
const emptyStateConfigs = {
    noMembers: {
        type: 'no-members',
        title: 'No Members Yet',
        description: 'Start building your club by adding members. They\'ll be able to participate in games and build their statistics.',
        actions: [{text: 'Add First Member', url: '#add-member'}]
    },
    noGames: {
        type: 'no-games',
        title: 'No Games Added',
        description: 'Build your game library. Add the games your club plays to start tracking results.',
        actions: [{text: 'Add Your First Game', url: '#add-game'}]
    },
    noResults: {
        type: 'no-results',
        title: 'No Game Results',
        description: 'Record your first game result to start tracking statistics and building member rankings.',
        actions: [{text: 'Record First Game', url: '#add-result'}]
    },
    noTeams: {
        type: 'no-teams',
        title: 'No Teams Yet',
        description: 'Create teams for team-based games. Teams can include multiple members.',
        actions: [{text: 'Create First Team', url: '#add-team'}]
    },
    noChampions: {
        type: 'no-champions',
        title: 'No Champions Yet',
        description: 'Celebrate your winners! Record champion information and upload trophy photos.',
        actions: [{text: 'Record Champion', url: '#add-champion'}]
    },
    searchNoResults: {
        type: 'search',
        title: 'No Results Found',
        description: 'Try adjusting your search terms or filters.',
        actions: [{text: 'Clear Search', url: '#', secondary: true}]
    },
    error: {
        type: 'error',
        title: 'Something Went Wrong',
        description: 'An error occurred while loading this content. Please try again.',
        actions: [{text: 'Refresh', url: '#', secondary: true}]
    }
};
