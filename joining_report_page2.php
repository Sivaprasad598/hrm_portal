<?php
// Page 2 - Joining Report - Employment History & Bank Details

$employee_name = $_GET['name'] ?? '';
$total_experience = $_GET['total_experience'] ?? '';

$employment_history = [
    'company_name' => $_GET['company_name'] ?? '',
    'designation' => $_GET['last_designation'] ?? '',
    'joining_date' => $_GET['emp_joining_date'] ?? '',
    'leaving_date' => $_GET['emp_leaving_date'] ?? '',
    'emp_id' => $_GET['emp_id'] ?? '',
    'pf_account' => $_GET['pf_account'] ?? '',
    'esi_number' => $_GET['esi_number'] ?? '',
    'bank_account' => $_GET['bank_account'] ?? '',
    'ifsc_code' => $_GET['ifsc_code'] ?? '',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arowana Joining Report - Page 2</title>

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

.employee-name{
    text-align:center;
    font-size:14px;
    margin-bottom:15px;
    color:#333;
}

table{
    width:100%;
    border-collapse:collapse;
}

.exp{
    margin-top:20px;
    margin-bottom:15px;
    font-weight:bold;
    font-size:14px;
}

.previous{
    text-align:center;
    font-weight:bold;
    font-size:18px;
    margin:25px 0 15px 0;
    color:#234b88;
    text-decoration:underline;
}

.previous-table td{
    border:1px solid #555;
    height:36px;
    padding:8px;
    font-size:15px;
}

.previous-table td:first-child{
    width:45%;
    font-weight:500;
    background:#f9f9f9;
}

.section-title{
    margin-top:30px;
    margin-bottom:12px;
    font-weight:bold;
    color:#1d4e89;
    font-size:14px;
}

.signature-box{
    display:flex;
    justify-content:space-between;
    margin-top:40px;
    gap:20px;
}

.sig-line{
    flex:1;
    text-align:center;
    border-top:2px solid #000;
    padding-top:5px;
    font-size:12px;
}

.page-number{
    text-align:right;
    margin-top:20px;
    font-size:12px;
    color:#999;
}

.footer{
    margin-top:30px;
    text-align:center;
    border-top:2px solid #ddd;
    padding-top:15px;
}

.footer h2{
    color:#a96c38;
    font-size:24px;
    margin-bottom:6px;
}

.footer p{
    font-size:12px;
    font-weight:bold;
    line-height:18px;
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

<div class="title">JOINING REPORT - PAGE 2</div>
<div class="subtitle">Employment History & Bank Details</div>

<?php if($employee_name): ?>
<div class="employee-name">
    <strong>Employee Name:</strong> <?php echo htmlspecialchars($employee_name); ?>
</div>
<?php endif; ?>

<div class="exp">
    Total Work Experience: <?php echo htmlspecialchars($total_experience); ?>
</div>

<div class="previous">
    Previous Employment Particulars
</div>

<table class="previous-table">
    <tr>
        <td>Name of the Company and Location</td>
        <td><?php echo htmlspecialchars($employment_history['company_name']); ?></td>
    </tr>
    <tr>
        <td>Last Held Designation</td>
        <td><?php echo htmlspecialchars($employment_history['designation']); ?></td>
    </tr>
    <tr>
        <td>Date of Joining</td>
        <td><?php echo htmlspecialchars($employment_history['joining_date']); ?></td>
    </tr>
    <tr>
        <td>Date of Leaving</td>
        <td><?php echo htmlspecialchars($employment_history['leaving_date']); ?></td>
    </tr>
    <tr>
        <td>EMP ID</td>
        <td><?php echo htmlspecialchars($employment_history['emp_id']); ?></td>
    </tr>
    <tr>
        <td>PF Account Number</td>
        <td><?php echo htmlspecialchars($employment_history['pf_account']); ?></td>
    </tr>
    <tr>
        <td>ESI (IP Number)</td>
        <td><?php echo htmlspecialchars($employment_history['esi_number']); ?></td>
    </tr>
    <tr>
        <td>Personal Account Number</td>
        <td><?php echo htmlspecialchars($employment_history['bank_account']); ?></td>
    </tr>
    <tr>
        <td>IFSC Code</td>
        <td><?php echo htmlspecialchars($employment_history['ifsc_code']); ?></td>
    </tr>
</table>

<div class="signature-box">
    <div class="sig-line">
        Candidate Signature<br/>
        (with Date)
    </div>
    <div class="sig-line">
        HR Signature<br/>
        (with Date)
    </div>
</div>

<div class="footer">
    <h2>AROWANA CORPORATIONS (P) LTD</h2>
    <p>
        #30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA, BANK STREET,<br>
        MURALI NAGAR, VISAKHAPATNAM - 530007
    </p>
</div>

<div class="page-number">Page 2 of 2</div>

</div>

</body>
</html>
