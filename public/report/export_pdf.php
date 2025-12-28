<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/report_model.php';
require_login();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$r = getReport($pdo, $id);
if (!$r) { http_response_code(404); echo 'Not found'; exit; }

// Dompdf integration note: ensure composer autoload exists and fonts support Devanagari (DejaVu Sans)
if (!class_exists('Dompdf\\Dompdf')) { die('Dompdf not installed. Run composer require dompdf/dompdf'); }

$data = $r['data'] ?? [];
$html = '<html><meta charset="UTF-8"><style>body{font-family: DejaVu Sans, sans-serif;} table{width:100%;border-collapse:collapse} td,th{border:1px solid #ccc;padding:6px}</style><body>';
$html .= '<h3 style="text-align:center">WCL Rajbhasha Report #'.(int)$r['id'].'</h3>';
$html .= '<p>Unit: '.htmlspecialchars($r['unit_name']).' | Period: Q'.(int)$r['period_quarter'].' '.(int)$r['period_year'].' | By: '.htmlspecialchars($r['user_name']).'</p>';
$html .= '<table><tr><th>Field</th><th>Value</th></tr>';
foreach ($data as $k=>$v) { $html.='<tr><td>'.htmlspecialchars($k).'</td><td>'.htmlspecialchars((string)$v).'</td></tr>'; }
$html .= '</table></body></html>';

$dompdf = new Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans']);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4');
$dompdf->render();
$dompdf->stream('report_'.$r['id'].'.pdf', ['Attachment'=>true]);
