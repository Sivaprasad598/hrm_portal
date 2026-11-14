<?php
// --- Include database configuration and establish connection ---
require_once __DIR__ . '/config/database.php';

// require_once does nothing when the framework has already loaded this
// config, so the connection it makes is taken from the shared handle.
$conn = $GLOBALS['legacyMysqli'] ?? ($conn ?? null);

// --- Get parameters ---
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$designation_id = isset($_POST['designation_id']) ? intval($_POST['designation_id']) : (isset($_GET['designation_id']) ? intval($_GET['designation_id']) : 0);

if ($id <= 0) {
    header('Location: ' . (function_exists('route') ? route('legacy.page', 'identity') : 'identity.php') . '?error=missing_id');
    exit;
}

$one = function (string $sql, string $types = '', array $args = []) use ($conn) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$args);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
};

// --- The employee: account, record, branch ---
$user = $one('SELECT id, name, email, mobile AS phone, image, company_id FROM users WHERE id = ? LIMIT 1', 'i', [$id]);

if (!$user) {
    die('<p style="font-family:Arial;text-align:center;margin-top:100px;color:#d92d20;">Employee not found.</p>');
}

$detail = $one(
    'SELECT ed.employee_id, ed.blood_group, ed.joining_date, ed.branch_id, ed.company_address_id, d.name AS designation, t.team_name AS department
     FROM employee_details ed
     LEFT JOIN designations d ON d.id = ed.designation_id
     LEFT JOIN teams t ON t.id = ed.department_id
     WHERE ed.user_id = ? LIMIT 1', 'i', [$id]
) ?? [];

// designation: the employee's own; an id passed in is only a fallback
$designationName = $detail['designation'] ?? null;
if (!$designationName && $designation_id > 0) {
    $designationName = $one('SELECT name FROM designations WHERE id = ?', 'i', [$designation_id])['name'] ?? null;
}

$branchName = null;
if (!empty($detail['branch_id'])) {
    $branchName = $one('SELECT branch_name FROM branches WHERE id = ?', 'i', [(int) $detail['branch_id']])['branch_name'] ?? null;
}
if (!$branchName && !empty($detail['company_address_id'])) {
    $branchName = $one('SELECT location FROM company_addresses WHERE id = ?', 'i', [(int) $detail['company_address_id']])['location'] ?? null;
}

// --- The company (Settings -> Company), for "if found, please return to" ---
$company = $one('SELECT company_name, company_email, company_phone, website, address FROM companies WHERE id = ? LIMIT 1', 'i', [(int) ($user['company_id'] ?: 1)])
    ?? $one('SELECT company_name, company_email, company_phone, website, address FROM companies ORDER BY id LIMIT 1') ?? [];

// --- The first emergency contact on the employee's record ---
$emergency = $one('SELECT name, mobile, relation FROM emergency_contacts WHERE user_id = ? ORDER BY id LIMIT 1', 'i', [$id]);

// --- Display values (escaped once, here) ---
$h = fn ($v) => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$name = $h($user['name']);
$role = $designationName ? $h($designationName) : 'Employee';
$department = $h($detail['department'] ?? '');
$empId = !empty($detail['employee_id']) ? $h($detail['employee_id']) : 'EMP' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
$phone = $h($user['phone'] ?? '');
$email = $h($user['email'] ?? '');
$blood = $h($detail['blood_group'] ?? '');
$branch = $h($branchName ?? '');
$joined = !empty($detail['joining_date']) ? date('d M Y', strtotime($detail['joining_date'])) : '';
$companyName = $h($company['company_name'] ?? '');
$companyPhone = $h($company['company_phone'] ?? '');
$companyEmail = $h($company['company_email'] ?? '');
$companyWebsite = $h(preg_replace('#^https?://|/$#', '', (string) ($company['website'] ?? '')));
$companyAddress = $h(preg_replace('/\s+/', ' ', strip_tags((string) ($company['address'] ?? ''))));
// the address Company Settings starts with is a placeholder, not an address
if (strcasecmp($companyAddress, 'Your Company address here') === 0) {
    $companyAddress = '';
}
$emergencyLine = $emergency ? trim($h($emergency['name']) . ($emergency['relation'] ? ' (' . $h($emergency['relation']) . ')' : '')) : '';
$emergencyPhone = $emergency ? $h($emergency['mobile']) : '';

// Initials for a person without a photo: first and last word, brackets left out
// ("A S V N Murthy (avm)" -> "AM")
$words = preg_split('/\s+/', trim(preg_replace('/\(.*?\)|[^\p{L}\s]/u', ' ', (string) $user['name']))) ?: [];
$words = array_values(array_filter($words));
$initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));

$photoSrc = null;
if (!empty($user['image'])) {
    $photoSrc = function_exists('asset_url_local_s3')
        ? asset_url_local_s3('avatar/' . $user['image'])
        : (function_exists('asset') ? asset('user-uploads/avatar/' . rawurlencode($user['image'])) : 'user-uploads/avatar/' . rawurlencode($user['image']));
}

