<?php
session_start();
require_once '../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/SecurityUtils.php';

// Ensure user is logged in
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin'])) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

// Fetch all clubs this admin has access to
$stmt = $pdo->prepare("
    SELECT c.club_id, c.club_name, c.logo_image,
           (SELECT COUNT(*) FROM members WHERE club_id = c.club_id) as member_count
    FROM clubs c
    JOIN club_admins ca ON c.club_id = ca.club_id
    WHERE ca.admin_id = ?
    ORDER BY c.club_name ASC
");
$stmt->execute([$_SESSION['admin_id']]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$club_count = count($clubs);

// If user has 0 clubs, redirect to dashboard or create club
if ($club_count === 0) {
    if (isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'single_club') {
        header("Location: create_first_club.php");
        exit();
    }
    header("Location: account.php");
    exit();
}

// If user has only 1 club, auto-select it and proceed
if ($club_count === 1) {
    $_SESSION['current_club_id'] = (int)$clubs[0]['club_id'];
    $_SESSION['club_id'] = (int)$clubs[0]['club_id'];
    header("Location: club_new_results.php?club_id=" . (int)$clubs[0]['club_id']);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: select_club.php");
        exit();
    }

    $selected_club_id = (int)($_POST['club_id'] ?? 0);
    
    // Verify the selected club belongs to this admin
    $valid_club = false;
    foreach ($clubs as $club) {
        if ((int)$club['club_id'] === $selected_club_id) {
            $valid_club = true;
            break;
        }
    }

    if ($valid_club) {
        $_SESSION['current_club_id'] = $selected_club_id;
        $_SESSION['club_id'] = $selected_club_id;

        // Set as default club if requested
        if (!empty($_POST['set_default'])) {
            $upd = $pdo->prepare("UPDATE admin_users SET default_club_id = ? WHERE admin_id = ?");
            $upd->execute([$selected_club_id, $_SESSION['admin_id']]);
            $_SESSION['success'] = "Club selected and saved as your default club.";
        }

        header("Location: club_new_results.php?club_id=" . $selected_club_id);
        exit();
    } else {
        $_SESSION['error'] = "Please select a valid club.";
    }
}

$csrf_token = $security->generateCSRFToken();

$pageTitle = 'Choose a club to manage - Board Game Club StatApp';
$htmlAttributes = '';
require_once '../includes/templates/header.php';
?>
    <div class="header">
        <div class="header-title-group">
            <h1>Board Game Club StatApp</h1>
            <p class="header-subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
        </div>
    </div>

    <div class="container container--narrow auth-shell">
        <?php display_session_message('success'); ?>
        <?php display_session_message('error'); ?>

        <div class="card auth-card">
            <h2 style="margin-bottom: 1.25rem;">Choose a club to manage.</h2>

            <form method="POST" action="select_club.php" class="stack">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="club_id" id="selected_club_id" value="">

                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($clubs as $club): ?>
                        <button type="submit" 
                                onclick="document.getElementById('selected_club_id').value='<?php echo (int)$club['club_id']; ?>';"
                                class="btn btn--secondary" 
                                style="display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.25rem; text-align: left; width: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-md, 8px); cursor: pointer; transition: all 0.2s ease;">
                            
                            <?php if ($club['logo_image']): ?>
                                <img src="../images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="Logo" class="club-logo-thumbnail" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div class="club-logo-thumbnail" style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-primary-soft); color: var(--color-primary-strong); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">🎯</div>
                            <?php endif; ?>

                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 1.05rem; color: var(--color-heading);"><?php echo htmlspecialchars($club['club_name']); ?></div>
                                <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: normal;"><?php echo (int)$club['member_count']; ?> member<?php echo $club['member_count'] == 1 ? '' : 's'; ?></div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="form-group form-check" style="margin-top: 0.75rem; align-items: center;">
                    <input type="checkbox" name="set_default" id="set_default" value="1" class="form-check-input">
                    <label for="set_default" style="cursor: pointer; font-size: 0.9rem; color: var(--color-heading); font-weight: 500; margin: 0;">
                        Set the club I select as my default club
                    </label>
                </div>

                <p style="font-size: 0.8rem; color: var(--color-text-muted); text-align: center;">
                    You can also set or change your default club later in your <a href="account.php" style="color: inherit; text-decoration: underline;">Account settings</a>.
                </p>
            </form>
        </div>
    </div>
<?php
$extraScripts = '<script src="../js/form-loading.js"></script><script src="../js/form-validation.js"></script>';
require_once '../includes/templates/footer.php';
?>
