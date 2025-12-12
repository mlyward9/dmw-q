
<?php
// Include database connection
require_once '../config/conn.php';

// Date range filter (default: last 30 days)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Debug: Check if there's any data in service_logs
$debug_sql = "SELECT COUNT(*) as total FROM service_logs";
$debug_result = $conn->query($debug_sql);
$debug_row = $debug_result->fetch_assoc();
$total_records = $debug_row['total'];

// Statistics by Service
$sql = "SELECT 
    service,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration,
    MIN(time_duration) as min_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
GROUP BY service
ORDER BY total_transactions DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$service_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics by Counter
$sql = "SELECT 
    counter,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
GROUP BY counter
ORDER BY total_transactions DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$counter_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics by Staff Member
$sql = "SELECT 
    served_by_username,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration,
    MIN(time_duration) as min_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
GROUP BY served_by_username
ORDER BY total_transactions DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$staff_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Daily Transaction Volume
$sql = "SELECT 
    DATE(time_added) as date,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
GROUP BY DATE(time_added)
ORDER BY DATE(time_added) ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$daily_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Peak Hours Analysis
$sql = "SELECT 
    HOUR(time_added) as hour,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
GROUP BY HOUR(time_added)
ORDER BY hour";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$hourly_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics by Region
$sql = "SELECT 
    region,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration,
    COUNT(DISTINCT province) as unique_provinces
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
AND region IS NOT NULL
AND region != ''
GROUP BY region
ORDER BY total_transactions DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$region_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics by Province
$sql = "SELECT 
    province,
    region,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
AND province IS NOT NULL
AND province != ''
GROUP BY province, region
ORDER BY total_transactions DESC
LIMIT 20"; // Limit to top 20 provinces
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$province_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics by Gender
$sql = "SELECT 
    gender,
    COUNT(*) as total_transactions,
    AVG(time_duration) as avg_duration,
    COUNT(DISTINCT region) as unique_regions
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?
AND gender IS NOT NULL
AND gender != ''
GROUP BY gender
ORDER BY total_transactions DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$gender_stats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Overall Summary
$sql = "SELECT 
    COUNT(*) as total_transactions,
    AVG(time_duration) as overall_avg_duration,
    SUM(time_duration) as total_time_spent,
    COUNT(DISTINCT served_by_username) as unique_staff,
    COUNT(DISTINCT region) as unique_regions,
    COUNT(DISTINCT province) as unique_provinces
FROM service_logs
WHERE DATE(time_added) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary = $result->fetch_assoc();
$stmt->close();

