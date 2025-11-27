<?php
// Simple test to see if POST works at all
echo "<h1>POST Test</h1>";
echo "<pre>Request Method: " . $_SERVER['REQUEST_METHOD'] . "</pre>";
echo "<pre>POST Data: " . print_r($_POST, true) . "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2 style='color: green;'>SUCCESS! POST is working!</h2>";
} else {
    echo "<h2 style='color: orange;'>This is a GET request</h2>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>POST Test</title>
</head>
<body>
    <h3>Test Form</h3>
    <form method="POST" action="test-post.php">
        <input type="text" name="test_field" value="test value" />
        <button type="submit">Submit Test</button>
    </form>
</body>
</html>
