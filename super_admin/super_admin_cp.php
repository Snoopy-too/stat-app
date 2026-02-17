<?php
declare(strict_types=1);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// ── Handle POST actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // ── Admin‑user actions (require admin_id) ───────────────────────
        if (isset($_POST['admin_id'])) {
            $admin_id = intval($_POST['admin_id']);

            if ($_POST['action'] === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE admin_users SET is_deactivated = 1 WHERE admin_id = ?");
                $stmt->execute([$admin_id]);
            } elseif ($_POST['action'] === 'activate') {
                $stmt = $pdo->prepare("UPDATE admin_users SET is_deactivated = 0 WHERE admin_id = ?");
                $stmt->execute([$admin_id]);
            } elseif ($_POST['action'] === 'change_email' && isset($_POST['new_email'])) {
                $new_email = trim($_POST['new_email']);
                if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $pdo->prepare("UPDATE admin_users SET email = ? WHERE admin_id = ?");
                    $stmt->execute([$new_email, $admin_id]);
                }
            } elseif ($_POST['action'] === 'change_password' && isset($_POST['new_password'])) {
                $new_password = $_POST['new_password'];
                if (strlen($new_password) >= 6) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
                    $stmt->execute([$hashed, $admin_id]);
                }
            }
        }

        // ── Club assignment actions ─────────────────────────────────────
        if ($_POST['action'] === 'assign_club'
            && !empty($_POST['target_admin_id'])
            && !empty($_POST['club_id'])
        ) {
            $target_admin_id = intval($_POST['target_admin_id']);
            $club_id         = intval($_POST['club_id']);
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO club_admins (club_id, admin_id, role) VALUES (?, ?, 'admin')"
            );
            $stmt->execute([$club_id, $target_admin_id]);
        }

        if ($_POST['action'] === 'unassign_club'
            && !empty($_POST['target_admin_id'])
            && !empty($_POST['club_id'])
        ) {
            $target_admin_id = intval($_POST['target_admin_id']);
            $club_id         = intval($_POST['club_id']);
            $stmt = $pdo->prepare(
                "DELETE FROM club_admins WHERE club_id = ? AND admin_id = ?"
            );
            $stmt->execute([$club_id, $target_admin_id]);
        }
    }

    // PRG pattern – redirect to avoid re‑submission on refresh
    header("Location: super_admin_cp.php");
    exit();
}

// ── Fetch data ──────────────────────────────────────────────────────────
$stmt   = $pdo->query("SELECT admin_id, username, email, is_super_admin, is_deactivated FROM admin_users ORDER BY admin_id");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt     = $pdo->query("SELECT club_id, club_name FROM clubs ORDER BY club_name");
$allClubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map: admin_id → [ {club_id, club_name, role} , … ]
$clubsByAdmin = [];
$caStmt = $pdo->query(
    "SELECT ca.admin_id, ca.club_id, ca.role, c.club_name
     FROM club_admins ca
     JOIN clubs c ON ca.club_id = c.club_id
     ORDER BY c.club_name"
);
foreach ($caStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $clubsByAdmin[(int)$row['admin_id']][] = $row;
}

