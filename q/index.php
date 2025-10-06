<?php
// index.php (login)
session_start();
include("config/conn.php"); // your DB connection file

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare query
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check password (assuming hashed with password_hash)
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];

            header("Location: udashboard/tickets.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Department of Migrant Workers</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <h2>Login to Queueing System</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label><i class="fa-solid fa-user"></i> Username:</label>
        <input type="text" name="username" required>

        <label><i class="fa-solid fa-lock"></i> Password:</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-right-to-bracket"></i> Login
        </button>
    </form>

    <div class="links-container">
        <p>Don't have an account? <a href="udashboard/register.php">Register here</a></p>
        
        <div class="quick-links">
            <a href="qdisplay/queue.php" class="link-btn">
                <i class="fa-solid fa-list"></i> View Queue
            </a>
            <a href="cdashboard/add_client.php" class="link-btn">
                <i class="fa-solid fa-user-plus"></i> Add Client
            </a>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        ©Department of Migrant Workers
    </div>
</body>
</html>