<?php
require_once 'C:\xampp\htdocs\snmp_simple\tcpdf\tcpdf.php';
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->Write(0, 'Test TCPDF');
$pdf->Output('C:\xampp\htdocs\snmp_simple\reports\test_tcpdf.pdf', 'F');
echo "PDF créé!";
?>