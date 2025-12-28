<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
$lang = $_GET['lang'] ?? 'hi';
if (!in_array($lang, ['hi','en'], true)) { $lang = 'hi'; }
setcookie('app_lang', $lang, time()+60*60*24*365, '/', '', false, true);
redirect('dashboard.php');
