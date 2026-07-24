<?php
/**
 * Compatibility component for DashboardRouter.
 *
 * The route loader currently resolves the canonical page first. Keeping this
 * component as an include preserves DashboardRouter registry compatibility
 * without maintaining a second dashboard UI or controller contract.
 */
require __DIR__ . '/../../pages/system_administrator_dashboard.php';
