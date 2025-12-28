<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
$lang = $_GET['lang'] ?? 'hi';
if (!in_array($lang, ['hi','en'], true)) { $lang = 'hi'; }
setcookie('app_lang', $lang, time()+60*60*24*365, '/', '', false, true);
$return = $_GET['return'] ?? '';
if (is_string($return)) {
	$return = trim($return);
}
$target = 'dashboard.php';
if ($return !== '' && strpos($return, '://') === false && !str_starts_with($return, '//')) {
	$clean = ltrim($return, '/');
	if ($clean !== '') {
		$target = $clean;
	}
}
redirect($target);