function formatDuration($seconds) {
    if ($seconds === null || $seconds == 0) return '0s';

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf("%dh %dm %ds", $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf("%dm %ds", $minutes, $secs);
    } else {
        return sprintf("%ds", $secs);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Statistics Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: #856404;
        }
        
        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filter label {
            font-weight: 600;
        }
        
        .date-filter input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .date-filter button {
            padding: 8px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .date-filter button:hover {
            background: #0056b3;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .summary-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .summary-card .value {
            color: #007bff;
            font-size: 28px;
            font-weight: bold;
        }
        
        .chart-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 20px;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .stats-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .highlight {
            color: #007bff;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }

        .geo-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 12px;
            color: #6c757d;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="tickets.php" style="padding:8px 18px;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block;">
            ← Back
            </a>
        </div>
        <h1>📊 Service Statistics Dashboard</h1>
        
        <!-- Debug Info -->
        <div class="debug-info">
            <strong>Debug Info:</strong> Total records in service_logs: <?php echo $total_records; ?> | 
            Date Range: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?> | 
            Records in this range: <?php echo $summary['total_transactions'] ?? 0; ?>
        </div>
        
        <div class="date-filter">
            <form method="GET">
                <label>Start Date:</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                
                <label>End Date:</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                
                <button type="submit">Apply Filter</button>
                <button type="button" onclick="window.location.href='stats.php?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>'" style="background:#6c757d;">Reset to Last 30 Days</button>
            </form>
        </div>
        
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Transactions</h3>
                <div class="value"><?php echo number_format($summary['total_transactions'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <h3>Avg Duration</h3>
                <div class="value"><?php echo formatDuration($summary['overall_avg_duration'] ?? 0); ?></div>
            </div>
            
        </div>

        <?php if (count($daily_stats) > 0): ?>
        <div class="chart-section">
            <h2>📈 Daily Transaction Trend</h2>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-section">
            <h2>📈 Daily Transaction Trend</h2>
            <div class="no-data">No daily transaction data available for the selected date range</div>
        </div>
        <?php endif; ?>

        <div class="chart-grid">
            <?php if (count($service_stats) > 0): ?>
            <div class="chart-section">
                <h2>🔧 Transactions by Service</h2>
                <div class="chart-container">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>🔧 Transactions by Service</h2>
                <div class="no-data">No service data available</div>
            </div>
            <?php endif; ?>

            <?php if (count($hourly_stats) > 0): ?>
            <div class="chart-section">
                <h2>⏰ Peak Hours</h2>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>⏰ Peak Hours</h2>
                <div class="no-data">No hourly data available</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- NEW: Region and Province Charts -->
        <div class="chart-grid">
            <?php if (count($region_stats) > 0): ?>
            <div class="chart-section">
                <h2>🗺️ Transactions by Region</h2>
                <div class="chart-container">
                    <canvas id="regionChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>🗺️ Transactions by Region</h2>
                <div class="no-data">No region data available</div>
            </div>
            <?php endif; ?>

            <?php if (count($province_stats) > 0): ?>
            <div class="chart-section">
                <h2>🏙️ Top Provinces</h2>
                <div class="chart-container">
                    <canvas id="provinceChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>🏙️ Top Provinces</h2>
                <div class="no-data">No province data available</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="chart-grid">
            <?php if (count($counter_stats) > 0): ?>
            <div class="chart-section">
                <h2>🪟 Transactions by Counter</h2>
                <div class="chart-container">
                    <canvas id="counterChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>🪟 Transactions by Counter</h2>
                <div class="no-data">No counter data available</div>
            </div>
            <?php endif; ?>

            <?php if (count($staff_stats) > 0): ?>
            <div class="chart-section">
                <h2>👥 Staff Transactions</h2>
                <div class="chart-container">
                    <canvas id="staffChart"></canvas>
                </div>
            </div>
            <?php else: ?>
            <div class="chart-section">
                <h2>👥 Staff Performance</h2>
                <div class="no-data">No staff data available</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- NEW: Gender Chart -->
        <?php if (count($gender_stats) > 0): ?>
        <div class="chart-section">
            <h2>👥 Transactions by Gender</h2>
            <div class="chart-container">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-section">
            <h2>👥 Transactions by Gender</h2>
            <div class="no-data">No gender data available</div>
        </div>
        <?php endif; ?>

        <?php if (count($service_stats) > 0): ?>
        <div class="chart-section">
            <h2>⏱️ Average Duration by Service</h2>
            <div class="chart-container">
                <canvas id="durationChart"></canvas>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-section">
            <h2>⏱️ Average Duration by Service</h2>
            <div class="no-data">No duration data available</div>
        </div>
        <?php endif; ?>
        
        <!-- NEW: Region Statistics Table -->
        <div class="stats-section">
            <h2>🗺️ Detailed Statistics by Region</h2>
            <?php if (count($region_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Total Transactions</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($region_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['region']); ?></strong></td>
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No region data available for the selected date range.</div>
            <?php endif; ?>
        </div>

        <!-- NEW: Province Statistics Table -->
        <div class="stats-section">
            <h2>🏙️ Detailed Statistics by Province</h2>
            <?php if (count($province_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Province</th>
                        
                        <th>Total Transactions</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($province_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['province']); ?></strong></td>
                        
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No province data available for the selected date range.</div>
            <?php endif; ?>
        </div>

        <!-- NEW: Gender Statistics Table -->
        <div class="stats-section">
            <h2>👥 Detailed Statistics by Gender</h2>
            <?php if (count($gender_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Gender</th>
                        <th>Total Transactions</th>
                       
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gender_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['gender']); ?></strong></td>
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No gender data available for the selected date range.</div>
            <?php endif; ?>
        </div>
        
        <div class="stats-section">
            <h2>Detailed Statistics by Service</h2>
            <?php if (count($service_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Total Transactions</th>
                        <th>Avg Duration</th>
                        <th>Min Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['service']); ?></strong></td>
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        <td><?php echo formatDuration($row['avg_duration']); ?></td>
                        <td><?php echo formatDuration($row['min_duration']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No data available for the selected date range. Please ensure tickets have been completed and try adjusting the date range.</div>
            <?php endif; ?>
        </div>
        
        <div class="stats-section">
            <h2>Detailed Statistics by Counter</h2>
            <?php if (count($counter_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Counter</th>
                        <th>Total Transactions</th>
                        <th>Avg Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($counter_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['counter']); ?></strong></td>
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        <td><?php echo formatDuration($row['avg_duration']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No data available for the selected date range</div>
            <?php endif; ?>
        </div>
        
        <div class="stats-section">
            <h2>Detailed Statistics by Staff Member</h2>
            <?php if (count($staff_stats) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Staff Username</th>
                        <th>Total Transactions</th>
                        <th>Avg Duration</th>
                        <th>Min Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_stats as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['served_by_username']); ?></strong></td>
                        <td class="highlight"><?php echo number_format($row['total_transactions']); ?></td>
                        <td><?php echo formatDuration($row['avg_duration']); ?></td>
                        <td><?php echo formatDuration($row['min_duration']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">No data available for the selected date range</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Chart.js default configuration
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.plugins.legend.position = 'bottom';

        // Color palette
        const colors = [
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)',
            'rgba(255, 99, 255, 0.8)',
            'rgba(99, 255, 132, 0.8)'
        ];

        // Daily Trend Chart
        <?php if (count($daily_stats) > 0): ?>
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $daily_stats)); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($daily_stats, 'total_transactions')); ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // Service Pie Chart
        <?php if (count($service_stats) > 0): ?>
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        new Chart(serviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($service_stats, 'service')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($service_stats, 'total_transactions')); ?>,
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>

        // Hourly Bar Chart
        <?php if (count($hourly_stats) > 0): ?>
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($h) { return sprintf('%02d:00', $h['hour']); }, $hourly_stats)); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($hourly_stats, 'total_transactions')); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // NEW: Region Chart
        <?php if (count($region_stats) > 0): ?>
        const regionCtx = document.getElementById('regionChart').getContext('2d');
        new Chart(regionCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($region_stats, 'region')); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($region_stats, 'total_transactions')); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // NEW: Province Chart
        <?php if (count($province_stats) > 0): ?>
        const provinceCtx = document.getElementById('provinceChart').getContext('2d');
        new Chart(provinceCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($province_stats, 'province')); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($province_stats, 'total_transactions')); ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.8)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // NEW: Gender Chart
        <?php if (count($gender_stats) > 0): ?>
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($gender_stats, 'gender')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($gender_stats, 'total_transactions')); ?>,
                    backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)', 'rgba(255, 206, 86, 0.8)']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Counter Bar Chart
        <?php if (count($counter_stats) > 0): ?>
        const counterCtx = document.getElementById('counterChart').getContext('2d');
        new Chart(counterCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($counter_stats, 'counter')); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($counter_stats, 'total_transactions')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // Staff Bar Chart
        <?php if (count($staff_stats) > 0): ?>
        const staffCtx = document.getElementById('staffChart').getContext('2d');
        new Chart(staffCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($staff_stats, 'served_by_username')); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_column($staff_stats, 'total_transactions')); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.8)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

         // Duration Chart (show avg duration as minutes + seconds)
            <?php if (count($service_stats) > 0): ?>
            const durationCtx = document.getElementById('durationChart').getContext('2d');

            // helper to format seconds -> "Mm Ss" (used for ticks and tooltips)
            function formatSecondsToMMSS(sec) {
                sec = Math.round(Number(sec) || 0);
                var mins = Math.floor(sec / 60);
                var secs = sec % 60;
                if (mins > 0) {
                return mins + 'm ' + (secs < 10 ? '0' + secs : secs) + 's';
                }
                return secs + 's';
            }

            const durationValues = <?php echo json_encode(array_map('floatval', array_column($service_stats, 'avg_duration'))); ?>;
            new Chart(durationCtx, {
                type: 'bar',
                data: {
                labels: <?php echo json_encode(array_column($service_stats, 'service')); ?>,
                datasets: [{
                    label: 'Avg Duration (seconds)',
                    data: durationValues,
                    backgroundColor: 'rgba(255, 159, 64, 0.8)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
                },
                options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                    beginAtZero: true,
                    ticks: {
                        // show ticks in mm:ss
                        callback: function(value) {
                        return formatSecondsToMMSS(value);
                        }
                    }
                    }
                },
                plugins: {
                    legend: {
                    display: false
                    },
                    tooltip: {
                    callbacks: {
                        label: function(context) {
                        // for horizontal bar, value is in parsed.x (Chart.js v3+)
                        var v = (context.parsed && typeof context.parsed.x !== 'undefined') ? context.parsed.x : context.parsed;
                        return 'Avg: ' + formatSecondsToMMSS(v);
                        }
                    }
                    }
                }
                }
            });
            <?php endif; ?>
    </script>
</body>
</html>
