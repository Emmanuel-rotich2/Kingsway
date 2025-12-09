<?php
// permissions.php - Permission helper functions

function has_permission($user, $permission)
{
    // Example: $user['permissions'] is an array of permission strings
    if (!isset($user['permissions']) || !is_array($user['permissions'])) {
        return false;
    }
    return in_array($permission, $user['permissions']);
}
