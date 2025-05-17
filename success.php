<?php
session_start();

// If no success message in session, redirect to registration page
if (!isset($_SESSION['registration_success'])) {
    header('Location: register.php');
    exit;
}

// Get the message and clear it from session
$message = $_SESSION['registration_success'];
unset($_SESSION['registration_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - Board Game Club StatApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }

        .success-title {
            color: #28a745;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .success-message {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #0056b3;
        }

        .email-note {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
    </div>
    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h2 class="success-title">Registration Successful!</h2>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <a href="admin/login.php" class="button">Go to Login</a>
        <p class="email-note">
            <i class="fas fa-envelope"></i>
            Please check your email for the verification link.
        </p>
    </div>
</body>
</html>