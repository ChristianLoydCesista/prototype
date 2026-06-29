<?php
require_once '../shared/bootstrap.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../admin_login.php");
    exit;
}

$file = isset($_GET['file']) ? htmlspecialchars($_GET['file']) : 'N/A';
$download_url = '/prototype/public/uploads/documents/' . $file;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Generated - Arteche CI System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%); min-height: 100vh; }
        .success-container { max-width: 600px; margin: 50px auto; padding: 40px; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); text-align: center; }
        .check-icon { font-size: 6rem; color: #28a745; margin-bottom: 20px; animation: bounce 1s infinite; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-10px); } 60% { transform: translateY(-5px); } }
        .success-title { font-size: 2.5rem; font-weight: bold; color: #155724; margin-bottom: 10px; }
        .filename { font-family: monospace; background: #f8f9fa; padding: 12px; border-radius: 8px; font-size: 1.1rem; color: #495057; word-break: break-all; }
        .btn-download { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; padding: 15px 40px; font-size: 1.1rem; font-weight: bold; border-radius: 50px; color: white; transition: all 0.3s; }
        .btn-download:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(40,167,69,0.4); }
        .btn-back { background: #6c757d; border: none; padding: 12px 30px; font-size: 1rem; border-radius: 50px; color: white; text-decoration: none; transition: all 0.3s; }
        .btn-back:hover { background: #5a6268; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="check-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1 class="success-title">Document Generated Successfully!</h1>
        <p class="lead mb-4">Your PDF certificate has been created and is ready for pickup or download.</p>
        
        <div class="filename mb-4 p-3 border rounded-3 shadow-sm">
            📄 <strong><?= $file ?></strong>
        </div>
        
        <div class="d-grid gap-3 col-8 mx-auto">
            <a href="shared/document_requests.php" class="btn-back">
                <i class="bi bi-arrow-left me-2"></i> Back to Requests
            </a>
        </div>
        
        <div class="mt-5 pt-4 border-top">
            <small class="text-muted">
                Generated on <?= date('F j, Y \a\t g:i A') ?> | Arteche CI System
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
