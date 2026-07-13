<?php
/**
 * Graduation Certificate Template
 * Template for certifying successful completion of studies
 * 
 * Variables passed from PrintManager or PrintService:
 * - $schoolName: School name from config
 * - $schoolMotto: School motto from config
 * - $schoolLogo: School logo URL from config
 * - $schoolAddress: School address from config
 * - $schoolPhone: School phone from config
 * - $schoolEmail: School email from config
 * - $schoolWebsite: School website from config
 * - $principalName: Principal name from config
 * - $principalTitle: Principal title from config
 * - $recipientName: Student name
 * - $course: Course/program name
 * - $academicYear: Academic year
 * - $certificateNumber: Certificate number
 * - $dateAwarded: Date awarded
 * - $examOfficerName: Examinations officer name
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduation Certificate</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', serif;
            background: #f5f5f5;
        }
        
        .certificate {
            width: 297mm;
            height: 210mm;
            margin: 0 auto;
            background: white;
            position: relative;
            border: 20px solid #1a1a1a;
            box-sizing: border-box;
            padding: 30px;
        }
        
        .certificate::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 3px solid #4169e1;
            pointer-events: none;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .school-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
            border-radius: 50%;
            border: 3px solid #4169e1;
        }
        
        .school-name {
            font-size: 32pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .school-motto {
            font-size: 14pt;
            font-style: italic;
            color: #4169e1;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .school-contact {
            font-size: 10pt;
            color: #666;
            margin-bottom: 20px;
        }
        
        .certificate-title {
            font-size: 36pt;
            font-weight: bold;
            color: #4169e1;
            text-align: center;
            margin: 30px 0;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-family: 'Georgia', serif;
        }
        
        .certificate-body {
            text-align: center;
            margin: 40px 0;
        }
        
        .graduation-icon {
            font-size: 64pt;
            color: #4169e1;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .presented-to {
            font-size: 16pt;
            color: #333;
            margin-bottom: 10px;
            font-style: italic;
        }
        
        .recipient-name {
            font-size: 36pt;
            font-weight: bold;
            color: #1a1a1a;
            margin: 20px 0;
            text-decoration: underline;
            text-decoration-color: #4169e1;
            text-decoration-thickness: 3px;
            text-transform: uppercase;
            font-family: 'Georgia', serif;
        }
        
        .achievement-text {
            font-size: 16pt;
            color: #333;
            margin: 20px 0;
            line-height: 1.8;
        }
        
        .achievement-text strong {
            color: #4169e1;
            font-weight: 600;
        }
        
        .certificate-footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .signature-section {
            text-align: center;
            width: 220px;
        }
        
        .signature-line {
            border-top: 2px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }
        
        .signature-label {
            font-size: 12pt;
            color: #333;
            font-weight: bold;
        }
        
        .signature-title {
            font-size: 10pt;
            color: #666;
            font-style: italic;
        }
        
        .certificate-details {
            text-align: center;
            margin-top: 40px;
            font-size: 10pt;
            color: #666;
        }
        
        .certificate-number {
            font-weight: bold;
            color: #1a1a1a;
            font-size: 12pt;
        }
        
        .seal {
            position: absolute;
            bottom: 30px;
            right: 30px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #4169e1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .seal-inner {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px dashed #4169e1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .seal-text {
            font-size: 9pt;
            font-weight: bold;
            color: #4169e1;
            text-align: center;
            line-height: 1.4;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            color: rgba(65, 105, 225, 0.05);
            font-weight: bold;
            pointer-events: none;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="watermark">GRADUATE</div>
        
        <div class="certificate-header">
            <img src="<?= $schoolLogo ?>" alt="School Logo" class="school-logo">
            <div class="school-name"><?= $schoolName ?></div>
            <div class="school-motto">"<?= $schoolMotto ?>"</div>
            <div class="school-contact">
                <?= $schoolAddress ?> | <?= $schoolPhone ?> | <?= $schoolEmail ?> | <?= $schoolWebsite ?>
            </div>
        </div>
        
        <div class="certificate-title">Certificate of Graduation</div>
        
        <div class="certificate-body">
            <div class="graduation-icon">🎓</div>
            <div class="presented-to">This certificate is proudly presented to</div>
            <div class="recipient-name"><?= $recipientName ?></div>
            <div class="achievement-text">
                In recognition of the <strong>successful completion</strong> of 
                <strong><?= $course ?></strong> studies at 
                <strong><?= $schoolName ?></strong>.
            </div>
            <div class="achievement-text">
                Having completed all requirements and demonstrated 
                <strong>academic excellence</strong> and <strong>personal growth</strong> 
                during the <strong><?= $academicYear ?> Academic Year</strong>.
            </div>
        </div>
        
        <div class="seal">
            <div class="seal-inner">
                <div class="seal-text">OFFICIAL<br>SEAL</div>
            </div>
        </div>
        
        <div class="certificate-footer">
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-label"><?= $principalName ?></div>
                <div class="signature-title"><?= $principalTitle ?></div>
            </div>
            
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-label"><?= $examOfficerName ?></div>
                <div class="signature-title">Examinations Officer</div>
            </div>
        </div>
        
        <div class="certificate-details">
            <div>Certificate Number: <span class="certificate-number"><?= $certificateNumber ?></span></div>
            <div>Date Awarded: <?= $dateAwarded ?></div>
        </div>
    </div>
</body>
</html>