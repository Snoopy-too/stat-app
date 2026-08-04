## Admin Dashboard Improvements

### Issue 1: Dark Mode Toggle Not Working
**Problem:** The dark-mode.js script is loading in the `<head>` before the DOM is ready, so the toggle button isn't being found.

**Solution:** Move the dark-mode.js script to the end of `<body>` (before `</body>`).

**Files to update:** All PHP files that have `<script src="...dark-mode.js"></script>` in the `<head>`.

### Issue 2: Account Button UX Improvement
**Problem:** The "Account" dropdown button doesn't work, and the "Change Password" button is in the header.

**Best Practice Solution:**
Create a dedicated **Account/Profile Settings page** (`admin/account.php`) where users can:
- View their account info
- Change username
- Change email  
- Change password
- View login history (optional security feature)

**Benefits:**
1. Cleaner header UI
2. All account-related actions in one place
3. Better mobile experience
4. Room for future account features (2FA, notifications, etc.)

**Implementation Plan:**
1. Create `admin/account.php` page
2. Move password change functionality there
3. Add username/email edit functionality
4. Update the Account dropdown to link to this page
5. Remove "Change Password" button from header

Would you like me to implement this solution?
