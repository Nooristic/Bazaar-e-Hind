<?php
header('Content-Type: application/json');

try {
    $mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
    $mysqli->set_charset("utf8mb4");

    $manufacturer_id = (int)($_GET['manufacturer_id'] ?? 0);
    if (!$manufacturer_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $mysqli->prepare("
    SELECT fabric_id, name
    FROM fabrics
    WHERE user_id = ?          
      AND is_active = 1
      AND visibility_type = 'public'
    ORDER BY name
");
    $stmt->bind_param("i", $manufacturer_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];

    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    echo json_encode($out);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([]);
}