$pdfName = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $user['name']));
$logoPath = function_exists('asset') ? asset('img/arowana_logo_new.png') : 'img/arowana_logo_new.png';
$backUrl = function_exists('route') ? route('identity.index') : 'identity.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ID Card – <?= $name ?></title>
<style>
/* The app's theme: brand colour (Theme Settings, --epic-blue) and the dark of the top bar */
:root { --brand: var(--epic-blue, #cb6b4f); --ink: #231f20; --text: #101828; --muted: #667085; --line: #eaecf0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f3f3f9; font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; color: var(--text); padding: 0 0 24px; }

/* the page bar: back on the left, download on the right */
.idc-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #fff; border: 1px solid #e8e9e9;
    border-radius: 12px; box-shadow: 0 1px 3px rgba(16,24,40,.06); padding: 12px 18px; margin-bottom: 16px; }
.idc-back { color: var(--muted); text-decoration: none; font-size: 14px; font-weight: 500; }
.idc-back:hover { color: var(--brand); }
.idc-who { font-size: 14px; color: var(--muted); }
.idc-who b { color: var(--text); }
.btn-dl { height: 38px; padding: 0 16px; border: 1px solid var(--brand); border-radius: 8px; background: var(--brand); color: #fff;
    font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-dl:hover { filter: brightness(.93); }
.btn-dl:disabled { opacity: .65; cursor: not-allowed; }

/* the two sides, at ID-card proportions (CR80: 54 x 86 mm) */
.idc-sheet { display: flex; gap: 32px; justify-content: center; flex-wrap: wrap; padding: 28px 16px; background: #fff; border: 1px solid #e8e9e9;
    border-radius: 12px; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
.idc-card { width: 324px; height: 514px; background: #fff; border-radius: 16px; overflow: hidden; position: relative; display: flex; flex-direction: column;
    border: 1px solid var(--line); box-shadow: 0 10px 30px rgba(16,24,40,.12); }
.idc-side { text-align: center; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #98a2b3; margin-top: 10px; }

/* front */
.f-head { height: 64px; display: flex; align-items: center; justify-content: center; background: #fff; }
.f-head img { height: 36px; width: auto; }
.f-band { height: 92px; background: var(--brand); position: relative; flex: none; }
.f-band::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 6px; background: var(--ink); opacity: .9; }
.f-photo { position: absolute; left: calc(50% - 62px); bottom: -58px; width: 124px; height: 124px; border-radius: 50%;
    border: 5px solid #fff; box-shadow: 0 6px 18px rgba(16,24,40,.18); overflow: hidden; background: #f2f4f7; z-index: 1; }
.f-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block; }
.f-initials { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 42px; line-height: 1; font-weight: 700;
    color: var(--brand); background: #fbeee9; letter-spacing: .02em; }
.f-body { flex: 1; padding: 70px 22px 0; text-align: center; display: flex; flex-direction: column; }
.f-name { font-size: 19px; font-weight: 700; line-height: 1.25; color: var(--text); }
.f-role { margin-top: 4px; font-size: 13px; font-weight: 600; color: var(--brand); }
.f-dept { margin-top: 2px; font-size: 12px; color: var(--muted); }
.f-facts { margin-top: 18px; display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.f-facts > div { padding: 10px 6px; }
.f-facts > div + div { border-left: 1px solid var(--line); }
.f-facts.one { grid-template-columns: 1fr; }
.k { font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase; color: #98a2b3; font-weight: 600; }
.v { margin-top: 3px; font-size: 13.5px; font-weight: 700; color: var(--text); word-break: break-word; }
.f-foot { margin-top: auto; height: 40px; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; letter-spacing: .3em; text-transform: uppercase; }
.f-foot i { width: 6px; height: 6px; border-radius: 50%; background: var(--brand); display: inline-block; margin: 0 10px; }

/* back */
.b-head { height: 56px; background: var(--ink); display: flex; align-items: center; justify-content: space-between; padding: 0 18px; flex: none;
    border-bottom: 4px solid var(--brand); }
.b-head .t { color: #fff; font-size: 11px; font-weight: 600; letter-spacing: .22em; text-transform: uppercase; }
.b-head .id { color: #fff; font-size: 12.5px; font-weight: 700; letter-spacing: .06em; }
.b-body { flex: 1; padding: 16px 20px 0; display: flex; flex-direction: column; }
.b-title { font-size: 9.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--brand); font-weight: 700; margin-bottom: 6px; }
.b-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px dashed var(--line); font-size: 12px; }
.b-row .k { margin: 0; flex: none; padding-top: 1px; }
.b-row .x { color: var(--text); font-weight: 600; text-align: right; word-break: break-word; }
.b-block { margin-top: 14px; }
.b-return { margin: auto 0 16px; /* at the foot of the card */ padding: 10px 12px; border-radius: 10px; background: #f9fafb; border: 1px solid var(--line); font-size: 11.5px;
    color: var(--muted); line-height: 1.5; }
.b-return b { color: var(--text); display: block; font-size: 12px; margin-bottom: 2px; }
.b-foot { height: 8px; background: var(--brand); flex: none; }

body.pdf-capture .idc-card { border-radius: 0; box-shadow: none; border: 0; }

@media print {
    body { background: #fff; padding: 0; }
    .idc-bar, .idc-side { display: none; }
    .idc-sheet { border: 0; box-shadow: none; padding: 0; }
    .idc-card { box-shadow: none; }
}
</style>
</head>
<body>

<div class="idc-bar">
    <a class="idc-back" href="<?= $h($backUrl) ?>" target="_top">&larr; Another employee</a>
    <span class="idc-who"><b><?= $name ?></b> &middot; <?= $empId ?></span>
    <button class="btn-dl" type="button" onclick="downloadPDF()">Download PDF</button>
</div>

<div class="idc-sheet" id="idcSheet">

    <!-- FRONT -->
    <div>
        <div class="idc-card" id="idcFront">
            <div class="f-head"><img src="<?= $h($logoPath) ?>" alt="<?= $companyName ?>"></div>
            <div class="f-band">
                <div class="f-photo">
                    <?php if ($photoSrc): ?>
                        <img src="<?= $h($photoSrc) ?>" alt="<?= $name ?>"
                             onerror="this.parentNode.innerHTML='<div class=&quot;f-initials&quot;><?= $initials ?></div>'">
                    <?php else: ?>
                        <div class="f-initials"><?= $initials ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="f-body">
                <div class="f-name"><?= $name ?></div>
                <div class="f-role"><?= $role ?></div>
                <?php if ($department): ?><div class="f-dept"><?= $department ?></div><?php endif; ?>

                <div class="f-facts<?= $blood ? '' : ' one' ?>">
                    <div><div class="k">Employee ID</div><div class="v"><?= $empId ?></div></div>
                    <?php if ($blood): ?><div><div class="k">Blood group</div><div class="v"><?= $blood ?></div></div><?php endif; ?>
                </div>
            </div>
            <div class="f-foot">Employee<i></i>Identity</div>
        </div>
        <div class="idc-side">Front</div>
    </div>

    <!-- BACK -->
    <div>
        <div class="idc-card" id="idcBack">
            <div class="b-head"><span class="t">Identity card</span><span class="id"><?= $empId ?></span></div>
            <div class="b-body">
                <div class="b-title">Employee</div>
                <?php if ($phone): ?><div class="b-row"><span class="k">Mobile</span><span class="x"><?= $phone ?></span></div><?php endif; ?>
                <?php if ($email): ?><div class="b-row"><span class="k">Email</span><span class="x"><?= $email ?></span></div><?php endif; ?>
                <?php if ($branch): ?><div class="b-row"><span class="k">Branch</span><span class="x"><?= $branch ?></span></div><?php endif; ?>
                <?php if ($joined): ?><div class="b-row"><span class="k">Joined</span><span class="x"><?= $joined ?></span></div><?php endif; ?>

                <?php if ($emergencyPhone): ?>
                    <div class="b-block">
                        <div class="b-title">In an emergency</div>
                        <div class="b-row"><span class="k"><?= $emergencyLine ?: 'Contact' ?></span><span class="x"><?= $emergencyPhone ?></span></div>
                    </div>
                <?php endif; ?>

                <div class="b-return">
                    <b>If found, please return to</b>
                    <?= $companyName ?><?= $companyAddress ? '<br>' . $companyAddress : '' ?>
                    <?php $reach = array_filter([$companyPhone, $companyEmail, $companyWebsite]); ?>
                    <?= $reach ? '<br>' . implode(' &middot; ', $reach) : '' ?>
                </div>

            </div>
            <div class="b-foot"></div>
        </div>
        <div class="idc-side">Back</div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// The two sides as images, each exactly one card (CR80: 54 x 85.6 mm)
async function renderCards() {
    document.body.classList.add('pdf-capture');
    try {
        await document.fonts.ready;
        const opts = { scale: 4, backgroundColor: '#ffffff', logging: false, useCORS: true };
        return [await html2canvas(document.getElementById('idcFront'), opts), await html2canvas(document.getElementById('idcBack'), opts)];
    } finally {
        document.body.classList.remove('pdf-capture');
    }
}

// One PDF, card size: page 1 the front, page 2 the back - ready for a card printer
async function downloadPDF() {
    const btn = document.querySelector('.btn-dl');
    btn.disabled = true;
    btn.textContent = 'Preparing…';

    try {
        const [front, back] = await renderCards();
        const { jsPDF } = window.jspdf;
        const size = [54, 85.6];
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: size });

        pdf.addImage(front.toDataURL('image/png'), 'PNG', 0, 0, size[0], size[1]);
        pdf.addPage(size, 'portrait');
        pdf.addImage(back.toDataURL('image/png'), 'PNG', 0, 0, size[0], size[1]);
        pdf.save('ID_Card_<?= $pdfName ?>.pdf');
    } catch (e) {
        console.error(e);
        alert('The PDF could not be made. Please try again.');
    }

    btn.textContent = 'Download PDF';
    btn.disabled = false;
}
</script>

</body>
</html>
