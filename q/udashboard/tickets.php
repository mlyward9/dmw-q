<?php 
session_start();
require_once __DIR__ . '/../config/conn.php';

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown';
$current_counter = 'pacd'; 
$current_date = $_POST['ticket_date'] ?? date('Y-m-d');

// Fetch user's current counter
if ($user_id) {
    $sql = "SELECT counter FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_counter = $row['counter'];
    }
}

// Handle counter change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['counter'], $user_id)) {
    $new_counter = $_POST['counter'];
    $sql = "UPDATE users SET counter = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_counter, $user_id);
    $stmt->execute();
    $current_counter = $new_counter;
}

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Call ticket
    if (isset($_POST['get_ticket_id'])) {
        $ticket_id = (int) $_POST['get_ticket_id'];
        $sql = "UPDATE queue SET status = 'called', counter = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $current_counter, $ticket_id);
        $stmt->execute();
    }

    // Mark ticket as done with ERN
    if (isset($_POST['done_ticket_id'])) {
        $ticket_id = (int) $_POST['done_ticket_id'];
        $ern = trim($_POST['ern'] ?? '');
        
        // Get ticket details
        $stmt = $conn->prepare("SELECT * FROM queue WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        
        if ($ticket) {
            // Update queue with ERN and status
            $sql = "UPDATE queue SET status = 'done', ern = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $ern, $ticket_id);
            $stmt->execute();
            
            // Calculate time duration
            $time_added = strtotime($ticket['time_added']);
            $time_updated = time();
            $duration = $time_updated - $time_added;
            
            // Insert into service_logs
            $sql = "INSERT INTO service_logs (ticket_id, ticket_number, service, counter, served_by, served_by_username, ern, time_added, updated_at, time_duration) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssisssi", 
                $ticket_id, 
                $ticket['ticket_number'], 
                $ticket['service'], 
                $current_counter, 
                $user_id, 
                $username, 
                $ern, 
                $ticket['time_added'], 
                $duration
            );
            $stmt->execute();
        }
    }

    // Cancel ticket
    if (isset($_POST['cancel_ticket_id'])) {
        $ticket_id = (int) $_POST['cancel_ticket_id'];
        $sql = "UPDATE queue SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
    }

    // Transfer ticket to different service/counter
    if (isset($_POST['transfer_ticket_id'], $_POST['new_service'])) {
        $ticket_id = (int) $_POST['transfer_ticket_id'];
        $new_service = $_POST['new_service'];
        
        // Determine counter based on service
        $new_counter = '';
        if ($new_service === "Legal Assistance") {
            $new_counter = '1'; // Can be 1, 2, or 3
        } elseif (in_array($new_service, ["Balik Manggagawa", "Direct Hire", "Infosheet", "E Registration", "OEC Assistance", "Government to Government"])) {
            $new_counter = '4'; // Can be 4, 5, 6, or 7
        } elseif (in_array($new_service, ["Financial Assistance", "Shipment of Remains", "Livelihood"])) {
            $new_counter = '8'; // Can be 8, 9, or 10
        } elseif ($new_service === "E Registration, OEC Assistance Etc.") {
            $new_counter = 'pacd';
        }
        
        $sql = "UPDATE queue SET service = ?, counter = ?, status = 'waiting', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_service, $new_counter, $ticket_id);
        $stmt->execute();
    }
}

// Define services based on counter
$services = [];
if ($current_counter === 'pacd') {
    $services = ["E Registration, OEC Assistance Etc."];
} elseif (in_array($current_counter, [1, 2, 3])) {
    $services = ["Legal Assistance"];
} elseif (in_array($current_counter, [4, 5, 6, 7])) {
    $services = [
        "Balik Manggagawa",
        "Direct Hire",
        "Infosheet",
        "E Registration",
        "OEC Assistance",
        "Government to Government"
    ];
} elseif (in_array($current_counter, [8, 9, 10])) {
    $services = [
        "Financial Assistance",
        "Shipment of Remains",
        "Livelihood"
    ];
}

