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

// --- Check if blood_group column exists ---
$bg_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'blood_group'");
$blood_col = mysqli_num_rows($bg_check) > 0 ? ", COALESCE(u.blood_group,'') AS blood_group" : ", '' AS blood_group";

// --- Fetch user data (only columns that exist) ---
$stmt = $conn->prepare(
    "SELECT u.id, u.name, u.email, u.mobile AS phone, u.image
            $blood_col
     FROM users u
     WHERE u.id = ? LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('<p style="font-family:Arial;text-align:center;margin-top:100px;color:red;">User not found.</p>');
}

// --- Fetch designation name (if provided) ---
$designationName = null;
if ($designation_id > 0) {
    $designQuery = "SELECT name FROM designations WHERE id = ?";
    $stmtDes = $conn->prepare($designQuery);
    $stmtDes->bind_param("i", $designation_id);
    $stmtDes->execute();
    $desResult = $stmtDes->get_result();
    $desRow = $desResult->fetch_assoc();
    if ($desRow) {
        $designationName = $desRow['name'];
    }
    $stmtDes->close();
}
$conn->close();

// --- Determine role ---
$role = $designationName ? ucfirst(htmlspecialchars($designationName)) : 'Employee';

// --- Generate employee ID from user ID ---
$empId = 'EMP' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);

// --- Prepare display variables ---
$name   = htmlspecialchars(ucwords(strtolower($user['name'] ?? '')));
$phone  = !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'N/A';
$email  = htmlspecialchars($user['email'] ?? '');
$branch = 'N/A';
$blood  = htmlspecialchars($user['blood_group'] ?? '');

// --- Always use avatar fallback, but prefer stored user image ---
if (!empty($user['image'])) {
    $photoSrc = '/hrm/public/user-uploads/avatar/' . rawurlencode($user['image']);
} else {
    $photoSrc = 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=150&background=b84cff&color=fff&bold=true';
}

$pdfName  = strtoupper(str_replace(' ', '_', $user['name']));

// --- Logo path – absolute from document root ---
$logoPath = '/hrm/public/img/arowana_logo_new.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AROWANA ID Card – <?php echo $name; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100vh;
    background: #12111f;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 20px;
    font-family: 'Inter', sans-serif;
}

.page-title {
    font-family: 'Cinzel', serif;
    font-size: 1.1rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #c8922a;
    margin-bottom: 44px;
    text-align: center;
}

.cards-container {
    display: flex;
    gap: 50px;
    flex-wrap: wrap;
    justify-content: center;
    align-items: flex-start;
}

.id-wrapper { display: flex; flex-direction: column; align-items: center; }

