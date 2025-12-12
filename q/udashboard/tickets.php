<?php 
session_start();
require_once __DIR__ . '/../config/conn.php';

// Set proper charset
header('Content-Type: text/html; charset=UTF-8');

// Authentication check
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$user_id || !$username) {
    header('Location: logout.php');
    exit;
}

$current_counter = '3'; 
$current_date = $_POST['ticket_date'] ?? $_GET['ticket_date'] ?? date('Y-m-d');
$search_query = $_POST['search'] ?? $_GET['search'] ?? '';
$error_message = '';
$success_message = '';

// Fetch user's current counter
if ($user_id) {
    $stmt = $conn->prepare("SELECT counter FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_counter = $row['counter'];
    }
    $stmt->close();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle counter change
    if (isset($_POST['counter']) && $user_id) {
        $new_counter = filter_var($_POST['counter'], FILTER_VALIDATE_INT);
        if ($new_counter >= 1 && $new_counter <= 13) {
            $stmt = $conn->prepare("UPDATE users SET counter = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_counter, $user_id);
            if ($stmt->execute()) {
                $current_counter = $new_counter;
                $success_message = "Counter updated successfully";
            }
            $stmt->close();
        }
    }
    
    // Call ticket
    if (isset($_POST['get_ticket_id'])) {
        $ticket_id = filter_var($_POST['get_ticket_id'], FILTER_VALIDATE_INT);
        if ($ticket_id) {
            $conn->begin_transaction();
            try {
                // Get ticket number
                $stmt = $conn->prepare("SELECT ticket_number FROM queue WHERE id = ? AND status = 'waiting' LIMIT 1");
                $stmt->bind_param("i", $ticket_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $ticket = $result->fetch_assoc();
                $stmt->close();
                
                if ($ticket) {
                    // Update ticket status
                    $stmt = $conn->prepare("UPDATE queue SET status = 'called', counter = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $current_counter, $ticket_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Insert notification
                    $stmt = $conn->prepare("INSERT INTO ticket_notifications (ticket_number, counter, created_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ss", $ticket['ticket_number'], $current_counter);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $success_message = "Ticket {$ticket['ticket_number']} called successfully";
                } else {
                    $conn->rollback();
                    $error_message = "Ticket not found or already called";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error calling ticket: " . $e->getMessage();
            }
        }
    }

// Mark ticket as done with remarks
if (isset($_POST['done_ticket_id'])) {
    $ticket_id = filter_var($_POST['done_ticket_id'], FILTER_VALIDATE_INT);
    $ern = trim($_POST['ern'] ?? '');
    
    if ($ticket_id) {
        $conn->begin_transaction();
        try {
            // Get ticket details
            $stmt = $conn->prepare("SELECT * FROM queue WHERE id = ? AND status = 'called' LIMIT 1");
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            $ticket = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($ticket) {
                // Get the current time for updated_at
                $updated_at = date('Y-m-d H:i:s');
                
                // Update queue
                $stmt = $conn->prepare("UPDATE queue SET status = 'done', ern = ?, updated_at = ? WHERE id = ?");
                $stmt->bind_param("ssi", $ern, $updated_at, $ticket_id);
                $stmt->execute();
                $stmt->close();
                
                // Handle time_added - ensure it's a valid datetime
                $time_added = $ticket['time_added'];
                
                // If time_added is null or invalid, use current time minus 1 hour as fallback
                if (empty($time_added) || $time_added == '0000-00-00 00:00:00') {
                    $time_added = date('Y-m-d H:i:s', strtotime('-1 hour'));
                }
                
                // Calculate duration in seconds
                $time_added_timestamp = strtotime($time_added);
                $time_updated_timestamp = strtotime($updated_at);
                
                // Ensure we have valid timestamps
                if ($time_added_timestamp === false) {
                    $time_added_timestamp = strtotime('-1 hour');
                    $time_added = date('Y-m-d H:i:s', $time_added_timestamp);
                }
                
                $duration = $time_updated_timestamp - $time_added_timestamp;
                
                // Ensure duration is not negative
                if ($duration < 0) {
                    $duration = 0;
                }
                
                // Prepare variables for service_logs
                $region = $ticket['region'] ?? '';
                $province = $ticket['province'] ?? '';
                $gender = $ticket['gender'] ?? '';
                
                // Insert into service_logs - use proper types for bind_param
                $stmt = $conn->prepare(
                    "INSERT INTO service_logs 
                    (ticket_id, ticket_number, service, counter, served_by, served_by_username, ern, time_added, updated_at, time_duration, region, province, gender) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                
                // Use proper parameter types: i=integer, s=string, d=double
                $stmt->bind_param(
                    "isssissssisss", // Changed to match parameter count and types
                    $ticket_id,
                    $ticket['ticket_number'],
                    $ticket['service'],
                    $current_counter,
                    $user_id,
                    $username,
                    $ern,
                    $time_added,
                    $updated_at,
                    $duration,
                    $region,
                    $province,
                    $gender
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert service log: " . $stmt->error);
                }
                $stmt->close();
                
                $conn->commit();
                $success_message = "Ticket marked as done";
            } else {
                $conn->rollback();
                $error_message = "Ticket not found or not in called status";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error marking ticket as done: " . $e->getMessage();
        }
    }
}
    // Cancel ticket
    if (isset($_POST['cancel_ticket_id'])) {
        $ticket_id = filter_var($_POST['cancel_ticket_id'], FILTER_VALIDATE_INT);
        if ($ticket_id) {
            $stmt = $conn->prepare("UPDATE queue SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status IN ('waiting', 'called')");
            $stmt->bind_param("i", $ticket_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success_message = "Ticket cancelled";
            } else {
                $error_message = "Could not cancel ticket";
            }
            $stmt->close();
        }
    }

    // Transfer ticket
    if (isset($_POST['transfer_ticket_id'], $_POST['new_service'])) {
        $ticket_id = filter_var($_POST['transfer_ticket_id'], FILTER_VALIDATE_INT);
        $new_service = trim($_POST['new_service']);
        
        // Service to counter mapping
        $service_counter_map = [
            "Legal Assistance" => '1',
            "Balik Manggagawa" => '4',
            "Direct Hire" => '7',
            "Infosheet" => '6',
            "E Registration / OEC Assistance" => '3',
            "Government to Government" => '7',
            "Welfare" => '8',
            "Shipment of Remains" => '8',
            "Livelihood" => '8',
            "Cashier" => '11',
            "OWWA" => '12',
            "PAG IBIG" => '13'
        ];
        
        if ($ticket_id && isset($service_counter_map[$new_service])) {
            $new_counter = $service_counter_map[$new_service];
            $stmt = $conn->prepare("UPDATE queue SET service = ?, counter = ?, status = 'waiting', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $new_service, $new_counter, $ticket_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $success_message = "Ticket transferred to $new_service";
            } else {
                $error_message = "Could not transfer ticket";
            }
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    if (!isset($_POST['counter']) && !isset($_POST['ticket_date']) && !isset($_POST['search'])) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Define services based on counter
$services = [];
$counter_int = (int)$current_counter;

if (in_array($counter_int, [1, 2])) {
    $services = ["Legal Assistance"];
} elseif (in_array($counter_int, [3, 4, 5, 6, 7])) {
    $services = ["Balik Manggagawa", "Direct Hire", "Infosheet", "E Registration / OEC Assistance", "Government to Government"];
} elseif (in_array($counter_int, [8, 9, 10])) {
    $services = ["Welfare", "Shipment of Remains", "Livelihood"];
} elseif ($counter_int == 11) {
    $services = ["Cashier"];
} elseif ($counter_int == 12) {
    $services = ["OWWA"];
} elseif ($counter_int == 13) {
    $services = ["PAG IBIG"];
}

// Fetch tickets - OPTIMIZED QUERY with SEARCH
$tickets = [];
if (!empty($services)) {
    $placeholders = implode(',', array_fill(0, count($services), '?'));
    
    // Build query with optional search
    $sql = "SELECT * FROM queue 
            WHERE service IN ($placeholders) 
            AND DATE(time_added) = ?";
    
    // Add search condition if search query exists
    if (!empty($search_query)) {
        $sql .= " AND (ticket_number LIKE ? OR service LIKE ? OR ern LIKE ?)";
    }
    
    $sql .= " ORDER BY 
                CASE status 
                    WHEN 'called' THEN 1 
                    WHEN 'waiting' THEN 2 
                    WHEN 'done' THEN 3 
                    WHEN 'cancelled' THEN 4 
                END,
                priority DESC,
                id ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = str_repeat('s', count($services)) . 's';
        $params = array_merge($services, [$current_date]);
        
        // Add search parameters if search query exists
        if (!empty($search_query)) {
            $search_param = "%{$search_query}%";
            $types .= 'sss';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Calculate statistics
$stats = ['waiting' => 0, 'called' => 0, 'done' => 0, 'cancelled' => 0];
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
    <style>
        .alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-form input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 250px;
        }
        
        .search-form button {
            padding: 8px 16px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .search-form button:hover {
            background: #0052a3;
        }
        
        .btn-clear-search {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-clear-search:hover {
            background: #5a6268;
        }
        
        .search-info {
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 14px;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .search-info i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-ticket"></i> Tickets for Counter <?= htmlspecialchars($current_counter) ?></h1>
            <p>Showing tickets for <strong><?= htmlspecialchars($current_date) ?></strong> | Logged in as <strong><?= htmlspecialchars($username) ?></strong></p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="filters-left">
                <form method="post" class="filter-form">
                    <label for="counter"><i class="fa-solid fa-computer"></i> Counter:</label>
                    <select name="counter" id="counter" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 13; $i++): ?>
                            <option value="<?= $i ?>" <?= $current_counter == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </form>

                <form method="post" class="filter-form">
                    <label for="ticket_date"><i class="fa-solid fa-calendar"></i> Date:</label>
                    <input type="date" id="ticket_date" name="ticket_date" value="<?= htmlspecialchars($current_date) ?>" onchange="this.form.submit()">
                </form>
                
                <form method="post" class="search-form">
                    <input type="hidden" name="ticket_date" value="<?= htmlspecialchars($current_date) ?>">
                    <input type="text" name="search" placeholder="Search ticket number, service, or remarks..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit"><i class="fa-solid fa-search"></i> Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="?ticket_date=<?= urlencode($current_date) ?>" class="btn-clear-search"><i class="fa-solid fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <form action="logout.php" method="post">
                <button type="submit" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
            </form>
        </div>

        <?php if (!empty($search_query)): ?>
            <div class="search-info">
                <i class="fa-solid fa-info-circle"></i>
                Showing search results for "<strong><?= htmlspecialchars($search_query) ?></strong>" - Found <?= count($tickets) ?> ticket(s)
            </div>
        <?php endif; ?>

        <div class="ticket-layout">
            <div class="ticket-table-wrapper">
                <?php if (!empty($tickets)): ?>
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Service</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Counter</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['ticket_number'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($t['service']) ?></td>
                                <td>
                                    <?php if (($t['priority'] ?? 0) == 1): ?>
                                        <span class="priority-badge priority-yes"><i class="fa-solid fa-star"></i> Priority</span>
                                    <?php else: ?>
                                        <span class="priority-badge priority-no"><i class="fa-solid fa-minus"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $status = strtolower($t['status']); 
                                        $class = "status status-" . $status;
                                    ?>
                                    <span class="<?= $class ?>"><?= htmlspecialchars($t['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($t['counter'] ?? 'Not Assigned') ?></td>
                                <td><?= htmlspecialchars($t['ern'] ?? '-') ?></td>
                                <td class="actions-cell">
                                    <?php if ($status === 'waiting'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="get_ticket_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-call"><i class="fa-solid fa-bullhorn"></i> Call</button>
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
                        <?php if (!empty($search_query)): ?>
                            <p>No tickets found matching "<?= htmlspecialchars($search_query) ?>"</p>
                            <p><a href="?ticket_date=<?= urlencode($current_date) ?>">Clear search to view all tickets</a></p>
                        <?php else: ?>
                            <p>No tickets found for this counter on <?= htmlspecialchars($current_date) ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stats-panel">
                <h3><a href="stats.php" style="text-decoration:none; color:inherit;"><i class="fa-solid fa-chart-pie"></i> Total</a></h3>
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
                    <option value="Legal Assistance">Legal Assistance</option>
                    <option value="Balik Manggagawa">Balik Manggagawa</option>
                    <option value="Direct Hire">Direct Hire</option>
                    <option value="Infosheet">Infosheet</option>
                    <option value="E Registration / OEC Assistance">E Registration / OEC Assistance</option>
                    <option value="Government to Government">Government to Government</option>
                    <option value="Welfare">Welfare</option>
                    <option value="Shipment of Remains">Shipment of Remains</option>
                    <option value="Livelihood">Livelihood</option>
                    <option value="Cashier">Cashier</option>
                    <option value="OWWA">OWWA</option>
                    <option value="PAG IBIG">PAG IBIG</option>
                </select>
                <button type="submit" class="btn-primary">Transfer Ticket</button>
            </form>
        </div>
    </div>

    <div class="footer">© Department of Migrant Workers</div>

    <script>
        function promptERN(ticketId) {
            const ern = prompt("Please enter remarks:");
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

        // OPTIMIZED Database Update Checker with proper cleanup
        class DatabaseUpdateChecker {
            constructor(options = {}) {
                this.apiEndpoint = options.apiEndpoint || 'check_updates.php';
                this.interval = options.interval || 3000;
                this.lastUpdateTimestamp = null;
                this.isChecking = false;
                this.timerId = null;
                this.abortController = null;
            }

            start() {
                console.log('Auto-refresh started');
                this.checkForUpdates();
                this.timerId = setInterval(() => this.checkForUpdates(), this.interval);
            }

            stop() {
                if (this.timerId) {
                    clearInterval(this.timerId);
                    this.timerId = null;
                }
                if (this.abortController) {
                    this.abortController.abort();
                }
                console.log('Auto-refresh stopped');
            }

            async checkForUpdates() {
                if (this.isChecking) return;
                
                this.isChecking = true;
                this.abortController = new AbortController();
                
                try {
                    const response = await fetch(this.apiEndpoint, {
                        signal: this.abortController.signal,
                        cache: 'no-cache'
                    });
                    
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    const data = await response.json();
                    
                    if (this.lastUpdateTimestamp === null) {
                        this.lastUpdateTimestamp = data.lastUpdate;
                    } else if (data.lastUpdate !== this.lastUpdateTimestamp) {
                        console.log('Update detected, refreshing...');
                        this.stop();
                        window.location.reload();
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Update check error:', error);
                    }
                } finally {
                    this.isChecking = false;
                    this.abortController = null;
                }
            }
        }

        // Initialize
        let updateChecker;
        document.addEventListener('DOMContentLoaded', function() {
            updateChecker = new DatabaseUpdateChecker({ interval: 3000 });
            updateChecker.start();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (updateChecker) updateChecker.stop();
        });
    </script>
</body>
</html>