<?php
session_start();
// permissions.php

function can(string $permission): bool
{
    // owner overrides everything
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        return true;
    }

    if (empty($_SESSION['permissions'])) {
        return false;
    }

    return in_array($permission, $_SESSION['permissions'], true);
}
