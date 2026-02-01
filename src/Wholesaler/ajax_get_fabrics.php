<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    echo json_encode([]);
    exit;
}

try {
    $mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
    if ($mysqli->connect_error) {
        throw new Exception("DB error");
    }
    $mysqli->set_charset("utf8mb4");

    $manufacturer_id = (int)($_GET['manufacturer_id'] ?? 0);
    $wholesaler_id   = (int)$_SESSION['user_id'];

    if (!$manufacturer_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT DISTINCT
            f.fabric_id,
            f.name
        FROM fabrics f

        LEFT JOIN fabric_exclusivity_agreements fe
          ON fe.fabric_id = f.fabric_id
          AND fe.is_active = 1
          AND CURDATE() BETWEEN fe.start_date AND fe.end_date

        WHERE f.user_id = ?
          AND f.status = 'approved'
          AND f.is_active = 1
          AND (
              f.visibility_type = 'public'
              OR fe.wholesaler_id = ?
          )
        ORDER BY f.name
    ");

    $stmt->bind_param("ii", $manufacturer_id, $wholesaler_id);
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
