<?php
// Include the database connection
require_once __DIR__ . '/../config/conn.php';

// Define all counters
$counters = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];

// Get current date
$current_date = date('Y-m-d');

// Function to get services for each counter
function getServicesForCounter($counter) {
    if (in_array($counter, [1, 2], true)) {
        return ["Legal Assistance"];
    } elseif (in_array($counter, [3, 4, 5, 6, 7], true)) {
        return [
            "Balik Manggagawa",
            "Direct Hire",
            "Infosheet",
            "E Registration / OEC Assistance",
            "Government to Government"
        ];
    } elseif (in_array($counter, [8, 9, 10], true)) {
        return [
            "Welfare",
            "Shipment of Remains",
            "Livelihood"
        ];
    } elseif ($counter == 11) {
        return ["Cashier"];
    } elseif ($counter == 12) {
        return ["OWWA"];
    } elseif ($counter == 13) {
        return ["PAG IBIG"];
    }
    return []; // pacd or other counters with no specific services
}

// Helper to bind params (mysqli requires references)
function bindParamsByRef($stmt, $types, $params) {
    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        // create references
        $bind_names[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// Get ticket data for all counters
$counter_data = [];
$all_waiting_tickets = []; // Track all waiting tickets to avoid duplicates
$waiting_priority = []; // Track priority status for waiting tickets

foreach ($counters as $counter) {
    $counter_data[$counter] = [
        'called' => [],
        'waiting' => [],
        'priority' => [] // Add priority tracking
    ];

    // Get services for this counter
    $services = getServicesForCounter($counter);

    if (!empty($services)) {
        // Query for tickets with specific services AND current date - INCLUDE priority column
        $placeholders = implode(',', array_fill(0, count($services), '?'));
        $sql = "SELECT ticket_number, status, counter, priority FROM queue WHERE service IN ($placeholders) AND DATE(time_added) = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            continue;
        }
        $types = str_repeat('s', count($services)) . 's';
        $params = array_merge($services, [$current_date]);
        bindParamsByRef($stmt, $types, $params);
    } else {
        // For pacd or counters without specific services - INCLUDE priority column
        $sql = "SELECT ticket_number, status, counter, priority FROM queue WHERE counter = ? AND DATE(time_added) = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            continue;
        }
        // ensure counter passed as string
        $c = (string)$counter;
        bindParamsByRef($stmt, "ss", [$c, $current_date]);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
        $ticket_counter = $row['counter'];
        $ticket_number = $row['ticket_number'];
        $is_priority = isset($row['priority']) ? (bool)$row['priority'] : false;

        // For called tickets, only show if assigned to this counter
        if ($status === 'called' && $ticket_counter == $counter) {
            $counter_data[$counter]['called'][] = $ticket_number;
            $counter_data[$counter]['priority'][$ticket_number] = $is_priority;
        }
        // For waiting tickets, add to global waiting list only once
        elseif ($status === 'waiting' && !in_array($ticket_number, $all_waiting_tickets, true)) {
            $all_waiting_tickets[] = $ticket_number;
            $counter_data[$counter]['waiting'][] = $ticket_number;
            $waiting_priority[$ticket_number] = $is_priority;
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
</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="../img/logo.png" alt="Department Logo" style="height:60px; margin-right:15px;">
            <h1 style="margin:0;">Department of Migrant Workers Region IV-A</h1>
        </div>
        <div class="header-right">
            <span id="last-update"><?= date('h:i A') ?></span>
        </div>
    </div>

<div class="main-container">
      <!-- CALLED NUMBERS SECTION -->
      <div class="called-section">
            <div class="section-title called-title">CURRENTLY SERVING</div>
            
            <?php
            // Check if any counters have called tickets
            $hasCalledTickets = false;
            foreach ($counters as $counter) {
                 if (!empty($counter_data[$counter]['called'])) {
                      $hasCalledTickets = true;
                      break;
                 }
            }
            ?>

            <?php if ($hasCalledTickets): ?>
                 <?php
                 // Collect all called counters
                 $calledCounters = [];
                 foreach ($counters as $counter) {
                      if (!empty($counter_data[$counter]['called'])) {
                            $calledCounters[] = $counter;
                      }
                 }
                 
                 // Split into two columns
                 $totalCalled = count($calledCounters);
                 $midPoint = ceil($totalCalled / 2);
                 $column1 = array_slice($calledCounters, 0, $midPoint);
                 $column2 = array_slice($calledCounters, $midPoint);
                 ?>
                 
                 <div class="called-grid-wrapper">
                      <!-- Left Column -->
                      <div class="called-list-container">
                            <?php foreach ($column1 as $counter): ?>
                                 <div class="counter-row">
                                      <div class="counter-label">Counter <?= htmlspecialchars((string)$counter) ?></div>
                                      <div class="counter-number">
                                            <?php foreach ($counter_data[$counter]['called'] as $ticket): ?>
                                                 <div class="ticket-wrapper">
                                                      <span class="ticket-display"><?= htmlspecialchars($ticket) ?></span>
                                                      <?php if (isset($counter_data[$counter]['priority'][$ticket]) && $counter_data[$counter]['priority'][$ticket]): ?>
                                                            <span class="priority-badge-small">PRIORITY</span>
                                                      <?php endif; ?>
                                                 </div>
                                            <?php endforeach; ?>
                                      </div>
                                 </div>
                            <?php endforeach; ?>
                      </div>
                      
                      <!-- Right Column -->
                      <div class="called-list-container">
                            <?php foreach ($column2 as $counter): ?>
                                 <div class="counter-row">
                                      <div class="counter-label">Counter <?= htmlspecialchars((string)$counter) ?></div>
                                      <div class="counter-number">
                                            <?php foreach ($counter_data[$counter]['called'] as $ticket): ?>
                                                 <div class="ticket-wrapper">
                                                      <span class="ticket-display"><?= htmlspecialchars($ticket) ?></span>
                                                      <?php if (isset($counter_data[$counter]['priority'][$ticket]) && $counter_data[$counter]['priority'][$ticket]): ?>
                                                            <span class="priority-badge-small">PRIORITY</span>
                                                      <?php endif; ?>
                                                 </div>
                                            <?php endforeach; ?>
                                      </div>
                                 </div>
                            <?php endforeach; ?>
                      </div>
                 </div>
            <?php else: ?>
                 <div style="text-align: center; color: #7f8c8d; font-size: 20px; padding: 40px;">
                      <p>No tickets currently being served</p>
                 </div>
            <?php endif; ?>
      </div>

        <!-- WAITING NUMBERS SECTION -->
        <div class="waiting-section">
            <div class="section-title waiting-title">WAITING IN QUEUE</div>
            <div class="waiting-numbers-container">
                <?php if (!empty($all_waiting_tickets)): ?>
                    <?php
                    // Sort the waiting numbers for better display
                    sort($all_waiting_tickets, SORT_NATURAL);

                    // Split tickets into two columns
                    $total_tickets = count($all_waiting_tickets);
                    $mid_point = ceil($total_tickets / 2);
                    $column1 = array_slice($all_waiting_tickets, 0, $mid_point);
                    $column2 = array_slice($all_waiting_tickets, $mid_point);
                    ?>

                    <div class="waiting-numbers-grid">
                        <div class="waiting-column">
                            <div class="ticket-numbers">
                                <?php foreach ($column1 as $ticket): ?>
                                    <div class="ticket-wrapper">
                                        <span class="ticket-number waiting"><?= htmlspecialchars($ticket) ?></span>
                                        <?php if (isset($waiting_priority[$ticket]) && $waiting_priority[$ticket]): ?>
                                            <span class="priority-label">PRIORITY</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="waiting-column">
                            <div class="ticket-numbers">
                                <?php foreach ($column2 as $ticket): ?>
                                    <div class="ticket-wrapper">
                                        <span class="ticket-number waiting"><?= htmlspecialchars($ticket) ?></span>
                                        <?php if (isset($waiting_priority[$ticket]) && $waiting_priority[$ticket]): ?>
                                            <span class="priority-label">PRIORITY</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="all-waiting-box">
                        <div class="no-tickets" style="text-align: center; font-size: 20px; padding: 40px;">
                            No tickets waiting
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden audio element for preloading -->
    <audio id="notificationSound" preload="auto">
        <source src="notification.mp3" type="audio/mpeg">
        <source src="../audio/notification.mp3" type="audio/mpeg">
        <source src="notification.wav" type="audio/wav">
    </audio>

    <script>
       // Update the timestamp when content is refreshed
function updateTimestamp() {
    const now = new Date();
    let hour = now.getHours();
    let minute = now.getMinutes();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    hour = hour ? hour : 12; // the hour '0' should be '12'
    minute = minute < 10 ? '0' + minute : minute;
    const timeString = hour + ':' + minute + ' ' + ampm;
    const timestampElement = document.getElementById('last-update');
    if (timestampElement) {
        timestampElement.textContent = timeString;
    }
}

// Audio management
let audioElement = null;
let audioReady = false;

// Initialize audio on page load
function initializeAudio() {
    audioElement = document.getElementById('notificationSound');
    
    if (audioElement) {
        audioElement.volume = 1.0;
        
        // Mark audio as ready when it can play
        audioElement.addEventListener('canplaythrough', () => {
            audioReady = true;
            console.log('Audio ready to play');
        });
        
        audioElement.addEventListener('error', (e) => {
            console.warn('Audio loading error:', e);
            audioReady = false;
        });
        
        // Try to load the audio
        audioElement.load();
    }
}

// Queue system for notifications
const notificationQueue = [];
let isProcessingNotification = false;
let userInteracted = false;

// Enable audio on first user interaction (required by browsers)
function enableAudioOnInteraction() {
    if (!userInteracted) {
        userInteracted = true;
        console.log('User interaction detected, audio enabled');
        
        // Try to play and immediately pause to "unlock" audio
        if (audioElement) {
            audioElement.play().then(() => {
                audioElement.pause();
                audioElement.currentTime = 0;
                audioReady = true;
            }).catch(err => {
                console.warn('Audio unlock failed:', err);
            });
        }
    }
}

// Add event listeners for user interaction
document.addEventListener('click', enableAudioOnInteraction, { once: true });
document.addEventListener('keydown', enableAudioOnInteraction, { once: true });
document.addEventListener('touchstart', enableAudioOnInteraction, { once: true });

// Play notification audio
function playNotificationSound() {
    return new Promise((resolve) => {
        if (!audioElement || !audioReady) {
            console.warn('Audio not ready, skipping sound');
            resolve();
            return;
        }

        // Reset audio to start
        audioElement.currentTime = 0;
        
        const playPromise = audioElement.play();
        
        if (playPromise !== undefined) {
            playPromise
                .then(() => {
                    console.log('Audio playing successfully');
                    audioElement.onended = () => {
                        resolve();
                    };
                })
                .catch((error) => {
                    console.warn('Audio play failed:', error);
                    resolve(); // Continue even if audio fails
                });
        } else {
            // Fallback for older browsers
            audioElement.onended = () => {
                resolve();
            };
        }

        // Safety timeout in case onended doesn't fire
        setTimeout(resolve, 3000);
    });
}

// Text-to-speech function
function speakNotification(ticketNumber, counter) {
    return new Promise((resolve) => {
        if (!('speechSynthesis' in window)) {
            console.log('Text-to-speech not supported');
            resolve();
            return;
        }

        // Prepare utterances
        const part1 = new SpeechSynthesisUtterance(`Now serving ticket number ${ticketNumber} At counter ${counter}.`);

        part1.rate = 0.9;
        part1.pitch = 0.9;
        part1.volume = 1;
        part1.lang = 'en-US';

        part1.onend = () => {
            resolve();
        };

        part1.onerror = (error) => {
            console.warn('Speech synthesis error:', error);
            resolve();
        };

        window.speechSynthesis.speak(part1);
        console.log('Speaking:', `Now serving ticket ${ticketNumber} at counter ${counter}`);
    });
}

// Process notification: play sound then speak
async function processNotification(ticketNumber, counter) {
    try {
        // Play notification sound first
        await playNotificationSound();
        
        // Small delay between sound and speech
        await new Promise(resolve => setTimeout(resolve, 200));
        
        // Then speak the notification
        await speakNotification(ticketNumber, counter);
    } catch (error) {
        console.error('Error processing notification:', error);
    }
}

// Process notifications from queue one by one
async function processNotificationQueue() {
    if (isProcessingNotification || notificationQueue.length === 0) {
        return;
    }

    isProcessingNotification = true;

    while (notificationQueue.length > 0) {
        const notification = notificationQueue.shift(); // FIFO: get first item
        
        // Cancel any ongoing speech before starting new one
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        
        await processNotification(notification.ticketNumber, notification.counter);
        
        // Small delay between notifications
        await new Promise(resolve => setTimeout(resolve, 500));
    }

    isProcessingNotification = false;
}

// Add notification to queue
function queueNotification(ticketNumber, counter) {
    notificationQueue.push({ ticketNumber, counter });
    console.log(`Added to queue: Ticket ${ticketNumber} at Counter ${counter}. Queue length: ${notificationQueue.length}`);
    processNotificationQueue();
}

// Check for new notifications
function checkForNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNotification) {
                queueNotification(data.ticketNumber, data.counter);
            }
        })
        .catch(error => {
            console.log('Error checking notifications:', error);
        });
}

// Fetch updates every 1 second
function fetchUpdates() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(data => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data;
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

// Start automatic updates every 1 second
setInterval(fetchUpdates, 1000);

// Check for notifications every 1 second
setInterval(checkForNotifications, 1000);

function refreshNow() {
    fetchUpdates();
}

// Initial check on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTimestamp();
    initializeAudio();
    checkForNotifications();
});
    </script>
</body>
</html>