.lanyard-strap {
    width: 10px;
    height: 58px;
    background: linear-gradient(to bottom, #3b1fa3, #0b1c63);
    border-radius: 0 0 3px 3px;
    position: relative;
}
.lanyard-strap::before {
    content: '';
    width: 78px;
    height: 78px;
    border: 7px solid #0b1c63;
    border-bottom: none;
    border-radius: 60px 60px 0 0;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    top: -73px;
}
.lanyard-clip {
    width: 18px;
    height: 22px;
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    border-radius: 3px;
    box-shadow: 0 2px 6px rgba(124,58,237,.45);
}

.card {
    width: 360px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.65);
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* ── FRONT CARD ── */
.front-card { background: #0d0d0d; }

.photo-zone {
    height: 290px;
    background: #ffffff;
    position: relative;
    flex-shrink: 0;
}
.photo-zone img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
}

.curve-svg {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 68px;
    display: block;
}

.info-zone {
    background: #0d0d0d;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 6px 26px 22px;
    text-align: center;
    min-height: 240px;
}

.emp-name {
    font-family: 'Cinzel', serif;
    font-size: 21px;
    font-weight: 700;
    background: linear-gradient(180deg,#fff8dc 0%,#ffd700 35%,#d4af37 65%,#b8860b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.3;
    margin-bottom: 7px;
    padding-top: 2px;
}

.gold-hr {
    width: 50%;
    border: none;
    border-top: 1px solid rgba(200,146,42,.55);
    margin: 4px auto 10px;
}

.emp-role {
    font-size: 14.5px;
    color: rgba(255,255,255,.85);
    font-weight: 500;
    letter-spacing: .4px;
    margin-bottom: 9px;
}

.emp-id {
    font-family: 'Cinzel', serif;
    font-size: 17px;
    font-weight: 700;
    color: #c8922a;
    letter-spacing: 2px;
    margin-bottom: 5px;
}

.emp-blood {
    font-size: 13.5px;
    color: rgba(255,255,255,.7);
    letter-spacing: .8px;
}

.bottom-logo {
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid rgba(200,146,42,.18);
    width: 100%;
    text-align: center;
}
.bottom-logo img {
    max-width: 175px;
    height: auto;
    /* no filter – show original colors */
}

/* ── BACK CARD ── */
.back-card {
    background: linear-gradient(155deg, #0e0c2e 0%, #0a081e 55%, #050310 100%);
    min-height: 560px;
}

.back-card::before {
    content: '';
    position: absolute;
    top: -70px; right: -70px;
    width: 200px; height: 200px;
    background: rgba(200,146,42,.07);
    border-radius: 50%;
    pointer-events: none;
}
.back-card::after {
    content: '';
    position: absolute;
    bottom: -50px; left: -50px;
    width: 160px; height: 160px;
    background: rgba(109,40,217,.09);
    border-radius: 50%;
    pointer-events: none;
}

.back-header {
    padding: 22px 20px 18px;
    text-align: center;
    border-bottom: 2px solid rgba(200,146,42,.35);
    background: rgba(255,255,255,.03);
}
.back-header img {
    max-width: 155px;
    height: auto;
    /* no filter – show original colors */
}

.back-body {
    padding: 20px 22px 60px;
    position: relative;
    z-index: 1;
}

.b-section {
    font-size: 9.5px;
    color: #d4af37;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid rgba(200,146,42,.22);
}

.b-row {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-bottom: 8px;
    font-size: 11.5px;
    color: #d4af37;
    line-height: 1.5;
}
.b-icon {
    font-size: 12px;
    flex-shrink: 0;
    margin-top: 1px;
    color: #c8922a;
}
.b-val  { word-break: break-word; }
.b-val strong { color: #ffd700; }

.b-divider {
    border: none;
    border-top: 1px solid rgba(200,146,42,.18);
    margin: 14px 0;
}

.sig-wrap {
    margin-top: 18px;
    text-align: center;
}
.sig-line {
    width: 130px;
    border: none;
    border-top: 1.5px solid rgba(200,146,42,.55);
    margin: 0 auto 6px;
}
.sig-label {
    font-size: 9px;
    color: #d4af37;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.back-tagline {
    position: absolute;
    bottom: 16px;
    left: 0; right: 0;
    text-align: center;
    font-size: 8.5px;
    color: rgba(200,146,42,.45);
    letter-spacing: 3px;
    text-transform: uppercase;
    z-index: 1;
}

.action-bar {
    margin-top: 38px;
    display: flex;
    gap: 14px;
    justify-content: center;
}
.btn-dl {
    padding: 13px 32px;
    border: none;
    border-radius: 9px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    background: linear-gradient(135deg, #c8922a 0%, #f0c040 50%, #c8922a 100%);
    color: #0d0d0d;
    box-shadow: 0 4px 20px rgba(200,146,42,.35);
    transition: all .3s ease;
    letter-spacing: .4px;
    font-family: 'Inter', sans-serif;
}
.btn-dl:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(200,146,42,.5); }
.btn-dl:disabled { opacity: .65; cursor: not-allowed; transform: none; }
</style>
</head>
<body>

<div class="page-title">Arowana &mdash; Employee Identity Card</div>

<div class="cards-container">

    <!-- ══ FRONT ══ -->
    <div class="id-wrapper">
        <div class="lanyard-strap"></div>
        <div class="lanyard-clip"></div>

        <div class="card front-card">

            <div class="photo-zone">
                <img src="<?php echo $photoSrc; ?>" alt="<?php echo $name; ?>">
                <svg class="curve-svg" viewBox="0 0 360 68" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0,68 L0,50 C90,4 270,4 360,50 L360,68 Z" fill="#0d0d0d"/>
                    <path d="M0,50 C90,4 270,4 360,50" fill="none" stroke="#c8922a" stroke-width="2.5"/>
                </svg>
            </div>

            <div class="info-zone">
                <div class="emp-name"><?php echo $name; ?></div>
                <hr class="gold-hr">
                <div class="emp-role"><?php echo $role; ?></div>
                <div class="emp-id"><?php echo $empId; ?></div>
                <?php if(!empty($blood)): ?>
                <div class="emp-blood"><?php echo $blood; ?></div>
                <?php endif; ?>
                <div class="bottom-logo">
                    <img src="<?php echo $logoPath; ?>" alt="Arowana">
                </div>
            </div>

        </div>
    </div>

    <!-- ══ BACK ══ -->
    <div class="id-wrapper">
        <div class="lanyard-strap"></div>
        <div class="lanyard-clip"></div>

        <div class="card back-card">

            <div class="back-header">
                <img src="<?php echo $logoPath; ?>" alt="Arowana">
            </div>

            <div class="back-body">

                <div class="b-section">Employee Details</div>
                <div class="b-row"><span class="b-icon">👤</span><span class="b-val"><strong><?php echo $name; ?></strong></span></div>
                <div class="b-row"><span class="b-icon">🪪</span><span class="b-val">ID &nbsp;<strong><?php echo $empId; ?></strong></span></div>
                <div class="b-row"><span class="b-icon">💼</span><span class="b-val"><?php echo $role; ?></span></div>
                <div class="b-row"><span class="b-icon">📧</span><span class="b-val"><?php echo $email; ?></span></div>
                <div class="b-row"><span class="b-icon">📱</span><span class="b-val"><?php echo $phone; ?></span></div>
                <div class="b-row"><span class="b-icon">🏢</span><span class="b-val"><?php echo $branch; ?></span></div>
                <?php if(!empty($blood)): ?>
                <div class="b-row"><span class="b-icon">🩸</span><span class="b-val">Blood Group &nbsp;<strong><?php echo $blood; ?></strong></span></div>
                <?php endif; ?>

                <hr class="b-divider">

                <div class="b-section">Company Information</div>
                <div class="b-row"><span class="b-icon">📍</span><span class="b-val">Hyderabad, Telangana, India</span></div>
                <div class="b-row"><span class="b-icon">📞</span><span class="b-val">+91 9876543210</span></div>
                <div class="b-row"><span class="b-icon">✉️</span><span class="b-val">info@arowana.com</span></div>
                <div class="b-row"><span class="b-icon">🌐</span><span class="b-val">www.arowana.com</span></div>

                <hr class="b-divider">

                <div class="b-section">Emergency Contact</div>
                <div class="b-row"><span class="b-icon">🚨</span><span class="b-val">+91 9999999999</span></div>

                <div class="sig-wrap">
                    <div class="sig-line"></div>
                    <div class="sig-label">Authorized Signature</div>
                </div>

            </div>

            <div class="back-tagline">Precision &bull; Innovation &bull; Success</div>

        </div>
    </div>

</div>

<div class="action-bar">
    <button class="btn-dl" onclick="downloadPDF()">📥 Download ID Card (PDF)</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
async function downloadPDF() {
    const btn = document.querySelector('.btn-dl');
    btn.disabled = true;
    btn.textContent = '⏳ Generating PDF…';

    try {
        const container = document.querySelector('.cards-container');
        const canvas = await html2canvas(container, {
            scale: 2.5,
            backgroundColor: '#12111f',
            logging: false,
            useCORS: true
        });

        const { jsPDF } = window.jspdf;
        const imgData   = canvas.toDataURL('image/png');
        const pdf       = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pw        = pdf.internal.pageSize.getWidth();
        const ph        = pdf.internal.pageSize.getHeight();
        const ratio     = Math.min((pw - 20) / canvas.width, (ph - 20) / canvas.height);
        const w         = canvas.width  * ratio;
        const h         = canvas.height * ratio;

        pdf.addImage(imgData, 'PNG', (pw - w) / 2, (ph - h) / 2, w, h);
        pdf.save('Arowana_ID_<?php echo $pdfName; ?>.pdf');
    } catch (e) {
        console.error(e);
        alert('Error generating PDF. Please try again.');
    }

    btn.textContent = '📥 Download ID Card (PDF)';
    btn.disabled = false;
}
</script>

</body>
</html>
