<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/shared/services/TemplateResolver.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| 1. AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Access denied.');
}

$conn = getDB();

if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Database unavailable.');
}

$requestId = filter_input(
    INPUT_GET,
    'request_id',
    FILTER_VALIDATE_INT
);

if (!$requestId || $requestId <= 0) {
    http_response_code(400);
    exit('A valid request ID is required.');
}

$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
$adminBarangayId = isset($_SESSION['barangay_id'])
    ? (int)$_SESSION['barangay_id']
    : null;

$adminUserId = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : 0;

$projectRoot = dirname(__DIR__, 2);

/*
|--------------------------------------------------------------------------
| 2. HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

/**
 * Load a request together with its citizen, barangay,
 * document type, and template configuration.
 */
function loadRequestForGeneration(
    mysqli $conn,
    int $requestId,
    bool $isSuperAdmin,
    ?int $adminBarangayId
): ?array {
    $sql = "
        SELECT
            cr.id,
            cr.request_number,
            cr.citizen_id,
            cr.document_type_id,
            cr.purpose,
            cr.status,
            cr.fee,
            cr.payment_status,
            cr.submitted_at,
            cr.reviewed_by,
            cr.reviewed_at,
            cr.released_at,
            cr.completed_at,
            cr.rejection_reason,
            cr.notes,
            cr.document_path,
            cr.created_at,

            c.first_name,
            c.middle_name,
            c.last_name,
            c.suffix,
            c.address,
            c.gender,
            c.civil_status,
            c.barangay_id,

            b.name AS barangay_name,
            b.municipality,
            b.province,

            dt.name AS document_name,
            dt.template_key,
            dt.default_template_path,
            dt.requires_signature,

            bds.office_name,
            bds.barangay_hall_address,
            bds.header_image_path,
            bds.seal_path,
            bds.watermark_path,
            bds.custom_template_directory

        FROM citizen_requests cr

        INNER JOIN citizens c
            ON c.id = cr.citizen_id

        INNER JOIN document_types dt
            ON dt.id = cr.document_type_id

        LEFT JOIN barangays b
            ON b.id = c.barangay_id

        LEFT JOIN barangay_document_settings bds
            ON bds.barangay_id = c.barangay_id
            AND bds.is_active = 1

        WHERE cr.id = ?
    ";

    $types = 'i';
    $params = [$requestId];

    if (!$isSuperAdmin) {
        if (!$adminBarangayId) {
            return null;
        }

        $sql .= " AND c.barangay_id = ?";
        $types .= 'i';
        $params[] = $adminBarangayId;
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare request query: ' . $conn->error
        );
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $request = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $request ?: null;
}

/**
 * Resolve a local asset path that Dompdf can read.
 *
 * Barangay assets will become database-driven in Phase 3.
 */
function resolveAssetPath(
    string $projectRoot,
    ?string $storedPath,
    ?string $fallbackPath = null
): string {
    $paths = [];

    if (!empty($storedPath)) {
        $paths[] = $storedPath;
    }

    if (!empty($fallbackPath)) {
        $paths[] = $fallbackPath;
    }

    foreach ($paths as $path) {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            continue;
        }

        $isAbsolute = preg_match('/^[A-Za-z]:\//', $path) === 1
            || substr($path, 0, 1) === '/';

        $candidates = $isAbsolute
            ? [$path]
            : [
                $projectRoot . '/' . ltrim($path, '/'),
                $projectRoot . '/public/' . ltrim($path, '/'),
                $projectRoot . '/assets/' . ltrim($path, '/'),
            ];

        foreach ($candidates as $candidate) {
            $candidate = str_replace('\\', '/', $candidate);

            if (is_file($candidate)) {
                $realPath = realpath($candidate);

                if ($realPath !== false) {
                    return str_replace('\\', '/', $realPath);
                }
            }
        }
    }

    return '';
}

