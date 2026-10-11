<?php
// Page 1 - Joining Report - Personal & Contact Information

// Sample employee data (can be fetched from database)
$employee = [
    'name' => $_GET['name'] ?? '',
    'date_of_joining' => $_GET['doj'] ?? '',
    'designation' => $_GET['designation'] ?? '',
    'experience' => $_GET['experience'] ?? '',
    'department' => $_GET['department'] ?? '',
    'father_name' => $_GET['father_name'] ?? '',
    'contact_no' => $_GET['contact_no'] ?? '',
    'marital_status' => $_GET['marital_status'] ?? '',
    'qualification' => $_GET['qualification'] ?? '',
    'personal_email' => $_GET['personal_email'] ?? '',
    'aadhaar_no' => $_GET['aadhaar_no'] ?? '',
    'pan_no' => $_GET['pan_no'] ?? '',
    'blood_group' => $_GET['blood_group'] ?? '',
    'permanent_address' => $_GET['permanent_address'] ?? '',
];

$emergency_contacts = [
    ['name' => $_GET['emergency_name_1'] ?? '', 'relation' => $_GET['emergency_relation_1'] ?? '', 'contact' => $_GET['emergency_contact_1'] ?? ''],
    ['name' => $_GET['emergency_name_2'] ?? '', 'relation' => $_GET['emergency_relation_2'] ?? '', 'contact' => $_GET['emergency_contact_2'] ?? ''],
];

$total_experience = $_GET['total_experience'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arowana Joining Report - Page 1</title>

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

.container{
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

.logo-icon{
    width:45px;
    height:45px;
    background:#b36a32;
    clip-path:polygon(50% 0%,100% 100%,0% 100%);
    position:relative;
}

.logo-icon:after{
    content:"";
    position:absolute;
    left:12px;
    top:15px;
    width:21px;
    height:18px;
    border:4px solid #fff;
    border-top:none;
}

.logo-text h1{
    font-size:32px;
    font-weight:bold;
    color:#222;
    letter-spacing:1px;
}

.logo-text p{
    font-size:11px;
    color:#555;
    letter-spacing:1px;
}

.title{
    text-align:center;
    font-weight:bold;
    font-size:22px;
    margin:20px 0;
    letter-spacing:1px;
}

.subtitle{
    text-align:center;
    font-size:14px;
    color:#666;
    margin-bottom:15px;
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
    background:#f9f9f9;
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
    font-weight:bold;
}

.page-number{
    text-align:right;
    margin-top:20px;
    font-size:12px;
    color:#999;
}

.footer{
    margin-top:35px;
    text-align:center;
    border-top:2px solid #ddd;
    padding-top:15px;
}

.footer h2{
    color:#a96c38;
    font-size:28px;
    margin-bottom:6px;
}

.footer p{
    font-size:13px;
    font-weight:bold;
    line-height:20px;
}

@media print{
    body{
        background:#fff;
        padding:0;
    }
    .container{
        box-shadow:none;
        width:100%;
        padding:20px;
    }
}

</style>

</head>
<body>

<div class="container">

<div class="header">
    <div class="logo">
        <div class="logo-icon"></div>
        <div class="logo-text">
            <h1>AROWANA</h1>
            <p>PRECISION • INNOVATION • SUCCESS</p>
        </div>
    </div>
</div>

<div class="title">JOINING REPORT - PAGE 1</div>
<div class="subtitle">Personal & Contact Information</div>

<table class="main">
    <tr><td>Name</td><td><?php echo htmlspecialchars($employee['name']); ?></td></tr>
    <tr><td>Date Of Joining</td><td><?php echo htmlspecialchars($employee['date_of_joining']); ?></td></tr>
    <tr><td>Designation</td><td><?php echo htmlspecialchars($employee['designation']); ?></td></tr>
    <tr><td>Experience</td><td><?php echo htmlspecialchars($employee['experience']); ?></td></tr>
    <tr><td>Department</td><td><?php echo htmlspecialchars($employee['department']); ?></td></tr>
    <tr><td>Father's/Husband Name</td><td><?php echo htmlspecialchars($employee['father_name']); ?></td></tr>
    <tr><td>Contact No</td><td><?php echo htmlspecialchars($employee['contact_no']); ?></td></tr>
    <tr><td>Marital Status</td><td><?php echo htmlspecialchars($employee['marital_status']); ?></td></tr>
    <tr><td>Qualification</td><td><?php echo htmlspecialchars($employee['qualification']); ?></td></tr>
    <tr><td>Personal E-Mail ID</td><td><?php echo htmlspecialchars($employee['personal_email']); ?></td></tr>
    <tr><td>Aadhaar Card No</td><td><?php echo htmlspecialchars($employee['aadhaar_no']); ?></td></tr>
    <tr><td>PAN Number</td><td><?php echo htmlspecialchars($employee['pan_no']); ?></td></tr>
    <tr><td>Blood Group</td><td><?php echo htmlspecialchars($employee['blood_group']); ?></td></tr>
    <tr><td>Permanent Address</td><td><?php echo htmlspecialchars($employee['permanent_address']); ?></td></tr>
</table>

<div class="section">
    Emergency Contact Information
</div>

<table class="contact-table">
    <tr>
        <th>S.No</th>
        <th>Name</th>
        <th>Relation</th>
        <th>Contact Number</th>
    </tr>
    <?php foreach($emergency_contacts as $index => $contact): ?>
    <tr>
        <td><?php echo $index + 1; ?>.</td>
        <td><?php echo htmlspecialchars($contact['name']); ?></td>
        <td><?php echo htmlspecialchars($contact['relation']); ?></td>
        <td><?php echo htmlspecialchars($contact['contact']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="footer">
    <h2>AROWANA CORPORATIONS (P) LTD</h2>
    <p>
        #30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA, BANK STREET,<br>
        MURALI NAGAR, VISAKHAPATNAM - 530007
    </p>
</div>

<div class="page-number">Page 1 of 2</div>

</div>

</body>
</html>
