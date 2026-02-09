<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$manufacturer_id = $_SESSION['user_id'];
$agreement_id = (int)($_GET['id'] ?? 0);

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
$mysqli->set_charset("utf8mb4");

/* ================= FETCH AGREEMENT ================= */
$stmt = $mysqli->prepare("
    SELECT 
        a.*,
        u.company_name AS wholesaler_name
    FROM exclusivity_agreements a
    JOIN users u ON u.user_id = a.wholesaler_id
    WHERE a.agreement_id = ?
      AND a.manufacturer_id = ?
");
$stmt->bind_param("ii", $agreement_id, $manufacturer_id);
$stmt->execute();
$agreement = $stmt->get_result()->fetch_assoc();

if (!$agreement) {
    die("Unauthorized access");
}
$status = strtolower(trim($agreement['status']));
$action = $_POST['action'] ?? null;
/* ================= REJECT (DELETE) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject') {
    if ($status !== 'pending_manufacturer') {
        die("Invalid action");
    }

    $upd = $mysqli->prepare("
        UPDATE exclusivity_agreements
        SET status = 'declined'
        WHERE agreement_id = ? AND manufacturer_id = ?
    ");
    $upd->bind_param("ii", $agreement_id, $manufacturer_id);
    $upd->execute();

    header("Location: ex_agreements_manufacturer.php");
    exit();
}
/* ================= ACCEPT & SIGN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'accept') {
    if ($status !== 'pending_manufacturer') {
        die("Invalid action");
    }

    $signature = $_POST['signature'] ?? '';
    if (!$signature) die("Signature required");

    $sig = base64_decode(
        preg_replace('#^data:image/\w+;base64,#', '', $signature)
    );

    $dir = $_SERVER['DOCUMENT_ROOT'] . "/trial_project/uploads/signatures/";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$path = $dir . "wholesaler_" . time() . ".png";

    file_put_contents($path, $sig);

    $upd = $mysqli->prepare("
        UPDATE exclusivity_agreements
        SET status = 'active',
            manufacturer_signature = ?,
            manufacturer_signed_at = NOW()
        WHERE agreement_id = ?
    ");
    $upd->bind_param("si", $path, $agreement_id);
    $upd->execute();

    header("Location: ex_agreements_manufacturer.php");
    exit();
}
/* ================= FETCH FABRICS ================= */
$fabric_ids = json_decode($agreement['fabric_ids'], true) ?? [];
$fabrics = [];

if (!empty($fabric_ids)) {
    $placeholders = implode(',', array_fill(0, count($fabric_ids), '?'));
    $types = str_repeat('i', count($fabric_ids));

    $stmtF = $mysqli->prepare("
        SELECT fabric_id, name
        FROM fabrics
        WHERE fabric_id IN ($placeholders)
    ");
    $stmtF->bind_param($types, ...$fabric_ids);
    $stmtF->execute();
    $res = $stmtF->get_result();

    while ($row = $res->fetch_assoc()) {
        $fabrics[] = $row;
    }
    $stmtF->close();
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>View Agreement</title>
<link rel="stylesheet" href="../css_all_pages.css">
</head>

<body style="background:#f4efe7;">

<div style="
  max-width:900px;
  margin:40px auto;
  background:#fff;
  padding:35px;
  border-radius:16px;
  box-shadow:0 12px 30px rgba(0,0,0,.12);
">

  <h2 style="text-align:center;color:#6d4c1e;margin-bottom:8px;">
    Exclusivity Agreement
  </h2>

  <p style="text-align:center;color:#777;font-size:14px;margin-bottom:25px;">
    Agreement ID: EA<?= str_pad($agreement_id,5,'0',STR_PAD_LEFT) ?>
  </p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <p><strong>Wholesaler:</strong><br><?= htmlspecialchars($agreement['wholesaler_name']) ?></p>
    <p><strong>Status:</strong><br><?= ucwords(str_replace('_',' ', $agreement['status'])) ?></p>
  </div>

  <p style="margin-top:10px;">
    <strong>Duration:</strong><br>
    <?= date('d M Y', strtotime($agreement['start_date'])) ?> –
    <?= date('d M Y', strtotime($agreement['end_date'])) ?>
  </p>

  <!-- ================= FABRICS ================= -->
  <hr style="margin:25px 0">

  <h4 style="color:#6d4c1e;">Exclusive Fabrics</h4>

  <?php if ($fabrics): ?>
    <table style="
      width:100%;
      border-collapse:collapse;
      margin-top:10px;
      background:#fff9e6;
      border-radius:10px;
      overflow:hidden;
    ">
      <tr style="background:#e7d2a3;">
        <th style="padding:10px;text-align:left;">Fabric ID</th>
        <th style="padding:10px;text-align:left;">Fabric Name</th>
      </tr>
      <?php foreach ($fabrics as $f): ?>
      <tr>
        <td style="padding:10px;border-top:1px solid #ddd;">
          F<?= str_pad($f['fabric_id'],4,'0',STR_PAD_LEFT) ?>
        </td>
        <td style="padding:10px;border-top:1px solid #ddd;">
          <?= htmlspecialchars($f['name']) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p>No fabrics listed.</p>
  <?php endif; ?>

  <!-- ================= TERMS ================= -->
  <hr style="margin:25px 0">

  <h4 style="color:#6d4c1e;">Terms & Conditions</h4>
  <p style="line-height:1.6;color:#444;">
    <?= nl2br(htmlspecialchars($agreement['terms'])) ?>
  </p>

  <!-- ================= ACTIONS ================= -->
  <?php if ($status === 'pending_manufacturer'): ?>

  <hr style="margin:30px 0">

  <div style="display:flex;gap:15px;">
    <button class="submit-btn" onclick="showSignature()">Accept</button>

    <form method="POST"
          onsubmit="return confirm('Reject this agreement?');">
      <input type="hidden" name="action" value="reject">
      <button class="submit-btn" style="background:#b33;">
        Reject
      </button>
    </form>
  </div>

  <!-- ================= SIGNATURE ================= -->
  <form method="POST" id="sign-form" style="display:none;margin-top:25px;">
    <input type="hidden" name="action" value="accept">
    <input type="hidden" name="signature" id="signature">

    <h4 style="color:#6d4c1e;">Digital Signature</h4>

    <canvas id="signature-pad"
      style="
        width:100%;
        height:180px;
        border:2px dashed #c9a24d;
        border-radius:10px;
        background:#fff;
      ">
    </canvas>

    <div style="margin-top:15px;display:flex;gap:10px;">
      <button type="button"
              class="submit-btn"
              style="background:#999;"
              onclick="clearSig()">Clear</button>

      <button type="submit"
              class="submit-btn">
        Confirm & Sign
      </button>
    </div>
  </form>

  <?php endif; ?>

</div>

<script>
const canvas = document.getElementById("signature-pad");
let ctx, drawing = false;

function showSignature() {
  document.getElementById("sign-form").style.display = "block";
  setTimeout(initCanvas, 50);
}

function initCanvas() {
  ctx = canvas.getContext("2d");
  const r = canvas.getBoundingClientRect();
  canvas.width = r.width;
  canvas.height = r.height;

  canvas.style.touchAction = "none";

  ctx.lineWidth = 2;
  ctx.lineCap = "round";
  ctx.strokeStyle = "#5a3e1b";

  canvas.onmousedown = e => {
    drawing = true;
    ctx.beginPath();
    ctx.moveTo(e.offsetX, e.offsetY);
  };

  canvas.onmousemove = e => {
    if (!drawing) return;
    ctx.lineTo(e.offsetX, e.offsetY);
    ctx.stroke();
  };

  canvas.onmouseup = endDraw;
  canvas.onmouseleave = endDraw;

  canvas.ontouchstart = e => {
    e.preventDefault();
    const t = e.touches[0];
    const r = canvas.getBoundingClientRect();
    drawing = true;
    ctx.beginPath();
    ctx.moveTo(t.clientX - r.left, t.clientY - r.top);
  };

  canvas.ontouchmove = e => {
    e.preventDefault();
    if (!drawing) return;
    const t = e.touches[0];
    const r = canvas.getBoundingClientRect();
    ctx.lineTo(t.clientX - r.left, t.clientY - r.top);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(t.clientX - r.left, t.clientY - r.top);
  };

  canvas.ontouchend = endDraw;
}

function endDraw() {
  if (!drawing) return;
  drawing = false;
  document.getElementById("signature").value = canvas.toDataURL();
}

function clearSig() {
  ctx.clearRect(0,0,canvas.width,canvas.height);
  document.getElementById("signature").value = "";
}
</script>

</body>
</html>

<?php $mysqli->close(); ?>
