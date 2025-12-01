<?php
// Test the regex pattern
$test_names = [
    'TestClub',
    'Test Club',
    'Test-Club',
    'Test_Club',
    'Test123',
    'Test@Club',  // Should fail
    'Test!Club',  // Should fail
];

$pattern = '/^[a-zA-Z0-9\\s_-]+$/';

foreach ($test_names as $name) {
    $result = preg_match($pattern, $name) ? 'PASS' : 'FAIL';
    echo "$name: $result\n";
}
?>
