<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
if ($base === '' || $base === '.') {
    $base = '';
}
$target = $base . '/public/index.html';
header('Location: ' . $target, true, 302);
exit;