/**
 * Join name components while removing unnecessary spaces.
 */
function buildFullName(array $request): string
{
    $parts = [
        trim((string)($request['first_name'] ?? '')),
        trim((string)($request['middle_name'] ?? '')),
        trim((string)($request['last_name'] ?? '')),
        trim((string)($request['suffix'] ?? '')),
    ];

    $parts = array_filter(
        $parts,
        static fn(string $part): bool => $part !== ''
    );

    return implode(' ', $parts);
}

/**
 * Build all placeholders that are currently supported.
 *
 * Phase 3 will replace the temporary captain and asset fallbacks
 * with actual barangay settings and active official information.
 */
function buildTemplateReplacements(
    array $request,
    string $projectRoot
): array {
    $fullName = buildFullName($request);

    $barangayName = trim(
        (string)($request['barangay_name'] ?? '')
    );

    $municipality = trim(
        (string)($request['municipality'] ?? 'Arteche')
    );

    $province = trim(
        (string)($request['province'] ?? 'Eastern Samar')
    );

    $officeName = trim(
        (string)(
            $request['office_name']
            ?? 'Office of the Punong Barangay'
        )
    );

    /*
    |--------------------------------------------------------------------------
    | Temporary Phase 2.5 fallbacks
    |--------------------------------------------------------------------------
    | These values will be replaced by barangay officials and settings
    | during Phase 3.
    |--------------------------------------------------------------------------
    */

    $captainName = 'HON. BARANGAY CAPTAIN';
    $captainPosition = 'Punong Barangay';

    $headerImage = resolveAssetPath(
        $projectRoot,
        $request['header_image_path'] ?? null,
        'assets/img/header.png'
    );

    $sealImage = resolveAssetPath(
        $projectRoot,
        $request['seal_path'] ?? null,
        null
    );

    $watermarkImage = resolveAssetPath(
        $projectRoot,
        $request['watermark_path'] ?? null,
        'assets/img/watermark_garden.png'
    );

    $documentTitle = strtoupper(
        trim(
            (string)(
                $request['document_name']
                ?? 'Barangay Certificate'
            )
        )
    );

    $householdSize = 1;
    $monthlyIncome = 0;
    $riskScore = 0;

    return [
        '[DOCUMENT_TITLE]' => $documentTitle,

        '[FULL_NAME]' => $fullName,
        '[FIRST_NAME]' => $request['first_name'] ?? '',
        '[MIDDLE_NAME]' => $request['middle_name'] ?? '',
        '[LAST_NAME]' => $request['last_name'] ?? '',
        '[SUFFIX]' => $request['suffix'] ?? '',

        '[SEX]' => $request['gender'] ?? '',
        '[CIVIL_STATUS]' => $request['civil_status'] ?? '',
        '[ADDRESS]' => $request['address'] ?? '',

        '[BARANGAY_NAME]' => $barangayName,
        '[MUNICIPALITY]' => $municipality,
        '[PROVINCE]' => $province,
        '[BARANGAY_LOCATION]' => trim(
            $municipality . ', ' . $province
        ),
        '[BARANGAY_HALL_ADDRESS]' =>
        $request['barangay_hall_address'] ?? '',
        '[OFFICE_NAME]' => $officeName,

        '[CAPTAIN_NAME]' => $captainName,
        '[CAPTAIN_POSITION]' => $captainPosition,
        '[CAPTAIN_SIGNATURE_PATH]' => '',

        // Compatibility with your older placeholders
        '[BARANGAY_CAPTAIN_NAME]' => $captainName,
        '[BARANGAY_CAPTAIN_POSITION]' => $captainPosition,

        '[HEADER_IMAGE_PATH]' => $headerImage,
        '[BARANGAY_SEAL_PATH]' => $sealImage,
        '[WATERMARK_IMAGE_PATH]' => $watermarkImage,

        '[PURPOSE]' =>
        $request['purpose'] ?? 'legal purposes',

        '[DATE_ISSUED]' => date('F j, Y'),
        '[DATE_EXTENDED]' => date('F j, Y'),

        '[REQUEST_NUMBER]' =>
        $request['request_number'] ?? 'N/A',

        /*
        |--------------------------------------------------------------------------
        | Existing compatibility placeholders
        |--------------------------------------------------------------------------
        | These remain until household/income data is formally connected.
        |--------------------------------------------------------------------------
        */

        '[HOUSEHOLD_HEAD]' => $fullName,
        '[HOUSEHOLD_SIZE]' => (string)$householdSize,
        '[MONTHLY_INCOME]' => number_format($monthlyIncome, 0),
        '[INCOME_PER_CAPITA]' => number_format(
            $monthlyIncome / max(1, $householdSize),
            0
        ),
        '[OCCUPATION]' => 'N/A',
        '[DEPENDENTS]' => (string)max(0, $householdSize - 1),
        '[INCOME_SOURCE]' => 'N/A',

        '[GOOD_MORAL_STATEMENT]' =>
        'has good moral character and standing in the community',

        '[SECRETARY_NAME]' => 'Barangay Secretary',
        '[TREASURER_NAME]' => 'Barangay Treasurer',

        '[RISK_SCORE]' => match (true) {
            $riskScore <= 30 => 'LOW RISK',
            $riskScore <= 60 => 'MEDIUM RISK',
            default => 'HIGH RISK',
        },

        '[HOUSEHOLD_IDENTIFIER]' => 'N/A',
    ];
}

