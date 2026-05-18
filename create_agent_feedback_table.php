<?php
/**
 * Create Agent Feedback Table
 * Adds a table to store client feedback ratings for agents
 */

require_once 'config.php';

// Create agent_feedback table
$sql = "CREATE TABLE IF NOT EXISTS agent_feedback (
    id SERIAL PRIMARY KEY,
    agent_id INT REFERENCES users(id) ON DELETE CASCADE,
    client_id INT REFERENCES users(id) ON DELETE CASCADE,
    consultation_id INT REFERENCES consultations(id) ON DELETE CASCADE,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 10),
    feedback_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_id (agent_id),
    INDEX idx_client_id (client_id),
    INDEX idx_consultation_id (consultation_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Agent feedback table created successfully!\n";
} else {
    echo "❌ Error creating agent feedback table: " . $conn->error . "\n";
}

$conn->close();
?>