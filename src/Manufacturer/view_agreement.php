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
/* ================= REJECT (DELETE) ================= */
if ($_POST['action'] === 'reject') {
    if ($status !== 'pending_wholesaler') {
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
if ($_POST['action'] === 'accept') {
    if ($status !== 'pending_wholesaler') {
        die("Invalid action");
    }

    $signature = $_POST['signature'] ?? '';
    if (!$signature) die("Signature required");

    $sig = base64_decode(
        preg_replace('#^data:image/\w+;base64,#', '', $signature)
    );

    $dir = "../../uploads/signatures/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $path = $dir . "manufacturer_" . $agreement_id . ".png";
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

    header("Location: manufacturer_exclusivity.php");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>View Agreement</title>
<link rel="stylesheet" href="../css_all_pages.css">
</head>

<body>

<h2>Exclusivity Agreement</h2>

<p><strong>Wholesaler:</strong> <?= htmlspecialchars($agreement['wholesaler_name']) ?></p>
<p><strong>Duration:</strong>
  <?= date('d M Y', strtotime($agreement['start_date'])) ?> –
  <?= date('d M Y', strtotime($agreement['end_date'])) ?>
</p>

<p><strong>Status:</strong>
  <?= ucwords(str_replace('_',' ', $agreement['status'])) ?>
</p>

<hr>

<h4>Terms & Conditions</h4>
<p><?= nl2br(htmlspecialchars($agreement['terms'])) ?></p>

<hr>

<?php if ($status === 'pending_wholesaler'): ?>

<!-- ================= ACTION BUTTONS ================= -->
<div style="display:flex;gap:15px;margin-top:20px;">

  <button class="submit-btn" onclick="showSignature()">
    Accept
  </button>

  <form method="POST" onsubmit="return confirm('Reject this agreement? It will be deleted.');">
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

  <h4>Digital Signature</h4>
  <canvas id="signature-pad"
    style="width:100%;height:180px;border:2px dashed #c9a24d;border-radius:8px;">
  </canvas>

  <div style="margin-top:15px;display:flex;gap:10px;">
    <button type="button" class="submit-btn" style="background:#999;" onclick="clearSig()">Clear</button>
    <button type="submit" class="submit-btn">Confirm & Sign</button>
  </div>
</form>

<?php endif; ?>

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
