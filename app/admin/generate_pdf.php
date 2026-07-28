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
        c.birth_date,
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
        bds.custom_template_directory,

        bo.full_name AS captain_name,
        bo.position AS captain_position,
        bo.signature_path AS captain_signature_path

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

    LEFT JOIN barangay_officials bo
        ON bo.id = (
            SELECT bo2.id
            FROM barangay_officials bo2
            WHERE bo2.barangay_id = c.barangay_id
              AND bo2.is_active = 1
              AND bo2.is_primary_signatory = 1
            ORDER BY
                bo2.term_start DESC,
                bo2.id DESC
            LIMIT 1
        )

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
 * Convert one local image into a Base64 data URI.
 *
 * Use this only for the Rawis full-page background.
 */
function localImageToDataUri(string $absolutePath): string
{
    $absolutePath = trim($absolutePath);

    if ($absolutePath === '' || !is_file($absolutePath)) {
        return '';
    }

    $imageContents = file_get_contents($absolutePath);

    if ($imageContents === false) {
        return '';
    }

    $extension = strtolower(
        pathinfo($absolutePath, PATHINFO_EXTENSION)
    );

    $mimeType = match ($extension) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        default => '',
    };

    if ($mimeType === '') {
        return '';
    }

    return 'data:'
        . $mimeType
        . ';base64,'
        . base64_encode($imageContents);
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
 * Escape a value for safe insertion into an HTML template.
 */
