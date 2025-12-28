<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';

if (!is_logged_in()) {
    redirect('login.php');
    exit;
}

redirect('dashboard.php');

