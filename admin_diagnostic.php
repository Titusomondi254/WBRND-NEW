<?php
require_once 'admin_auth.php';

echo "=== ADMIN DIAGNOSTIC REPORT ===\n\n";

// STEP 1: Check all properties and their data
echo "STEP 1: Property Data Audit\n";
echo "============================\n";
$result = $conn->query("SELECT id, location, bedrooms, category, main_category FROM properties WHERE verification_status = 'verified' ORDER BY id");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Property ID {$row['id']}: {$row['location']}\n";
        echo "  - Bedrooms: {$row['bedrooms']}\n";
        echo "  - Category: {$row['category']}\n";
        echo "  - Main Category: {$row['main_category']}\n";
        echo "\n";
    }
} else {
    echo "No verified properties found!\n";
}

// STEP 2: Identify properties with incomplete data
echo "\n\nSTEP 2: Data Completeness Check\n";
echo "================================\n";
$incomplete = $conn->query("SELECT COUNT(*) as count FROM properties WHERE verification_status = 'verified' AND (bedrooms IS NULL OR bedrooms = 0 OR location IS NULL OR location = '')");
$row = $incomplete->fetch_assoc();
echo "Properties with missing bedrooms or location: {$row['count']}\n";

// STEP 3: Check location keywords coverage
echo "\n\nSTEP 3: Location Keywords Coverage Analysis\n";
echo "=============================================\n";

$highEndKeywords = ['Karen', 'Muthaiga', 'Runda', 'Rosslyn', 'Riverside', 'Kilimani', 'Lavington', 'Westlands', 'Ridgeways', 'Ruaka', 'Kikuyu', 'Imara Daima', 'Donholm', 'Thindigua', 'Kiambu Town', 'Fourways Junction', 'Paradise', 'Kenya School of Government', 'Edenville', 'Roysambu', 'Garden Estate', 'Kahawa Sukari', 'Zimmerman', 'Kitisuru'];
$midTierKeywords = ['Githurai', 'Kahawa West', 'Ruiru', 'Kasarani', 'Ngumba', 'Ruaraka', 'Mwiki', 'Juja', 'South B/C', 'Nairobi West', 'Syokimau', 'Mlolongo', 'Athi River'];

$properties = $conn->query("SELECT id, location, bedrooms FROM properties WHERE verification_status = 'verified'");

while ($prop = $properties->fetch_assoc()) {
    $location = strtolower($prop['location']);
    $bedrooms = intval($prop['bedrooms']) ?: 1;
    
    $isHighEnd = false;
    foreach ($highEndKeywords as $keyword) {
        if (strpos($location, strtolower($keyword)) !== false) {
            $isHighEnd = true;
            break;
        }
    }
    
    $isMidTier = false;
    if (!$isHighEnd) {
        foreach ($midTierKeywords as $keyword) {
            if (strpos($location, strtolower($keyword)) !== false) {
                $isMidTier = true;
                break;
            }
        }
    }
    
    // Calculate expected fee
    if ($isHighEnd) {
        if ($bedrooms === 1) $fee = 2000;
        else if ($bedrooms === 2) $fee = 2500;
        else $fee = 3500;
        $category = 'HIGH-END';
    } else if ($isMidTier) {
        if ($bedrooms === 1) $fee = 1500;
        else if ($bedrooms === 2) $fee = 2000;
        else $fee = 2500;
        $category = 'MID-TIER';
    } else {
        if ($bedrooms === 1) $fee = 1000;
        else if ($bedrooms === 2) $fee = 1500;
        else $fee = 2000;
        $category = 'AFFORDABLE';
    }
    
    echo "Property {$prop['id']}: {$prop['location']} ({$bedrooms}BR) → {$category} → KSH {$fee}\n";
}

echo "\n";
?>