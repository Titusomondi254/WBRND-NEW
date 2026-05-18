<?php
require_once 'admin_auth.php';
require_once 'notification_utils.php';
require_once 'helpers.php';

// Ensure required tables exist
ensure_consultations_table_exists($conn);
if (function_exists('ensure_installation_payments_table_exists')) {
    ensure_installation_payments_table_exists($conn);
}
if (function_exists('ensure_agent_earnings_table_exists')) {
    ensure_agent_earnings_table_exists($conn);
}

$service_types = [
    'wifi_distribution' => 'WiFi Distribution',
    'cctv_installation' => 'CCTV Installation',
    'alexa_installation' => 'Alexa Installation',
    'digital_product' => 'Digital Product Upload',
    'digital_installation' => 'Digital Service Installation'
];

$allowed_status = ['pending', 'pending_payment_verification', 'approved', 'scheduled', 'completed', 'cancelled'];
$status_labels = [
    'pending' => 'Pending',
    'pending_payment_verification' => 'Pending Payment Verification',
    'approved' => 'Approved',
    'scheduled' => 'Scheduled',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];
$status_filter = $_GET['status'] ?? 'pending';
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'pending';
}

$service_filter = $_GET['service_type'] ?? 'all';
if ($service_filter !== 'all' && !isset($service_types[$service_filter])) {
    $service_filter = 'all';
}

