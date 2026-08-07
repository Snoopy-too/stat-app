<?php
declare(strict_types=1);
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : null;
$type = $_GET['type'] ?? '';
header("Location: edit_result.php?result_id=" . $result_id . ($type ? "&type=" . urlencode($type) : ""));
exit();