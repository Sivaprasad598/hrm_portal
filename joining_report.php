<?php
// Joining Report - Combined Page 1 & 2
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = ($page === 2) ? 2 : 1;

$employeeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$idQuerySuffix = $employeeId > 0 ? '&id=' . $employeeId : '';

function reportEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function reportDate($value): string
{
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('d-m-Y', $timestamp) : (string) $value;
}

function reportCheck($value): string
{
    return (int) $value === 1 ? '✔️' : '☐';
}

$employeeData = [
    'name' => '',
    'joining_date' => '',
    'designation' => '',
    'experience' => '',
    'department' => '',
    'father_or_husband_name' => '',
    'contact_no' => '',
    'marital_status' => '',
    'qualification' => '',
    'personal_email' => '',
    'aadhar_card_number' => '',
    'pan_number' => '',
    'blood_group' => '',
    'address' => '',
    'previous_company_name' => '',
    'company_location' => '',
    'last_held_designation' => '',
    'experience_joining_date' => '',
    'experience_leaving_date' => '',
    'employee_id' => '',
    'pf_account_number' => '',
    'esi_number' => '',
    'personal_account_number' => '',
    'ifsc_code' => '',
];

$passportData = [
    'passport_number' => '',
    'passport_date_of_issue' => '',
    'passport_place_of_issue' => '',
    'passport_valid_up_to' => '',
];

$checklistData = [
    'educational_testimonials' => 0,
    'experience_letter' => 0,
    'relieving_letter' => 0,
    'two_references' => 0,
    'salary_certificate' => 0,
    'bank_statement' => 0,
    'passport_copy' => 0,
    'form_16' => 0,
    'passport_photographs' => 0,
    'offer_letter' => 0,
    'resume_copy' => 0,
    'nda' => 0,
];

$finalDeclaration = [
    'reported_duty_day' => '',
    'reported_duty_month' => '',
    'reported_duty_year' => '',
    'candidate_signature' => '',
    'hr_signature' => '',
    'hr_comments' => '',
];

$emergencyContacts = [
    1 => ['name' => '', 'relation' => '', 'contact_number' => ''],
    2 => ['name' => '', 'relation' => '', 'contact_number' => ''],
];

$reportError = '';

