<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Notifications - Arteche Citizen Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/citizen_notifications.css">
</head>

<body>
    <header class="cis-topbar">
        <div class="cis-shell cis-topbar-inner">
            <div>
                <div class="cis-brand">
                    <i class="bi bi-bell"></i> Notifications
                </div>
                <small class="cis-subtitle">Stay updated with your requests</small>
            </div>

            <a href="my_request.php" class="cis-icon-btn" title="My Requests">
                <i class="bi bi-files"></i>
            </a>
        </div>
    </header>

    <main class="cis-shell">
        <section class="cis-hero">
            <span class="cis-eyebrow">Citizen Alerts</span>
            <h1>Notifications</h1>
            <p>Track important updates about your barangay document requests and announcements.</p>
        </section>

        <section class="cis-stat-grid">
            <button class="cis-stat-card active" type="button" data-filter="all">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>All</small>
                        <h2 id="totalCount">0</h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-bell"></i></div>
                </div>
            </button>

            <button class="cis-stat-card" type="button" data-filter="unread">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Unread</small>
                        <h2 id="unreadCount">0</h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-envelope-exclamation"></i></div>
                </div>
            </button>

            <button class="cis-stat-card" type="button" data-filter="read">
                <div class="cis-stat-card-inner">
                    <div>
                        <small>Read</small>
                        <h2 id="readCount">0</h2>
                    </div>
                    <div class="cis-stat-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
            </button>
        </section>

        <section class="cis-section-head">
            <h2 class="cis-section-title">
                <i class="bi bi-list-ul"></i> Recent Notifications
            </h2>

            <button class="cis-btn cis-btn-light" type="button" id="markAllReadBtn">
                <i class="bi bi-check2-all"></i> Mark all read
            </button>
        </section>

        <section class="cis-card cis-card-pad">
            <div id="notificationsList" class="cis-notification-list"></div>
        </section>
    </main>

    <nav class="cis-bottom-nav">
        <a href="citizen_dashboard.php">
            <i class="bi bi-house-fill"></i>
            Home
        </a>

        <a href="my_request.php">
            <i class="bi bi-files"></i>
            Requests
        </a>

        <a href="request_document.php">
            <i class="bi bi-plus-circle-fill"></i>
            New
        </a>

        <a href="citizen_notification.php" class="active">
            <i class="bi bi-bell"></i>
            Alerts
        </a>

        <a href="citizen_profile.php">
            <i class="bi bi-person"></i>
            Profile
        </a>
    </nav>
</body>

</html>