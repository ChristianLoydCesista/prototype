<?php
/**
 * Standard Navbar Component
 * Used across public/citizen/admin pages
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/assets/css/citizen-theme.css">
    <title><?= $page_title ?? 'Arteche CIS' ?></title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
    <div class="container-fluid px-4">
        <!-- Brand -->
        <a class="navbar-brand fw-bold fs-4" href="<?= SITE_URL ?>">
            <i class="bi bi-building-heart me-2"></i>
            Arteche CIS
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>"><i class="bi bi-house-door"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>app/citizen/citizen_portal.php"><i class="bi bi-person-badge"></i> Citizen Portal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>app/citizen/citizen_register_view.php"><i class="bi bi-person-plus"></i> Register</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>app/citizen/citizen_login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                </li>
            </ul>

            <!-- Right Side -->
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['citizen_logged_in']) && $_SESSION['citizen_logged_in']): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['citizen']['first_name'] ?? 'Citizen') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>app/citizen/citizen_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>app/citizen/my_request.php"><i class="bi bi-files"></i> My Requests</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>app/shared/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="pt-3">
    <?php echo $content ?? ''; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