if ($employeeId > 0) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $app = require __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $db = \Illuminate\Support\Facades\DB::connection();
        $employeeRow = $db->table('users as u')
            ->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->select([
                'u.name',
                'u.mobile',
                'u.email',
                'u.pan_number',
                'ed.joining_date',
                'ed.total_experience',
                'ed.father_or_husband_name',
                'ed.marital_status',
                'ed.qualification',
                'ed.aadhar_card_number',
                'ed.blood_group',
                'ed.address',
                'ed.previous_company_name',
                'ed.company_location',
                'ed.last_held_designation',
                'ed.experience_joining_date',
                'ed.experience_leaving_date',
                'ed.employee_id',
                'ed.pf_account_number',
                'ed.esi_number',
                'ed.personal_account_number',
                'ed.ifsc_code',
                'd.name as designation_name',
                't.team_name as department_name',
            ])
            ->where('u.id', $employeeId)
            ->first();

        if ($employeeRow) {
            $employeeRow = (array) $employeeRow;
            $employeeData['name'] = $employeeRow['name'] ?? '';
            $employeeData['joining_date'] = reportDate($employeeRow['joining_date'] ?? '');
            $employeeData['designation'] = $employeeRow['designation_name'] ?? '';
            $employeeData['experience'] = $employeeRow['total_experience'] ?? '';
            $employeeData['department'] = $employeeRow['department_name'] ?? '';
            $employeeData['father_or_husband_name'] = $employeeRow['father_or_husband_name'] ?? '';
            $employeeData['contact_no'] = $employeeRow['mobile'] ?? '';
            $employeeData['marital_status'] = $employeeRow['marital_status'] ?? '';
            $employeeData['qualification'] = $employeeRow['qualification'] ?? '';
            $employeeData['personal_email'] = $employeeRow['email'] ?? '';
            $employeeData['aadhar_card_number'] = $employeeRow['aadhar_card_number'] ?? '';
            $employeeData['pan_number'] = $employeeRow['pan_number'] ?? '';
            $employeeData['blood_group'] = $employeeRow['blood_group'] ?? '';
            $employeeData['address'] = $employeeRow['address'] ?? '';
            $employeeData['previous_company_name'] = $employeeRow['previous_company_name'] ?? '';
            $employeeData['company_location'] = $employeeRow['company_location'] ?? '';
            $employeeData['last_held_designation'] = $employeeRow['last_held_designation'] ?? '';
            $employeeData['experience_joining_date'] = reportDate($employeeRow['experience_joining_date'] ?? '');
            $employeeData['experience_leaving_date'] = reportDate($employeeRow['experience_leaving_date'] ?? '');
            $employeeData['employee_id'] = $employeeRow['employee_id'] ?? '';
            $employeeData['pf_account_number'] = $employeeRow['pf_account_number'] ?? '';
            $employeeData['esi_number'] = $employeeRow['esi_number'] ?? '';
            $employeeData['personal_account_number'] = $employeeRow['personal_account_number'] ?? '';
            $employeeData['ifsc_code'] = $employeeRow['ifsc_code'] ?? '';
        }

        $passportRow = $db->table('emp_passport_details')
            ->select(['passport_number', 'passport_date_of_issue', 'passport_place_of_issue', 'passport_valid_up_to'])
            ->where('user_id', $employeeId)
            ->orderByDesc('id')
            ->first();

        if ($passportRow) {
            $passportRow = (array) $passportRow;
            $passportData['passport_number'] = $passportRow['passport_number'] ?? '';
            $passportData['passport_date_of_issue'] = reportDate($passportRow['passport_date_of_issue'] ?? '');
            $passportData['passport_place_of_issue'] = $passportRow['passport_place_of_issue'] ?? '';
            $passportData['passport_valid_up_to'] = reportDate($passportRow['passport_valid_up_to'] ?? '');
        }

        $checklistRow = $db->table('emp_joining_checklists')
            ->select([
                'educational_testimonials', 'experience_letter', 'relieving_letter', 'two_references',
                'salary_certificate', 'bank_statement', 'passport_copy', 'form_16',
                'passport_photographs', 'offer_letter', 'resume_copy', 'nda',
            ])
            ->where('user_id', $employeeId)
            ->orderByDesc('id')
            ->first();

        if ($checklistRow) {
            $checklistData = array_merge($checklistData, (array) $checklistRow);
        }

        $finalRow = $db->table('emp_final_declarations')
            ->select(['reported_duty_day', 'reported_duty_month', 'reported_duty_year', 'candidate_signature', 'hr_signature', 'hr_comments'])
            ->where('user_id', $employeeId)
            ->orderByDesc('id')
            ->first();

        if ($finalRow) {
            $finalDeclaration = array_merge($finalDeclaration, (array) $finalRow);
        }

        $emergencyRows = $db->table('employee_relations')
            ->select(['serial_no', 'name', 'relation', 'contact_number'])
            ->where('user_id', $employeeId)
            ->whereIn('serial_no', [1, 2])
            ->get();

        foreach ($emergencyRows as $emergencyRow) {
            $emergencyContacts[(int) $emergencyRow->serial_no] = [
                'name' => $emergencyRow->name ?? '',
                'relation' => $emergencyRow->relation ?? '',
                'contact_number' => $emergencyRow->contact_number ?? '',
            ];
        }
    }
    catch (Throwable $e) {
        $reportError = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arowana Joining Report - Page <?php echo $page; ?></title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    background:#ececec;
    padding:30px;
}

.page-nav{
    width:850px;
    margin:auto;
    margin-bottom:15px;
    display:flex;
    gap:10px;
    justify-content:center;
}

.page-nav a, .page-nav button{
    padding:10px 20px;
    border:none;
    border-radius:4px;
    cursor:pointer;
    font-weight:bold;
    text-decoration:none;
    font-size:14px;
    transition:all 0.3s;
}

.page-nav a{
    background:#007bff;
    color:white;
}

.page-nav a:hover{
    background:#0056b3;
}

.page-nav button{
    background:#6c757d;
    color:white;
    cursor:not-allowed;
    opacity:0.6;
}

