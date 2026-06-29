<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Notifications Demo - Arteche Citizen Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --primary-dark: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fb;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        /* Modern Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1030;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-brand {
            padding: 1.75rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1.5rem;
        }

        .user-profile-sidebar {
            text-align: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            transition: transform 0.3s;
        }

        .avatar-circle:hover {
            transform: scale(1.05);
        }

        .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.875rem 1.5rem;
            transition: all 0.3s;
            position: relative;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            padding-left: 2rem;
        }

        .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
            border-left: 4px solid white;
        }

        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        .top-navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1020;
            height: 70px;
        }

        /* Notification Styles */
        .notification-item {
            transition: all 0.3s;
            border-left: 4px solid transparent;
            cursor: pointer;
            animation: slideIn 0.3s ease-out;
        }

        .notification-item.unread {
            background: #f0f9ff;
            border-left-color: var(--primary-color);
        }

        .notification-item.unread:hover {
            background: #e6f3ff;
        }

        .notification-item.read {
            background: white;
        }

        .notification-item.read:hover {
            background: #f8f9fa;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #adb5bd;
        }

        .notification-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .notification-item:hover .notification-actions {
            opacity: 1;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border: none;
            border-radius: 1rem;
            transition: all 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .stat-card.active {
            border: 2px solid var(--primary-color);
            background: #f0f9ff;
        }

        /* Settings Panel */
        .settings-card {
            background: white;
            border-radius: 1rem;
            border: none;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        /* Pagination */
        .pagination {
            gap: 0.25rem;
        }

        .page-link {
            border-radius: 8px;
            border: none;
            padding: 0.5rem 1rem;
            color: var(--primary-color);
        }

        .page-link:hover {
            background: #e9ecef;
            color: var(--primary-dark);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            color: white;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 1050;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .notification-actions {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-20px);
            }
        }

        .notification-item.removing {
            animation: slideOut 0.3s ease-out forwards;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4 class="mb-0"><i class="bi bi-shield-check"></i> Arteche Portal</h4>
            <small class="text-white-50">Eastern Samar</small>
        </div>

        <div class="user-profile-sidebar">
            <div class="avatar-circle">
                <i class="bi bi-person-fill" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="mb-1">Juan Dela Cruz</h6>
            <small class="text-white-50 opacity-75">Resident</small>
            <div class="mt-2">
                <span class="badge bg-success bg-opacity-75">
                    <i class="bi bi-check-circle-fill"></i> Verified
                </span>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return false;">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return false;">
                        <i class="bi bi-file-earmark-plus"></i> New Request
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return false;">
                        <i class="bi bi-files"></i> My Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#" onclick="return false;">
                        <i class="bi bi-bell"></i> Notifications
                        <span class="badge bg-danger float-end" id="sidebarUnreadBadge">5</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return false;">
                        <i class="bi bi-person"></i> Profile Settings
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <hr class="border-light opacity-25">
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="#" onclick="return false;">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <div class="position-absolute bottom-0 start-0 end-0 p-3 text-center">
            <small class="text-white-50 opacity-50">© 2024 LGU Arteche</small>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar px-4 d-flex align-items-center justify-content-between">
            <div>
                <button class="btn btn-link d-md-none text-dark p-0" type="button" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-3"></i>
                </button>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-link text-dark p-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="text-end d-none d-sm-block">
                            <div class="fw-semibold small">Juan Dela Cruz</div>
                            <div class="text-muted small">Resident</div>
                        </div>
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
                <div>
                    <h2 class="h3 mb-1 fw-bold">
                        <i class="bi bi-bell-fill text-primary me-2"></i>Notifications
                    </h2>
                    <p class="text-muted mb-0">Stay updated with your document requests and barangay announcements</p>
                </div>
                <button class="btn btn-outline-primary" id="markAllReadBtn">
                    <i class="bi bi-check2-all"></i> Mark all as read
                </button>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="notificationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" 
                            type="button" role="tab">
                        <i class="bi bi-bell"></i> Notifications
                        <span class="badge bg-danger ms-1" id="unreadBadge">5</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" 
                            type="button" role="tab">
                        <i class="bi bi-gear"></i> Settings
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Notifications Tab -->
                <div class="tab-pane fade show active" id="notifications" role="tabpanel">
                    
                    <!-- Filter Bar -->
                    <div class="d-flex gap-2 mb-4">
                        <div class="stat-card flex-fill text-center p-3" data-filter="all" onclick="filterNotifications('all')">
                            <div class="stat-card-content">
                                <h3 class="mb-0 fw-bold" id="totalCount">12</h3>
                                <small class="text-muted">All</small>
                            </div>
                        </div>
                        <div class="stat-card flex-fill text-center p-3" data-filter="unread" onclick="filterNotifications('unread')">
                            <div class="stat-card-content">
                                <h3 class="mb-0 fw-bold text-warning" id="unreadCount">5</h3>
                                <small class="text-muted">Unread</small>
                            </div>
                        </div>
                        <div class="stat-card flex-fill text-center p-3" data-filter="read" onclick="filterNotifications('read')">
                            <div class="stat-card-content">
                                <h3 class="mb-0 fw-bold text-success" id="readCount">7</h3>
                                <small class="text-muted">Read</small>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications List Container -->
                    <div id="notificationsList">
                        <!-- Notifications will be loaded here -->
                    </div>

                    <!-- Pagination -->
                    <nav class="mt-4" id="paginationContainer">
                        <ul class="pagination justify-content-center" id="pagination">
                        </ul>
                    </nav>
                </div>

                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings" role="tabpanel">
                    <div class="settings-card p-4">
                        <h5 class="mb-4">
                            <i class="bi bi-bell-slash me-2"></i>Notification Preferences
                        </h5>

                        <form id="preferencesForm">
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">Request Updates</h6>
                                        <small class="text-muted">Get notified when your document request status changes</small>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="request_updates" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">Announcements</h6>
                                        <small class="text-muted">Receive barangay announcements and updates</small>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="announcements" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">Payment Reminders</h6>
                                        <small class="text-muted">Get reminders for pending payments</small>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="payment_reminders" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="mb-3">Delivery Channels</h6>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">Email Notifications</h6>
                                        <small class="text-muted">Receive notifications via email</small>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="email_notifications" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">SMS Notifications</h6>
                                        <small class="text-muted">Receive notifications via SMS (charges may apply)</small>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="sms_notifications">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                You'll always receive important notifications about your document requests regardless of these settings.
                            </div>

                            <div class="mt-4">
                                <button type="button" class="btn btn-primary" onclick="savePreferences()">
                                    <i class="bi bi-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mock notifications data
        let notifications = [
            {
                id: 1,
                type: 'request_approved',
                title: 'Document Request Approved',
                message: 'Your Barangay Clearance request has been approved. You can now claim it at the barangay hall.',
                time: '2024-01-15T10:30:00',
                is_read: false,
                data: { request_id: 101 }
            },
            {
                id: 2,
                type: 'announcement',
                title: 'Barangay Assembly',
                message: 'Barangay Assembly will be held on January 20, 2024 at 9:00 AM at the Barangay Hall. Your attendance is requested.',
                time: '2024-01-14T09:00:00',
                is_read: false,
                data: { event_id: 201 }
            },
            {
                id: 3,
                type: 'payment_reminder',
                title: 'Payment Reminder',
                message: 'Your payment for Barangay Clearance is due in 3 days. Please settle at the treasurer\'s office.',
                time: '2024-01-13T14:15:00',
                is_read: false,
                data: { amount: 50.00 }
            },
            {
                id: 4,
                type: 'request_ready',
                title: 'Document Ready for Pickup',
                message: 'Your Certificate of Indigency is now ready for pickup at the barangay hall.',
                time: '2024-01-12T11:45:00',
                is_read: true,
                data: { request_id: 102 }
            },
            {
                id: 5,
                type: 'request_updated',
                title: 'Request Status Updated',
                message: 'Your Business Permit application is now under review by the barangay captain.',
                time: '2024-01-11T16:20:00',
                is_read: false,
                data: { request_id: 103 }
            },
            {
                id: 6,
                type: 'system',
                title: 'System Maintenance',
                message: 'The portal will undergo maintenance on January 25, 2024 from 2:00 AM to 4:00 AM.',
                time: '2024-01-10T08:00:00',
                is_read: true,
                data: {}
            },
            {
                id: 7,
                type: 'request_completed',
                title: 'Request Completed',
                message: 'Your Barangay ID request has been completed. Thank you for using our services!',
                time: '2024-01-09T13:30:00',
                is_read: true,
                data: { request_id: 104 }
            },
            {
                id: 8,
                type: 'announcement',
                title: 'Free Medical Mission',
                message: 'Free medical mission on January 28, 2024 at the Barangay Health Center. 8:00 AM - 12:00 PM.',
                time: '2024-01-08T10:00:00',
                is_read: true,
                data: {}
            },
            {
                id: 9,
                type: 'request_rejected',
                title: 'Request Requires Additional Documents',
                message: 'Your request for Building Permit needs additional documents. Please check the requirements.',
                time: '2024-01-07T15:45:00',
                is_read: true,
                data: { request_id: 105 }
            },
            {
                id: 10,
                type: 'request_created',
                title: 'New Request Submitted',
                message: 'Your request for Business Clearance has been submitted successfully. Reference #: BC-2024-001',
                time: '2024-01-06T09:15:00',
                is_read: true,
                data: { request_id: 106 }
            }
        ];

        let currentFilter = 'all';
        let currentPage = 1;
        const itemsPerPage = 5;

        // Icon mapping
        const getNotificationIcon = (type) => {
            const icons = {
                'request_created': { icon: 'bi-file-earmark-plus', color: 'primary' },
                'request_updated': { icon: 'bi-arrow-repeat', color: 'info' },
                'request_approved': { icon: 'bi-check-circle', color: 'success' },
                'request_rejected': { icon: 'bi-x-circle', color: 'danger' },
                'request_ready': { icon: 'bi-box-seam', color: 'warning' },
                'request_completed': { icon: 'bi-trophy', color: 'success' },
                'payment_reminder': { icon: 'bi-credit-card', color: 'danger' },
                'announcement': { icon: 'bi-megaphone', color: 'info' },
                'system': { icon: 'bi-gear', color: 'secondary' }
            };
            return icons[type] || { icon: 'bi-bell', color: 'secondary' };
        };

        // Format time
        const formatTime = (timeString) => {
            const time = new Date(timeString);
            const now = new Date();
            const diff = Math.floor((now - time) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return time.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };

        // Update statistics
        const updateStats = () => {
            const unreadCount = notifications.filter(n => !n.is_read).length;
            const readCount = notifications.filter(n => n.is_read).length;
            const totalCount = notifications.length;
            
            document.getElementById('unreadCount').textContent = unreadCount;
            document.getElementById('readCount').textContent = readCount;
            document.getElementById('totalCount').textContent = totalCount;
            document.getElementById('unreadBadge').textContent = unreadCount;
            document.getElementById('sidebarUnreadBadge').textContent = unreadCount;
            
            // Hide badge if zero
            if (unreadCount === 0) {
                document.getElementById('unreadBadge').style.display = 'none';
                document.getElementById('sidebarUnreadBadge').style.display = 'none';
            } else {
                document.getElementById('unreadBadge').style.display = 'inline-block';
                document.getElementById('sidebarUnreadBadge').style.display = 'inline-block';
            }
        };

        // Get filtered notifications
        const getFilteredNotifications = () => {
            if (currentFilter === 'unread') {
                return notifications.filter(n => !n.is_read);
            } else if (currentFilter === 'read') {
                return notifications.filter(n => n.is_read);
            }
            return notifications;
        };

        // Render notifications
        const renderNotifications = () => {
            const filtered = getFilteredNotifications();
            const start = (currentPage - 1) * itemsPerPage;
            const paginated = filtered.slice(start, start + itemsPerPage);
            const container = document.getElementById('notificationsList');
            
            if (paginated.length === 0) {
                container.innerHTML = `
                    <div class="card border-0 shadow-sm">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-bell-slash"></i>
                            </div>
                            <h5>No notifications yet</h5>
                            <p class="text-muted mb-3">
                                ${currentFilter === 'unread' ? 'You\'ve read all your notifications! 🎉' :
                                  currentFilter === 'read' ? 'No read notifications found.' :
                                  'When you receive notifications about your document requests, they\'ll appear here.'}
                            </p>
                            ${currentFilter !== 'all' ? 
                                '<button class="btn btn-primary" onclick="filterNotifications(\'all\')"><i class="bi bi-eye"></i> View all notifications</button>' :
                                '<button class="btn btn-primary" onclick="showDemoToast()"><i class="bi bi-file-earmark-plus"></i> Make your first request</button>'}
                        </div>
                    </div>
                `;
                document.getElementById('paginationContainer').style.display = 'none';
                return;
            }
            
            document.getElementById('paginationContainer').style.display = 'block';
            
            container.innerHTML = `
                <div class="card border-0 shadow-sm">
                    <div class="list-group list-group-flush">
                        ${paginated.map(notification => {
                            const iconInfo = getNotificationIcon(notification.type);
                            const isUnread = !notification.is_read;
                            return `
                                <div class="list-group-item notification-item ${isUnread ? 'unread' : 'read'}" 
                                     data-id="${notification.id}">
                                    <div class="d-flex gap-3">
                                        <div class="notification-icon bg-${iconInfo.color} bg-opacity-10 text-${iconInfo.color}">
                                            <i class="bi ${iconInfo.icon} fs-5"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="notification-title">
                                                        ${escapeHtml(notification.title)}
                                                        ${isUnread ? '<span class="badge bg-primary ms-2">New</span>' : ''}
                                                    </div>
                                                    <div class="notification-message">
                                                        ${escapeHtml(notification.message)}
                                                    </div>
                                                    <div class="notification-time">
                                                        <i class="bi bi-clock"></i> ${formatTime(notification.time)}
                                                        ${notification.read_at ? ` • Read ${formatTime(notification.read_at)}` : ''}
                                                    </div>
                                                </div>
                                                <div class="notification-actions">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            ${!notification.is_read ? `
                                                                <li>
                                                                    <button class="dropdown-item" onclick="markAsRead(${notification.id})">
                                                                        <i class="bi bi-check2"></i> Mark as read
                                                                    </button>
                                                                </li>
                                                            ` : ''}
                                                            <li>
                                                                <button class="dropdown-item text-danger" onclick="deleteNotification(${notification.id})">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            
            renderPagination(filtered.length);
        };
        
        // Render pagination
        const renderPagination = (totalItems) => {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const paginationContainer = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }
            
            let paginationHtml = '';
            
            // Previous button
            paginationHtml += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            `;
            
            // Page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                    </li>
                `;
            }
            
            // Next button
            paginationHtml += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            `;
            
            paginationContainer.innerHTML = paginationHtml;
        };
        
        // Change page
        const changePage = (page) => {
            currentPage = page;
            renderNotifications();
        };
        
        // Filter notifications
        const filterNotifications = (filter) => {
            currentFilter = filter;
            currentPage = 1;
            
            // Update active state on stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                if (card.getAttribute('data-filter') === filter) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
            
            renderNotifications();
        };
        
        // Mark notification as read
        const markAsRead = (id) => {
            const notification = notifications.find(n => n.id === id);
            if (notification && !notification.is_read) {
                notification.is_read = true;
                notification.read_at = new Date().toISOString();
                updateStats();
                renderNotifications();
                showToast('Notification marked as read', 'success');
            }
        };
        
        // Mark all as read
        const markAllAsRead = () => {
            let markedCount = 0;
            notifications.forEach(n => {
                if (!n.is_read) {
                    n.is_read = true;
                    n.read_at = new Date().toISOString();
                    markedCount++;
                }
            });
            if (markedCount > 0) {
                updateStats();
                renderNotifications();
                showToast(`${markedCount} notification${markedCount > 1 ? 's' : ''} marked as read`, 'success');
            } else {
                showToast('No unread notifications', 'info');
            }
        };
        
        // Delete notification
        const deleteNotification = (id) => {
            if (confirm('Are you sure you want to delete this notification?')) {
                const index = notifications.findIndex(n => n.id === id);
                if (index !== -1) {
                    const element = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (element) {
                        element.classList.add('removing');
                        setTimeout(() => {
                            notifications.splice(index, 1);
                            updateStats();
                            renderNotifications();
                            showToast('Notification deleted', 'success');
                        }, 300);
                    } else {
                        notifications.splice(index, 1);
                        updateStats();
                        renderNotifications();
                        showToast('Notification deleted', 'success');
                    }
                }
            }
        };
        
        // Save preferences
        const savePreferences = () => {
            const form = document.getElementById('preferencesForm');
            const formData = new FormData(form);
            const preferences = {};
            for (let [key, value] of formData.entries()) {
                preferences[key] = true;
            }
            
            showToast('Preferences saved successfully!', 'success');
        };
        
        // Show toast notification
        const showToast = (message, type = 'info') => {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        };
        
        // Demo toast for "Make your first request"
        const showDemoToast = () => {
            showToast('This is a demo. In the actual portal, you would be redirected to the request form.', 'info');
        };
        
        // Escape HTML to prevent XSS
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Toggle sidebar for mobile
        const toggleSidebar = () => {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
            
            if (sidebar.classList.contains('open')) {
                let overlay = document.querySelector('.sidebar-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.right = '0';
                    overlay.style.bottom = '0';
                    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
                    overlay.style.zIndex = '1029';
                    overlay.onclick = toggleSidebar;
                    document.body.appendChild(overlay);
                }
            } else {
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) overlay.remove();
            }
        };
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            updateStats();
            renderNotifications();
            
            // Mark all as read button
            document.getElementById('markAllReadBtn').addEventListener('click', markAllAsRead);
            
            // Add notification simulation (demo purpose)
            setTimeout(() => {
                const newNotification = {
                    id: Date.now(),
                    type: 'announcement',
                    title: 'New Announcement',
                    message: 'Barangay Fiesta celebration will be on February 15, 2024. Everyone is invited!',
                    time: new Date().toISOString(),
                    is_read: false,
                    data: {}
                };
                notifications.unshift(newNotification);
                updateStats();
                renderNotifications();
                showToast('New announcement received!', 'info');
            }, 5000);
        });
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (event) => {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.top-navbar button');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('open') &&
                !sidebar.contains(event.target) &&
                event.target !== toggleBtn &&
                !toggleBtn?.contains(event.target)) {
                toggleSidebar();
            }
        });
    </script>
</body>
</html>