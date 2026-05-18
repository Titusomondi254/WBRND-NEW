<?php
/**
 * Verify viewing_requests table schema and data
 */

require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    echo "<h1>Viewing Requests Table Verification</h1>";

    // 1. Check if table exists
    echo "<h2>1. Table Structure</h2>";
    $result = $conn->query("DESCRIBE viewing_requests");
    
    if (!$result) {
        echo "<p style='color: red;'><strong>ERROR: viewing_requests table does not exist!</strong></p>";
        exit;
    }

    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 2rem;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th style='padding: 10px;'>Field</th>";
    echo "<th style='padding: 10px;'>Type</th>";
    echo "<th style='padding: 10px;'>Null</th>";
    echo "<th style='padding: 10px;'>Key</th>";
    echo "<th style='padding: 10px;'>Default</th>";
    echo "<th style='padding: 10px;'>Extra</th>";
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($row['Field'] ?? '') . "</strong></td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['Type'] ?? '') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['Null'] ?? '') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['Key'] ?? '') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['Default'] ?? '') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Count viewing requests
    echo "<h2>2. Data Count</h2>";
    $count_result = $conn->query("SELECT COUNT(*) as total FROM viewing_requests");
    $count_row = $count_result->fetch_assoc();
    echo "<p><strong>Total viewing requests:</strong> " . $count_row['total'] . "</p>";

    // 3. Count by status
    echo "<h2>3. Requests by Status</h2>";
    $status_result = $conn->query("SELECT status, COUNT(*) as count FROM viewing_requests GROUP BY status ORDER BY count DESC");
    
    if ($status_result->num_rows > 0) {
        echo "<ul>";
        while ($status_row = $status_result->fetch_assoc()) {
            echo "<li><strong>" . htmlspecialchars($status_row['status'] ?? '') . ":</strong> " . $status_row['count'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>No viewing requests found.</p>";
    }

    // 4. Sample viewing requests (last 5)
    echo "<h2>4. Recent Viewing Requests (Last 5)</h2>";
    $sample_result = $conn->query("
        SELECT 
            vr.id, vr.property_id, vr.user_id, vr.status, vr.requested_date, vr.requested_time,
            p.property_code, u.first_name, u.last_name, u.email
        FROM viewing_requests vr
        LEFT JOIN properties p ON vr.property_id = p.id
        LEFT JOIN users u ON vr.user_id = u.id
        ORDER BY vr.created_at DESC
        LIMIT 5
    ");

    if ($sample_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 2rem;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Property</th>";
        echo "<th style='padding: 10px;'>Client</th>";
        echo "<th style='padding: 10px;'>Email</th>";
        echo "<th style='padding: 10px;'>Date</th>";
        echo "<th style='padding: 10px;'>Time</th>";
        echo "<th style='padding: 10px;'>Status</th>";
        echo "</tr>";

        while ($sample_row = $sample_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $sample_row['id'] . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($sample_row['property_code'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars(($sample_row['first_name'] ?? '') . ' ' . ($sample_row['last_name'] ?? '')) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($sample_row['email'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($sample_row['requested_date'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($sample_row['requested_time'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($sample_row['status'] ?? '') . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No viewing requests found in database.</p>";
    }

    // 5. Test admin query
    echo "<h2>5. Test Admin Query (Status: pending)</h2>";
    $test_query = "
        SELECT
            vr.id, vr.property_id, vr.user_id, vr.agent_id,
            vr.viewing_fee, vr.fee_paid, vr.payment_reference,
            vr.requested_date, vr.requested_time,
            vr.scheduled_date, vr.scheduled_time,
            vr.status, vr.client_notes, vr.admin_notes,
            vr.terms_accepted, vr.created_at,
            p.property_code, p.location, p.bedrooms, p.price,
            u.first_name, u.last_name, u.email, u.phone,
            a.first_name as agent_first_name, a.last_name as agent_last_name,
            a.email as agent_email, a.phone as agent_phone
        FROM viewing_requests vr
        LEFT JOIN properties p ON vr.property_id = p.id
        LEFT JOIN users u ON vr.user_id = u.id
        LEFT JOIN users a ON vr.agent_id = a.id
        WHERE vr.status = 'pending'
        ORDER BY vr.created_at DESC
        LIMIT 5
    ";
    
    $test_result = $conn->query($test_query);
    
    if ($test_result->num_rows > 0) {
        echo "<p style='color: green;'><strong>✓ Admin query returns " . $test_result->num_rows . " pending request(s)</strong></p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 2rem; font-size: 0.9em;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Property</th>";
        echo "<th style='padding: 8px;'>Client</th>";
        echo "<th style='padding: 8px;'>Date</th>";
        echo "<th style='padding: 8px;'>Fee</th>";
        echo "<th style='padding: 8px;'>Paid</th>";
        echo "<th style='padding: 8px;'>Status</th>";
        echo "</tr>";

        while ($test_row = $test_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $test_row['id'] . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($test_row['property_code'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars(($test_row['first_name'] ?? '') . ' ' . ($test_row['last_name'] ?? '')) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($test_row['requested_date'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>KES " . number_format($test_row['viewing_fee']) . "</td>";
            echo "<td style='padding: 8px;'>" . ($test_row['fee_paid'] ? '✓ Yes' : '✗ No') . "</td>";
            echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($test_row['status'] ?? '') . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>No pending viewing requests found.</strong></p>";
        echo "<p>This is expected if no clients have submitted viewing requests yet.</p>";
    }

    $conn->close();

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
