<?php
/**
 * Sample Property Data Creation
 * Creates comprehensive sample properties to demonstrate the enhanced property card system
 * Walbrand Properties & Interiors - Kenya Real Estate Marketplace
 */

session_start();
require_once 'config.php';

// Generate sample properties with complete data
$sampleProperties = [
    [
        'title' => 'Modern 3-Bedroom Apartment in Kilimani',
        'property_code' => 'PROP001',
        'seller_id' => 1,
        'property_type' => 'Apartment',
        'category' => 'lease',
        'listing_type' => 'lease',
        'location' => 'Kilimani, Nairobi',
        'price' => 85000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'size_sqm' => 180,
        'description' => 'Beautiful modern apartment with excellent finishing, fully furnished, secure compound with 24/7 security. Walking distance to shopping mall.',
        'features' => 'Modern Kitchen, Spacious Balcony, Built-in Wardrobes, Air Conditioning, Water Heater',
        'target_group' => 'family',
        'occupancy_status' => 'available',
        'occupancy_type' => 'residential',
        'electricity_terms' => 'Included in rent for first 100 units, excess charged separately',
        'water_terms' => 'Included in rent for first 40 cubic meters, metered excess',
        'units_available' => 1,
        'deposit_amount' => 85000,
        'furnished' => 1,
        'parking_available' => 1,
        'internet_available' => 1,
        'pet_friendly' => 1,
        'verification_status' => 'verified',
        'status' => 'verified',
        'featured' => 1,
        'urgent_sale' => 0,
        'is_verified' => 1,
    ],
    [
        'title' => 'Spacious 4-Bedroom House in Kileleshwa',
        'property_code' => 'PROP002',
        'seller_id' => 1,
        'property_type' => 'House',
        'category' => 'buy',
        'listing_type' => 'buy',
        'location' => 'Kileleshwa, Nairobi',
        'price' => 25000000,
        'bedrooms' => 4,
        'bathrooms' => 3,
        'size_sqm' => 450,
        'description' => 'Magnificent 4-bedroom house on a quarter-acre plot. Modern architecture, well-maintained gardens, separate guest wing, and security room.',
        'features' => 'Swimming Pool, Tennis Court, Security Room, Backup Generator, Borehole, Garden',
        'target_group' => 'family',
        'occupancy_status' => 'available',
        'occupancy_type' => 'residential',
        'electricity_terms' => 'Fully connected to main grid',
        'water_terms' => 'Main connection with backup borehole',
        'units_available' => 1,
        'deposit_amount' => null,
        'furnished' => 0,
        'parking_available' => 1,
        'internet_available' => 1,
        'pet_friendly' => 1,
        'verification_status' => 'verified',
        'status' => 'verified',
        'featured' => 1,
        'urgent_sale' => 1,
        'is_verified' => 1,
    ],
    [
        'title' => 'Student Housing - 2-Bedroom Maisonette Near Strathmore',
        'property_code' => 'PROP003',
        'seller_id' => 1,
        'property_type' => 'Maisonette',
        'category' => 'student_housing',
        'listing_type' => 'student_housing',
        'location' => 'Westlands, Nairobi',
        'price' => 45000,
        'bedrooms' => 2,
        'bathrooms' => 2,
        'size_sqm' => 120,
        'description' => 'Perfect for students! Comfortable 2-bedroom maisonette near Strathmore University. Common study areas, secure compound with 24/7 guard.',
        'features' => 'Study Lounge, WiFi, Common Kitchen, Laundry Service, Secure Gates',
        'target_group' => 'students',
        'occupancy_status' => 'available',
        'occupancy_type' => 'student_housing',
        'electricity_terms' => 'Shared metering, 3000 KES per room per month',
        'water_terms' => 'Shared metering, 1500 KES per room per month',
        'units_available' => 5,
        'deposit_amount' => 45000,
        'furnished' => 1,
        'parking_available' => 0,
        'internet_available' => 1,
        'pet_friendly' => 0,
        'verification_status' => 'verified',
        'status' => 'verified',
        'featured' => 0,
        'urgent_sale' => 0,
        'is_verified' => 1,
    ],
    [
        'title' => 'Luxury 2-Bedroom Apartment in Westlands',
        'property_code' => 'PROP004',
        'seller_id' => 1,
        'property_type' => 'Apartment',
        'category' => 'lease',
        'listing_type' => 'lease',
        'location' => 'Westlands, Nairobi',
        'price' => 120000,
        'bedrooms' => 2,
        'bathrooms' => 2,
        'size_sqm' => 200,
        'description' => 'Luxurious apartment in premium location. State-of-the-art amenities, gym, pool, concierge service. Perfect for professionals.',
        'features' => 'Smart Home Technology, Gym, Pool, Concierge, CCTV Security, Energy Efficient',
        'target_group' => 'general',
        'occupancy_status' => 'available',
        'occupancy_type' => 'residential',
        'electricity_terms' => 'Included in rent, consumption monitored',
        'water_terms' => 'Included in rent, consumption monitored',
        'units_available' => 2,
        'deposit_amount' => 240000,
        'furnished' => 1,
        'parking_available' => 1,
        'internet_available' => 1,
        'pet_friendly' => 1,
        'verification_status' => 'verified',
        'status' => 'verified',
        'featured' => 1,
        'urgent_sale' => 0,
        'is_verified' => 1,
    ],
];

