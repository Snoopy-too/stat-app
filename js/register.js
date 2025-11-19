document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const email = document.getElementById('email');
    const clubName = document.getElementById('club_name');
    const adminUsername = document.getElementById('admin_username');

    form.addEventListener('submit', function(event) {
        // Reset any previous error styles
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => input.style.borderColor = '#ddd');

        let hasError = false;

        // Validate club name (at least 3 characters)
        if (clubName.value.length < 3) {
            clubName.style.borderColor = 'red';
            alert('Club name must be at least 3 characters long');
            hasError = true;
        }

        // Validate admin username (at least 3 characters, no spaces)
        if (adminUsername.value.length < 3 || adminUsername.value.includes(' ')) {
            adminUsername.style.borderColor = 'red';
            alert('Username must be at least 3 characters long and contain no spaces');
            hasError = true;
        }

        // Validate email format
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            email.style.borderColor = 'red';
            alert('Please enter a valid email address');
            hasError = true;
        }

        // Validate password (at least 8 characters)
        if (password.value.length < 8) {
            password.style.borderColor = 'red';
            alert('Password must be at least 8 characters long');
            hasError = true;
        }

        // Check if passwords match
        if (password.value !== confirmPassword.value) {
            password.style.borderColor = 'red';
            confirmPassword.style.borderColor = 'red';
            alert('Passwords do not match');
            hasError = true;
        }

        if (hasError) {
            event.preventDefault();
        }
    });
});