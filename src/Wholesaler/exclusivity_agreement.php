<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}
$wholesaler_id = $_SESSION['user_id'];

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
$mysqli->set_charset("utf8mb4");

/* =========================
   HANDLE REQUEST + SIGN
   ========================= */
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_exclusivity') {

    $manufacturer_id = (int)$_POST['manufacturer_id'];
    $start_date      = $_POST['start_date'];
    $end_date        = $_POST['end_date'];
    $terms           = trim($_POST['terms'] ?? '');
    $signature       = $_POST['signature'] ?? '';
    $fabric_ids      = $_POST['fabrics'] ?? [];

    if (
        !$manufacturer_id ||
        !$start_date ||
        !$end_date ||
        empty($fabric_ids) ||
        empty($signature)
    ) {
        $message = "All fields including digital signature are required.";
    } else {

        // Save signature
        $sig = base64_decode(str_replace('data:image/png;base64,', '', $signature));
       $dir = $_SERVER['DOCUMENT_ROOT'] . "/trial_project/uploads/signatures/";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$path = $dir . "wholesaler_" . time() . ".png";

        file_put_contents($path, $sig);

        $fabric_json = json_encode(array_map('intval', $fabric_ids));

        $stmt = $mysqli->prepare("
            INSERT INTO exclusivity_agreements
            (wholesaler_id, manufacturer_id, fabric_ids, start_date, end_date, terms,
             status, wholesaler_signature, wholesaler_signed_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_manufacturer', ?, NOW())
        ");
        $stmt->bind_param(
            "iisssss",
            $wholesaler_id,
            $manufacturer_id,
            $fabric_json,
            $start_date,
            $end_date,
            $terms,
            $path
        );
        $stmt->execute();
        $stmt->close();

        $message = "Exclusivity request sent to manufacturer.";
    }
}

/* =========================
   FETCH AGREEMENTS
   ========================= */
$agreements = $mysqli->query("
    SELECT 
        a.agreement_id,
        a.start_date,
        a.end_date,
        a.status,
        u.company_name AS manufacturer
    FROM exclusivity_agreements a
    JOIN users u ON u.user_id = a.manufacturer_id
    WHERE a.wholesaler_id = $wholesaler_id
    ORDER BY a.created_at DESC
");

/* =========================
   MANUFACTURERS
   ========================= */
$manufacturers = $mysqli->query("
    SELECT user_id, company_name
    FROM users
    WHERE role='manufacturer' AND account_status='active'
    ORDER BY company_name
");

/* =========================
   FABRICS (OWNED / AVAILABLE)
   ========================= */
$fabrics = $mysqli->query("
    SELECT fabric_id, name
    FROM fabrics
    WHERE is_active = 1
    ORDER BY name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exclusivity Agreements</title>
<link rel="stylesheet" href="../css_all_pages.css">

<style>
/* === Modal === */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.55);
  z-index: 9999 !important;
}
.modal-box {
  background:#f6efe6;
  width:850px;
  max-width:95%;
  margin:4% auto;
  padding:30px;
  border-radius:14px;
  box-shadow:0 12px 40px rgba(0,0,0,.35);
  font-family: Georgia, serif;

  max-height: 85vh;        /* ✅ KEY */
  overflow-y: auto;        /* ✅ KEY */
}
.modal-box h2 {
  color:#6d4c1e;
  margin-bottom:20px;
}

.row {
  display:flex;
  gap:20px;
  margin-bottom:16px;
}

.field {
  flex:1;
}

label {
  font-weight:600;
  color:#5a3e1b;
}

select, input, textarea {
  width:100%;
  padding:10px;
  border-radius:8px;
  border:1.5px solid #c9a24d;
  background:#fff;
}

textarea { min-height:90px; }

canvas {
  width:100%;
  height:180px;
  border:2px dashed #c9a24d;
  border-radius:10px;
  background:#fff;
}

.actions {
  display:flex;
  gap:12px;
  justify-content:flex-end;
  margin-top:20px;
}

.btn {
  padding:10px 22px;
  border-radius:20px;
  border:none;
  cursor:pointer;
  font-size:1rem;
}

.btn.gold { background:#c9a24d; color:#fff; }
.btn.gray { background:#aaa; color:#fff; }
.btn.dark { background:#444; color:#fff; }

#bg-video {
  pointer-events: none !important;
  z-index: -1 !important;
}

.header,
.container,
.list-section,
.submit-btn,
table {
  position: relative;
  z-index: 10;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: rgba(255,255,255,0.95);
  border-radius: 12px;
  overflow: hidden;
}

th {
  background: #e0c68c;
  color: #5a3e1b;
  padding: 12px;
}

td {
  padding: 12px;
  border-top: 1px solid #eee;
}

.list-section {
  margin-top: 40px;
}

</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="header">
  <a href="home_page.php" class="back-link">← Home</a>
  <div class="header-title">Exclusivity Agreements</div>
</div>

<div class="container">
<div class="list-section">

<?php if ($message): ?>
<div class="msg success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<table>
<thead>
<tr>
  <th>ID</th>
  <th>Manufacturer</th>
  <th>Duration</th>
  <th>Status</th>
  <th>Action</th>
</tr>
</thead>
<tbody>
<?php while($a = $agreements->fetch_assoc()): ?>
<tr>
  <td>EA<?= str_pad($a['agreement_id'],5,'0',STR_PAD_LEFT) ?></td>

  <td><?= htmlspecialchars($a['manufacturer']) ?></td>

  <td>
    <?= date('d/m/Y',strtotime($a['start_date'])) ?>
    –
    <?= date('d/m/Y',strtotime($a['end_date'])) ?>
  </td>

  <td>
    <span class="status <?= $a['status'] ?>">
      <?= ucwords(str_replace('_',' ',$a['status'])) ?>
    </span>
  </td>

  <td>
    <a
      href="/trial_project/src/download_agreement.php?id=<?= (int)$a['agreement_id'] ?>"
      class="submit-btn"
      style="padding:6px 14px;"
    >
      Download
    </a>
  </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<div style="text-align:center;margin-top:30px;">
<button
  type="button"
  class="submit-btn"
  id="open-exclusivity-btn">
  Request New Exclusivity ₹
</button>
</div>

</div>
</div>

<!-- ================= MODAL ================= -->
<!-- ================= MODAL ================= -->
<div class="modal" id="exclusivityModal">
  <div class="modal-box">

    <h2>Request New Exclusivity</h2>

    <form method="POST">
      <input type="hidden" name="action" value="request_exclusivity">

      <!-- ROW 1 -->
      <div class="row">
        <div class="field">
          <label>Manufacturer *</label>
          <select name="manufacturer_id" id="manufacturer-select" required>
  <option value="">Select manufacturer</option>
  <?php while ($m = $manufacturers->fetch_assoc()): ?>
    <option value="<?= $m['user_id'] ?>">
      <?= htmlspecialchars($m['company_name']) ?>
    </option>
  <?php endwhile; ?>
</select>
        </div>

        <div class="field">
          <label>Fabrics *</label>
          <select name="fabrics[]" id="fabrics-select" multiple required>
            <option value="">Select manufacturer first</option>
          </select>
        </div>
      </div>

      <!-- ROW 2 -->
      <div class="row">
        <div class="field">
          <label>Start Date *</label>
          <input type="date" name="start_date" required>
        </div>
        <div class="field">
          <label>End Date *</label>
          <input type="date" name="end_date" required>
        </div>
      </div>

      <!-- TERMS -->
      <div class="field">
        <label>Terms & Conditions</label>
        <textarea name="terms"></textarea>
      </div>

      <!-- SIGNATURE -->
      <div class="field">
        <label>Digital Signature *</label>
        <canvas id="signature-pad"></canvas>
        <input type="hidden" name="signature" id="signature">
      </div>

      <!-- ACTIONS -->
      <div class="actions">
        
       <button type="button" class="btn gray" onclick="clearSignature()">
  Clear
</button>

<button type="submit" class="btn gold">
  Submit Request
</button>

<button type="button" class="btn dark" onclick="document.getElementById('exclusivityModal').style.display='none'">
  Cancel
</button>

      </div>

    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

  /* ================= MODAL ================= */
  const modal   = document.getElementById("exclusivityModal");
  const openBtn = document.getElementById("open-exclusivity-btn");

  openBtn.addEventListener("click", function () {
    modal.style.display = "block";
    setTimeout(initCanvas, 100); // init AFTER visible
  });

  window.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.style.display = "none";
    }
  });
   // 🔥 FORCE initial load if a manufacturer is already selected
  const manufacturerSelect = document.getElementById("manufacturer-select");
  if (manufacturerSelect && manufacturerSelect.value) {
    manufacturerSelect.dispatchEvent(new Event("change"));
  }

  /* ================= SIGNATURE ================= */
  let canvas, ctx, drawing = false;

  function initCanvas() {
    canvas = document.getElementById("signature-pad");
    ctx = canvas.getContext("2d");

    const rect = canvas.getBoundingClientRect();
    canvas.width  = rect.width;
    canvas.height = rect.height;

    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#6d4c1e";

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
  const x = t.clientX - r.left;
  const y = t.clientY - r.top;

  ctx.lineTo(x, y);
  ctx.stroke();
  ctx.beginPath();       // 👈 CRITICAL
  ctx.moveTo(x, y);      // 👈 CRITICAL
};
    canvas.ontouchend = endDraw;
  }

  function endDraw() {
    if (!drawing) return;
    drawing = false;
    document.getElementById("signature").value =
      canvas.toDataURL("image/png");
  }

  window.clearSignature = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById("signature").value = "";
  };

  /* ================= FABRICS AJAX ================= */
  document
    .getElementById("manufacturer-select")
    .addEventListener("change", function () {

      const manufacturerId = this.value;
      const fabricSelect = document.getElementById("fabrics-select");

      fabricSelect.innerHTML = "<option>Loading…</option>";

      if (!manufacturerId) {
        fabricSelect.innerHTML = "<option>Select manufacturer first</option>";
        return;
      }

      fetch("ajax_get_fabrics.php?manufacturer_id=" + manufacturerId)
  .then(r => {
    if (!r.ok) throw new Error("HTTP " + r.status);
    return r.json();
  })
  .then(data => {
    fabricSelect.innerHTML = "";
    if (!data.length) {
      fabricSelect.innerHTML = "<option>No fabrics available</option>";
      return;
    }
    data.forEach(f => {
      const opt = document.createElement("option");
      opt.value = f.fabric_id;
      opt.textContent = f.name;
      fabricSelect.appendChild(opt);
    });
  })
  .catch(err => {
    console.error("Fabric load error:", err);
    fabricSelect.innerHTML = "<option>Error loading fabrics</option>";
  });
    });

});
</script>

</body>
</html>

<?php $mysqli->close(); ?>