// Flash messages
$sa_error   = $_SESSION['sa_error']   ?? '';
$sa_success = $_SESSION['sa_success'] ?? '';
unset($_SESSION['sa_error'], $_SESSION['sa_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Control Panel</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
    <style>
        /* ── Inline overrides for this page only ──────────────────────── */
        .sa-section-title {
            margin: 2rem 0 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-heading);
            border-bottom: 2px solid var(--color-border);
            padding-bottom: 0.35rem;
        }
        .club-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.5rem;
        }
        .club-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--color-surface-muted);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 1rem;
            padding: 0.2rem 0.65rem;
            font-size: 0.82rem;
        }
        .club-chip form {
            display: inline;
        }
        .club-chip .chip-remove {
            background: none;
            border: none;
            color: var(--color-error-text, #ef4444);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0;
        }
        .club-chip .chip-remove:hover {
            color: #f87171;
        }
        .assign-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .assign-row select {
            max-width: 220px;
            background-color: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        .admin-card {
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: var(--color-surface);
            color: var(--color-text);
        }
        .admin-card.deactivated {
            opacity: 0.55;
        }
        .admin-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .admin-card-header h3 {
            margin: 0;
            font-size: 1.05rem;
            color: var(--color-heading);
        }
        .admin-meta {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-bottom: 0.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge--green  { background: var(--color-success-bg); color: var(--color-success-text); }
        .badge--red    { background: var(--color-error-bg);   color: var(--color-error-text); }
        .badge--blue   { background: var(--color-primary-soft); color: var(--color-primary-strong); }
        .admin-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .sa-flash {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .sa-flash--error   { background: var(--color-error-bg);   color: var(--color-error-text);   border: 1px solid var(--color-error-border); }
        .sa-flash--success { background: var(--color-success-bg); color: var(--color-success-text); border: 1px solid var(--color-success-border); }
        .toggle-clubs-btn {
            background: var(--color-surface-muted);
            border: 1px solid var(--color-border);
            border-radius: 0.35rem;
            padding: 0.25rem 0.6rem;
            font-size: 0.8rem;
            cursor: pointer;
            color: var(--color-text-muted);
        }
        .toggle-clubs-btn:hover {
            background: var(--color-surface-alt);
            color: var(--color-text);
        }
        .clubs-detail { display: none; margin-top: 0.75rem; }
        .clubs-detail.open { display: block; }
        /* Labels inside admin cards */
        .admin-card label {
            color: var(--color-text-muted);
        }
        .no-clubs-msg {
            font-size: 0.85rem;
            color: var(--color-text-soft);
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title-group">
            <h1>Super Admin Control Panel</h1>
            <p class="header-subtitle">Manage administrator accounts &amp; club assignments</p>
        </div>
        <a href="logout.php" class="btn btn--secondary">Logout</a>
    </div>

    <div class="container">
        <?php if ($sa_error): ?>
            <div class="sa-flash sa-flash--error"><?php echo htmlspecialchars($sa_error); ?></div>
        <?php endif; ?>
        <?php if ($sa_success): ?>
            <div class="sa-flash sa-flash--success"><?php echo htmlspecialchars($sa_success); ?></div>
        <?php endif; ?>

        <?php foreach ($admins as $admin):
            $aid           = (int) $admin['admin_id'];
            $assignedClubs = $clubsByAdmin[$aid] ?? [];
            $assignedIds   = array_column($assignedClubs, 'club_id');
            $available     = array_filter($allClubs, fn($c) => !in_array($c['club_id'], $assignedIds));
        ?>
        <div class="admin-card <?php echo $admin['is_deactivated'] ? 'deactivated' : ''; ?>">
            <!-- ── Header row ────────────────────────────────────────── -->
            <div class="admin-card-header">
                <h3>
                    <?php echo htmlspecialchars($admin['username']); ?>
                    <span class="admin-meta">(ID <?php echo $aid; ?>)</span>
                    <?php if ($admin['is_super_admin']): ?>
                        <span class="badge badge--blue">Super Admin</span>
                    <?php endif; ?>
                    <?php if ($admin['is_deactivated']): ?>
                        <span class="badge badge--red">Deactivated</span>
                    <?php else: ?>
                        <span class="badge badge--green">Active</span>
                    <?php endif; ?>
                </h3>
                <div class="admin-actions">
                    <?php if (!$admin['is_super_admin'] && !$admin['is_deactivated']): ?>
                        <form method="POST" action="impersonate.php" style="display:inline;">
                            <input type="hidden" name="admin_id" value="<?php echo $aid; ?>">
                            <button class="btn btn--info btn--small" type="submit"
                                    onclick="return confirmAction(event, this.form, 'Confirm Access', 'Switch to dashboard for <strong><?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?></strong>?', 'primary', 'Switch User');">
                                Login As
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$admin['is_super_admin']): ?>
                        <form class="action-form" method="POST" style="display:inline;">
                            <input type="hidden" name="admin_id" value="<?php echo $aid; ?>">
                            <input type="hidden" name="action" value="<?php echo $admin['is_deactivated'] ? 'activate' : 'deactivate'; ?>">
                            <button class="btn <?php echo $admin['is_deactivated'] ? 'btn--success' : 'btn--danger'; ?> btn--small" type="submit"
                                    onclick="return confirmAction(event, this.form, 'Confirm Status Change', 'Are you sure you want to <strong><?php echo $admin['is_deactivated'] ? 'activate' : 'deactivate'; ?></strong> this user?', '<?php echo $admin['is_deactivated'] ? 'primary' : 'danger'; ?>', '<?php echo $admin['is_deactivated'] ? 'Activate' : 'Deactivate'; ?>');">
                                <?php echo $admin['is_deactivated'] ? 'Activate' : 'Deactivate'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Email row ─────────────────────────────────────────── -->
            <div style="margin-bottom:0.5rem;">
                <form class="action-form" method="POST" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="admin_id" value="<?php echo $aid; ?>">
                    <input type="hidden" name="action" value="change_email">
                    <label style="font-size:0.85rem;font-weight:600;">Email:</label>
                    <input class="form-control input-inline" type="email" name="new_email"
                           value="<?php echo htmlspecialchars($admin['email']); ?>" required style="max-width:280px;">
                    <button class="btn btn--info btn--small" type="submit">Update</button>
                </form>
            </div>

            <!-- ── Password row ──────────────────────────────────────── -->
            <div style="margin-bottom:0.75rem;">
                <form class="action-form" method="POST" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="admin_id" value="<?php echo $aid; ?>">
                    <input type="hidden" name="action" value="change_password">
                    <label style="font-size:0.85rem;font-weight:600;">Password:</label>
                    <input class="form-control input-inline" type="password" name="new_password"
                           placeholder="New Password" minlength="6" required style="max-width:200px;">
                    <button class="btn btn--warning btn--small" type="submit">Change Password</button>
                </form>
            </div>

            <!-- ── Club management toggle ────────────────────────────── -->
            <button class="toggle-clubs-btn" onclick="toggleClubs(this)">
                ▸ Clubs (<?php echo count($assignedClubs); ?>)
            </button>

            <div class="clubs-detail">
                <!-- Assigned clubs -->
                <?php if ($assignedClubs): ?>
                    <div class="club-chips">
                        <?php foreach ($assignedClubs as $ac): ?>
                            <span class="club-chip">
                                <?php echo htmlspecialchars($ac['club_name']); ?>
                                <span style="font-size:0.7rem;opacity:0.6;">(<?php echo htmlspecialchars($ac['role']); ?>)</span>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action"          value="unassign_club">
                                    <input type="hidden" name="target_admin_id" value="<?php echo $aid; ?>">
                                    <input type="hidden" name="club_id"         value="<?php echo $ac['club_id']; ?>">
                                    <button type="submit" class="chip-remove" title="Unassign"
                                            onclick="return confirmAction(event, this.form, 'Unassign Club', 'Remove access to <strong><?php echo htmlspecialchars($ac['club_name'], ENT_QUOTES); ?></strong> from <strong><?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?></strong>?', 'danger', 'Unassign');">
                                        &times;
                                    </button>
                                </form>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-clubs-msg">No clubs assigned.</p>
                <?php endif; ?>

                <!-- Assign new club -->
                <?php if (!empty($available)): ?>
                    <form class="assign-row" method="POST">
                        <input type="hidden" name="action"          value="assign_club">
                        <input type="hidden" name="target_admin_id" value="<?php echo $aid; ?>">
                        <select name="club_id" class="form-control" required>
                            <option value="">— select club —</option>
                            <?php foreach ($available as $c): ?>
                                <option value="<?php echo $c['club_id']; ?>">
                                    <?php 
                                        $cleanName = strip_tags(html_entity_decode($c['club_name']));
                                        if (trim($cleanName) === '') {
                                            $cleanName = "Club #" . $c['club_id'];
                                        }
                                        echo htmlspecialchars(mb_strimwidth($cleanName, 0, 50, "...")); 
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn--success btn--small" type="submit">Assign</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="../js/confirmations.js"></script>
    <script>
    function toggleClubs(btn) {
        const detail = btn.nextElementSibling;
        detail.classList.toggle('open');
        btn.textContent = detail.classList.contains('open')
            ? btn.textContent.replace('▸', '▾')
            : btn.textContent.replace('▾', '▸');
    }

    // Proxy function to handle form submissions via the modal
    function confirmAction(event, form, title, message, type = 'warning', confirmText = 'Confirm') {
        showConfirmDialog(event, {
            title: title,
            message: message,
            type: type,
            confirmText: confirmText,
            onConfirm: () => form.submit()
        });
        return false;
    }
    </script>
</body>
</html>