.container, .page{
    width:850px;
    margin:auto;
    background:#fff;
    padding:35px;
    box-shadow:0 0 12px rgba(0,0,0,.15);
}

.header{
    display:flex;
    align-items:center;
    margin-bottom:20px;
}

.logo{
    display:flex;
    align-items:center;
    gap:12px;
}

.logo-img{
    height:45px;
    width:auto;
}

.title{
    text-align:center;
    font-weight:bold;
    font-size:22px;
    margin:20px 0;
    letter-spacing:1px;
}

table{
    width:100%;
    border-collapse:collapse;
}

.main td{
    border:1px solid #555;
    height:36px;
    padding:8px;
    font-size:15px;
}

.main td:first-child{
    width:35%;
    font-weight:500;
}

.section{
    margin-top:35px;
    margin-bottom:12px;
    font-weight:bold;
    color:#1d4e89;
}

.contact-table th,
.contact-table td{
    border:1px solid #555;
    height:35px;
    padding:8px;
    text-align:center;
}

.contact-table th{
    background:#f5f5f5;
}

.exp{
    margin-top:30px;
    margin-bottom:15px;
    font-weight:bold;
}

.previous{
    text-align:center;
    font-weight:bold;
    font-size:21px;
    margin-bottom:10px;
    color:#234b88;
}

.previous-table td{
    border:1px solid #555;
    height:36px;
    padding:8px;
}

.previous-table td:first-child{
    width:45%;
}

.section-title{
    text-align:center;
    color:#204d8a;
    font-size:20px;
    font-weight:bold;
    margin:10px 0 20px;
}

.passport-table td{
    border:1px solid #555;
    height:35px;
    padding:8px;
}

.passport-table td:first-child{
    width:28%;
    font-weight:500;
}

.checklist-title{
    text-align:center;
    color:#204d8a;
    font-size:22px;
    font-weight:bold;
    margin-top:40px;
    margin-bottom:20px;
}

.checklist{
    width:85%;
    margin:auto;
    line-height:36px;
    font-size:19px;
}

.report{
    margin-top:30px;
    font-size:18px;
}

.line{
    display:inline-block;
    border-bottom:1px solid #000;
    width:70px;
}

.signature{
    display:flex;
    justify-content:space-between;
    margin:70px 0 25px;
    font-weight:bold;
    font-size:18px;
}

.comment-box{
    border:1px solid #444;
    height:130px;
    padding:10px;
    font-size:18px;
}

.version{
    display:flex;
    justify-content:space-between;
    margin-top:5px;
    font-size:11px;
}

.footer{
    margin-top:35px;
    text-align:center;
}

.footer h2{
    color:#a96c38;
    font-size:28px;
    margin-bottom:6px;
}

.footer p{
    font-size:15px;
    font-weight:bold;
    line-height:25px;
}

.page-indicator{
    text-align:center;
    margin-top:20px;
    font-size:12px;
    color:#999;
}

@media print{
    body{
        background:#fff;
        padding:0;
    }
    .container, .page{
        box-shadow:none;
        width:100%;
        padding:20px;
    }
    .page-nav{
        display:none;
    }
}

</style>

</head>
<body>

<div class="page-nav">
    <?php if($page === 1): ?>
        <button disabled>← Previous</button>
        <a href="?page=2<?php echo $idQuerySuffix; ?>">Next →</a>
    <?php else: ?>
        <a href="?page=1<?php echo $idQuerySuffix; ?>">← Previous</a>
        <button disabled>Next →</button>
    <?php endif; ?>
</div>

<?php if($employeeId <= 0): ?>
<div class="page-nav" style="justify-content:flex-start; color:#856404; background:#fff3cd; border:1px solid #ffeeba; padding:10px 14px; border-radius:4px;">
    Employee ID is missing. Open this report from employee profile or add <strong>?id=EMPLOYEE_ID</strong> in URL.
</div>
<?php elseif($reportError !== ''): ?>
<div class="page-nav" style="justify-content:flex-start; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; padding:10px 14px; border-radius:4px;">
    Could not load employee data. <?php echo reportEsc($reportError); ?>
</div>
<?php endif; ?>

<?php if($page === 1): ?>

<!-- PAGE 1 CONTENT -->
<div class="container">

