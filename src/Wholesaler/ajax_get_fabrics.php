<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    echo json_encode([]);
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}
$mysqli->set_charset("utf8mb4");

$manufacturer_id = isset($_GET['manufacturer_id']) ? (int)$_GET['manufacturer_id'] : 0;
$wholesaler_id   = (int)$_SESSION['user_id'];

if (!$manufacturer_id) {
    echo json_encode([]);
    exit;
}

$sql = "
SELECT
    f.fabric_id,
    f.name
FROM fabrics f
WHERE f.user_id = ?                      -- manufacturer (users.user_id)
  AND f.status = 'approved'
  AND f.is_active = 1

  AND NOT EXISTS (
      SELECT 1
      FROM exclusivity_agreements ea
      WHERE ea.manufacturer_id = f.user_id
        AND ea.status IN ('pending_manufacturer','active')
        AND (
              ea.end_date IS NULL
              OR ea.end_date >= CURDATE()
            )
        AND FIND_IN_SET(
              f.fabric_id,
              REPLACE(REPLACE(ea.fabric_ids,'[',''),']','')
            ) > 0
  )

ORDER BY f.name
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

/**
 * IMPORTANT:
 * There are ONLY 2 placeholders → "ii"
 */
$stmt->bind_param("i", $manufacturer_id);
$stmt->execute();
$result = $stmt->get_result();

$out = [];
while ($row = $result->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out);
