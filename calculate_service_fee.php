<?php
/**
 * SERVICE FEE CALCULATION ENGINE
 * Centralized fee calculation for property viewing requests
 * Provides both API endpoint and reusable function
 */

require_once 'config.php';

/**
 * Calculate service fee for a property
 * @param int $propertyId - Property ID
 * @return array - Calculation result with fee, tier, and details
 */
function calculateServiceFeeEngine($propertyId) {
    global $conn;
    
    // Validate input
    $propertyId = intval($propertyId);
    if ($propertyId <= 0) {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Invalid property ID',
            'debug' => 'Property ID must be positive integer'
        ];
    }
    
    // Fetch property
    $stmt = $conn->prepare("SELECT id, location, bedrooms, verification_status FROM properties WHERE id = ?");
    if (!$stmt) {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Database error',
            'debug' => $conn->error
        ];
    }
    
    $stmt->bind_param("i", $propertyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Property not found',
            'debug' => "No property with ID $propertyId"
        ];
    }
    
    $property = $result->fetch_assoc();
    
    // Check if property is verified
    if ($property['verification_status'] !== 'verified') {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Property not verified',
            'debug' => 'Only verified properties can accept viewing requests',
            'status' => $property['verification_status']
        ];
    }
    
    // Validate property data
    if (empty($property['location']) || is_null($property['location'])) {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Property location missing',
            'debug' => 'Property location is empty or NULL'
        ];
    }
    
    if (is_null($property['bedrooms']) || $property['bedrooms'] <= 0) {
        return [
            'success' => false,
            'fee' => 0,
            'error' => 'Property bedrooms invalid',
            'debug' => 'Bedrooms must be greater than 0',
            'bedrooms' => $property['bedrooms']
        ];
    }
    
    // Prepare for calculation
    $location = trim($property['location']);
    $bedrooms = intval($property['bedrooms']);
    
// Fee structure - tiered rates based on bedroom count
    if ($bedrooms <= 2) {
        $fee = 1000;
    } elseif ($bedrooms <= 4) {
        $fee = 1500;
    } else {
        $fee = 2000;
    }
    
    return [
        'success' => true,
        'fee' => $fee,
        'bedrooms' => $bedrooms,
        'location' => $location,
        'propertyId' => $propertyId
    ];
}

// API ENDPOINT: Returns fee calculation as JSON
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $propertyId = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
    
    if ($propertyId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'property_id parameter required',
            'example' => 'calculate_service_fee.php?api=1&property_id=38'
        ]);
        exit();
    }
    
    $result = calculateServiceFeeEngine($propertyId);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    exit();
}

// CLI TEST MODE: Test calculation from command line
if (php_sapi_name() === 'cli' && count($argv) > 1) {
    $propertyId = intval($argv[1]);
    echo "Testing property ID: $propertyId\n";
    
    $result = calculateServiceFeeEngine($propertyId);
    
    if ($result['success']) {
        echo "✓ SUCCESS\n";
        echo "  - Location: {$result['location']}\n";
        echo "  - Bedrooms: {$result['bedrooms']}\n";
        echo "  - Tier: {$result['tier']}\n";
        echo "  - Matched Keyword: {$result['matchedKeyword']}\n";
        echo "  - Calculated Fee: KSH " . number_format($result['fee']) . "\n";
    } else {
        echo "✗ ERROR: {$result['error']}\n";
        if (isset($result['debug'])) {
            echo "  Debug: {$result['debug']}\n";
        }
    }
    exit();
}

// API ENDPOINT: Returns fee calculation as JSON
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['property_id'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $propertyId = intval($_GET['property_id']);
    $result = calculateServiceFeeEngine($propertyId);
    
    echo json_encode($result);
    exit;
}

?>