<div class="header">
    <div class="logo">
        <img src="<?= function_exists('asset') ? asset('img/arowana_full_logo_white_bg.png') : 'public/img/arowana_full_logo_white_bg.png' ?>" alt="Arowana" class="logo-img" />
    </div>
</div>

<div class="title">JOINING REPORT</div>

<table class="main">
    <tr><td>Name</td><td><?php echo reportEsc($employeeData['name']); ?></td></tr>
    <tr><td>Date Of Joining</td><td><?php echo reportEsc($employeeData['joining_date']); ?></td></tr>
    <tr><td>Designation</td><td><?php echo reportEsc($employeeData['designation']); ?></td></tr>
    <tr><td>Experience</td><td><?php echo reportEsc($employeeData['experience']); ?></td></tr>
    <tr><td>Department</td><td><?php echo reportEsc($employeeData['department']); ?></td></tr>
    <tr><td>Father's/Husband Name</td><td><?php echo reportEsc($employeeData['father_or_husband_name']); ?></td></tr>
    <tr><td>Contact No</td><td><?php echo reportEsc($employeeData['contact_no']); ?></td></tr>
    <tr><td>Marital Status</td><td><?php echo reportEsc($employeeData['marital_status']); ?></td></tr>
    <tr><td>Qualification</td><td><?php echo reportEsc($employeeData['qualification']); ?></td></tr>
    <tr><td>Personal E-Mail ID</td><td><?php echo reportEsc($employeeData['personal_email']); ?></td></tr>
    <tr><td>Aadhaar Card No</td><td><?php echo reportEsc($employeeData['aadhar_card_number']); ?></td></tr>
    <tr><td>PAN Number</td><td><?php echo reportEsc($employeeData['pan_number']); ?></td></tr>
    <tr><td>Blood Group</td><td><?php echo reportEsc($employeeData['blood_group']); ?></td></tr>
    <tr><td>Permanent Address</td><td><?php echo reportEsc($employeeData['address']); ?></td></tr>
</table>

<div class="section">
Please give Name and Phone Number of the two Contact persons In Case of Emergency
</div>

<table class="contact-table">
    <tr>
        <th>S.No</th>
        <th>Name</th>
        <th>Relation</th>
        <th>Contact Number</th>
    </tr>
    <tr>
        <td>1.</td>
        <td><?php echo reportEsc($emergencyContacts[1]['name']); ?></td>
        <td><?php echo reportEsc($emergencyContacts[1]['relation']); ?></td>
        <td><?php echo reportEsc($emergencyContacts[1]['contact_number']); ?></td>
    </tr>
    <tr>
        <td>2.</td>
        <td><?php echo reportEsc($emergencyContacts[2]['name']); ?></td>
        <td><?php echo reportEsc($emergencyContacts[2]['relation']); ?></td>
        <td><?php echo reportEsc($emergencyContacts[2]['contact_number']); ?></td>
    </tr>
</table>

<div class="exp">
Total Work Experience:
</div>

<div class="previous">
Previous Employment Particulars:
</div>

<table class="previous-table">
    <tr><td>Name of the Company and Location</td><td><?php echo reportEsc(trim($employeeData['previous_company_name'] . (empty($employeeData['company_location']) ? '' : ', ' . $employeeData['company_location']))); ?></td></tr>
    <tr><td>Last Held Designation</td><td><?php echo reportEsc($employeeData['last_held_designation']); ?></td></tr>
    <tr><td>Date of Joining</td><td><?php echo reportEsc($employeeData['experience_joining_date']); ?></td></tr>
    <tr><td>Date of Leaving</td><td><?php echo reportEsc($employeeData['experience_leaving_date']); ?></td></tr>
    <tr><td>EMP ID</td><td><?php echo reportEsc($employeeData['employee_id']); ?></td></tr>
    <tr><td>PF Account Number</td><td><?php echo reportEsc($employeeData['pf_account_number']); ?></td></tr>
    <tr><td>ESI (IP Number)</td><td><?php echo reportEsc($employeeData['esi_number']); ?></td></tr>
    <tr><td>Personal A/c Number</td><td><?php echo reportEsc($employeeData['personal_account_number']); ?></td></tr>
    <tr><td>IFSC Code</td><td><?php echo reportEsc($employeeData['ifsc_code']); ?></td></tr>
