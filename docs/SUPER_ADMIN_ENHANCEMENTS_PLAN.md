# Super Admin Dashboard Enhancements Plan

## Goal

Enhance `super_admin/super_admin_cp.php` with two new capabilities:

1. **Club Assignment Management** — Assign and unassign existing clubs to/from any admin user via the `club_admins` junction table.
2. **Admin Impersonation ("Login As")** — Allow the super admin to log into any admin's dashboard without needing their password.

---

## Current Architecture

| Concern | Current State |
|---|---|
| **Super Admin CP** | `super_admin/super_admin_cp.php` — manages admin users (activate/deactivate, change email/password) |
| **Club ↔ Admin link** | `club_admins` table (`id`, `club_id`, `admin_id`, `role`, `created_at`) with a UNIQUE constraint on (`club_id`, `admin_id`) |
| **Admin session vars** | `admin_id`, `admin_username`, `admin_type`, `is_super_admin`, `is_admin` |
| **Admin dashboard guard** | Checks `$_SESSION['is_admin']` or `$_SESSION['is_super_admin']` |

---

## Proposed Changes

### 1. Club Assignment Management

**Where:** `super_admin/super_admin_cp.php`

Add a **new section below the existing admin table** that provides a club management interface per admin:

- For each admin row, add an **expandable "Manage Clubs" section** (or a dedicated column) showing:
  - All clubs currently assigned to that admin (from `club_admins`).
  - A button to **unassign** each club (deletes the row from `club_admins`).
  - A dropdown/select listing **all clubs NOT currently assigned** to that admin, with an **"Assign"** button that inserts into `club_admins`.

**Back-end actions (POST handlers):**

| Action | SQL |
|---|---|
| `assign_club` | `INSERT INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'admin')` |
| `unassign_club` | `DELETE FROM club_admins WHERE club_id = ? AND admin_id = ?` |

**Security:**
- Both actions require `$_SESSION['is_super_admin']` === true (already enforced by page guard).
- `admin_id` and `club_id` are cast to `intval()`.
- All output escaped with `htmlspecialchars()`.

---

### 2. Admin Impersonation ("Login As")

**New file:** `super_admin/impersonate.php`

This endpoint allows the super admin to switch their session into an admin's session context, landing them on the admin dashboard.

**Mechanism:**
1. Super admin clicks **"Login As"** button next to an admin row.
2. POST request to `super_admin/impersonate.php` with the target `admin_id`.
3. Server-side:
   - Verify `$_SESSION['is_super_admin']` is true.
   - Look up the target admin in `admin_users`.
   - Save the super admin's original session data (`admin_id`, `admin_username`) into `$_SESSION['original_super_admin_id']` and `$_SESSION['original_super_admin_username']` for a "return to super admin" feature.
   - Overwrite `$_SESSION['admin_id']`, `$_SESSION['admin_username']`, `$_SESSION['admin_type']` with the target admin's values.
   - Set `$_SESSION['is_admin'] = true` and `$_SESSION['is_super_admin'] = false`.
   - Set `$_SESSION['is_impersonating'] = true` so the admin dashboard can show a "Return to Super Admin" banner.
   - Redirect to `../admin/dashboard.php`.

**New file:** `super_admin/return_to_super_admin.php`

- Verifies `$_SESSION['is_impersonating']` and `$_SESSION['original_super_admin_id']` exist.
- Restores the super admin session: resets `admin_id`, `admin_username`, `is_super_admin = true`, `is_admin = false`.
- Clears the impersonation flags.
- Redirects to `super_admin/super_admin_cp.php`.

**Admin Dashboard Banner (optional but recommended):**

Add a small PHP snippet at the top of `admin/dashboard.php` (and potentially other admin pages via a shared include) that detects `$_SESSION['is_impersonating']` and renders a warning banner:
> ⚠️ You are viewing as **{username}**. [Return to Super Admin Panel]

---

### 3. Summary of Files Changed

| File | Change Type | Description |
|---|---|---|
| `super_admin/super_admin_cp.php` | **MODIFY** | Add club assignment UI, "Login As" button, and POST handlers for `assign_club` / `unassign_club` |
| `super_admin/impersonate.php` | **NEW** | Endpoint to switch session to target admin |
| `super_admin/return_to_super_admin.php` | **NEW** | Endpoint to restore super admin session |
| `admin/dashboard.php` | **MODIFY** | Add impersonation warning banner at top |

---

## Security Considerations

> [!IMPORTANT]
> - **Impersonation is a privileged action** — only valid when `$_SESSION['is_super_admin'] === true` before the switch.
> - The original super admin identity is stored in session so it can be recovered, but the impersonating session **does not retain** super admin privileges (preventing privilege leakage).
> - All club assignment/unassignment actions validate integer IDs via `intval()`.
> - All rendered output uses `htmlspecialchars()` to prevent XSS.

---

## Verification Plan

### Manual Testing (Browser)

1. **Login as Super Admin** at `super_admin/login.php`.
2. **Club Assignment:**
   - Expand/view clubs for an admin → verify listed clubs match `club_admins` rows.
   - Assign a new club → verify it appears in the admin's club list AND in `club_admins` table.
   - Unassign a club → verify it disappears from the admin's club list AND from `club_admins` table.
   - Try assigning a club that's already assigned → verify graceful handling (no duplicate error).
3. **Impersonation:**
   - Click "Login As" on a non-super-admin user → verify redirect to `admin/dashboard.php`.
   - Verify the dashboard shows **that admin's clubs**, not the super admin's.
   - Verify the impersonation warning banner appears with a "Return to Super Admin" link.
   - Click "Return to Super Admin" → verify redirect back to `super_admin/super_admin_cp.php` with full super admin access restored.
4. **Edge Cases:**
   - Attempt to impersonate a deactivated admin → should behave sanely (optionally block or warn).
   - Access `impersonate.php` directly without super admin session → should redirect to login.
