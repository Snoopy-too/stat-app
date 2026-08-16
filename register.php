<?php
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '?tab=register';
if (!empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'tab=') === false) {
    $queryString = '?' . $_SERVER['QUERY_STRING'] . '&tab=register';
}
header('Location: admin/login.php' . $queryString, true, 302);
exit();