// Insert properties
$insertCount = 0;
$errorCount = 0;

foreach ($sampleProperties as $prop) {
    // Check if property already exists
    $checkStmt = $conn->prepare("SELECT id FROM properties WHERE property_code = ?");
    $checkStmt->bind_param("s", $prop['property_code']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo "⚠️  Property {$prop['property_code']} already exists, skipping...\n";
        $checkStmt->close();
        continue;
    }
    $checkStmt->close();

    // Insert property
    $sql = "INSERT INTO properties (
        title, property_code, seller_id, property_type, category, listing_type,
        location, price, bedrooms, bathrooms, size_sqm, description, features,
        target_group, occupancy_status, occupancy_type, electricity_terms, water_terms,
        units_available, deposit_amount, furnished, parking_available, internet_available,
        pet_friendly, verification_status, status, featured, urgent_sale, is_verified
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "❌ Prepare error: " . $conn->error . "\n";
        $errorCount++;
        continue;
    }

    $stmt->bind_param(
        "ssissssdiiissssssdiiiisssii",
        $prop['title'],
        $prop['property_code'],
        $prop['seller_id'],
        $prop['property_type'],
        $prop['category'],
        $prop['listing_type'],
        $prop['location'],
        $prop['price'],
        $prop['bedrooms'],
        $prop['bathrooms'],
        $prop['size_sqm'],
        $prop['description'],
        $prop['features'],
        $prop['target_group'],
        $prop['occupancy_status'],
        $prop['occupancy_type'],
        $prop['electricity_terms'],
        $prop['water_terms'],
        $prop['units_available'],
        $prop['deposit_amount'],
        $prop['furnished'],
        $prop['parking_available'],
        $prop['internet_available'],
        $prop['pet_friendly'],
        $prop['verification_status'],
        $prop['status'],
        $prop['featured'],
        $prop['urgent_sale'],
        $prop['is_verified']
    );

    if ($stmt->execute()) {
        $propertyId = $conn->insert_id;
        echo "✅ Created: {$prop['property_code']} - {$prop['title']} (ID: $propertyId)\n";
        $insertCount++;
        $stmt->close();
    } else {
        echo "❌ Error inserting {$prop['property_code']}: " . $stmt->error . "\n";
        $errorCount++;
        $stmt->close();
    }
}

echo "\n=== SUMMARY ===\n";
echo "✅ Successfully inserted: $insertCount properties\n";
echo "❌ Errors: $errorCount\n";

// Display inserted properties
echo "\n=== INSERTED PROPERTIES ===\n";
$result = $conn->query("SELECT id, title, location, price, bedrooms, bathrooms, size_sqm, target_group, featured, urgent_sale FROM properties WHERE verification_status='verified' ORDER BY created_at DESC LIMIT 5");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "\n📍 {$row['title']}\n";
        echo "   Location: {$row['location']}\n";
        echo "   Price: KES " . number_format($row['price']) . "\n";
        echo "   Size: {$row['bedrooms']}BR | {$row['bathrooms']}BA | {$row['size_sqm']}m²\n";
        echo "   Target: {$row['target_group']}\n";
        if ($row['featured']) echo "   ⭐ Featured\n";
        if ($row['urgent_sale']) echo "   ⚡ Urgent\n";
    }
}

$conn->close();
?>