$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_request_status') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $assigned_agent_id_raw = $_POST['assigned_agent_id'] ?? '';
    $assigned_agent_id = is_numeric($assigned_agent_id_raw) ? intval($assigned_agent_id_raw) : null;
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if ($request_id <= 0 || !in_array($status, $allowed_status, true)) {
        $error = 'Invalid update request.';
    } else {
        $is_agent_selected = $assigned_agent_id !== null && $assigned_agent_id > 0;
        $update_sql = "UPDATE consultations SET status = ?, admin_notes = ?";
        if ($is_agent_selected) {
            $update_sql .= ", agent_id = ?";
        }
        $update_sql .= " WHERE id = ? AND consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation', 'digital_product', 'digital_installation')";

        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            if ($is_agent_selected) {
                $stmt->bind_param('siii', $status, $admin_notes, $assigned_agent_id, $request_id);
            } else {
                $stmt->bind_param('ssi', $status, $admin_notes, $request_id);
            }
            if ($stmt->execute()) {
                $success = 'Request updated successfully.';

                $request_stmt = $conn->prepare("SELECT user_id, consultation_type, agent_id, service_fee FROM consultations WHERE id = ? LIMIT 1");
                if ($request_stmt) {
                    $request_stmt->bind_param('i', $request_id);
                    $request_stmt->execute();
                    $request_row = $request_stmt->get_result()->fetch_assoc();
                    $request_stmt->close();
                    if ($request_row) {
                        notifyDigitalServiceStatusUpdate($request_id, intval($request_row['user_id']), $request_row['consultation_type'], $status);

                        $effective_agent_id = $is_agent_selected ? $assigned_agent_id : intval($request_row['agent_id'] ?? 0);
                        if ($status === 'completed' && $effective_agent_id > 0) {
                            $service_fee = floatval($request_row['service_fee'] ?? 1000);
                            updateAgentStatsOnDigitalServiceCompletion($request_id, $effective_agent_id, $service_fee);

                            $payment_amount = 1000.00;
                            $payment_type = 'consultation_fee';
                            if ($request_row['consultation_type'] === 'digital_installation') {
                                $payment_amount = 15000.00;
                                $payment_type = 'installation_fee';
                            }

                            $payment_reference = 'digital_service_' . $request_id;
                            $check_payment_stmt = $conn->prepare("SELECT id FROM payments WHERE payment_reference = ? LIMIT 1");
                            if ($check_payment_stmt) {
                                $check_payment_stmt->bind_param('s', $payment_reference);
                                $check_payment_stmt->execute();
                                $check_payment_stmt->store_result();
                                $payment_exists = $check_payment_stmt->num_rows > 0;
                                $check_payment_stmt->close();
                            } else {
                                $payment_exists = false;
                            }

                            if (!$payment_exists) {
                                $payment_stmt = $conn->prepare(
                                    "INSERT INTO payments (user_id, amount, payment_type, payment_method, status, payment_reference, created_at, completed_at) VALUES (?, ?, ?, 'other', 'completed', ?, NOW(), NOW())"
                                );
                                if ($payment_stmt) {
                                    $payment_stmt->bind_param('idss', $request_row['user_id'], $payment_amount, $payment_type, $payment_reference);
                                    $payment_stmt->execute();
                                    $payment_stmt->close();
                                }
                            }
                        }
                    }
                }
            } else {
                $error = 'Unable to update request status. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Unable to update request. Please try again later.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_request') {
    $request_id = intval($_POST['request_id'] ?? 0);
    if ($request_id <= 0) {
        $error = 'Invalid request deletion request.';
    } else {
        $delete_sql = "DELETE FROM consultations WHERE id = ? AND consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation', 'digital_product', 'digital_installation')";
        $stmt = $conn->prepare($delete_sql);
        if ($stmt) {
            $stmt->bind_param('i', $request_id);
            if ($stmt->execute()) {
                $success = 'Request deleted successfully.';
            } else {
                $error = 'Unable to delete request. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Unable to process request deletion. Please try again later.';
        }
    }
}

// Handle product verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_product') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $action = $_POST['verification_action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if ($product_id <= 0 || !in_array($action, ['confirm', 'reject', 'suspend', 'delete'], true)) {
        $error = 'Invalid product verification request.';
    } else {
        if ($action === 'confirm') {
            $new_status = 'active';
            $update_sql = "UPDATE agent_digital_products SET status = ?, admin_notes = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param('siii', $new_status, $admin_notes, $_SESSION['admin_id'], $product_id);
                if ($stmt->execute()) {
                    $success = 'Product confirmed and published successfully.';
                } else {
                    $error = 'Unable to confirm product. Please try again.';
                }
                $stmt->close();
            }
        } elseif ($action === 'reject') {
            $new_status = 'inactive';
            $update_sql = "UPDATE agent_digital_products SET status = ?, admin_notes = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param('ssi', $new_status, $admin_notes, $product_id);
                if ($stmt->execute()) {
                    $success = 'Product rejected successfully.';
                } else {
                    $error = 'Unable to reject product. Please try again.';
                }
                $stmt->close();
            }
        } elseif ($action === 'suspend') {
            $new_status = 'suspended';
            $update_sql = "UPDATE agent_digital_products SET status = ?, admin_notes = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param('ssi', $new_status, $admin_notes, $product_id);
                if ($stmt->execute()) {
                    $success = 'Product suspended successfully.';
                } else {
                    $error = 'Unable to suspend product. Please try again.';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            // Get product info before deletion for cleanup
            $product_stmt = $conn->prepare("SELECT product_images, product_videos FROM agent_digital_products WHERE id = ?");
            $product_stmt->bind_param('i', $product_id);
            $product_stmt->execute();
            $product_data = $product_stmt->get_result()->fetch_assoc();
            $product_stmt->close();

            // Delete the product
            $delete_sql = "DELETE FROM agent_digital_products WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            if ($stmt) {
                $stmt->bind_param('i', $product_id);
                if ($stmt->execute()) {
                    $success = 'Product deleted successfully.';

                    // Clean up uploaded files
                    if ($product_data) {
                        $files_to_delete = [];
                        if ($product_data['product_images']) {
                            $images = json_decode($product_data['product_images'], true);
                            if (is_array($images)) {
                                $files_to_delete = array_merge($files_to_delete, $images);
                            }
                        }
                        if ($product_data['product_videos']) {
                            $videos = json_decode($product_data['product_videos'], true);
                            if (is_array($videos)) {
                                $files_to_delete = array_merge($files_to_delete, $videos);
                            }
                        }

                        foreach ($files_to_delete as $file_path) {
                            $full_path = __DIR__ . '/' . $file_path;
                            if (file_exists($full_path)) {
                                unlink($full_path);
                            }
                        }
                    }
                } else {
                    $error = 'Unable to delete product. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}

$filter_conditions = ["c.consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation', 'digital_product', 'digital_installation')"];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $filter_conditions[] = 'c.status = ?';
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($service_filter !== 'all') {
    $filter_conditions[] = 'c.consultation_type = ?';
    $params[] = $service_filter;
    $param_types .= 's';
}

if ($search !== '') {
    $filter_conditions[] = '(c.issue_description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR c.email LIKE ? OR c.contact_number LIKE ?)';
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'sssss';
}

$where_clause = implode(' AND ', $filter_conditions);
$count_sql = "SELECT COUNT(*) AS total FROM consultations c LEFT JOIN users u ON c.user_id = u.id WHERE {$where_clause}";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if ($param_types !== '') {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $total_count = intval($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $count_stmt->close();
} else {
    $total_count = 0;
}

$total_pages = max(1, ceil($total_count / $per_page));

$query_sql = "SELECT c.id, c.user_id, c.consultation_type, c.status, c.scheduled_date, c.contact_number, c.email, c.issue_description, c.admin_notes, c.agent_id, c.created_at, c.product_title, u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) AS client_name, a.first_name AS agent_first_name, a.last_name AS agent_last_name, ip.mpesa_name, ip.mpesa_code, ip.mpesa_contact, ip.mpesa_time, ip.screenshot_path, ip.payment_amount, ip.payment_percentage, ip.payment_status FROM consultations c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN users a ON c.agent_id = a.id LEFT JOIN installation_payments ip ON c.id = ip.consultation_id WHERE {$where_clause} ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query_sql);
if ($stmt) {
    if ($param_types !== '') {
        $types = $param_types . 'ii';
        $bind_values = array_merge([$types], $params, [$per_page, $offset]);
        $bind_refs = [];
        foreach ($bind_values as $key => $value) {
            $bind_refs[$key] = &$bind_values[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_refs);
    } else {
        $stmt->bind_param('ii', $per_page, $offset);
    }
    $stmt->execute();
    $requests_result = $stmt->get_result();
    $requests = $requests_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $requests = [];
}

$agents_stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'agent' AND is_active = 1 ORDER BY first_name, last_name");
$agents = [];
if ($agents_stmt) {
    $agents_stmt->execute();
    $agents = $agents_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $agents_stmt->close();
}

// Build moving average statistics for admin overview
$overview_start_date = date('Y-m-d', strtotime('-29 days'));
$overview_labels = [];
$overview_index = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("{$overview_start_date} +{$i} days"));
    $overview_labels[] = date('M j', strtotime($date));
    $overview_index[$date] = $i;
}

$installation_history = array_fill(0, 30, 0);
$submission_history = array_fill(0, 30, 0);
$approval_history = array_fill(0, 30, 0);
$rejection_history = array_fill(0, 30, 0);

$installation_requests = [];
$installation_requests_stmt = $conn->prepare("SELECT c.id, c.user_id, c.consultation_type, c.status, c.scheduled_date, c.contact_number, c.email, c.issue_description, c.admin_notes, c.agent_id, c.created_at, c.product_title, u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) AS client_name, a.first_name AS agent_first_name, a.last_name AS agent_last_name, ip.mpesa_name, ip.mpesa_code, ip.mpesa_contact, ip.mpesa_time, ip.screenshot_path, ip.payment_amount, ip.payment_percentage, ip.payment_status FROM consultations c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN users a ON c.agent_id = a.id LEFT JOIN installation_payments ip ON c.id = ip.consultation_id WHERE c.consultation_type IN ('digital_installation', 'installation_request') ORDER BY c.created_at DESC LIMIT 30");
if ($installation_requests_stmt) {
    $installation_requests_stmt->execute();
    $installation_requests = $installation_requests_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $installation_requests_stmt->close();
}

$installation_count_stmt = $conn->prepare("SELECT DATE(created_at) AS event_date, COUNT(*) AS count FROM consultations WHERE consultation_type IN ('digital_installation', 'installation_request') AND DATE(created_at) >= ? GROUP BY event_date");
if ($installation_count_stmt) {
    $installation_count_stmt->bind_param('s', $overview_start_date);
    $installation_count_stmt->execute();
    $result = $installation_count_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($overview_index[$row['event_date']])) {
            $installation_history[$overview_index[$row['event_date']]] = intval($row['count']);
        }
    }
    $installation_count_stmt->close();
}

$submission_count_stmt = $conn->prepare("SELECT DATE(created_at) AS event_date, COUNT(*) AS count FROM agent_digital_products WHERE DATE(created_at) >= ? GROUP BY event_date");
if ($submission_count_stmt) {
    $submission_count_stmt->bind_param('s', $overview_start_date);
    $submission_count_stmt->execute();
    $result = $submission_count_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($overview_index[$row['event_date']])) {
            $submission_history[$overview_index[$row['event_date']]] = intval($row['count']);
        }
    }
    $submission_count_stmt->close();
}

