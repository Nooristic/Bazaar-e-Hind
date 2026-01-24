<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    die("Unauthorized");
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ---------------- IMAGE EMBED (BASE64 for local) ---------------- */
function dompdfImage($path)
{
    if (!$path) return null;

    // If URL (http/https) - keep as is (remote enabled)
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }

    // Convert relative → absolute
    if (!file_exists($path)) {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    }

    if (!file_exists($path)) return null;

    // Embed as base64
    $data = file_get_contents($path);
    $type = pathinfo($path, PATHINFO_EXTENSION);
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

/* ---------------- DB ---------------- */
$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("DB error");

/* ---------------- ID ---------------- */
$agreement_id = (int)($_GET['id'] ?? 0);
if (!$agreement_id) die("Invalid agreement");

/* ---------------- FETCH ---------------- */
$stmt = $mysqli->prepare("
SELECT 
    a.*,
    m.username AS manufacturer_name,
    m.logo_url AS manufacturer_logo,
    w.username AS wholesaler_name,
    w.logo_url AS wholesaler_logo
FROM exclusivity_agreements a
JOIN users m ON a.manufacturer_id = m.user_id
JOIN users w ON a.wholesaler_id = w.user_id
WHERE a.agreement_id = ?
");
$stmt->bind_param("i", $agreement_id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();

if (!$a) die("Agreement not found");
/* ---------------- ACCESS CONTROL ---------------- */
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

if (
    ($role === 'manufacturer' && (int)$a['manufacturer_id'] !== $user_id) ||
    ($role === 'wholesaler'   && (int)$a['wholesaler_id']   !== $user_id)
) {
    die("Unauthorized access");
}
/* ---------------- IMAGES ---------------- */
$man_logo = dompdfImage($a['manufacturer_logo']);
$wh_logo  = dompdfImage($a['wholesaler_logo']);

$man_sign = dompdfImage($a['manufacturer_signature']);
$wh_sign  = dompdfImage($a['wholesaler_signature']);

/* ---------------- DOMPDF ---------------- */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

/* ---------------- HTML ---------------- */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: DejaVu Sans;
    color:#333;
}
.header {
    position: relative;
    border-bottom: 3px solid #c9a24d;
    padding-bottom: 12px;
    margin-bottom: 25px;
}
.header h1 {
    text-align:center;
    color:#8b5e1a;
    margin:0;
}
.logo-left, .logo-right {
    position:absolute;
    top:0;
}
.logo-left { left:0; }
.logo-right { right:0; }
.logo-left img, .logo-right img {
    height:55px;
}
.box {
    background:#fff9e6;
    border:1px solid #e5c87a;
    padding:15px;
    border-radius:8px;
    margin-bottom:18px;
}
.label {
    font-weight:bold;
    color:#8b5e1a;
}
.signatures {
    margin-top:40px;
}
.sig {
    width:45%;
    display:inline-block;
    text-align:center;
    vertical-align:top;
}
.sig img {
    width:200px;
    height:auto;
    margin:10px 0;
}
.line {
    border-top:1px solid #999;
    margin-top:12px;
}
.date {
    font-size:12px;
    color:#666;
}
</style>
</head>
<body>

<div class="header">
    <div class="logo-left">'.($man_logo ? '<img src="'.$man_logo.'">' : '').'</div>
    <h1>Exclusivity Agreement</h1>
    <div class="logo-right">'.($wh_logo ? '<img src="'.$wh_logo.'">' : '').'</div>
</div>

<div class="box">
    <p><span class="label">Manufacturer:</span> '.htmlspecialchars($a['manufacturer_name']).'</p>
    <p><span class="label">Wholesaler:</span> '.htmlspecialchars($a['wholesaler_name']).'</p>
    <p><span class="label">Duration:</span> '.date("d M Y", strtotime($a['start_date'])).' to '.date("d M Y", strtotime($a['end_date'])).'</p>
</div>

<div class="box">
    <p class="label">Terms & Conditions</p>
    <p>'.nl2br(htmlspecialchars($a['terms'])).'</p>
</div>

<div class="signatures">

    <div class="sig">
        <strong>Manufacturer</strong><br>';
        if ($man_sign) {
            $man_date = $a['manufacturer_signed_at'] ?? $a['created_at'];
            $html .= '<img src="'.$man_sign.'"><div class="date">Signed on '.date("d M Y", strtotime($man_date)).'</div>';
        } else {
            $html .= '<div class="date">Not signed</div>';
        }
$html .= '
        <div class="line"></div>
        '.htmlspecialchars($a['manufacturer_name']).'
    </div>

    <div class="sig" style="float:right;">
        <strong>Wholesaler</strong><br>';
        if ($wh_sign) {
            $wh_date = $a['wholesaler_signed_at'] ?? $a['created_at'];
            $html .= '<img src="'.$wh_sign.'"><div class="date">Signed on '.date("d M Y", strtotime($wh_date)).'</div>';
        } else {
            $html .= '<div class="date">Not signed</div>';
        }
$html .= '
        <div class="line"></div>
        '.htmlspecialchars($a['wholesaler_name']).'
    </div>

</div>

</body>
</html>
';

/* ---------------- RENDER ---------------- */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream(
    "Exclusivity_Agreement_{$agreement_id}.pdf",
    ["Attachment" => true]
);
exit;