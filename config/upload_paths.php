<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Kingsway Preparatory School
 * Canonical upload paths shared by all environments.
 *
 * Requirements:
 * - BASE_URL must be defined first.
 * - UPLOAD_PATH must be defined first.
 */

if (!defined('BASE_URL')) {
    throw new RuntimeException(
        'BASE_URL must be defined before loading upload_paths.php.'
    );
}

if (!defined('UPLOAD_PATH')) {
    throw new RuntimeException(
        'UPLOAD_PATH must be defined before loading upload_paths.php.'
    );
}

$normalizedUploadPath = rtrim(
    str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) UPLOAD_PATH),
    DIRECTORY_SEPARATOR
);

if ($normalizedUploadPath === '') {
    throw new RuntimeException('UPLOAD_PATH cannot be empty.');
}

/*
|--------------------------------------------------------------------------
| Public upload URL
|--------------------------------------------------------------------------
|
| UPLOAD_PATH is a filesystem path used by PHP.
| UPLOAD_URL is a browser-facing URL.
|
*/

// UPLOAD_URL may be set in config/.env when the uploads folder is served from
// somewhere else (another path, subdomain or CDN). Stored references to
// uploaded files are relative to this root, so changing UPLOAD_PATH and
// UPLOAD_URL together is all a move needs.
define(
    'UPLOAD_URL',
    rtrim(
        trim((string) ($_ENV['UPLOAD_URL'] ?? '')) !== ''
            ? (string) $_ENV['UPLOAD_URL']
            : rtrim((string) BASE_URL, '/') . '/uploads',
        '/'
    )
);

/*
|--------------------------------------------------------------------------
| Student uploads
|--------------------------------------------------------------------------
*/

define('STUDENT_UPLOADS', $normalizedUploadPath . '/students');

define(
    'STUDENT_AVATAR_DEFAULT',
    STUDENT_UPLOADS . '/avatar.jpg'
);

define(
    'STUDENT_IMAGES',
    STUDENT_UPLOADS . '/images'
);

define(
    'STUDENT_QR_CODES',
    STUDENT_IMAGES . '/qr_codes'
);

define(
    'STUDENT_DOCUMENTS',
    STUDENT_UPLOADS . '/documents'
);

/*
|--------------------------------------------------------------------------
| Admission uploads
|--------------------------------------------------------------------------
*/

define(
    'ADMISSION_UPLOADS',
    STUDENT_UPLOADS . '/admissions'
);

define(
    'ADMISSION_DOCUMENTS',
    ADMISSION_UPLOADS . '/documents'
);

/*
|--------------------------------------------------------------------------
| Staff uploads
|--------------------------------------------------------------------------
*/

define(
    'STAFF_UPLOADS',
    $normalizedUploadPath . '/staff'
);

define(
    'STAFF_PHOTOS',
    STAFF_UPLOADS . '/profile_pictures'
);

define(
    'STAFF_IMAGES',
    STAFF_UPLOADS . '/images'
);

define(
    'STAFF_QR_CODES',
    STAFF_IMAGES . '/qr_codes'
);

define(
    'STAFF_AVATAR_DEFAULT',
    STAFF_PHOTOS . '/avatar.jpg'
);

define(
    'STAFF_DOCUMENTS',
    STAFF_UPLOADS . '/documents'
);

/*
|--------------------------------------------------------------------------
| Academic uploads
|--------------------------------------------------------------------------
*/

define(
    'ACADEMIC_UPLOADS',
    $normalizedUploadPath . '/academic'
);

define(
    'ACADEMIC_ASSESSMENTS',
    ACADEMIC_UPLOADS . '/assessments'
);

/*
|--------------------------------------------------------------------------
| School assets
|--------------------------------------------------------------------------
*/

define(
    'SCHOOL_ASSETS',
    $normalizedUploadPath . '/school_assets'
);

define(
    'SCHOOL_ASSETS_DOCUMENTS',
    SCHOOL_ASSETS . '/documents'
);

