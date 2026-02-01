<?php
require('../../vendor/fpdf/fpdf.php');
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");

$payment_id = $_POST['payment_id'];

/* Prevent duplicate receipt */
$exists = $conn->query("
  SELECT receipt_id FROM receipts WHERE payment_id = $payment_id
");

if ($exists->num_rows > 0) {
    header("Location: payment_management.php?msg=receipt_exists");
    exit();
}

/* Create receipt record */
$conn->query("
  INSERT INTO receipts (payment_id) VALUES ($payment_id)
");

$payment = $conn->query("
  SELECT * FROM payments WHERE payment_id = $payment_id
")->fetch_assoc();

/* Generate PDF */
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);

$pdf->Cell(0,10,'Payment Receipt',0,1,'C');
$pdf->Ln(10);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Payment ID: '.$payment_id,0,1);
$pdf->Cell(0,8,'Amount: ₹'.$payment['amount'],0,1);
$pdf->Cell(0,8,'Method: '.$payment['method'],0,1);
$pdf->Cell(0,8,'Status: Completed',0,1);

$pdf->Output('D', 'receipt_'.$payment_id.'.pdf');