/**
 * Replace template placeholders with their corresponding values.
 */
function applyTemplateReplacements(
    string $template,
    array $replacements
): string {
    return str_replace(
        array_keys($replacements),
        array_map(
            static fn($value): string => (string)$value,
            array_values($replacements)
        ),
        $template
    );
}

/**
 * Create a safe filename component.
 */
function makeFilenamePart(string $value, string $fallback): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value !== '' ? $value : $fallback;
}

/**
 * Build the saved PDF filename and paths.
 */
function buildDocumentPaths(
    array $request,
    int $requestId,
    string $projectRoot
): array {
    $requestNumber = makeFilenamePart(
        (string)($request['request_number'] ?? ''),
        'req-' . str_pad(
            (string)$requestId,
            6,
            '0',
            STR_PAD_LEFT
        )
    );

    $documentName = makeFilenamePart(
        (string)($request['document_name'] ?? ''),
        'document'
    );

    $filename = $requestNumber
        . '-'
        . $documentName
        . '.pdf';

    $webPath = 'uploads/documents/' . $filename;

    $absolutePath = $projectRoot
        . '/public/'
        . $webPath;

    return [
        'filename' => $filename,
        'web_path' => $webPath,
        'absolute_path' => str_replace(
            '\\',
            '/',
            $absolutePath
        ),
    ];
}

/**
 * Render and save the PDF.
 */
function generateAndSavePdf(
    string $html,
    string $destinationPath,
    string $projectRoot
): int {
    $destinationDirectory = dirname($destinationPath);

    if (
        !is_dir($destinationDirectory)
        && !mkdir($destinationDirectory, 0755, true)
        && !is_dir($destinationDirectory)
    ) {
        throw new RuntimeException(
            'Unable to create document output directory.'
        );
    }

    $options = new Options();

    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', $projectRoot);

    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfBytes = $dompdf->output();

    $writtenBytes = file_put_contents(
        $destinationPath,
        $pdfBytes
    );

    if ($writtenBytes === false) {
        throw new RuntimeException(
            'The generated PDF could not be saved.'
        );
    }

    return strlen($pdfBytes);
}

/**
 * Update the request after the PDF was saved.
 */
