<?php
require_once '../shared/bootstrap.php';

$conn = getDB();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['request_id'])) {
    http_response_code(400);
    exit('Missing request_id');
}

$request_id = intval($_GET['request_id']);
$admin_barangay_id = $_SESSION['barangay_id'] ?? null;
$is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin';

$stmt = $conn->prepare("
    SELECT cr.*, 
           c.first_name, c.last_name, c.address,
           b.name AS barangay_name,
           dt.name AS document_name,
           dt.id AS document_type_id
    FROM citizen_requests cr
    JOIN citizens c ON cr.citizen_id = c.id
    LEFT JOIN barangays b ON c.barangay_id = b.id
    LEFT JOIN document_types dt ON cr.document_type_id = dt.id
    WHERE cr.id = ?
    " . (!$is_super_admin ? " AND c.barangay_id = ?" : "")
);

$params = [$request_id];
$types = 'i';

if (!$is_super_admin) {
    $params[] = $admin_barangay_id;
    $types .= 'i';
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    http_response_code(404);
    exit('Request not found or access denied');
}

// Update DB after file is saved

$template_file = match ((int) $req['document_type_id']) {
    1 => 'barangay_certificate.html',
    2 => 'indigency_certificate.html',
    default => 'barangay_certificate.html'
};

$template_path = dirname(__DIR__, 2) . '/templates/' . $template_file;

if (!file_exists($template_path)) {
    http_response_code(500);
    exit('Template missing: ' . $template_file);
}

$template = file_get_contents($template_path);

$risk_score = $req['risk_score'] ?? 0;
$household_size = $req['household_size'] ?? 1;
$income_monthly = $req['income_monthly'] ?? 0;

$replacements = [

    '[FULL_NAME]' => trim(
        ($req['first_name'] ?? '') . ' ' .
        ($req['middle_name'] ?? '') . ' ' .
        ($req['last_name'] ?? '') . ' ' .
        ($req['suffix'] ?? '')
    ),

    '[SEX]' => $req['gender'] ?? 'Male',

    '[CIVIL_STATUS]' => $req['civil_status'] ?? 'Single',

    '[ADDRESS]' => $req['address'] ?? '',

    '[BARANGAY_NAME]' => $req['barangay_name'] ?? 'Arteche Poblacion',

    '[PURPOSE]' => $req['purpose'] ?? 'legal purposes',

    '[DATE_ISSUED]' => date('F j, Y'),

    '[DATE_EXTENDED]' => date('F j, Y'),

    '[REQUEST_NUMBER]' => $req['request_number'] ?? 'N/A',

    '[HOUSEHOLD_HEAD]' =>
        $req['household_head'] ??
        trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')),

    '[HOUSEHOLD_SIZE]' => $household_size,

    '[MONTHLY_INCOME]' => number_format($income_monthly, 0),

    '[INCOME_PER_CAPITA]' =>
        number_format($income_monthly / max(1, $household_size), 0),

    '[OCCUPATION]' => 'Varies',

    '[DEPENDENTS]' => ($household_size - 1),

    '[INCOME_SOURCE]' => $req['income_source'] ?? 'N/A',

    '[GOOD_MORAL_STATEMENT]' =>
        'has good moral character and standing in the community',

    '[BARANGAY_CAPTAIN_NAME]' => 'Barangay Captain',

    '[SECRETARY_NAME]' => 'Barangay Secretary',

    '[TREASURER_NAME]' => 'Barangay Treasurer',

    '[RISK_SCORE]' => match (true) {
        ($risk_score <= 30) => 'LOW RISK',
        ($risk_score <= 60) => 'MEDIUM RISK',
        default => 'HIGH RISK'
    },

    '[HOUSEHOLD_IDENTIFIER]' => $req['household_identifier'] ?? 'N/A'
];

foreach ($replacements as $placeholder => $value) {
    $template = str_replace($placeholder, $value, $template);
}

// Generate and save HTML file
$doc_name = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $req['document_name'] ?? 'certificate')));
$request_number = $req['request_number'] ?? 'REQ-' . str_pad($request_id, 6, '0', STR_PAD_LEFT);
$filename = $request_number . '-' . $doc_name . '.pdf';
$web_path = 'uploads/documents/' . $filename;
$file_path = __DIR__ . '/../../public/' . $web_path;

require_once '../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($template);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$bytes = $dompdf->output();
if (file_put_contents($file_path, $bytes) === false) {
    error_log("generate_pdf.php ERROR: Failed to save $file_path");
    die('Error: Failed to save PDF.');
}
error_log("generate_pdf.php SUCCESS: Saved $file_path ($bytes bytes), web_path: $web_path, request_id: $request_id");


$update = $conn->prepare("UPDATE citizen_requests SET status = 'Ready for Pickup', document_path = ? WHERE id = ?");
$update->bind_param("si", $web_path, $request_id);
if (!$update->execute()) {
    unlink($file_path); // cleanup
    die('Error: Failed to update database.');
}
$update->close();

// Success redirect to dedicated page
header("Location: pdf_success.php?file=" . urlencode($filename));
exit;
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title><?= htmlspecialchars($req['document_name'] ?? 'Document') ?>
        #<?= htmlspecialchars($req['request_number'] ?? '') ?></title>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            .certificate,
            .certificate * {
                visibility: visible;
                position: absolute;
                left: 0;
                top: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 14px;
            line-height: 1.4;
            background: white;
        }

        .certificate {
            max-width: 8.5in;
            margin: 1in auto;
        }

        .print-controls {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
        }

        .btn-print {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }

        .status-bar {
            background: #e9ecef;
            padding: 8px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }
    </style>

</head>

<body>

    <div class="print-controls no-print">
        <a href="#" onclick="printDocument();" class="btn-print">
            🖨️ PRINT / SAVE AS PDF
        </a>
        <p><small>Tip: Ctrl+P → Destination: "Save as PDF"</small></p>
    </div>

    <div class="status-bar no-print">
        <strong>Official Document</strong> |
        Request #<?= htmlspecialchars($req['request_number'] ?? '') ?> |
        Status: Ready for Pickup
    </div>

    <div class="certificate">
        <?= $template ?>
    </div>

    <script>
        function printDocument() {
            window.print();
        }

        setTimeout(() => {
            window.print();
        }, 3000);
    </script>

</body>

</html>

<?php exit; ?>