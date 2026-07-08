<?php
require_once '../shared/bootstrap.php';

$conn = getDB();

if (!$conn) {
    http_response_code(500);
    exit('Database unavailable');
}

function getTableColumns($conn, $table)
{
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}

$citizenRequestColumns = getTableColumns($conn, 'citizen_requests');
$citizenColumns = getTableColumns($conn, 'citizens');
$documentTypeColumns = getTableColumns($conn, 'document_types');
$barangayColumns = getTableColumns($conn, 'barangays');

function slugifyTemplateName($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-');
}

function resolveCertificateTemplatePath($barangayName, $documentTypeId, $projectRoot)
{
    $documentTemplate = ((int) $documentTypeId === 2) ? 'indigency_certificate.html' : 'barangay_certificate.html';
    $barangaySlug = slugifyTemplateName($barangayName);
    $candidates = [];

    if ($barangaySlug !== '') {
        $candidates[] = $projectRoot . '/templates/barangays/' . $barangaySlug . '/' . $documentTemplate;
        $candidates[] = $projectRoot . '/templates/barangays/' . $barangaySlug . '/barangay_certificate.html';
        $candidates[] = $projectRoot . '/templates/barangays/' . $barangaySlug . '/indigency_certificate.html';
    }

    $candidates[] = $projectRoot . '/templates/' . $documentTemplate;
    $candidates[] = $projectRoot . '/templates/barangay_certificate.html';

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return $projectRoot . '/templates/' . $documentTemplate;
}

function resolveAssetUri($projectRoot, $relativePath)
{
    $candidates = [];

    if (preg_match('#^(?:[A-Za-z]:)?[\\/]#', $relativePath)) {
        $candidates[] = str_replace('\\', '/', $relativePath);
    } else {
        $candidates[] = $projectRoot . '/' . ltrim($relativePath, '/');
        $candidates[] = $projectRoot . '/public/' . ltrim($relativePath, '/');
        $candidates[] = $projectRoot . '/assets/' . ltrim($relativePath, '/');
        $candidates[] = $relativePath;
    }

    foreach ($candidates as $candidate) {
        $normalizedPath = str_replace('\\', '/', $candidate);
        if (is_file($normalizedPath)) {
            // FIX: Return the direct absolute server path rather than 'file:///'
            return $normalizedPath;
        }
    }

    return $relativePath;
}

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

$selectParts = [
    'cr.id',
    'cr.request_number',
    'cr.citizen_id',
    'cr.document_type_id',
    'cr.purpose',
    'cr.status',
    'cr.fee',
    'cr.payment_status',
    'cr.submitted_at',
    'cr.reviewed_by',
    'cr.reviewed_at',
    'cr.released_at',
    'cr.completed_at',
    'cr.rejection_reason',
    'cr.notes',
    'cr.document_path',
    'cr.created_at',
    in_array('first_name', $citizenColumns) ? 'c.first_name' : "'' AS first_name",
    in_array('middle_name', $citizenColumns) ? 'c.middle_name' : "'' AS middle_name",
    in_array('last_name', $citizenColumns) ? 'c.last_name' : "'' AS last_name",
    in_array('suffix', $citizenColumns) ? 'c.suffix' : "'' AS suffix",
    in_array('address', $citizenColumns) ? 'c.address' : "'' AS address",
    in_array('gender', $citizenColumns) ? 'c.gender' : "'' AS gender",
    in_array('civil_status', $citizenColumns) ? 'c.civil_status' : "'' AS civil_status",
    in_array('barangay_id', $citizenColumns) ? 'c.barangay_id' : 'NULL AS barangay_id',
    (in_array('name', $barangayColumns) && in_array('barangay_id', $citizenColumns)) ? 'b.name AS barangay_name' : "'' AS barangay_name",
    in_array('name', $documentTypeColumns) ? 'dt.name AS document_name' : "'' AS document_name",
    in_array('id', $documentTypeColumns) ? 'dt.id AS document_type_id' : 'NULL AS document_type_id'
];

$joins = [
    'FROM citizen_requests cr',
    'LEFT JOIN citizens c ON cr.citizen_id = c.id'
];

if (in_array('name', $documentTypeColumns) || in_array('id', $documentTypeColumns)) {
    $joins[] = 'LEFT JOIN document_types dt ON cr.document_type_id = dt.id';
}

if (in_array('name', $barangayColumns) && in_array('barangay_id', $citizenColumns)) {
    $joins[] = 'LEFT JOIN barangays b ON c.barangay_id = b.id';
}

$where = ['cr.id = ?'];
$params = [$request_id];
$types = 'i';

if (!$is_super_admin && in_array('barangay_id', $citizenColumns)) {
    $where[] = 'c.barangay_id = ?';
    $params[] = $admin_barangay_id;
    $types .= 'i';
}

$query = "SELECT " . implode(",\n", $selectParts) . "\n" . implode("\n", $joins) . "\nWHERE " . implode(' AND ', $where);

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    exit('Unable to load request details for PDF generation.');
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

$projectRoot = dirname(__DIR__, 2);
$documentTypeId = (int) ($req['document_type_id'] ?? 1);
$barangayName = trim((string) ($req['barangay_name'] ?? $req['barangay'] ?? 'Garden'));
$template_path = resolveCertificateTemplatePath($barangayName, $documentTypeId, $projectRoot);
$template_file = basename($template_path);

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

    '[BARANGAY_NAME]' => $barangayName ?: 'Garden',

    '[BARANGAY_LOCATION]' => 'Arteche, Eastern Samar',

    '[OFFICE_NAME]' => 'Office of the Punong Barangay',

    '[BARANGAY_CAPTAIN_NAME]' => $req['captain_name'] ?? 'HON. BARANGAY CAPTAIN',

    '[BARANGAY_CAPTAIN_POSITION]' => $req['captain_position'] ?? 'Punong Barangay',

    '[CERTIFICATION_TITLE]' => ((int) $documentTypeId === 2) ? 'CERTIFICATE OF INDIGENCY' : 'BARANGAY CERTIFICATION',

    '[HEADER_IMAGE_PATH]' => resolveAssetUri($projectRoot, 'assets/img/header.png'),

    '[WATERMARK_IMAGE_PATH]' => resolveAssetUri($projectRoot, 'assets/img/watermark_garden.png'),

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
$target_dir = dirname($file_path);
if (!is_dir($target_dir)) {
    @mkdir($target_dir, 0755, true);
}

require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', $projectRoot); // FIX: Tells Dompdf it is safe to read files inside your project directory
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