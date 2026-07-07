<?php
require_once __DIR__ . '/../../shared/bootstrap.php';

require_once __DIR__ . '/../../shared/config/database.php';
$conn = getDB();

if (!$conn) {
    http_response_code(500);
    die('Database unavailable');
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
$userColumns = getTableColumns($conn, 'users');

// Authentication check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    die('Invalid request ID');
}

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
    (in_array('first_name', $citizenColumns) && in_array('last_name', $citizenColumns)) ? "TRIM(CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''))) AS citizen_name" : "'' AS citizen_name",
    in_array('email', $citizenColumns) ? 'c.email' : "'' AS email",
    in_array('phone', $citizenColumns) ? 'c.phone' : "'' AS phone",
    in_array('address', $citizenColumns) ? 'c.address' : "'' AS address",
    in_array('birth_date', $citizenColumns) ? 'c.birth_date' : 'NULL AS birth_date',
    (in_array('name', $barangayColumns) && in_array('barangay_id', $citizenColumns)) ? 'b.name AS barangay_name' : "'' AS barangay_name",
    in_array('name', $documentTypeColumns) ? 'dt.name AS document_name' : "'' AS document_name",
    in_array('description', $documentTypeColumns) ? 'dt.description AS document_description' : "'' AS document_description",
    in_array('requirements', $documentTypeColumns) ? 'dt.requirements' : "'' AS requirements",
    in_array('processing_days', $documentTypeColumns) ? 'dt.processing_days' : 'NULL AS processing_days',
    in_array('fee', $documentTypeColumns) ? 'dt.fee AS document_fee' : 'NULL AS document_fee',
    (in_array('full_name', $userColumns) && in_array('reviewed_by', $citizenRequestColumns)) ? 'reviewer.full_name AS reviewed_by_name' : "'' AS reviewed_by_name"
];

$joins = [
    'FROM citizen_requests cr',
    'LEFT JOIN citizens c ON cr.citizen_id = c.id'
];

if (in_array('name', $documentTypeColumns) || in_array('description', $documentTypeColumns) || in_array('requirements', $documentTypeColumns) || in_array('processing_days', $documentTypeColumns) || in_array('fee', $documentTypeColumns)) {
    $joins[] = 'LEFT JOIN document_types dt ON cr.document_type_id = dt.id';
}

if (in_array('name', $barangayColumns) && in_array('barangay_id', $citizenColumns)) {
    $joins[] = 'LEFT JOIN barangays b ON c.barangay_id = b.id';
}

if (in_array('full_name', $userColumns) && in_array('reviewed_by', $citizenRequestColumns)) {
    $joins[] = 'LEFT JOIN users reviewer ON cr.reviewed_by = reviewer.id';
}

$query = "SELECT " . implode(",\n", $selectParts) . "\n" . implode("\n", $joins) . "\nWHERE cr.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    die('Unable to load request details.');
}

