<?php
// Temporary local test helper: seed a logged-in session for Booking Approvals.
session_name('DCDMIS_SESSION');
session_start();
$p = 'depeddipolog_';
$_SESSION[$p . 'userId'] = '221110470000';
$_SESSION[$p . 'activeApp'] = 'bsa';
$_SESSION[$p . 'stationId'] = '143';
$_SESSION[$p . 'station'] = 'Dipolog';
$_SESSION[$p . 'code'] = '143';
$_SESSION[$p . 'portal'] = 'sch_portal';
$_SESSION[$p . 'email'] = 'randyl.geraga@deped.gov.ph';
$_SESSION[$p . 'data_privacy_agreed_bsa'] = 1;
header('Location: /bsa/?v=' . rawurlencode(base64_encode('Booking Approvals')));
exit;