define(
    'SCHOOL_ASSETS_GALLERY',
    SCHOOL_ASSETS . '/gallery'
);

define(
    'SCHOOL_ASSETS_QR_CODES',
    SCHOOL_ASSETS . '/qr_codes'
);

/**
 * Filesystem path for PHP, Dompdf, image processing, and storage checks.
 */
define(
    'SCHOOL_LOGO_PATH',
    SCHOOL_ASSETS . '/official_school_logo.png'
);

/**
 * Browser URL for HTML pages.
 */
define(
    'SCHOOL_LOGO_URL',
    UPLOAD_URL . '/school_assets/official_school_logo.png'
);

/*
|--------------------------------------------------------------------------
| Trusted print templates
|--------------------------------------------------------------------------
|
| Templates are code: they ship with the application in resources/templates
| (tracked in Git, deployed with every release) and are never stored under
| the uploads tree, which holds content only and must not run PHP. Images,
| logos and photos stay in uploads and are reached through UPLOAD_PATH.
*/

define(
    'TEMPLATES_PATH',
    dirname(__DIR__) . '/resources/templates'
);

define(
    'PRINT_TEMPLATES',
    TEMPLATES_PATH . '/print'
);

define(
    'PRINT_SERVER_TEMPLATES',
    PRINT_TEMPLATES . '/server'
);

define(
    'CERTIFICATE_TEMPLATES',
    TEMPLATES_PATH . '/certificates'
);

define(
    'ID_CARD_TEMPLATES',
    TEMPLATES_PATH . '/id_cards'
);


define(
    'PORTFOLIO_TEMPLATES',
    TEMPLATES_PATH . '/portfolios'
);

define(
    'REPORT_CARD_TEMPLATES',
    TEMPLATES_PATH . '/report_cards'
);

define(
    'STAFF_SECURITY_PASS_TEMPLATES',
    ID_CARD_TEMPLATES . '/staff_security_pass'
);

/*
|--------------------------------------------------------------------------
| Generated temporary files
|--------------------------------------------------------------------------
*/

define(
    'TEMP_UPLOADS',
    $normalizedUploadPath . '/temp'
);

define(
    'PRINT_OUTPUT_PATH',
    TEMP_UPLOADS . '/print'
);

/*
|--------------------------------------------------------------------------
| Required directory creation
|--------------------------------------------------------------------------
*/

$requiredUploadDirectories = [
    $normalizedUploadPath,

    STUDENT_UPLOADS,
    STUDENT_IMAGES,
    STUDENT_QR_CODES,
    STUDENT_DOCUMENTS,
    ADMISSION_UPLOADS,
    ADMISSION_DOCUMENTS,

    STAFF_UPLOADS,
    STAFF_PHOTOS,
    STAFF_IMAGES,
    STAFF_QR_CODES,
    STAFF_DOCUMENTS,

    ACADEMIC_UPLOADS,
    ACADEMIC_ASSESSMENTS,

    SCHOOL_ASSETS,
    SCHOOL_ASSETS_DOCUMENTS,
    SCHOOL_ASSETS_GALLERY,
    SCHOOL_ASSETS_QR_CODES,

    TEMP_UPLOADS,
    PRINT_OUTPUT_PATH,
];

foreach ($requiredUploadDirectories as $directory) {
    if (is_dir($directory)) {
        continue;
    }

    $parentDirectory = dirname($directory);

    if (!is_dir($parentDirectory)) {
        if (
            !mkdir($parentDirectory, 0775, true)
            && !is_dir($parentDirectory)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Unable to create parent directory "%s" '
                    . 'required for "%s".',
                    $parentDirectory,
                    $directory
                )
            );
        }
    }

    if (!is_writable($parentDirectory)) {
        throw new RuntimeException(
            sprintf(
                'Parent directory is not writable by PHP: "%s". '
                . 'Cannot create "%s".',
                $parentDirectory,
                $directory
            )
        );
    }

    if (
        !mkdir($directory, 0775, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            sprintf(
                'Unable to create required upload directory: "%s".',
                $directory
            )
        );
    }
}