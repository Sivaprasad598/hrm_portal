<?php
/**
 * Account Dashboard - Role-Based Manual Auth Protected Page
 */

require_once __DIR__ . '/manual-auth-check.php';
requireAuth(); // Require authentication

$user = auth();

// Get user roles
require_once __DIR__ . '/manual-db-helper.php';
$conn = getManualDBConnection();
$stmt = $conn->prepare('
    SELECT r.id, r.name, r.display_name
    FROM roles r
    JOIN role_user ru ON r.id = ru.role_id
    WHERE ru.user_id = ?
    ORDER BY r.id ASC
');
$stmt->execute([$user['user_id']]);
$userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isAdmin = false;
$isEmployee = false;

foreach ($userRoles as $role) {
    if ($role['name'] === 'admin') {
        $isAdmin = true;
    } elseif ($role['name'] === 'employee') {
        $isEmployee = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        header { background: #2c3e50; color: white; padding: 20px; }
        header h1 { margin: 0; font-size: 28px; }
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .welcome-banner { background: white; padding: 30px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .welcome-banner h2 { color: #2c3e50; margin-bottom: 10px; font-size: 24px; }
        .welcome-banner p { color: #666; font-size: 16px; margin: 5px 0; }
        .badge { display: inline-block; background: #007bff; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 10px; font-size: 12px; font-weight: bold; margin-top: 10px; }
        .badge.admin { background: #dc3545; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
        .card.admin { border-left-color: #dc3545; }
        .card h3 { color: #2c3e50; margin-bottom: 15px; font-size: 16px; }
        .card p { color: #666; line-height: 1.6; }
        .card a { color: #007bff; text-decoration: none; font-weight: bold; }
        .card a:hover { text-decoration: underline; }
        .user-info { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .info-item { padding: 12px; background: #f9f9f9; border-left: 3px solid #007bff; }
        .info-item strong { display: block; color: #2c3e50; margin-bottom: 5px; }
        .info-item span { display: block; color: #666; }
        .logout-btn { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .logout-btn:hover { background: #c82333; }
        footer { background: #2c3e50; color: white; padding: 20px; text-align: center; margin-top: 40px; }
        .role-section { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .role-section h3 { color: #2c3e50; margin-bottom: 15px; }
        .admin-section { border-top: 4px solid #dc3545; }
        .employee-section { border-top: 4px solid #28a745; }
    </style>
</head>
<body>
    <header>
        <h1>Dashboard</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Logged in as: <?php echo htmlspecialchars($user['name']); ?></p>
    </header>

    <div class="container">
        <div class="welcome-banner">
            <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>! 🎉</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">Active</span></p>
            <div>
                <?php foreach ($userRoles as $role): ?>
                    <span class="badge <?php echo $role['name'] === 'admin' ? 'admin' : ''; ?>">
                        <?php echo htmlspecialchars($role['display_name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div class="role-section admin-section">
                <h3>👨‍💼 Admin Dashboard</h3>
                <div class="dashboard-grid">
                    <div class="card admin">
                        <h3>System Configuration</h3>
                        <p>Manage application settings, users, roles, and permissions. Configure email notifications, payment gateways, and system-wide preferences.</p>
                    </div>
                    <div class="card admin">
                        <h3>User Management</h3>
                        <p>Create, edit, and manage user accounts. Assign roles and permissions to control access levels across the organization.</p>
                    </div>
                    <div class="card admin">
                        <h3>Reports & Analytics</h3>
                        <p>View comprehensive reports on organizational metrics, employee performance, attendance, and financial data.</p>
                    </div>
                    <div class="card admin">
                        <h3>Audit Logs</h3>
                        <p>Review system activity logs, user actions, and security events for compliance and troubleshooting.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isEmployee): ?>
            <div class="role-section employee-section">
                <h3>👤 Employee Portal</h3>
                <div class="dashboard-grid">
                    <div class="card">
                        <h3>My Profile</h3>
                        <p>View and update your personal information, contact details, and profile picture.</p>
                    </div>
                    <div class="card">
                        <h3>Leaves & Attendance</h3>
                        <p>Request leaves, view your attendance records, and manage your work schedule.</p>
                    </div>
                    <div class="card">
                        <h3>Documents</h3>
                        <p>Access and download your employment documents, payslips, and certificates.</p>
                    </div>
                    <div class="card">
                        <h3>Notifications</h3>
                        <p>Stay updated with important announcements and system notifications.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="user-info">
            <h3>Account Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>User ID</strong>
                    <span><?php echo $user['user_id']; ?></span>
                </div>
                <div class="info-item">
                    <strong>Name</strong>
                    <span><?php echo htmlspecialchars($user['name']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Email</strong>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Authentication Type</strong>
                    <span>Manual Auth Token</span>
                </div>
            </div>
        </div>

        <div style="text-align: right;">
            <form method="POST" action="manual-logout.php" style="display: inline;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <footer>
        <p>Arowana HR Portal - Manual Authentication System</p>
        <p style="font-size: 12px; margin-top: 10px; opacity: 0.8;">Authentication: Manual Token-Based | Session Storage: Database | Role-Based Access Control Enabled</p>
    </footer>
</body>
</html>