$status_history_stmt = $conn->prepare("SELECT DATE(COALESCE(approved_at, updated_at, created_at)) AS event_date, status, COUNT(*) AS count FROM agent_digital_products WHERE status IN ('active', 'inactive') AND DATE(COALESCE(approved_at, updated_at, created_at)) >= ? GROUP BY event_date, status");
if ($status_history_stmt) {
    $status_history_stmt->bind_param('s', $overview_start_date);
    $status_history_stmt->execute();
    $result = $status_history_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!isset($overview_index[$row['event_date']])) {
            continue;
        }
        $index = $overview_index[$row['event_date']];
        if ($row['status'] === 'active') {
            $approval_history[$index] = intval($row['count']);
        } elseif ($row['status'] === 'inactive') {
            $rejection_history[$index] = intval($row['count']);
        }
    }
    $status_history_stmt->close();
}

function moving_average(array $values, int $window = 7): array {
    $result = [];
    $n = count($values);
    for ($i = 0; $i < $n; $i++) {
        $start = max(0, $i - $window + 1);
        $sum = 0;
        $count = 0;
        for ($j = $start; $j <= $i; $j++) {
            $sum += $values[$j];
            $count++;
        }
        $result[] = $count > 0 ? round($sum / $count, 2) : 0;
    }
    return $result;
}

$installation_mavg = moving_average($installation_history);
$submission_mavg = moving_average($submission_history);
$approval_mavg = moving_average($approval_history);
$rejection_mavg = moving_average($rejection_history);

$installation_total_30d = array_sum($installation_history);
$submission_total_30d = array_sum($submission_history);
$approval_total_30d = array_sum($approval_history);
$rejection_total_30d = array_sum($rejection_history);

