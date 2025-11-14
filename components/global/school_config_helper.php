<?php
/**
 * School Configuration Helper
 * 
 * This file provides helper functions to retrieve and display school configuration
 * across public and internal pages.
 */

class SchoolConfig {
    private static $instance = null;
    private $config = null;
    private $db;

    private function __construct() {
        require_once __DIR__ . '/../config/db_connection.php';
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfiguration();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration from database
     */
    private function loadConfiguration() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM school_configuration 
                WHERE is_active = 1 
                ORDER BY id DESC 
                LIMIT 1
            ");
            
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no configuration exists, use defaults
            if (!$this->config) {
                $this->config = $this->getDefaultConfig();
            }
        } catch (Exception $e) {
            error_log("Failed to load school configuration: " . $e->getMessage());
            $this->config = $this->getDefaultConfig();
        }
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig() {
        return [
            'school_name' => 'Kingsway Academy',
            'school_code' => 'KWA',
            'motto' => 'Excellence in Education',
            'vision' => 'To be a leading institution in providing quality education and nurturing future leaders.',
            'mission' => 'To provide holistic education that empowers students to achieve academic excellence.',
            'email' => 'info@kingswayacademy.ac.ke',
            'phone' => '+254 700 000 000',
            'address' => 'Kingsway Road',
            'city' => 'Nairobi',
            'country' => 'Kenya',
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'language' => 'en'
        ];
    }

    /**
     * Get specific configuration value
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all configuration
     */
    public function getAll() {
        return $this->config;
    }

    /**
     * Get public configuration (safe for public pages)
     */
    public function getPublic() {
        $publicFields = [
            'school_name', 'school_code', 'logo_url', 'favicon_url',
            'motto', 'vision', 'mission', 'core_values', 'about_us',
            'email', 'phone', 'address', 'city', 'state', 'country',
            'website', 'facebook_url', 'twitter_url', 'instagram_url',
            'linkedin_url', 'youtube_url', 'established_year',
            'principal_name', 'principal_message'
        ];

        $publicConfig = [];
        foreach ($publicFields as $field) {
            if (isset($this->config[$field])) {
                $publicConfig[$field] = $this->config[$field];
            }
        }

        return $publicConfig;
    }

    /**
     * Refresh configuration from database
     */
    public function refresh() {
        $this->loadConfiguration();
    }
}

/**
 * Helper functions for easy access
 */

function getSchoolConfig($key = null, $default = null) {
    $config = SchoolConfig::getInstance();
    
    if ($key === null) {
        return $config->getAll();
    }
    
    return $config->get($key, $default);
}

function getSchoolName() {
    return getSchoolConfig('school_name', 'Kingsway Academy');
}

function getSchoolLogo() {
    return getSchoolConfig('logo_url', '/images/favicon/logo.png');
}

function getSchoolFavicon() {
    return getSchoolConfig('favicon_url', '/images/favicon/favicon.ico');
}

function getSchoolMotto() {
    return getSchoolConfig('motto', 'Excellence in Education');
}

function getSchoolVision() {
    return getSchoolConfig('vision', '');
}

function getSchoolMission() {
    return getSchoolConfig('mission', '');
}

function getSchoolEmail() {
    return getSchoolConfig('email', 'info@kingswayacademy.ac.ke');
}

function getSchoolPhone() {
    return getSchoolConfig('phone', '+254 700 000 000');
}

function getSchoolAddress() {
    $address = getSchoolConfig('address', '');
    $city = getSchoolConfig('city', '');
    $country = getSchoolConfig('country', '');
    
    $parts = array_filter([$address, $city, $country]);
    return implode(', ', $parts);
}

function getSchoolSocialMedia() {
    return [
        'facebook' => getSchoolConfig('facebook_url'),
        'twitter' => getSchoolConfig('twitter_url'),
        'instagram' => getSchoolConfig('instagram_url'),
        'linkedin' => getSchoolConfig('linkedin_url'),
        'youtube' => getSchoolConfig('youtube_url')
    ];
}

/**
 * Render school header for public pages
 */
function renderSchoolHeader() {
    $name = getSchoolName();
    $logo = getSchoolLogo();
    $motto = getSchoolMotto();
    
    echo <<<HTML
    <header class="school-header">
        <div class="school-logo">
            <img src="{$logo}" alt="{$name} Logo">
        </div>
        <div class="school-info">
            <h1>{$name}</h1>
            <p class="motto">{$motto}</p>
        </div>
    </header>
HTML;
}

/**
 * Render school footer for public pages
 */
function renderSchoolFooter() {
    $name = getSchoolName();
    $email = getSchoolEmail();
    $phone = getSchoolPhone();
    $address = getSchoolAddress();
    $social = getSchoolSocialMedia();
    $year = date('Y');
    
    echo <<<HTML
    <footer class="school-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Phone:</strong> {$phone}</p>
                <p><strong>Address:</strong> {$address}</p>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-links">
HTML;
    
    if ($social['facebook']) {
        echo "<a href='{$social['facebook']}' target='_blank'>Facebook</a>";
    }
    if ($social['twitter']) {
        echo "<a href='{$social['twitter']}' target='_blank'>Twitter</a>";
    }
    if ($social['instagram']) {
        echo "<a href='{$social['instagram']}' target='_blank'>Instagram</a>";
    }
    if ($social['linkedin']) {
        echo "<a href='{$social['linkedin']}' target='_blank'>LinkedIn</a>";
    }
    if ($social['youtube']) {
        echo "<a href='{$social['youtube']}' target='_blank'>YouTube</a>";
    }
    
    echo <<<HTML
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; {$year} {$name}. All rights reserved.</p>
        </div>
    </footer>
HTML;
}

/**
 * Get meta tags for SEO
 */
function getSchoolMetaTags() {
    $name = getSchoolName();
    $description = getSchoolConfig('about_us', getSchoolConfig('mission', ''));
    $logo = getSchoolLogo();
    
    return [
        'title' => $name,
        'description' => substr($description, 0, 160),
        'image' => $logo,
        'og:title' => $name,
        'og:description' => substr($description, 0, 160),
        'og:image' => $logo,
        'twitter:card' => 'summary_large_image',
        'twitter:title' => $name,
        'twitter:description' => substr($description, 0, 160),
        'twitter:image' => $logo
    ];
}

/**
 * Render meta tags
 */
function renderSchoolMetaTags($pageTitle = null) {
    $meta = getSchoolMetaTags();
    $title = $pageTitle ? $pageTitle . ' - ' . $meta['title'] : $meta['title'];
    $favicon = getSchoolFavicon();
    
    echo <<<HTML
    <title>{$title}</title>
    <meta name="description" content="{$meta['description']}">
    <link rel="icon" type="image/x-icon" href="{$favicon}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{$meta['og:title']}">
    <meta property="og:description" content="{$meta['og:description']}">
    <meta property="og:image" content="{$meta['og:image']}">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="{$meta['twitter:card']}">
    <meta property="twitter:title" content="{$meta['twitter:title']}">
    <meta property="twitter:description" content="{$meta['twitter:description']}">
    <meta property="twitter:image" content="{$meta['twitter:image']}">
HTML;
}
