<?php
/**
 * About Us - Public Page (Professional Version)
 * Uses dynamic school configuration helper
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
        /*------------------------------------------------------------
            GLOBAL STYLES
        ------------------------------------------------------------*/
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        h1, h2, h3 {
            font-weight: 600;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        /*------------------------------------------------------------
            SECTION WRAPPER
        ------------------------------------------------------------*/
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .section {
            margin-bottom: 60px;
        }

        .section h2 {
            font-size: 2.2em;
            color: #4b5bdc;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4b5bdc;
        }

        /*------------------------------------------------------------
            GRID + CARDS
        ------------------------------------------------------------*/
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
            gap: 30px;
            margin-top: 25px;
        }

        .card {
            background: #ffffff;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 28px rgba(0,0,0,0.12);
        }

        .card h3 {
            color: #764ba2;
            margin-bottom: 15px;
            font-size: 1.4em;
        }

        /*------------------------------------------------------------
            CORE VALUES TAGS
        ------------------------------------------------------------*/
        .core-values {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 20px;
        }

        .value-tag {
            background: #667eea;
            padding: 10px 22px;
            border-radius: 18px;
            color: white;
            font-weight: 500;
            font-size: 0.95em;
            transition: background 0.3s ease;
        }

        .value-tag:hover {
            background: #764ba2;
        }

        /*------------------------------------------------------------
            PRINCIPAL MESSAGE
        ------------------------------------------------------------*/
        .principal-message {
            background: #ffffff;
            padding: 30px;
            border-left: 6px solid #667eea;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .principal-name {
            margin-top: 18px;
            font-weight: bold;
            color: #764ba2;
            font-size: 1.1em;
        }

        /*------------------------------------------------------------
            CONTACT INFO
        ------------------------------------------------------------*/
        .contact-info {
            list-style: none;
            font-size: 1em;
        }

        .contact-info li {
            padding: 12px 0;
            border-bottom: 1px solid #e7e7e7;
        }

        .contact-info li:last-child {
            border-bottom: none;
        }

        .contact-info strong {
            color: #4b5bdc;
            display: inline-block;
            width: 120px;
        }

        /*------------------------------------------------------------
            BUTTONS
        ------------------------------------------------------------*/
        .btn {
            display: inline-block;
            padding: 10px 18px;
            background: #667eea;
            color: white !important;
            border-radius: 6px;
            margin-top: 12px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #764ba2;
        }
    </style>
</head>

<body>

    <?php renderSchoolHeader(); ?>

    <div class="container">

        <!-- ABOUT US -->
        <section class="section">
            <h2>About <?php echo getSchoolName(); ?></h2>
            <p style="font-size:1.1em;">
                <?php echo getSchoolConfig('about_us', 'Welcome to our school!'); ?>
            </p>
        </section>

        <!-- VISION & MISSION -->
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

        <!-- CORE VALUES -->
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

        <!-- PRINCIPAL MESSAGE -->
        <?php 
            $principalName = getSchoolConfig('principal_name');
            $principalMessage = getSchoolConfig('principal_message');
            if ($principalMessage):
        ?>
        <section class="section">
            <h2>Message from the Principal</h2>
            <div class="principal-message">
                <p style="line-height:1.7;">
                    <?php echo nl2br(htmlspecialchars($principalMessage)); ?>
                </p>

                <?php if ($principalName): ?>
                    <div class="principal-name">— <?php echo htmlspecialchars($principalName); ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- CONTACT INFO -->
        <section class="section">
            <h2>Contact Information</h2>

            <div class="content-grid">
                <!-- CONTACTS -->
                <div class="card">
                    <h3>Get in Touch</h3>
                    <ul class="contact-info">
                        <li><strong>Email:</strong> <?php echo getSchoolEmail(); ?></li>
                        <li><strong>Phone:</strong> <?php echo getSchoolPhone(); ?></li>
                        
                        <?php if ($alt = getSchoolConfig('alternative_phone')): ?>
                            <li><strong>Alt Phone:</strong> <?php echo $alt; ?></li>
                        <?php endif; ?>

                        <?php if ($web = getSchoolConfig('website')): ?>
                            <li><strong>Website:</strong> <a href="<?php echo $web; ?>" target="_blank"><?php echo $web; ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- ADDRESS -->
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

        <!-- RESOURCES -->
        <section class="section">
            <h2>Resources</h2>
            <div class="content-grid">

                <?php if ($calendar = getSchoolConfig('academic_calendar_url')): ?>
                <div class="card">
                    <h3>Academic Calendar</h3>
                    <p>Stay updated with our school term dates and events.</p>
                    <a href="<?php echo $calendar; ?>" target="_blank" class="btn">View Calendar</a>
                </div>
                <?php endif; ?>

                <?php if ($prospectus = getSchoolConfig('prospectus_url')): ?>
                <div class="card">
                    <h3>Prospectus</h3>
                    <p>Download detailed information about our programs and structure.</p>
                    <a href="<?php echo $prospectus; ?>" target="_blank" class="btn">Download Prospectus</a>
                </div>
                <?php endif; ?>

                <?php if ($handbook = getSchoolConfig('student_handbook_url')): ?>
                <div class="card">
                    <h3>Student Handbook</h3>
                    <p>Access student rules, guidelines, and expectations.</p>
                    <a href="<?php echo $handbook; ?>" target="_blank" class="btn">View Handbook</a>
                </div>
                <?php endif; ?>

            </div>
        </section>
    </div>

    <?php renderSchoolFooter(); ?>
</body>
</html>
