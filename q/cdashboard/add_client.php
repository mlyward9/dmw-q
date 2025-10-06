<?php
require_once __DIR__ . '/../config/conn.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service']; // Store full service name directly
    $ticket_number = $_POST['ticket_number'];
    $counter = $_POST['counter'];
    $status = $_POST['status'];
    $time_added = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO queue (service, ticket_number, time_added, counter, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $service, $ticket_number, $time_added, $counter, $status);

    if ($stmt->execute()) {
        $success = "Client added successfully! Ticket Number: " . $ticket_number;
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Auto-assign ticket number based on today's count
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE DATE(time_added) = '$today'");
$row = $result->fetch_assoc();
$next_ticket_number = $row['cnt'] + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client - Department of Migrant Workers</title>
    <link rel="stylesheet" href="../css/add_client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <h2><i class="fa-solid fa-user-plus"></i> Add Client</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p style="color:green;"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="service"><i class="fa-solid fa-briefcase"></i> Service:</label>
        <select name="service" id="service" required>
            <option value="">-- Select Service --</option>
            <option>Legal Assistance</option>
            <option>Balik Manggagawa</option>
            <option>Direct Hire</option>
            <option>Infosheet</option>
            <option>E Registration, OEC Assistance Etc.</option>
            <option>Government to Government</option>
            <option>Financial Assistance</option>
            <option>Shipment of Remains</option>
            <option>Livelihood</option>
        </select>

        <label for="ticket_number"><i class="fa-solid fa-ticket"></i> Ticket Number:</label>
        <input type="text" name="ticket_number" id="ticket_number" value="<?php echo $next_ticket_number; ?>" readonly required>

        <input type="hidden" name="counter" id="counter" value="">
        <input type="hidden" name="status" id="status" value="waiting">

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-check"></i> Get Ticket
        </button>
    </form>

    <div class="links-container">
        <a href="../index.php" class="link-back">
            <i class="fa-solid fa-arrow-left"></i> Back to Login
        </a>
    </div>

    <!-- Footer -->
    <div class="footer">
        ©Department of Migrant Workers
    </div>
</body>
</html>