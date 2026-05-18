<?php
require_once 'config.php';

// Check current data for agent (user ID 5)
$user_id = 5;

echo "=== CHECKING AGENT DATA ===\n";

// Check user type
$user_stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();
echo "User Type: " . $user['user_type'] . "\n";

// Check properties
$properties_stmt = $conn->prepare("SELECT id, property_code, created_at FROM properties WHERE seller_id = ?");
$properties_stmt->bind_param("i", $user_id);
$properties_stmt->execute();
$properties_result = $properties_stmt->get_result();
$properties = [];
while ($row = $properties_result->fetch_assoc()) {
    $properties[] = $row;
}
$properties_stmt->close();
echo "Properties: " . count($properties) . "\n";
foreach ($properties as $prop) {
    echo "  - ID: {$prop['id']}, Code: {$prop['property_code']}, Created: {$prop['created_at']}\n";
}

// Check consultations
$consultations_stmt = $conn->prepare("SELECT id, user_id, status, created_at FROM consultations WHERE property_id IN (SELECT id FROM properties WHERE seller_id = ?)");
$consultations_stmt->bind_param("i", $user_id);
$consultations_stmt->execute();
$consultations_result = $consultations_stmt->get_result();
$consultations = [];
while ($row = $consultations_result->fetch_assoc()) {
    $consultations[] = $row;
}
$consultations_stmt->close();
echo "Consultations: " . count($consultations) . "\n";
foreach ($consultations as $cons) {
    echo "  - ID: {$cons['id']}, Client: {$cons['user_id']}, Status: {$cons['status']}, Created: {$cons['created_at']}\n";
}

// Check feedback
$feedback_stmt = $conn->prepare("SELECT id, rating, created_at FROM agent_feedback WHERE agent_id = ?");
$feedback_stmt->bind_param("i", $user_id);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();
$feedbacks = [];
while ($row = $feedback_result->fetch_assoc()) {
    $feedbacks[] = $row;
}
$feedback_stmt->close();
echo "Feedback: " . count($feedbacks) . "\n";
foreach ($feedbacks as $fb) {
    echo "  - ID: {$fb['id']}, Rating: {$fb['rating']}, Created: {$fb['created_at']}\n";
}

echo "\n=== CREATING SAMPLE DATA ===\n";

// Always create sample data for testing
if (count($properties) > 0) {
    $property_id = $properties[0]['id'];

    // Create 8 completed consultations (some recent, some old)
    for ($i = 1; $i <= 8; $i++) {
        $client_user_id = ($i <= 4) ? 6 : 8; // Use existing users as clients
        $status = 'completed';
        $days_ago = ($i <= 3) ? rand(1, 30) : rand(35, 90); // Some recent, some older

        $consultation_type = 'property_viewing';
        $email = 'client' . $i . '@example.com';
        $contact_number = '071234567' . $i;
        $issue_description = 'Interested in viewing this property - consultation ' . $i;

        $insert_consultation = $conn->prepare("INSERT INTO consultations (property_id, user_id, consultation_type, email, contact_number, issue_description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL $days_ago DAY))");

        $insert_consultation->bind_param("iisssss", $property_id, $client_user_id, $consultation_type, $email, $contact_number, $issue_description, $status);
        $insert_consultation->execute();
        $consultation_id = $conn->insert_id;
        $insert_consultation->close();

        echo "Created consultation ID: $consultation_id ($days_ago days ago)\n";

        // Add feedback for this consultation
        $rating = rand(7, 10);
        $feedback_text = 'Great service from the agent! Very professional and helpful.';

        $insert_feedback = $conn->prepare("INSERT INTO agent_feedback (agent_id, client_id, consultation_id, rating, feedback_text, created_at) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL $days_ago DAY))");
        $insert_feedback->bind_param("iiiis", $user_id, $client_user_id, $consultation_id, $rating, $feedback_text);
        $insert_feedback->execute();
        $insert_feedback->close();

        echo "Added feedback rating: $rating\n";
    }
}

$conn->close();
?>