$stmt->bind_param("i", $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    die('Request not found');
}
?>
<div class="container-fluid">
    <!-- Request Header -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h6 class="text-muted mb-1">Request Number</h6>
            <p class="fs-5 fw-bold"><?= htmlspecialchars($req['request_number'] ?? 'N/A') ?></p>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted mb-1">Current Status</h6>
            <p>
                <?php
                $status_class = '';
                switch ($req['status']) {
                    case 'Submitted':
                        $status_class = 'status-Submitted';
                        break;
                    case 'Under Review':
                        $status_class = 'status-Under Review';
                        break;
                    case 'Approved':
                        $status_class = 'status-Approved';
                        break;
                    case 'Ready for Pickup':
                        $status_class = 'status-Ready for Pickup';
                        break;
                    case 'Rejected':
                        $status_class = 'status-Rejected';
                        break;
                    case 'Completed':
                        $status_class = 'status-Completed';
                        break;
                    default:
                        $status_class = 'status-Draft';
                }
                ?>
                <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($req['status']) ?></span>
            </p>
        </div>
    </div>

    <!-- Citizen Information -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-person"></i> Citizen Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="detail-label">Full Name:</td>
                            <td class="fw-bold"><?= htmlspecialchars($req['citizen_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Email:</td>
                            <td><?= htmlspecialchars($req['email'] ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Phone:</td>
                            <td><?= htmlspecialchars($req['phone'] ?: 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="detail-label">Barangay:</td>
                            <td><?= htmlspecialchars($req['barangay_name'] ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Address:</td>
                            <td><?= htmlspecialchars($req['address'] ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Birth Date:</td>
                            <td><?= $req['birth_date'] ? date('F d, Y', strtotime($req['birth_date'])) : 'N/A' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Details -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-file-text"></i> Document Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="detail-label">Document Type:</td>
                            <td class="fw-bold"><?= htmlspecialchars($req['document_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Fee:</td>
                            <td>₱<?= number_format($req['fee'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Payment Status:</td>
                            <td>
                                <span class="payment-badge payment-<?= $req['payment_status'] ?>">
                                    <?= $req['payment_status'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="detail-label">Processing Days:</td>
                            <td><?= $req['processing_days'] ?> day(s)</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="detail-label">Purpose:</td>
                            <td><?= nl2br(htmlspecialchars($req['purpose'] ?? '')) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($req['requirements']): ?>
                <div class="mt-2 p-3 bg-light rounded">
                    <strong>Requirements:</strong>
                    <p class="text-muted small mb-0 mt-1"><?= nl2br(htmlspecialchars($req['requirements'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline / Processing History -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Processing Timeline</h6>
        </div>
        <div class="card-body">
            <div class="timeline">
                <!-- Submitted -->
                <div class="timeline-item">
                    <div class="timeline-icon completed"></div>
                    <div>
                        <strong>Request Submitted</strong>
                        <div class="text-muted small">
                            <?= date('F d, Y h:i A', strtotime($req['submitted_at'])) ?>
                        </div>
                    </div>
                </div>

                <!-- Under Review / Processed -->
                <?php if ($req['reviewed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed"></div>
                        <div>
                            <strong>Under Review</strong>
                            <div class="text-muted small">
                                <?= date('F d, Y h:i A', strtotime($req['reviewed_at'])) ?>
                                <?php if ($req['reviewed_by_name']): ?>
                                    <br>by <?= htmlspecialchars($req['reviewed_by_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Approval/Rejection -->
                <?php if (in_array($req['status'], ['Approved', 'Rejected', 'Ready for Pickup', 'Completed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed"></div>
                        <div>
                            <strong><?= $req['status'] == 'Rejected' ? 'Rejected' : 'Approved' ?></strong>
                            <div class="text-muted small">
                                <?php if ($req['reviewed_at']): ?>
                                    <?= date('F d, Y h:i A', strtotime($req['reviewed_at'])) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($req['rejection_reason']): ?>
                                <div class="alert alert-danger mt-2 py-2 small">
                                    <strong>Reason:</strong> <?= htmlspecialchars($req['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Ready for Pickup -->
                <?php if ($req['released_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed"></div>
                        <div>
                            <strong>Ready for Pickup</strong>
                            <div class="text-muted small">
                                <?= date('F d, Y h:i A', strtotime($req['released_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Completed -->
                <?php if ($req['completed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed"></div>
                        <div>
                            <strong>Completed (Claimed)</strong>
                            <div class="text-muted small">
                                <?= date('F d, Y h:i A', strtotime($req['completed_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($req['notes']): ?>
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-chat-text"></i> Notes / History</h6>
            </div>
            <div class="card-body">
                <pre class="mb-0"
                    style="white-space: pre-wrap; font-family: inherit; background: #f8f9fa; padding: 10px; border-radius: 5px;"><?= htmlspecialchars($req['notes']) ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-Submitted {
        background: #cfe2ff;
        color: #084298;
    }

    .status-Under\ Review {
        background: #fff3cd;
        color: #856404;
    }

    .status-Approved {
        background: #d1e7dd;
        color: #0f5132;
    }

    .status-Ready\ for\ Pickup {
        background: #cff4fc;
        color: #087990;
    }

    .status-Rejected {
        background: #f8d7da;
        color: #842029;
    }

    .status-Completed {
        background: #e2e3e5;
        color: #41464b;
    }

    .payment-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
    }

    .payment-Pending {
        background: #fff3cd;
        color: #856404;
    }

    .payment-Paid {
        background: #d1e7dd;
        color: #0f5132;
    }

    .payment-Waived {
        background: #cff4fc;
        color: #087990;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
        width: 120px;
    }

    .timeline {
        position: relative;
        padding-left: 20px;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-item:before {
        content: '';
        position: absolute;
        left: -4px;
        top: 8px;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item:last-child:before {
        display: none;
    }

    .timeline-icon {
        position: absolute;
        left: -11px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: white;
        border: 3px solid;
        z-index: 2;
    }

    .timeline-icon.completed {
        background: #198754;
        border-color: #198754;
    }
</style>