</table>

<div class="footer">
    <h2>AROWANA CORPORATIONS (P) LTD</h2>
    <p>
        #30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA, BANK STREET,<br>
        MURALI NAGAR, VISAKHAPATNAM - 530007
    </p>
</div>

<div class="page-indicator">Page 1 of 2</div>

</div>

<?php else: ?>

<!-- PAGE 2 CONTENT -->
<div class="page">

<div class="header">
    <div class="logo">
        <img src="<?= function_exists('asset') ? asset('img/arowana_full_logo_white_bg.png') : 'public/img/arowana_full_logo_white_bg.png' ?>" alt="Arowana" class="logo-img" />
    </div>
</div>

<div class="section-title">
Passport Details:
</div>

<table class="passport-table">
    <tr>
        <td>Number</td>
        <td><?php echo reportEsc($passportData['passport_number']); ?></td>
    </tr>
    <tr>
        <td>Date of Issue</td>
        <td><?php echo reportEsc($passportData['passport_date_of_issue']); ?></td>
    </tr>
    <tr>
        <td>Place of Issue</td>
        <td><?php echo reportEsc($passportData['passport_place_of_issue']); ?></td>
    </tr>
    <tr>
        <td>Valid up to</td>
        <td><?php echo reportEsc($passportData['passport_valid_up_to']); ?></td>
    </tr>
</table>

<div class="checklist-title">
Checklist for Joining:
</div>

<div class="checklist">
    <div><?php echo reportCheck($checklistData['educational_testimonials']); ?> Educational Testimonials - X<sup>th</sup>, INTER, Degree Certificates.</div>
    <div><?php echo reportCheck($checklistData['experience_letter']); ?> Experience Letter</div>
    <div><?php echo reportCheck($checklistData['relieving_letter']); ?> Relieving Letter</div>
    <div><?php echo reportCheck($checklistData['two_references']); ?> Two References</div>
    <div><?php echo reportCheck($checklistData['salary_certificate']); ?> Salary Certificate / Last Drawn Salary Slip</div>
    <div><?php echo reportCheck($checklistData['bank_statement']); ?> Six Months Bank Statement</div>
    <div><?php echo reportCheck($checklistData['passport_copy']); ?> A copy of the Passport</div>
    <div><?php echo reportCheck($checklistData['form_16']); ?> Form 16 (Wherever applicable)</div>
    <div><?php echo reportCheck($checklistData['passport_photographs']); ?> 6 Passport Size Photographs</div>
    <div><?php echo reportCheck($checklistData['offer_letter']); ?> Signed Copy of the Offer Letter (Wherever Applicable)</div>
    <div><?php echo reportCheck($checklistData['resume_copy']); ?> Hardcopy of the Resume</div>
    <div><?php echo reportCheck($checklistData['nda']); ?> Non-Disclosure Agreement</div>
</div>

<div class="report">
I have reported to duty on
<span class="line"><?php echo reportEsc($finalDeclaration['reported_duty_day']); ?></span>
Day of the
<span class="line" style="width:120px"><?php echo reportEsc($finalDeclaration['reported_duty_month']); ?></span>
Month in the Year
<span class="line"><?php echo reportEsc($finalDeclaration['reported_duty_year']); ?></span>.
</div>

<div class="signature">
    <div><small><?php echo reportEsc($finalDeclaration['candidate_signature']); ?></small><br>Signature of the Candidate</div>
    <div><small><?php echo reportEsc($finalDeclaration['hr_signature']); ?></small><br>Signature of the HR Representative</div>
</div>

<div class="comment-box">
<b>HR Comments:</b><br>
<?php echo nl2br(reportEsc($finalDeclaration['hr_comments'])); ?>
</div>

<div class="version">
<span>AROWANA Corporations PVT.LTD</span>
<span>Version 25.0</span>
</div>

<div class="footer">
    <h2>AROWANA CORPORATIONS (P) LTD</h2>
    <p>
        #30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA, BANK STREET,<br>
        MURALI NAGAR, VISAKHAPATNAM - 530007
    </p>
</div>

<div class="page-indicator">Page 2 of 2</div>

</div>

<?php endif; ?>

</body>
</html>