// Fetch tickets for current date
$tickets = [];
if (!empty($services)) {
    $placeholders = implode(',', array_fill(0, count($services), '?'));
    $sql = "SELECT * FROM queue WHERE service IN ($placeholders) AND DATE(time_added) = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $types = str_repeat('s', count($services)) . 's';
        $values = array_merge($services, [$current_date]);

        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($values); $i++) {
            $bind_names[] = &$values[$i];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
} else {
    $sql = "SELECT * FROM queue WHERE counter = ? AND DATE(time_added) = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $current_counter, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Calculate statistics
$stats = [
    'waiting' => 0,
    'called' => 0,
    'done' => 0,
    'cancelled' => 0
];
foreach ($tickets as $t) {
    $status = strtolower($t['status']);
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tickets - Department of Migrant Workers</title>
    <link rel="stylesheet" href="../css/tickets.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        function promptERN(ticketId) {
            const ern = prompt("Please enter ERN (E-Registration Number):");
            if (ern !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="done_ticket_id" value="${ticketId}">
                    <input type="hidden" name="ern" value="${ern}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showTransferModal(ticketId) {
            document.getElementById('transfer_ticket_id').value = ticketId;
            document.getElementById('transferModal').style.display = 'block';
        }

        function closeTransferModal() {
            document.getElementById('transferModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('transferModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-ticket"></i> Tickets for Counter <?= htmlspecialchars($current_counter) ?></h1>
            <p>Showing tickets for <strong><?= htmlspecialchars($current_date) ?></strong> | Logged in as <strong><?= htmlspecialchars($username) ?></strong></p>
        </div>

        <div class="filters">
            <div class="filters-left">
                <form method="post" class="filter-form">
                    <label for="counter"><i class="fa-solid fa-computer"></i> Counter:</label>
                    <select name="counter" id="counter" onchange="this.form.submit()">
                        <option value="pacd" <?= $current_counter === 'pacd' ? 'selected' : '' ?>>PACD</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= $current_counter == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </form>

                <form method="post" class="filter-form">
                    <label for="ticket_date"><i class="fa-solid fa-calendar"></i> Date:</label>
                    <input type="date" id="ticket_date" name="ticket_date" value="<?= htmlspecialchars($current_date) ?>" onchange="this.form.submit()">
                </form>
            </div>

            <form action="logout.php" method="post">
                <button type="submit" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
            </form>
        </div>

        <div class="ticket-layout">
            <div class="ticket-table-wrapper">
                <?php if (!empty($tickets)): ?>
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Counter</th>
                                <th>ERN</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['ticket_number'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($t['service']) ?></td>
                                <td>
                                    <?php 
                                        $status = strtolower($t['status']); 
                                        $class = "status";
                                        if ($status === "waiting") $class .= " status-waiting";
                                        if ($status === "called") $class .= " status-called";
                                        if ($status === "done") $class .= " status-done";
                                        if ($status === "cancelled") $class .= " status-cancelled";
                                    ?>
                                    <span class="<?= $class ?>"><?= htmlspecialchars($t['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($t['counter'] ?? 'Not Assigned') ?></td>
                                <td><?= htmlspecialchars($t['ern'] ?? '-') ?></td>
                                <td class="actions-cell">
                                    <?php if ($status === 'waiting'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="get_ticket_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-call">
                                                <i class="fa-solid fa-bullhorn"></i> Call
                                            </button>
                                        </form>
                                        <button type="button" class="btn-transfer" onclick="showTransferModal(<?= $t['id'] ?>)">
                                            <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($status === 'called'): ?>
                                        <button type="button" class="btn-done" onclick="promptERN(<?= $t['id'] ?>)">
                                            <i class="fa-solid fa-check"></i> Done
                                        </button>
                                        <button type="button" class="btn-transfer" onclick="showTransferModal(<?= $t['id'] ?>)">
                                            <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($status === 'done'): ?>
                                        <button type="button" class="btn-transfer" onclick="showTransferModal(<?= $t['id'] ?>)">
                                            <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($status, ['waiting', 'called'])): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="cancel_ticket_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this ticket?')">
                                                <i class="fa-solid fa-xmark"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-tickets">
                        <i class="fa-solid fa-inbox"></i>
                        <p>No tickets found for this counter on <?= htmlspecialchars($current_date) ?>.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stats-panel">
                <h3><i class="fa-solid fa-chart-pie"></i> Total</h3>
                <div class="stat-item">
                    <span class="status status-waiting">Waiting</span>
                    <span class="stat-count"><?= $stats['waiting'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="status status-called">Called</span>
                    <span class="stat-count"><?= $stats['called'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="status status-done">Done</span>
                    <span class="stat-count"><?= $stats['done'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="status status-cancelled">Cancelled</span>
                    <span class="stat-count"><?= $stats['cancelled'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transferModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTransferModal()">&times;</span>
            <h2><i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer Ticket</h2>
            <form method="post">
                <input type="hidden" name="transfer_ticket_id" id="transfer_ticket_id">
                <label for="new_service">Transfer to Service:</label>
                <select name="new_service" id="new_service" required>
                    <option value="">-- Select Service --</option>
                    <option value="Legal Assistance">Legal Assistance (Counter 1-3)</option>
                    <option value="Balik Manggagawa">Balik Manggagawa (Counter 4-7)</option>
                    <option value="Direct Hire">Direct Hire (Counter 4-7)</option>
                    <option value="Infosheet">Infosheet (Counter 4-7)</option>
                    <option value="E Registration">E Registration (Counter 4-7)</option>
                    <option value="OEC Assistance">OEC Assistance (Counter 4-7)</option>
                    <option value="Government to Government">Government to Government (Counter 4-7)</option>
                    <option value="Financial Assistance">Financial Assistance (Counter 8-10)</option>
                    <option value="Shipment of Remains">Shipment of Remains (Counter 8-10)</option>
                    <option value="Livelihood">Livelihood (Counter 8-10)</option>
                    <option value="E Registration, OEC Assistance Etc.">E Registration, OEC Assistance Etc. (PACD)</option>
                </select>
                <button type="submit" class="btn-primary">Transfer Ticket</button>
            </form>
        </div>
    </div>

    <div class="footer">
        ©Department of Migrant Workers
    </div>
</body>
</html>