function markRequestAsReady(
    mysqli $conn,
    int $requestId,
    string $documentWebPath
): void {
    $stmt = $conn->prepare("
        UPDATE citizen_requests
        SET
            status = 'Ready for Pickup',
            document_path = ?,
            released_at = NOW()
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare request update: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'si',
        $documentWebPath,
        $requestId
    );

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();

        throw new RuntimeException(
            'Unable to update request: ' . $message
        );
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| 3. LOAD REQUEST
|--------------------------------------------------------------------------
*/

try {
    $request = loadRequestForGeneration(
        $conn,
        (int)$requestId,
        $isSuperAdmin,
        $adminBarangayId
    );

    if (!$request) {
        http_response_code(404);
        exit('Request not found or access denied.');
    }

    $barangayId = (int)($request['barangay_id'] ?? 0);
    $templateKey = trim(
        (string)($request['template_key'] ?? '')
    );

    if ($barangayId <= 0) {
        throw new RuntimeException(
            'The citizen does not have a valid barangay assignment.'
        );
    }

    if ($templateKey === '') {
        throw new RuntimeException(
            'The selected document type does not have a template key.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. RESOLVE TEMPLATE
    |--------------------------------------------------------------------------
    */

    $templateResolver = new TemplateResolver($projectRoot);

    $resolvedTemplate = $templateResolver->resolve(
        $barangayId,
        $templateKey,
        $request['custom_template_directory'] ?? null,
        $request['default_template_path'] ?? null
    );

    $templateHtml = file_get_contents(
        $resolvedTemplate['path']
    );

    if ($templateHtml === false) {
        throw new RuntimeException(
            'The resolved template could not be read.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. BUILD DOCUMENT CONTENT
    |--------------------------------------------------------------------------
    */

    $replacements = buildTemplateReplacements(
        $request,
        $projectRoot
    );

    $documentHtml = applyTemplateReplacements(
        $templateHtml,
        $replacements
    );

    /*
    |--------------------------------------------------------------------------
    | 6. BUILD OUTPUT PATHS
    |--------------------------------------------------------------------------
    */

    $documentPaths = buildDocumentPaths(
        $request,
        (int)$requestId,
        $projectRoot
    );

    /*
    |--------------------------------------------------------------------------
    | 7. GENERATE AND SAVE PDF
    |--------------------------------------------------------------------------
    */

    $pdfSize = generateAndSavePdf(
        $documentHtml,
        $documentPaths['absolute_path'],
        $projectRoot
    );

    /*
    |--------------------------------------------------------------------------
    | 8. UPDATE REQUEST
    |--------------------------------------------------------------------------
    */

    try {
        markRequestAsReady(
            $conn,
            (int)$requestId,
            $documentPaths['web_path']
        );
    } catch (Throwable $updateError) {
        if (is_file($documentPaths['absolute_path'])) {
            unlink($documentPaths['absolute_path']);
        }

        throw $updateError;
    }

    /*
    |--------------------------------------------------------------------------
    | 9. LOG SUCCESS
    |--------------------------------------------------------------------------
    */

    error_log(
        sprintf(
            'PDF generated: request_id=%d, barangay_id=%d, '
                . 'template=%s, source=%s, file=%s, bytes=%d, admin_id=%d',
            $requestId,
            $barangayId,
            $templateKey,
            $resolvedTemplate['source'],
            $documentPaths['web_path'],
            $pdfSize,
            $adminUserId
        )
    );

    /*
    |--------------------------------------------------------------------------
    | 10. REDIRECT
    |--------------------------------------------------------------------------
    */

    header(
        'Location: pdf_success.php?file='
            . urlencode($documentPaths['filename'])
    );

    exit;
} catch (Throwable $e) {
    error_log(
        'generate_pdf.php ERROR: '
            . $e->getMessage()
            . ' in '
            . $e->getFile()
            . ':'
            . $e->getLine()
    );

    http_response_code(500);

    exit('Document generation failed. '
        . 'Please check the request, barangay configuration, '
        . 'and document template.');
}
