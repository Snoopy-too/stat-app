function validateTeamMembers() {
    const memberSelects = [
        document.getElementById('member1'),
        document.getElementById('member2'),
        document.getElementById('member3'),
        document.getElementById('member4')
    ];

    // Reset all select borders to default
    memberSelects.forEach(select => {
        if (select) select.style.border = '';
    });

    const selectedMembers = new Set();
    const duplicates = new Set();

    // Check for duplicates
    memberSelects.forEach(select => {
        if (select && select.value) {
            if (selectedMembers.has(select.value)) {
                duplicates.add(select.value);
                select.value = ''; // Clear the duplicate selection
                select.style.border = '2px solid red';
            } else {
                selectedMembers.add(select.value);
            }
        }
    });

    if (duplicates.size > 0) {
        alert('Each member can only be selected once. Please choose different members.');
        return false;
    }

    return true;
}

// Add event listeners to all member selects
document.addEventListener('DOMContentLoaded', function() {
    const memberSelects = [
        document.getElementById('member1'),
        document.getElementById('member2'),
        document.getElementById('member3'),
        document.getElementById('member4')
    ];

    memberSelects.forEach(select => {
        if (select) {
            select.addEventListener('change', validateTeamMembers);
        }
    });

    // Add form submission validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateTeamMembers()) {
                e.preventDefault();
            }
        });
    }
});