function escapeTemplateValue(mixed $value): string
{
    return htmlspecialchars(
        trim((string)$value),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}



/**
 * Build all placeholders that are currently supported.
 *
 * Phase 3 will replace the temporary captain and asset fallbacks
 * with actual barangay settings and active official information.
 */

function formatCertificateDate(?DateTimeInterface $date = null): string
{
    $date = $date ?? new DateTimeImmutable();

    return $date->format('jS')
        . ' day of '
        . strtoupper($date->format('F Y'));
}

function calculateAge(?string $birthDate): string
{
    $birthDate = trim((string)$birthDate);

    if ($birthDate === '') {
        return '';
    }

    try {
        $birthday = new DateTimeImmutable($birthDate);
        $today = new DateTimeImmutable('today');

        if ($birthday > $today) {
            return '';
        }

        return (string)$birthday->diff($today)->y;
    } catch (Throwable $e) {
        return '';
    }
}

function buildTemplateReplacements(
    array $request,
    string $projectRoot
): array {
    $fullName = buildFullName($request);

    $barangayName = trim(
        (string)($request['barangay_name'] ?? '')
    );

    $municipality = trim(
        (string)($request['municipality'] ?? '')
    );

    if ($municipality === '') {
        $municipality = 'Arteche';
    }

    $province = trim(
        (string)($request['province'] ?? '')
    );

    if ($province === '') {
        $province = 'Eastern Samar';
    }

    $officeName = trim(
        (string)($request['office_name'] ?? '')
    );

    if ($officeName === '') {
        $officeName = 'Office of the Punong Barangay';
    }

    $age = calculateAge(
        $request['birth_date'] ?? null
    );

    $purpose = trim(
        (string)($request['purpose'] ?? '')
    );

    if ($purpose === '') {
        $purpose = 'financial assistance';
    }

    // Rawis full-page background used by selected Rawis document templates
    $rawisFullPageBackground = '';

    $rawisTemplatesWithFullBackground = [
        'certificate-of-indigency',
        'barangay-certificate',
    ];

    $templateKey = $request['template_key'] ?? '';

    if (
        (int) $request['barangay_id'] === 17
        && in_array($templateKey, $rawisTemplatesWithFullBackground, true)
    ) {
        $rawisBackgroundPath =
            $projectRoot
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'uploads'
            . DIRECTORY_SEPARATOR
            . 'barangays'
            . DIRECTORY_SEPARATOR
            . '17'
            . DIRECTORY_SEPARATOR
            . 'full-page-template.png';

        if (!is_file($rawisBackgroundPath)) {
            throw new RuntimeException(
                'Rawis background was not found at: '
                    . $rawisBackgroundPath
            );
        }

        $rawisFullPageBackground =
            localImageToDataUri($rawisBackgroundPath);

        if ($rawisFullPageBackground === '') {
            throw new RuntimeException(
                'Rawis background could not be embedded.'
            );
        }
    }


    // Balud full-page background used by selected Balud document templates
    // Barangay Balud full-page background.
    // Barangay ID: 3
    // Used for Balud Certificate of Indigency.


    // Barangay Balud full-page background.
    // Barangay ID: 3
    // Used for Balud Certificate of Indigency.
    $baludFullPageBackground = '';

    $templateKey = trim((string) ($request['template_key'] ?? ''));
    $barangayId = (int) ($request['barangay_id'] ?? 0);

    if (
        $barangayId === 3
        && $templateKey === 'certificate-of-indigency'
    ) {
        $baludBackgroundPath =
            $projectRoot
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'uploads'
            . DIRECTORY_SEPARATOR
            . 'barangays'
            . DIRECTORY_SEPARATOR
            . '3'
            . DIRECTORY_SEPARATOR
            . 'full-page-template.png';

        if (!is_file($baludBackgroundPath)) {
            throw new RuntimeException(
                'Balud full-page background was not found at: '
                    . $baludBackgroundPath
            );
        }

        $baludFullPageBackground =
            localImageToDataUri($baludBackgroundPath);

        if ($baludFullPageBackground === '') {
            throw new RuntimeException(
                'Balud full-page background could not be embedded.'
            );
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Active Barangay Signatory
    |--------------------------------------------------------------------------
    | Loaded from barangay_officials. Fallback values prevent broken output
    | when a barangay has not yet configured an active primary signatory.
    |--------------------------------------------------------------------------
    */

    $captainName = trim(
        (string)($request['captain_name'] ?? '')
    );

    $captainPosition = trim(
        (string)($request['captain_position'] ?? '')
    );

    $requiresSignature = (int)($request['requires_signature'] ?? 1) === 1;

    if ($requiresSignature && $captainName === '') {
        throw new RuntimeException(
            'No active primary signatory is configured for Barangay '
                . ($request['barangay_name'] ?? 'Unknown') . '.'
        );
    }

    if ($captainPosition === '') {
        $captainPosition = 'Punong Barangay';
    }

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

    $captainSignature = resolveAssetPath(
        $projectRoot,
        $request['captain_signature_path'] ?? null,
        null
    );

    $watermarkImage = resolveAssetPath(
        $projectRoot,
        $request['watermark_path'] ?? null,
        'assets/img/watermark_garden.png'
    );

    error_log('Watermark database path: ' . ($request['watermark_path'] ?? 'NULL'));
    error_log('Watermark resolved path: ' . ($watermarkImage ?: 'NOT FOUND'));

    $templateKey = trim(
        (string)($request['template_key'] ?? '')
    );

    $documentTitle = match ($templateKey) {
        'barangay-certificate' => 'BARANGAY CERTIFICATION',
        'certificate-of-indigency' => 'CERTIFICATE OF INDIGENCY',
        'certificate-of-residency' => 'CERTIFICATE OF RESIDENCY',
        'business-permit' => 'BUSINESS PERMIT',
        default => strtoupper(
            trim(
                (string)(
                    $request['document_name']
                    ?? 'DOCUMENT'
                )
            )
        ),
    };

    $issueTimestamp = time();

    $issueDay = date('j', $issueTimestamp);
    $issueMonth = strtoupper(date('F', $issueTimestamp));
    $issueYear = date('Y', $issueTimestamp);

    $householdSize = 1;
    $monthlyIncome = 0;
    $riskScore = 0;

    return [
        '[CERTIFICATION_TITLE]' => $documentTitle,
        '[DOCUMENT_TITLE]' => $documentTitle,

        '[FULL_NAME]' => escapeTemplateValue($fullName),
        '[AGE]' => $age,
        '[FIRST_NAME]' => escapeTemplateValue($request['first_name'] ?? ''),
        '[MIDDLE_NAME]' => escapeTemplateValue($request['middle_name'] ?? ''),
        '[LAST_NAME]' => escapeTemplateValue($request['last_name'] ?? ''),
        '[SUFFIX]' => escapeTemplateValue($request['suffix'] ?? ''),

        '[SEX]' => escapeTemplateValue($request['gender'] ?? ''),
        '[CIVIL_STATUS]' => escapeTemplateValue($request['civil_status'] ?? ''),
        '[ADDRESS]' => escapeTemplateValue($request['address'] ?? ''),

        '[BARANGAY_NAME]' => escapeTemplateValue($barangayName),
        '[MUNICIPALITY]' => escapeTemplateValue($municipality),
        '[PROVINCE]' => escapeTemplateValue($province),

        '[BARANGAY_LOCATION]' => trim(
            $municipality . ', ' . $province
        ),

        '[BARANGAY_HALL_ADDRESS]' =>
        escapeTemplateValue($request['barangay_hall_address'] ?? ''),
        '[OFFICE_NAME]' => $officeName,

        '[CAPTAIN_NAME]' => escapeTemplateValue($captainName),
        '[CAPTAIN_POSITION]' => escapeTemplateValue($captainPosition),
        '[BARANGAY_CAPTAIN_NAME]' => escapeTemplateValue($captainName),
        '[BARANGAY_CAPTAIN_POSITION]' => escapeTemplateValue($captainPosition),
        '[CAPTAIN_SIGNATURE_PATH]' => $captainSignature,

        '[HEADER_IMAGE_PATH]' => $headerImage,
        '[BARANGAY_SEAL_PATH]' => $sealImage,
        '[WATERMARK_IMAGE_PATH]' => $watermarkImage,

        '[PURPOSE]' => $purpose,

        '[DATE_ISSUED]' => formatCertificateDate(),
        '[DATE_EXTENDED]' => formatCertificateDate(),

        '[REQUEST_NUMBER]' =>
        $request['request_number'] ?? 'N/A',

        // Rawis full-page background is used by selected Rawis document templates
        '[RAWIS_FULL_PAGE_BACKGROUND]' =>
        $rawisFullPageBackground,

        // Balud full-page background is used by selected Balud document templates
        '[BALUD_FULL_PAGE_BACKGROUND]' =>
        $baludFullPageBackground,

        '[ISSUE_DAY]' => htmlspecialchars(
            $issueDay,
            ENT_QUOTES,
            'UTF-8'
        ),

        '[ISSUE_MONTH]' => htmlspecialchars(
            $issueMonth,
            ENT_QUOTES,
            'UTF-8'
        ),

        '[ISSUE_YEAR]' => htmlspecialchars(
            $issueYear,
            ENT_QUOTES,
            'UTF-8'
        ),

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
    string $documentWebPath,
    int $generatedBy,
    string $templateKey,
    string $signatoryName,
    string $signatoryPosition,
    ?string $signaturePath
): void {
    $stmt = $conn->prepare("
        UPDATE citizen_requests
        SET
            status = 'Ready for Pickup',
            document_path = ?,
            released_at = NOW(),
            generated_at = NOW(),
            generated_by = NULLIF(?, 0),
            generated_template_key = ?,
            signatory_name_snapshot = ?,
            signatory_position_snapshot = ?,
            signature_path_snapshot = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare request update: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'sissssi',
        $documentWebPath,
        $generatedBy,
        $templateKey,
        $signatoryName,
        $signatoryPosition,
        $signaturePath,
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


    $allowedGenerationStatuses = [
        'Approved',
        'Ready for Pickup',
    ];

    if (!in_array($request['status'], $allowedGenerationStatuses, true)) {
        throw new RuntimeException(
            'Only approved requests can generate an official document.'
        );
    }

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
            $documentPaths['web_path'],
            $adminUserId,
            $templateKey,
            trim((string)($request['captain_name'] ?? '')),
            trim((string)($request['captain_position'] ?? 'Punong Barangay')),
            $request['captain_signature_path'] ?? null
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
