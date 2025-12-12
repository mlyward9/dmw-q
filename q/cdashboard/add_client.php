<?php
require_once __DIR__ . '/../config/conn.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service'];
    $ticket_number = $_POST['ticket_number'];
    $counter = $_POST['counter'];
    $status = $_POST['status'];
    $priority = isset($_POST['priority']) ? 1 : 0;
    $region = $_POST['region'];
    $province = $_POST['province'];
    $gender = $_POST['gender'];
    $time_added = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO queue (service, ticket_number, time_added, counter, status, priority, region, province, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisisss", $service, $ticket_number, $time_added, $counter, $status, $priority, $region, $province, $gender);

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
        <label for="region"><i class="fa-solid fa-map"></i> Region:</label>
        <select name="region" id="region" required>
            <option value="Calabarzon">IV-A - Calabarzon</option>
            <option value="Others">Others</option>
        </select>

        <label for="province"><i class="fa-solid fa-location-dot"></i> Province:</label>
        <select name="province" id="province" required>
            <option value="">-- Select Province --</option>
            <option value="Batangas">Batangas</option>
            <option value="Cavite">Cavite</option>
            <option value="Laguna">Laguna</option>
            <option value="Quezon">Quezon</option>
            <option value="Rizal">Rizal</option>
        </select>

        <label for="gender"><i class="fa-solid fa-venus-mars"></i> Gender:</label>
        <select name="gender" id="gender" required>
            <option value="">-- Select Gender --</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
        </select>

        <label for="service"><i class="fa-solid fa-briefcase"></i> Service:</label>
        <select name="service" id="service" required>
            <option value="">-- Select Service --</option>
            <option>Legal Assistance</option>
            <option>Balik Manggagawa</option>
            <option>Direct Hire</option>
            <option>Infosheet</option>
            <option>E Registration / OEC Assistance</option>
            <option>Government to Government</option>
            <option>Welfare</option>
            <option>Shipment of Remains</option>
            <option>Livelihood</option>
            <option>Cashier</option>
            <option>OWWA</option>
            <option>PAG IBIG</option>
        </select>

        <label for="ticket_number"><i class="fa-solid fa-ticket"></i> Ticket Number:</label>
        <input type="text" name="ticket_number" id="ticket_number" value="<?php echo $next_ticket_number; ?>" readonly required>

        <div class="priority-container">
            <input type="checkbox" name="priority" id="priority" value="1">
            <label for="priority" class="priority-label">
                <i class="fa-solid fa-star"></i> Priority Client (PWD, Senior Citizen, Pregnant)
            </label>
        </div>

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

    <script>
        const regionProvinces = {
            "Calabarzon": ["Batangas", "Cavite", "Laguna", "Quezon", "Rizal"],
            "Others": ["Others"]
        };

        const regionSelect = document.getElementById('region');
        const provinceSelect = document.getElementById('province');

        regionSelect.addEventListener('change', function() {
            const selectedRegion = this.value;
            provinceSelect.innerHTML = '<option value="">-- Select Province --</option>';
            
            if (selectedRegion && regionProvinces[selectedRegion]) {
                regionProvinces[selectedRegion].forEach(province => {
                    const option = document.createElement('option');
                    option.value = province;
                    option.textContent = province;
                    provinceSelect.appendChild(option);
                });
            }
        });

        // Set default provinces for Calabarzon on page load
        window.addEventListener('DOMContentLoaded', function() {
            const defaultRegion = regionSelect.value;
            if (defaultRegion && regionProvinces[defaultRegion]) {
                provinceSelect.innerHTML = '<option value="">-- Select Province --</option>';
                regionProvinces[defaultRegion].forEach(province => {
                    const option = document.createElement('option');
                    option.value = province;
                    option.textContent = province;
                    provinceSelect.appendChild(option);
                });
            }
        });
    </script>
</body>
</html>