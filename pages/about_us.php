<?php
/**
 * About Us - Public Page Example
 * Demonstrates usage of school configuration helper
 */
require_once __DIR__ . '/components/global/school_config_helper.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php renderSchoolMetaTags('About Us'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .school-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .school-logo img {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 50%;
        }

        .school-info h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .motto {
            font-size: 1.2em;
            font-style: italic;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .section {
            margin-bottom: 40px;
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 2em;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #764ba2;
            margin-bottom: 15px;
        }

        .contact-info {
            list-style: none;
        }

        .contact-info li {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .contact-info li:last-child {
            border-bottom: none;
        }

        .contact-info strong {
            color: #667eea;
            display: inline-block;
            width: 120px;
        }

        .school-footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px 20px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 20px;
        }

        .footer-section h3 {
            margin-bottom: 15px;
            color: #ecf0f1;
        }

        .social-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .social-links a {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .social-links a:hover {
            background: #764ba2;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #34495e;
            color: #95a5a6;
        }

        .principal-message {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding: 30px;
            border-radius: 8px;
            border-left: 5px solid #667eea;
            margin: 30px 0;
        }

        .principal-message .principal-name {
            font-weight: bold;
            color: #764ba2;
            margin-top: 15px;
        }

        .core-values {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .value-tag {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php renderSchoolHeader(); ?>

    <div class="container">
        <!-- About Us Section -->
        <section class="section">
            <h2>About <?php echo getSchoolName(); ?></h2>
            <p><?php echo getSchoolConfig('about_us', 'Welcome to our school!'); ?></p>
        </section>

        <!-- Vision & Mission -->
        <section class="section">
            <div class="content-grid">
                <div class="card">
                    <h3>Our Vision</h3>
                    <p><?php echo getSchoolVision(); ?></p>
                </div>
                <div class="card">
                    <h3>Our Mission</h3>
                    <p><?php echo getSchoolMission(); ?></p>
                </div>
            </div>
        </section>

        <!-- Core Values -->
        <?php 
        $coreValues = getSchoolConfig('core_values');
        if ($coreValues): 
            $values = array_map('trim', explode(',', $coreValues));
        ?>
        <section class="section">
            <h2>Our Core Values</h2>
            <div class="core-values">
                <?php foreach ($values as $value): ?>
                    <span class="value-tag"><?php echo htmlspecialchars($value); ?></span>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Principal's Message -->
        <?php 
        $principalName = getSchoolConfig('principal_name');
        $principalMessage = getSchoolConfig('principal_message');
        if ($principalMessage): 
        ?>
        <section class="section">
            <h2>Message from the Principal</h2>
            <div class="principal-message">
                <p><?php echo nl2br(htmlspecialchars($principalMessage)); ?></p>
                <?php if ($principalName): ?>
                    <p class="principal-name">- <?php echo htmlspecialchars($principalName); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Contact Information -->
        <section class="section">
            <h2>Contact Information</h2>
            <div class="content-grid">
                <div class="card">
                    <h3>Get In Touch</h3>
                    <ul class="contact-info">
                        <li><strong>Email:</strong> <?php echo getSchoolEmail(); ?></li>
                        <li><strong>Phone:</strong> <?php echo getSchoolPhone(); ?></li>
                        <?php if ($altPhone = getSchoolConfig('alternative_phone')): ?>
                            <li><strong>Alt Phone:</strong> <?php echo $altPhone; ?></li>
                        <?php endif; ?>
                        <?php if ($website = getSchoolConfig('website')): ?>
                            <li><strong>Website:</strong> <a href="<?php echo $website; ?>" target="_blank"><?php echo $website; ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card">
                    <h3>Visit Us</h3>
                    <ul class="contact-info">
                        <li><strong>Address:</strong> <?php echo getSchoolConfig('address'); ?></li>
                        <li><strong>City:</strong> <?php echo getSchoolConfig('city'); ?></li>
                        <?php if ($state = getSchoolConfig('state')): ?>
                            <li><strong>State:</strong> <?php echo $state; ?></li>
                        <?php endif; ?>
                        <li><strong>Country:</strong> <?php echo getSchoolConfig('country'); ?></li>
                        <?php if ($postal = getSchoolConfig('postal_code')): ?>
                            <li><strong>Postal Code:</strong> <?php echo $postal; ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Quick Links -->
        <section class="section">
            <h2>Resources</h2>
            <div class="content-grid">
                <?php if ($calendar = getSchoolConfig('academic_calendar_url')): ?>
                <div class="card">
                    <h3>Academic Calendar</h3>
                    <p>View our academic calendar to stay updated on important dates and events.</p>
                    <a href="<?php echo $calendar; ?>" target="_blank" class="btn">View Calendar</a>
                </div>
                <?php endif; ?>
                
                <?php if ($prospectus = getSchoolConfig('prospectus_url')): ?>
                <div class="card">
                    <h3>Prospectus</h3>
                    <p>Download our school prospectus to learn more about our programs.</p>
                    <a href="<?php echo $prospectus; ?>" target="_blank" class="btn">Download Prospectus</a>
                </div>
                <?php endif; ?>
                
                <?php if ($handbook = getSchoolConfig('student_handbook_url')): ?>
                <div class="card">
                    <h3>Student Handbook</h3>
                    <p>Access the student handbook for policies and guidelines.</p>
                    <a href="<?php echo $handbook; ?>" target="_blank" class="btn">View Handbook</a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php renderSchoolFooter(); ?>
</body>
</html>