// Get pending installation request total for summary card
$pending_installation_total = 0;
$pending_installation_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations WHERE consultation_type IN ('digital_installation', 'installation_request') AND (status = 'pending_payment_verification' OR status = '' OR status IS NULL)");
if ($pending_installation_stmt) {
    $pending_installation_stmt->execute();
    $pending_installation_total = intval($pending_installation_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pending_installation_stmt->close();
}

// Get active product submissions pending review total for summary card
$pending_submissions_total = 0;
$pending_submissions_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM agent_digital_products WHERE status = 'pending_review'");
if ($pending_submissions_stmt) {
    $pending_submissions_stmt->execute();
    $pending_submissions_total = intval($pending_submissions_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pending_submissions_stmt->close();
}

// Get approval and rejection totals in last 30 days for summary card
$active_products_30d = array_sum($approval_history);
$rejected_products_30d = array_sum($rejection_history);

// Get pending products for verification
$pending_products_stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name, u.email
    FROM agent_digital_products dp
    LEFT JOIN users u ON dp.agent_id = u.id
    WHERE dp.status = 'pending_review'
    ORDER BY dp.created_at DESC
");
$pending_products = [];
if ($pending_products_stmt) {
    $pending_products_stmt->execute();
    $pending_products_result = $pending_products_stmt->get_result();
    while ($row = $pending_products_result->fetch_assoc()) {
        $pending_products[] = $row;
    }
    $pending_products_stmt->close();
}

// Get verified products
$verified_products_stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name, u.email
    FROM agent_digital_products dp
    LEFT JOIN users u ON dp.agent_id = u.id
    WHERE dp.status = 'active'
    ORDER BY dp.approved_at DESC, dp.created_at DESC
");
$verified_products = [];
if ($verified_products_stmt) {
    $verified_products_stmt->execute();
    $verified_products_result = $verified_products_stmt->get_result();
    while ($row = $verified_products_result->fetch_assoc()) {
        $verified_products[] = $row;
    }
    $verified_products_stmt->close();
}

// Get rejected products
$rejected_products_stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name, u.email
    FROM agent_digital_products dp
    LEFT JOIN users u ON dp.agent_id = u.id
    WHERE dp.status = 'inactive'
    ORDER BY dp.updated_at DESC, dp.created_at DESC
");
$rejected_products = [];
if ($rejected_products_stmt) {
    $rejected_products_stmt->execute();
    $rejected_products_result = $rejected_products_stmt->get_result();
    while ($row = $rejected_products_result->fetch_assoc()) {
        $rejected_products[] = $row;
    }
    $rejected_products_stmt->close();
}

// Get suspended products
$suspended_products_stmt = $conn->prepare("
    SELECT dp.*, u.first_name, u.last_name, u.email
    FROM agent_digital_products dp
    LEFT JOIN users u ON dp.agent_id = u.id
    WHERE dp.status = 'suspended'
    ORDER BY dp.updated_at DESC, dp.created_at DESC
");
$suspended_products = [];
if ($suspended_products_stmt) {
    $suspended_products_stmt->execute();
    $suspended_products_result = $suspended_products_stmt->get_result();
    while ($row = $suspended_products_result->fetch_assoc()) {
        $suspended_products[] = $row;
    }
    $suspended_products_stmt->close();
}

function buildQueryString(array $params, $exclude = []) {
    $filtered = [];
    foreach ($params as $key => $value) {
        if (!in_array($key, $exclude, true) && $value !== '') {
            $filtered[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    return implode('&', $filtered);
}

$base_query = buildQueryString(['status' => $status_filter, 'service_type' => $service_filter, 'search' => $search]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Services Requests - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; color: #1f2937; margin: 0; }
        .container { max-width: 1240px; margin: 0 auto; padding: 24px; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 2rem; }
        .filters { display: grid; grid-template-columns: repeat(3, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .filters input, .filters select { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; }
        .requests-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; background: white; border-radius: 18px; overflow: hidden; }
        .requests-table th, .requests-table td { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .requests-table th { background: #eef2ff; color: #312e81; }
        .requests-table tbody tr:hover { background: #f8fafc; }
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-pending_payment_verification { background: #fce7f3; color: #9d174d; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-scheduled { background: #dbeafe; color: #1e3a8a; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #7f1d1d; }
        .panel { background: white; border-radius: 18px; padding: 22px; border: 1px solid #e2e8f0; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
        .form-group label { font-weight: 600; color: #111827; }
        .form-group input, .form-group select, .form-group textarea { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; border: none; cursor: pointer; font-weight: 700; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #e2e8f0; color: #1f2937; }
        .status-tabs { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .status-tab { display: inline-flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; background: white; color: #1f2937; text-decoration: none; cursor: pointer; }
        .status-tab.active { background: #2563eb; color: white; border-color: transparent; }
        .status-tab span.count { background: rgba(37, 99, 235, 0.15); color: #1d4ed8; padding: 3px 9px; border-radius: 999px; font-weight: 700; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .summary-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04); }
        .summary-card strong { display: block; margin-bottom: 10px; color: #0f172a; }
        .summary-value { font-size: 2rem; font-weight: 700; color: #1d4ed8; margin-bottom: 6px; }
        .summary-card small { color: #64748b; }
        .overview-panel { padding: 24px; margin-bottom: 32px; }
        .overview-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; margin-top: 18px; }
        .chart-container { background: white; border-radius: 18px; padding: 18px; border: 1px solid #e2e8f0; }
        .overview-legend { display: grid; gap: 16px; }
        .legend-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
        .legend-card div { margin-top: 10px; display: flex; align-items: center; gap: 10px; color: #334155; }
        .legend-color { width: 14px; height: 14px; display: inline-block; border-radius: 4px; }
        .legend-installation { background: #f97316; }
        .legend-submission { background: #2563eb; }
        .legend-approval { background: #10b981; }
        .legend-rejection { background: #ef4444; }
        .key-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
        .key-table th, .key-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .key-table th { background: #eef2ff; color: #1d4ed8; }
        .key-table tr:last-child td { border-bottom: none; }
        @media (max-width: 1080px) { .overview-grid { grid-template-columns: 1fr; } }
        @media (max-width: 920px) { .filters { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Digital Services Requests</h1>
                <p>Review and manage WiFi, CCTV, Alexa, and digital product verification requests from clients.</p>
            </div>
            <div><a href="admin_control_panel.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a></div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="status-message status-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="status-message status-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="status-tabs">
            <a href="#pending" class="status-tab active">
                Pending Review
                <span class="count"><?= count($pending_products) ?></span>
            </a>
            <a href="#verified" class="status-tab">
                Verified
                <span class="count"><?= count($verified_products) ?></span>
            </a>
            <a href="#rejected" class="status-tab">
                Rejected
                <span class="count"><?= count($rejected_products) ?></span>
            </a>
            <a href="#suspended" class="status-tab">
                Suspended
                <span class="count"><?= count($suspended_products) ?></span>
            </a>
            <a href="#installation-requests" class="status-tab">
                Installation Requests
                <span class="count"><?= count($installation_requests) ?></span>
            </a>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <strong>Pending Install Verifications</strong>
                <div class="summary-value"><?= intval($pending_installation_total) ?></div>
                <small>Awaiting payment verification</small>
            </div>
            <div class="summary-card">
                <strong>Pending Product Submissions</strong>
                <div class="summary-value"><?= intval($pending_submissions_total) ?></div>
                <small>Waiting for review</small>
            </div>
        </div>

        <div class="overview-panel panel">
            <h2 style="margin-bottom: 10px; color: #1f2937;"><i class="fas fa-chart-line"></i> Overview Statistics</h2>
            <p style="margin: 0 0 18px; color: #475569;">Moving average history for installation requests, product submissions, approvals, and rejections over the last 30 days.</p>
            <div class="overview-grid">
                <div class="chart-container" style="min-height: 360px;">
                    <canvas id="overviewChart"></canvas>
                </div>
                <div class="overview-legend">
                    <div class="legend-card">
                        <strong>30-Day Totals</strong>
                        <div><span class="legend-color legend-installation"></span> Installation Requests: <?= intval($installation_total_30d) ?></div>
                        <div><span class="legend-color legend-submission"></span> Product Submissions: <?= intval($submission_total_30d) ?></div>
                        <div><span class="legend-color legend-approval"></span> Product Approvals: <?= intval($approval_total_30d) ?></div>
                        <div><span class="legend-color legend-rejection"></span> Product Rejections: <?= intval($rejection_total_30d) ?></div>
                    </div>
                    <table class="key-table">
                        <thead>
                            <tr><th>Line</th><th>Activity</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><span class="legend-color legend-installation"></span></td><td>Installation request moving average</td></tr>
                            <tr><td><span class="legend-color legend-submission"></span></td><td>New product submission moving average</td></tr>
                            <tr><td><span class="legend-color legend-approval"></span></td><td>Product approval moving average</td></tr>
                            <tr><td><span class="legend-color legend-rejection"></span></td><td>Product rejection moving average</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="get" class="filters">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($allowed_status as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="service_type">Service Type</label>
                <select id="service_type" name="service_type">
                    <option value="all" <?= $service_filter === 'all' ? 'selected' : '' ?>>All Services</option>
                    <?php foreach ($service_types as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $service_filter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="search">Search</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Client name, phone, email...">
            </div>
            <div style="align-self:end;">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>

        <div id="installation-requests" class="panel" style="margin-bottom: 32px;">
            <h2 style="margin-bottom: 20px; color: #1f2937;"><i class="fas fa-tools"></i> Installation Requests</h2>
            <?php if (!empty($installation_requests)): ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installation_requests as $request): ?>
                            <tr>
                                <td>#<?= intval($request['id']) ?></td>
                                <td><?= htmlspecialchars($request['client_name'] ?: 'Unknown') ?><br><small><?= htmlspecialchars($request['email']) ?></small></td>
                                <td><?= htmlspecialchars($request['contact_number'] ?: 'N/A') ?></td>
                                <?php $request_status_key = $request['status'] !== '' ? $request['status'] : 'pending_payment_verification'; ?>
                                <td><span class="status-badge status-<?= htmlspecialchars($request_status_key) ?>"><?= htmlspecialchars($status_labels[$request_status_key] ?? ucfirst(str_replace('_', ' ', $request_status_key))) ?></span></td>
                                <td><?= htmlspecialchars($request['scheduled_date'] ? date('M d, Y g:i A', strtotime($request['scheduled_date'])) : 'No preference') ?></td>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                <td><button type="button" class="btn btn-secondary" style="background:#e2e8f0; color:#111827;" onclick='openRequestModal(<?= json_encode($request, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'><i class="fas fa-eye"></i> View</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin: 0; color: #6b7280;">No installation requests found.</p>
            <?php endif; ?>
        </div>

        <!-- Product Verification Section -->
        <?php if (!empty($pending_products)): ?>
        <div id="pending" class="panel" style="margin-bottom: 32px;">
            <h2 style="margin-bottom: 20px; color: #1f2937;"><i class="fas fa-check-circle"></i> Product Verification Queue</h2>
            <p style="color: #6b7280; margin-bottom: 20px;">Review and approve/reject digital products submitted by agents before they become visible to clients.</p>

            <div style="display: grid; gap: 20px;">
                <?php foreach ($pending_products as $product): ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; background: #fafafa;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <div>
                            <h3 style="margin: 0; color: #1f2937; font-size: 1.2rem;"><?= htmlspecialchars($product['product_name']) ?></h3>
                            <p style="margin: 4px 0; color: #6b7280;">
                                <strong>Agent:</strong> <?= htmlspecialchars($product['first_name'] . ' ' . $product['last_name']) ?> |
                                <strong>Category:</strong> <?= htmlspecialchars(ucfirst($product['product_category'])) ?> |
                                <strong>Provider:</strong> <?= htmlspecialchars($product['service_provider']) ?> |
                                <strong>Price:</strong> KES <?= number_format($product['price'], 2) ?>
                            </p>
                            <p style="margin: 4px 0; color: #6b7280;">
                                <strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($product['created_at'])) ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary" onclick="window.open('digital_product_details.php?id=<?= $product['id'] ?>', '_blank')" style="background: #e2e8f0; color: #111827;">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button type="button" class="btn btn-primary" onclick="openProductModal(<?= $product['id'] ?>, 'confirm')" style="background: #10b981;">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="openProductModal(<?= $product['id'] ?>, 'reject')" style="background: #f59e0b; color: white;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="openProductModal(<?= $product['id'] ?>, 'suspend')" style="background: #8b5cf6; color: white;">
                                <i class="fas fa-pause"></i> Suspend
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="openProductModal(<?= $product['id'] ?>, 'delete')" style="background: #ef4444; color: white;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                    <div style="margin-bottom: 16px;">
                        <strong>Description:</strong>
                        <p style="margin: 8px 0; color: #4b5563;"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Media Preview -->
                    <?php
                    $images = !empty($product['product_images']) ? json_decode($product['product_images'], true) : [];
                    $videos = !empty($product['product_videos']) ? json_decode($product['product_videos'], true) : [];
                    ?>
                    <?php if (!empty($images) || !empty($videos)): ?>
                    <div style="margin-bottom: 16px;">
                        <strong>Media:</strong>
                        <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                            <?php foreach ($images as $image): ?>
                            <div style="width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <img src="<?= htmlspecialchars($image) ?>" alt="Product image" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <?php endforeach; ?>
                            <?php foreach ($videos as $video): ?>
                            <div style="width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; position: relative;">
                                <video style="width: 100%; height: 100%; object-fit: cover;">
                                    <source src="<?= htmlspecialchars($video) ?>" type="video/mp4">
                                </video>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 20px;">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Technical Details -->
                    <div style="background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <strong>Technical Specifications:</strong>
                        <div style="margin-top: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                            <?php if ($product['product_category'] === 'wifi'): ?>
                                <span><strong>Speed:</strong> <?= htmlspecialchars($product['wifi_speed_mbps']) ?> Mbps</span>
                                <span><strong>Reliability:</strong> <?= htmlspecialchars($product['wifi_reliability_percent']) ?>%</span>
                                <span><strong>Latency:</strong> <?= htmlspecialchars($product['wifi_latency_ms']) ?> ms</span>
                                <span><strong>Coverage:</strong> <?= htmlspecialchars($product['wifi_coverage_range_meters']) ?> m</span>
                            <?php elseif ($product['product_category'] === 'cctv'): ?>
                                <span><strong>Resolution:</strong> <?= htmlspecialchars($product['cctv_resolution_standard']) ?></span>
                                <span><strong>Low Light:</strong> ISO <?= htmlspecialchars($product['cctv_low_light_iso']) ?></span>
                                <span><strong>Frame Rate:</strong> <?= htmlspecialchars($product['cctv_frame_rate_fps']) ?> fps</span>
                                <span><strong>IR Distance:</strong> <?= htmlspecialchars($product['cctv_ir_distance_meters']) ?> m</span>
                            <?php elseif ($product['product_category'] === 'alexa'): ?>
                                <span><strong>Energy:</strong> <?= htmlspecialchars($product['alexa_energy_consumption_watts']) ?> W</span>
                                <span><strong>Latency:</strong> <?= htmlspecialchars($product['alexa_system_latency_ms']) ?> ms</span>
                                <span><strong>Uptime:</strong> <?= htmlspecialchars($product['alexa_uptime_percent']) ?>%</span>
                                <span><strong>Rating:</strong> <?= htmlspecialchars($product['alexa_responsiveness_rating']) ?>/5</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div id="verified" class="panel" style="margin-bottom: 32px;">
            <h2 style="margin-bottom: 20px; color: #1f2937;"><i class="fas fa-thumbs-up"></i> Verified Digital Products</h2>
            <?php if (!empty($verified_products)): ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ($verified_products as $product): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; background: #f8fafc;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: #111827;"><?= htmlspecialchars($product['product_name']) ?></h3>
                                    <p style="margin: 6px 0; color: #6b7280;"><strong>Agent:</strong> <?= htmlspecialchars($product['first_name'] . ' ' . $product['last_name']) ?> | <strong>Category:</strong> <?= htmlspecialchars(ucfirst($product['product_category'])) ?></p>
                                </div>
                                <span style="background: #d1fae5; color: #065f46; border-radius: 999px; padding: 6px 12px; font-weight: 600;">Verified</span>
                            </div>
                            <p style="margin: 0; color: #475569;"><strong>Approved:</strong> <?= date('M j, Y g:i A', strtotime($product['approved_at'] ?? $product['updated_at'] ?? $product['created_at'])) ?></p>
                            <div style="margin-top: 16px;">
                                <button type="button" class="btn btn-secondary" onclick="window.open('digital_product_details.php?id=<?= $product['id'] ?>', '_blank')" style="background: #e2e8f0; color: #111827;">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="margin: 0; color: #6b7280;">No verified digital products yet.</p>
            <?php endif; ?>
        </div>

        <div id="rejected" class="panel" style="margin-bottom: 32px;">
            <h2 style="margin-bottom: 20px; color: #1f2937;"><i class="fas fa-ban"></i> Rejected Digital Products</h2>
            <?php if (!empty($rejected_products)): ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ($rejected_products as $product): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; background: #fef2f2;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: #111827;"><?= htmlspecialchars($product['product_name']) ?></h3>
                                    <p style="margin: 6px 0; color: #6b7280;"><strong>Agent:</strong> <?= htmlspecialchars($product['first_name'] . ' ' . $product['last_name']) ?> | <strong>Category:</strong> <?= htmlspecialchars(ucfirst($product['product_category'])) ?></p>
                                </div>
                                <span style="background: #fee2e2; color: #b91c1c; border-radius: 999px; padding: 6px 12px; font-weight: 600;">Rejected</span>
                            </div>
                            <p style="margin: 0; color: #475569;"><strong>Updated:</strong> <?= date('M j, Y g:i A', strtotime($product['updated_at'] ?? $product['created_at'])) ?></p>
                            <div style="margin-top: 16px;">
                                <button type="button" class="btn btn-secondary" onclick="window.open('digital_product_details.php?id=<?= $product['id'] ?>', '_blank')" style="background: #e2e8f0; color: #111827;">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="margin: 0; color: #6b7280;">No rejected digital products.</p>
            <?php endif; ?>
        </div>

        <div id="suspended" class="panel" style="margin-bottom: 32px;">
            <h2 style="margin-bottom: 20px; color: #1f2937;"><i class="fas fa-pause-circle"></i> Suspended Digital Products</h2>
            <?php if (!empty($suspended_products)): ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ($suspended_products as $product): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; background: #f8fafc;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: #111827;"><?= htmlspecialchars($product['product_name']) ?></h3>
                                    <p style="margin: 6px 0; color: #6b7280;"><strong>Agent:</strong> <?= htmlspecialchars($product['first_name'] . ' ' . $product['last_name']) ?> | <strong>Category:</strong> <?= htmlspecialchars(ucfirst($product['product_category'])) ?></p>
                                </div>
                                <span style="background: #f3e8ff; color: #7c3aed; border-radius: 999px; padding: 6px 12px; font-weight: 600;">Suspended</span>
                            </div>
                            <p style="margin: 0; color: #475569;"><strong>Updated:</strong> <?= date('M j, Y g:i A', strtotime($product['updated_at'] ?? $product['created_at'])) ?></p>
                            <div style="margin-top: 16px;">
                                <button type="button" class="btn btn-secondary" onclick="window.open('digital_product_details.php?id=<?= $product['id'] ?>', '_blank')" style="background: #e2e8f0; color: #111827;">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="margin: 0; color: #6b7280;">No suspended digital products.</p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2 style="margin-bottom: 16px; color: #1f2937;"><i class="fas fa-tools"></i> Installation & Digital Service Requests</h2>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Preferred Slot</th>
                        <th>Assigned Agent</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:28px 0;">No digital service requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>#<?= intval($request['id']) ?></td>
                                <td><?= htmlspecialchars($request['client_name'] ?: 'Unknown') ?><br><small><?= htmlspecialchars($request['email']) ?></small></td>
                                <td>
                                    <?php if ($request['consultation_type'] === 'digital_product'): ?>
                                        <strong>Digital Product:</strong><br>
                                        <?= htmlspecialchars($request['product_title'] ?: 'Untitled') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($service_types[$request['consultation_type']] ?? ucfirst(str_replace('_', ' ', $request['consultation_type']))) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(date('M d, Y g:i A', strtotime($request['scheduled_date']))) ?></td>
                                <td><?= htmlspecialchars(trim(($request['agent_first_name'] ?? '') . ' ' . ($request['agent_last_name'] ?? ''))) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($request['status']) ?>"><?= htmlspecialchars($status_labels[$request['status']] ?? ucfirst(str_replace('_', ' ', $request['status']))) ?></span></td>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                <td>
                                    <form method="post" style="display:inline-grid; gap:8px;">
                                        <input type="hidden" name="action" value="update_request_status">
                                        <input type="hidden" name="request_id" value="<?= intval($request['id']) ?>">
                                        <select name="status" style="min-width:160px; padding:8px 10px; border-radius:10px; border:1px solid #cbd5e1;">
                                            <?php foreach ($allowed_status as $status): ?>
                                                <option value="<?= htmlspecialchars($status) ?>" <?= $status === $request['status'] ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="assigned_agent_id" style="min-width:160px; padding:8px 10px; border-radius:10px; border:1px solid #cbd5e1;">
                                            <option value="">Assign Agent</option>
                                            <?php foreach ($agents as $agent): ?>
                                                <option value="<?= intval($agent['id']) ?>" <?= intval($agent['id']) === intval($request['agent_id']) ? 'selected' : '' ?>><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="admin_notes" rows="2" placeholder="Notes..." style="border-radius:12px;border:1px solid #cbd5e1;padding:10px;"><?= htmlspecialchars($request['admin_notes']) ?></textarea>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" style="margin-top:8px; background:#e2e8f0; color:#111827;" onclick='openRequestModal(<?= json_encode($request, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="admin_digital_services.php?<?= $base_query ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>

    </div>

    <!-- Product Verification Modal -->
    <div id="productVerificationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px;">
            <h3 id="modalTitle" style="margin-bottom: 16px;">Product Verification</h3>
            <form method="post">
                <input type="hidden" name="action" value="verify_product">
                <input type="hidden" name="product_id" id="modalProductId">
                <input type="hidden" name="verification_action" id="modalAction">

                <div class="form-group">
                    <label for="admin_notes">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" id="admin_notes" rows="4" placeholder="Add any notes about this decision..."></textarea>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeProductModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" id="modalSubmitBtn" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <div id="requestDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px;">
                <h3 style="margin:0;">Installation Request Details</h3>
                <button type="button" onclick="closeRequestModal()" class="btn btn-secondary" style="padding:8px 12px;">Close</button>
            </div>
            <div style="display:grid; gap:14px; margin-bottom:16px;">
                <div><strong>Client:</strong> <span id="requestModalClientName"></span></div>
                <div><strong>Contact:</strong> <span id="requestModalContact"></span></div>
                <div><strong>Email:</strong> <span id="requestModalEmail"></span></div>
                <div><strong>Service:</strong> <span id="requestModalService"></span></div>
                <div><strong>Request Title:</strong> <span id="requestModalProductTitle"></span></div>
                <div><strong>Preferred Slot:</strong> <span id="requestModalSchedule"></span></div>
                <div><strong>Status:</strong> <span id="requestModalStatusLabel"></span></div>
                <div><strong>Assigned Agent:</strong> <span id="requestModalAgent"></span></div>
                <div><strong>Submitted:</strong> <span id="requestModalCreated"></span></div>
                <div><strong>Issue Description:</strong><div id="requestModalIssue" style="margin-top: 6px; color: #4b5563;"></div></div>
                <div><strong>Admin Notes:</strong><div id="requestModalNotes" style="margin-top: 6px; color: #4b5563;"></div></div>
                <div><strong>M-Pesa Name:</strong> <span id="requestModalMpesaName"></span></div>
                <div><strong>M-Pesa Code:</strong> <span id="requestModalMpesaCode"></span></div>
                <div><strong>M-Pesa Contact:</strong> <span id="requestModalMpesaContact"></span></div>
                <div><strong>Payment Time:</strong> <span id="requestModalMpesaTime"></span></div>
                <div><strong>Payment Amount:</strong> <span id="requestModalPaymentAmount"></span></div>
                <div><strong>Payment Status:</strong> <span id="requestModalPaymentStatus"></span></div>
                <div><strong>M-Pesa Screenshot:</strong><br><img id="requestModalScreenshot" src="" alt="M-Pesa Screenshot" style="max-width: 100%; max-height: 300px; margin-top: 6px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer;" onclick="if(this.src) window.open(this.src, '_blank');"></div>
            </div>
            <form method="post" id="requestDetailsForm" style="display:grid; gap:14px;">
                <input type="hidden" name="request_id" id="requestModalId">
                <input type="hidden" name="action" value="update_request_status" id="requestModalActionField">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
                    <div>
                        <label for="requestModalStatus">Status</label>
                        <select name="status" id="requestModalStatus" style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; margin-top:6px;">
                            <?php foreach ($allowed_status as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="requestModalAssignedAgent">Assign Agent</label>
                        <select name="assigned_agent_id" id="requestModalAssignedAgent" style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid #cbd5e1; margin-top:6px;">
                            <option value="">No change</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= intval($agent['id']) ?>"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="requestModalAdminNotes">Admin Notes</label>
                    <textarea name="admin_notes" id="requestModalAdminNotes" rows="4" placeholder="Add or update notes..." style="width:100%; border-radius:12px; border:1px solid #cbd5e1; padding:10px;"></textarea>
                </div>
                <div style="display:flex; flex-wrap: wrap; gap:12px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Cancel</button>
                    <button type="button" class="btn btn-secondary" style="background:#ef4444; color:white;" onclick="submitRequestDelete()">Delete Request</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const overviewLabels = <?= json_encode($overview_labels) ?>;
        const installationMavg = <?= json_encode($installation_mavg) ?>;
        const submissionMavg = <?= json_encode($submission_mavg) ?>;
        const approvalMavg = <?= json_encode($approval_mavg) ?>;
        const rejectionMavg = <?= json_encode($rejection_mavg) ?>;

        function renderOverviewChart() {
            const ctx = document.getElementById('overviewChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: overviewLabels,
                    datasets: [
                        {
                            label: 'Installation Requests (7-day avg)',
                            data: installationMavg,
                            borderColor: '#f97316',
                            backgroundColor: 'rgba(249, 115, 22, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Submissions (7-day avg)',
                            data: submissionMavg,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Approvals (7-day avg)',
                            data: approvalMavg,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Rejections (7-day avg)',
                            data: rejectionMavg,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#475569' } },
                        y: { beginAtZero: true, ticks: { color: '#475569' }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#1f2937' } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            renderOverviewChart();
            initSectionNavigation();
        });

        function initSectionNavigation() {
            const tabs = Array.from(document.querySelectorAll('.status-tab'));
            const sections = tabs.map(tab => document.querySelector(tab.getAttribute('href'))).filter(Boolean);
            if (!tabs.length || !sections.length) return;

            function setActiveTab(activeTab) {
                tabs.forEach(tab => tab.classList.toggle('active', tab === activeTab));
            }

            tabs.forEach(tab => {
                const targetId = tab.getAttribute('href');
                if (!targetId || !targetId.startsWith('#')) return;
                tab.addEventListener('click', function(event) {
                    const targetSection = document.querySelector(targetId);
                    if (!targetSection) return;
                    event.preventDefault();
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    setActiveTab(tab);
                });
            });

            function updateActiveTabOnScroll() {
                const scrollPosition = window.scrollY + 120;
                let currentTab = tabs[0];
                sections.forEach((section, index) => {
                    if (section.offsetTop <= scrollPosition) {
                        currentTab = tabs[index];
                    }
                });
                setActiveTab(currentTab);
            }

            let scrollTimeout;
            window.addEventListener('scroll', function() {
                if (scrollTimeout) return;
                scrollTimeout = setTimeout(function() {
                    updateActiveTabOnScroll();
                    scrollTimeout = null;
                }, 50);
            });

            updateActiveTabOnScroll();
        }

        function openProductModal(productId, action) {
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalAction').value = action;

            const modal = document.getElementById('productVerificationModal');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('modalSubmitBtn');

            if (action === 'confirm') {
                title.textContent = 'Confirm Product';
                submitBtn.textContent = 'Confirm Product';
                submitBtn.style.background = '#10b981';
            } else if (action === 'reject') {
                title.textContent = 'Reject Product';
                submitBtn.textContent = 'Reject Product';
                submitBtn.style.background = '#f59e0b';
            } else if (action === 'suspend') {
                title.textContent = 'Suspend Product';
                submitBtn.textContent = 'Suspend Product';
                submitBtn.style.background = '#8b5cf6';
            } else if (action === 'delete') {
                title.textContent = 'Delete Product';
                submitBtn.textContent = 'Delete Product';
                submitBtn.style.background = '#ef4444';
            }

            modal.style.display = 'block';
        }

        function closeProductModal() {
            document.getElementById('productVerificationModal').style.display = 'none';
            document.getElementById('admin_notes').value = '';
        }

        function openRequestModal(request) {
            document.getElementById('requestModalId').value = request.id;
            document.getElementById('requestModalClientName').textContent = request.client_name || 'Unknown';
            document.getElementById('requestModalContact').textContent = request.contact_number || 'N/A';
            document.getElementById('requestModalEmail').textContent = request.email || 'N/A';
            document.getElementById('requestModalService').textContent = {
                'wifi_distribution': 'WiFi Distribution',
                'cctv_installation': 'CCTV Installation',
                'alexa_installation': 'Alexa Installation',
                'digital_product': 'Digital Product Upload',
                'digital_installation': 'Digital Service Installation'
            }[request.consultation_type] || request.consultation_type;
            document.getElementById('requestModalProductTitle').textContent = request.product_title || 'N/A';
            document.getElementById('requestModalSchedule').textContent = request.scheduled_date ? new Date(request.scheduled_date).toLocaleString() : 'No preference';
            document.getElementById('requestModalStatusLabel').textContent = request.status ? request.status.charAt(0).toUpperCase() + request.status.slice(1) : 'Unknown';
            document.getElementById('requestModalAgent').textContent = request.agent_first_name || request.agent_last_name ? (request.agent_first_name || '') + ' ' + (request.agent_last_name || '') : 'Unassigned';
            document.getElementById('requestModalCreated').textContent = request.created_at ? new Date(request.created_at).toLocaleString() : 'Unknown';
            document.getElementById('requestModalIssue').textContent = request.issue_description || 'No description provided.';
            document.getElementById('requestModalNotes').textContent = request.admin_notes || 'No admin notes yet.';
            document.getElementById('requestModalMpesaName').textContent = request.mpesa_name || 'N/A';
            document.getElementById('requestModalMpesaCode').textContent = request.mpesa_code || 'N/A';
            document.getElementById('requestModalMpesaContact').textContent = request.mpesa_contact || 'N/A';
            document.getElementById('requestModalMpesaTime').textContent = request.mpesa_time || 'N/A';
            document.getElementById('requestModalPaymentAmount').textContent = request.payment_amount ? 'KES ' + parseFloat(request.payment_amount).toLocaleString() : 'N/A';
            document.getElementById('requestModalPaymentStatus').textContent = request.payment_status ? request.payment_status.charAt(0).toUpperCase() + request.payment_status.slice(1).replace('_', ' ') : 'N/A';
            const screenshotImg = document.getElementById('requestModalScreenshot');
            if (request.screenshot_path) {
                screenshotImg.src = request.screenshot_path;
                screenshotImg.style.display = 'block';
                screenshotImg.onerror = function() {
                    this.style.display = 'none';
                };
            } else {
                screenshotImg.style.display = 'none';
            }
            document.getElementById('requestModalStatus').value = request.status || 'pending';
            document.getElementById('requestModalAssignedAgent').value = request.agent_id || '';
            document.getElementById('requestModalAdminNotes').value = request.admin_notes || '';
            document.getElementById('requestDetailsModal').style.display = 'block';
        }

        function closeRequestModal() {
            document.getElementById('requestDetailsModal').style.display = 'none';
            document.getElementById('requestModalAdminNotes').value = '';
        }

        function submitRequestDelete() {
            document.getElementById('requestModalActionField').value = 'delete_request';
            document.getElementById('requestDetailsForm').submit();
        }

        // Close modal when clicking outside
        document.getElementById('productVerificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });
        document.getElementById('requestDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRequestModal();
            }
        });
    </script>
</body>
</html>
