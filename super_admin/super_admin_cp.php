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
            color: var(--text-primary, #1e293b);
            border-bottom: 2px solid var(--border-color, #e2e8f0);
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
            background: var(--color-bg-muted, #f1f5f9);
            border: 1px solid var(--border-color, #e2e8f0);
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
            color: var(--color-danger, #ef4444);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0;
        }
        .club-chip .chip-remove:hover {
            color: #b91c1c;
        }
        .assign-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .assign-row select {
            max-width: 220px;
        }
        .admin-card {
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: var(--color-bg-card, #fff);
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
        }
        .admin-meta {
            font-size: 0.85rem;
            color: var(--text-secondary, #64748b);
            margin-bottom: 0.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge--green  { background: #dcfce7; color: #166534; }
        .badge--red    { background: #fee2e2; color: #991b1b; }
        .badge--blue   { background: #dbeafe; color: #1e40af; }
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
        .sa-flash--error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .sa-flash--success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .toggle-clubs-btn {
            background: none;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 0.35rem;
            padding: 0.25rem 0.6rem;
            font-size: 0.8rem;
            cursor: pointer;
            color: var(--text-secondary, #64748b);
        }
        .toggle-clubs-btn:hover {
            background: var(--color-bg-muted, #f1f5f9);
        }
        .clubs-detail { display: none; margin-top: 0.75rem; }
        .clubs-detail.open { display: block; }
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
                                    onclick="return confirm('Switch to <?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?>\'s dashboard?');">
                                Login As
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$admin['is_super_admin']): ?>
                        <form class="action-form" method="POST" style="display:inline;">
                            <input type="hidden" name="admin_id" value="<?php echo $aid; ?>">
                            <input type="hidden" name="action" value="<?php echo $admin['is_deactivated'] ? 'activate' : 'deactivate'; ?>">
                            <button class="btn <?php echo $admin['is_deactivated'] ? 'btn--success' : 'btn--danger'; ?> btn--small" type="submit">
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
                                            onclick="return confirm('Unassign &quot;<?php echo htmlspecialchars($ac['club_name'], ENT_QUOTES); ?>&quot; from <?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?>?');">
                                        &times;
                                    </button>
                                </form>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:var(--text-secondary,#94a3b8);margin:0.5rem 0;">No clubs assigned.</p>
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
                                    <?php echo htmlspecialchars($c['club_name']); ?>
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

    <script>
    function toggleClubs(btn) {
        const detail = btn.nextElementSibling;
        detail.classList.toggle('open');
        btn.textContent = detail.classList.contains('open')
            ? btn.textContent.replace('▸', '▾')
            : btn.textContent.replace('▾', '▸');
    }
    </script>
</body>
</html>
