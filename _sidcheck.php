<?php
ini_set('session.name', 'DCDMIS_SESSION');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
session_start();
header('Content-Type: text/plain');
echo "session_id=" . session_id() . "\n";
echo "save_path=" . ini_get('session.save_path') . "\n";
echo "sid_length=" . ini_get('session.sid_length') . "\n";
echo "cookie=" . ($_COOKIE['DCDMIS_SESSION'] ?? '(none)') . "\n";
echo "--- \$_SESSION ---\n";
var_export($_SESSION);
