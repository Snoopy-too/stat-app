<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Fetch all clubs for the current admin
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE admin_id = ? ORDER BY club_name");
$stmt->execute([$_SESSION['admin_id']]);
$admin_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Get club info and verify admin ownership
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE club_id = ? AND admin_id = ?");
$stmt->execute([$club_id, $_SESSION['admin_id']]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: dashboard.php");
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'member_name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Build the query - simplified version without game statistics
$query = "
    SELECT m.*, c.club_name
    FROM members m
    JOIN clubs c ON m.club_id = c.club_id AND c.admin_id = ?
    WHERE m.club_id = ?
";

$params = [$_SESSION['admin_id'], $club_id];

// Update search condition to include nickname
if ($search) {
    $query .= " AND (m.member_name LIKE ? OR m.nickname LIKE ? OR m.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

// Define valid sort columns
$valid_sort_columns = ['member_name', 'nickname', 'email', 'status'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'member_name';
$query .= " ORDER BY m." . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle member creation/deletion and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_members'])) {
        $selected_members = $_POST['selected_members'];
        $bulk_action = $_POST['bulk_action'];
        
        try {
            switch ($bulk_action) {
                case 'bulk_activate':
                    $stmt = $pdo->prepare("UPDATE members SET status = 'active' WHERE member_id = ? AND club_id = ? AND admin_id = ?");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members activated successfully!";
                    break;
                    
                case 'bulk_deactivate':
                    $stmt = $pdo->prepare("UPDATE members SET status = 'inactive' WHERE member_id = ? AND club_id = ? AND admin_id = ?");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members deactivated successfully!";
                    break;
                    
                case 'bulk_delete':
                    $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ? AND club_id = ? AND admin_id = ?");
                    foreach ($selected_members as $member_id) {
                        $stmt->execute([$member_id, $club_id, $_SESSION['admin_id']]);
                    }
                    $_SESSION['success'] = "Selected members deleted successfully!";
                    break;
            }
            
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to perform bulk action: " . $e->getMessage();
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();
        }
    } else if (isset($_POST['action'])) {
        // Update the INSERT query in the POST handling section
        if ($_POST['action'] === 'create' && !empty($_POST['member_name']) && !empty($_POST['email']) && !empty($_POST['club_id'])) {
            try {
                // Verify the club belongs to the admin
                $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ? AND admin_id = ?");
                $stmt->execute([$_POST['club_id'], $_SESSION['admin_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception("Unauthorized club access");
                }
                
                $stmt = $pdo->prepare("INSERT INTO members (club_id, member_name, nickname, email, status, admin_id) VALUES (?, ?, ?, ?, 'active', ?)");
                $stmt->execute([
                    $_POST['club_id'],
                    trim($_POST['member_name']),
                    trim($_POST['nickname']),
                    trim($_POST['email']),
                    $_SESSION['admin_id']
                ]);
                $club_id = $_POST['club_id'];
                $_SESSION['success'] = "Member added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to add member: " . $e->getMessage();
            }
            header("Location: manage_members.php?club_id=" . $club_id);
            exit();

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="header">
        <h1>Manage Members - <?php echo htmlspecialchars($club['club_name']); ?></h1>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Add New Member</h2>
            <form method="POST" class="form">
                <div class="form-group">
                    <input type="text" name="member_name" placeholder="Full Name" required class="form-control">
                    <input type="text" name="nickname" placeholder="Nickname (for public display)" required class="form-control">
                    <input type="email" name="email" placeholder="Email Address" required class="form-control">
                    <select name="club_id" required class="form-control">
                        <option value="">Select Club</option>
                        <?php foreach ($admin_clubs as $club): ?>
                            <option value="<?php echo $club['club_id']; ?>" <?php echo ($club_id == $club['club_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="action" value="create">
                    <button type="submit" class="button">Add Member</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="filters">
                <form method="GET" class="search-form" id="filter-form">
                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Search members..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        
                        <select name="status" id="status-filter" class="filter-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="bulk-actions">
                <form method="POST" id="bulk-form">
                    <select name="bulk_action" class="filter-select">
                        <option value="">Bulk Actions</option>
                        <option value="bulk_activate">Activate Selected</option>
                        <option value="bulk_deactivate">Deactivate Selected</option>
                    </select>
                    <button type="submit" class="button" onclick="return confirmBulkAction()">Apply</button>
                </form>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Club</th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=member_name&order=<?php echo $sort === 'member_name' && $order === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="sort-link">
                                Name <?php echo $sort === 'member_name' ? ($order === 'asc' ? '^' : 'v') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=nickname&order=<?php echo $sort === 'nickname' && $order === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="sort-link">
                                Nickname <?php echo $sort === 'nickname' ? ($order === 'asc' ? '^' : 'v') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=email&order=<?php echo $sort === 'email' && $order === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="sort-link">
                                Email <?php echo $sort === 'email' ? ($order === 'asc' ? '^' : 'v') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?club_id=<?php echo $club_id; ?>&sort=status&order=<?php echo $sort === 'status' && $order === 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="sort-link">
                                Status <?php echo $sort === 'status' ? ($order === 'asc' ? '^' : 'v') : ''; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_members[]" form="bulk-form"
                                       value="<?php echo $member['member_id']; ?>" class="member-checkbox">
                            </td>
                            <td><?php echo htmlspecialchars($member['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['nickname']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $member['status']; ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit_member.php?club_id=<?php echo $club_id; ?>&member_id=<?php echo $member['member_id']; ?>" 
                                   class="button">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.member-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            if (!action) {
                alert('Please select an action');
                return false;
            }
            return confirm('Are you sure you want to perform this action on the selected members?');
        }

         // Handle sorting links with AJAX using event delegation on the body
         document.body.addEventListener('click', function(e) {
             if (e.target.matches('.sort-link')) {
                e.preventDefault();
                const url = e.target.href;
                const currentTable = document.querySelector('.data-table');
                const tableTopOffset = currentTable ? currentTable.getBoundingClientRect().top : 0; // Save table's top offset relative to viewport
                const currentScrollY = window.scrollY;

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                         const parser = new DOMParser();
                         const doc = parser.parseFromString(html, 'text/html');
                         const newTableHeaderContent = doc.querySelector('.data-table thead').innerHTML;
                         const newTableRows = doc.querySelectorAll('.data-table tbody tr');
                         const currentTableHeader = document.querySelector('.data-table thead');
                         const currentTableBody = document.querySelector('.data-table tbody');

                         if (currentTableHeader && newTableHeaderContent) {
                             currentTableHeader.innerHTML = newTableHeaderContent; // Update header content
                         }
                         if (currentTableBody) {
                             currentTableBody.innerHTML = ''; // Clear existing rows
                             newTableRows.forEach(row => {
                                 currentTableBody.appendChild(row.cloneNode(true)); // Append new rows (clone to avoid issues)
                             });
                         }

                         window.history.pushState({}, '', url); // Update URL
                         // Use setTimeout to restore scroll after event loop turn
                         setTimeout(() => {
                             window.scrollTo(0, currentScrollY); // Restore original scroll position
                         }, 0);
                    })
                      .catch(error => console.error('Error:', error));
             }
         });

        // Automatically submit filter form on status change
        const statusFilter = document.getElementById('status-filter');
        const filterForm = document.getElementById('filter-form');
        if (statusFilter && filterForm) {
            statusFilter.addEventListener('change', function() {
                filterForm.submit();
            });
        }
    </script>
</body>
</html>