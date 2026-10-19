<?php
// --- Include database configuration and establish connection ---
require_once __DIR__ . '/config/database.php';

// require_once does nothing when the framework has already loaded this
// config, so the connection it makes is taken from the shared handle.
$conn = $GLOBALS['legacyMysqli'] ?? ($conn ?? null);

// --- Populate dropdowns ---
// Active employees only (not clients, not deactivated accounts), with their
// employee ID and designation, so the list is the people an ID card is for.
$users = [];
$userResult = safeQuery($conn,
    "SELECT u.id, u.name, ed.employee_id, ed.designation_id
       FROM users u
       JOIN employee_details ed ON ed.user_id = u.id
      WHERE u.status = 'active'
        AND EXISTS (SELECT 1 FROM role_user ru JOIN roles r ON r.id = ru.role_id
                     WHERE ru.user_id = u.id AND r.name = 'employee')
      ORDER BY u.name");
if ($userResult && $userResult->num_rows > 0) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

// Designations that active employees hold, with how many, so no choice
// leads to an empty list.
$designationCounts = [];
foreach ($users as $u) {
    if (!empty($u['designation_id'])) {
        $designationCounts[$u['designation_id']] = ($designationCounts[$u['designation_id']] ?? 0) + 1;
    }
}

$designations = [];
$designResult = safeQuery($conn, "SELECT id, name FROM designations ORDER BY name");
if ($designResult && $designResult->num_rows > 0) {
    while ($row = $designResult->fetch_assoc()) {
        if (isset($designationCounts[$row['id']])) {
            $row['count'] = $designationCounts[$row['id']];
            $designations[] = $row;
        }
    }
}
$conn->close();

$cardUrl = function_exists('route') ? route('legacy.page', 'identity_card') : 'identity_card.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate ID Card</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #e5e5e5;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .dashboard-navbar {
            width: 100%;
            max-width: 1100px;
            margin-bottom: 24px;
            padding: 16px 24px;
            border-radius: 18px;
            background: rgba(10, 10, 10, 0.95);
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
        }
        .dashboard-navbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .dashboard-brand {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: 0.8px;
        }
        .dashboard-links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            align-items: center;
        }
        .dashboard-links a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
        }
        .dashboard-links a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: #fff;
            border-radius: 20px;
            padding: 35px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        h1 {
            text-align: center;
            color: #0b1c63;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 22px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #222;
        }
        select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #d0d5dd;
            border-radius: 8px;
            font-size: 1rem;
            background: #fafbfc;
        }
        select:focus {
            border-color: #7c3aed;
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }
        .btn-generate {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #b84cff, #7c3aed);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-generate:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
        }
        .btn-generate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .error-msg {
            background: #fde8e8;
            color: #c0392b;
            padding: 10px 14px;
            border-radius: 6px;
            border-left: 4px solid #c0392b;
            margin-bottom: 20px;
        }
        .test-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #7c3aed;
        }
        .test-link a {
            color: #7c3aed;
            text-decoration: none;
        }
        .test-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Generate Employee ID Card</h1>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'missing_id'): ?>
        <div class="error-msg">⚠️ Please select a user from the dropdown.</div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <div class="error-msg">⚠️ No active employees found.</div>
    <?php endif; ?>
    <?php if (empty($designations)): ?>
        <div class="error-msg">⚠️ No designations found in the database.</div>
    <?php endif; ?>

    <!-- Form now uses GET so parameters appear in URL -->
    <form action="<?php echo htmlspecialchars($cardUrl); ?>" method="GET">
        <!-- DESIGNATION DROPDOWN ON TOP -->
        <div class="form-group">
            <label for="designationSelect">Select Designation</label>
            <select id="designationSelect">
                <option value="all">All designations (<?php echo count($users); ?>)</option>
                <?php foreach ($designations as $d): ?>
                    <option value="<?php echo $d['id']; ?>">
                        <?php echo htmlspecialchars($d['name']) . ' (' . $d['count'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- USER DROPDOWN BELOW (FILTERED BY DESIGNATION) -->
        <div class="form-group">
            <label for="userSelect">Select User</label>
            <select id="userSelect" name="id" required>
                <option value="">— Choose an employee —</option>
            </select>
        </div>

        <button type="submit" class="btn-generate" <?php echo (empty($users) || empty($designations)) ? 'disabled' : ''; ?>>
            Generate ID Card
        </button>
    </form>

</div>

<script>
    // Active employees: id, name, employee ID, designation.
    const employees = <?php echo json_encode(array_map(fn ($u) => [
        'id' => (int) $u['id'], 'name' => $u['name'], 'code' => $u['employee_id'], 'designation' => (string) $u['designation_id'],
    ], $users)); ?>;

    const designationSelect = document.getElementById('designationSelect');
    const userSelect = document.getElementById('userSelect');

    function fillEmployees() {
        const chosen = designationSelect.value;
        const list = employees.filter(e => chosen === 'all' || e.designation === chosen);

        userSelect.innerHTML = '';
        const placeholder = new Option(list.length ? '— Choose an employee (' + list.length + ') —' : 'No active employees with this designation', '');
        userSelect.add(placeholder);
        list.forEach(e => userSelect.add(new Option(e.name + (e.code ? ' (' + e.code + ')' : ''), e.id)));
        userSelect.disabled = list.length === 0;
    }

    designationSelect.addEventListener('change', fillEmployees);
    fillEmployees();
</script>
