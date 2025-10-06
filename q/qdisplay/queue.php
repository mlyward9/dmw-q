<?php
// Include the database connection
require_once __DIR__ . '/../config/conn.php';

// Define all counters
$counters = ['pacd', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// Get current date
$current_date = date('Y-m-d');

// Function to get services for each counter
function getServicesForCounter($counter) {
    if (in_array($counter, [1, 2, 3])) {
        return ["Legal Assistance"];
    } elseif (in_array($counter, [4, 5, 6, 7])) {
        return [
            "Balik Manggagawa",
            "Direct Hire", 
            "Infosheet",
            "E Registration",
            "OEC Assistance",
            "Government to Government"
        ];
    } elseif (in_array($counter, [8, 9, 10])) {
        return [
            "Financial Assistance",
            "Shipment of Remains",
            "Livelihood"
        ];
    }
    return []; // pacd or other counters with no specific services
}

// Get ticket data for all counters
$counter_data = [];
$all_waiting_tickets = []; // Track all waiting tickets to avoid duplicates

foreach ($counters as $counter) {
    $counter_data[$counter] = [
        'called' => [],
        'waiting' => []
    ];
    
    // Get services for this counter
    $services = getServicesForCounter($counter);
    
    if (!empty($services)) {
        // Query for tickets with specific services AND current date
        $placeholders = implode(',', array_fill(0, count($services), '?'));
        $sql = "SELECT ticket_number, status, counter FROM queue WHERE service IN ($placeholders) AND DATE(time_added) = ?";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($services)) . 's';
        $params = array_merge($services, [$current_date]);
        $stmt->bind_param($types, ...$params);
    } else {
        // For pacd or counters without specific services, get all tickets assigned to this counter AND current date
        $sql = "SELECT ticket_number, status, counter FROM queue WHERE counter = ? AND DATE(time_added) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $counter, $current_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
        $ticket_counter = $row['counter'];
        $ticket_number = $row['ticket_number'];
        
        // For called tickets, only show if assigned to this counter
        if ($status === 'called' && $ticket_counter == $counter) {
            $counter_data[$counter]['called'][] = $ticket_number;
        }
        // For waiting tickets, add to global waiting list only once
        elseif ($status === 'waiting' && !in_array($ticket_number, $all_waiting_tickets)) {
            $all_waiting_tickets[] = $ticket_number;
            $counter_data[$counter]['waiting'][] = $ticket_number;
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counter Display System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script>
        // Fetch updates every 3 seconds
        function fetchUpdates() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(data => {
                    // Create a temporary DOM element to parse the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    
                    // Extract the main container content
                    const newMainContainer = tempDiv.querySelector('.main-container');
                    const currentMainContainer = document.querySelector('.main-container');
                    
                    if (newMainContainer && currentMainContainer) {
                        currentMainContainer.innerHTML = newMainContainer.innerHTML;
                    }
                })
                .catch(error => {
                    console.log('Error fetching updates:', error);
                });
        }
        
        // Start automatic updates every 3 seconds
        setInterval(fetchUpdates, 3000);
        
        function refreshNow() {
            fetchUpdates();
        }
    </script>
</head>
<body>
    <div class="header">
        <img src="../img/logo.png" alt="Department Logo" style="height:60px; vertical-align:middle; margin-right:15px;">
        <h1 style="display:inline-block; vertical-align:middle; margin:0;">Department of Migrant Workers</h1>
    </div>
    
    <div class="main-container">
        <!-- CALLED NUMBERS SECTION -->
        <div class="called-section">
            <div class="section-title called-title">CURRENTLY SERVING</div>
            <div class="counter-grid">
                <?php foreach ($counters as $counter): ?>
                    <?php if (!empty($counter_data[$counter]['called'])): ?>
                    <div class="counter-box">
                        <div class="counter-title">
                            Counter <?= ($counter === 'pacd') ? 'PACD' : htmlspecialchars($counter) ?>
                        </div>
                        <div class="ticket-numbers">
                            <?php foreach ($counter_data[$counter]['called'] as $ticket): ?>
                                <span class="ticket-number called"><?= htmlspecialchars($ticket) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php 
                // Check if no counters have called tickets
                $hasCalledTickets = false;
                foreach ($counters as $counter) {
                    if (!empty($counter_data[$counter]['called'])) {
                        $hasCalledTickets = true;
                        break;
                    }
                }
                if (!$hasCalledTickets): 
                ?>
                <div style="text-align: center; color: #7f8c8d; font-size: 20px; padding: 40px; grid-column: 1/-1;">
                    <p>No tickets currently being served</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- WAITING NUMBERS SECTION -->
        <div class="waiting-section">
            <div class="section-title waiting-title">WAITING IN QUEUE</div>
            <div class="waiting-numbers-container">
                <div class="all-waiting-box">
                    <?php if (!empty($all_waiting_tickets)): ?>
                        <div class="ticket-numbers">
                            <?php 
                            // Sort the waiting numbers for better display
                            sort($all_waiting_tickets, SORT_NATURAL);
                            foreach ($all_waiting_tickets as $ticket): 
                            ?>
                                <span class="ticket-number waiting"><?= htmlspecialchars($ticket) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-tickets" style="text-align: center; font-size: 20px; padding: 40px;">
                            No tickets waiting
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 15px; color: #7f8c8d;">
        
        <p><small>Last updated: <span id="last-update"><?= date('H:i:s') ?></span></small></p>
    </div>
    
    <script>
        // Update the timestamp when content is refreshed
        function updateTimestamp() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-GB', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit', 
                second: '2-digit'
            });
            const timestampElement = document.getElementById('last-update');
            if (timestampElement) {
                timestampElement.textContent = timeString;
            }
        }
        
        // Fetch updates every 3 seconds
        function fetchUpdates() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(data => {
                    // Create a temporary DOM element to parse the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    
                    // Extract the main container content
                    const newMainContainer = tempDiv.querySelector('.main-container');
                    const currentMainContainer = document.querySelector('.main-container');
                    
                    if (newMainContainer && currentMainContainer) {
                        currentMainContainer.innerHTML = newMainContainer.innerHTML;
                        updateTimestamp();
                    }
                })
                .catch(error => {
                    console.log('Error fetching updates:', error);
                });
        }
        
        // Start automatic updates every 3 seconds
        setInterval(fetchUpdates, 2000);
        
        function refreshNow() {
            fetchUpdates();
        }
    </script>
</body>
</html>