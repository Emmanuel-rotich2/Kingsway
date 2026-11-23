<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
/** Compatibility wrapper: the page footer is only the reusable fixed band. */
include __DIR__ . '/fee_structure_page_footer.php';
