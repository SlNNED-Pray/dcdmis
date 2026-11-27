<?php
ini_set('session.name', 'DCDMIS_SESSION');
session_start();
$_SESSION['depeddipolog_userId'] = '221116000000';
$_SESSION['depeddipolog_stationId'] = '';
$_SESSION['depeddipolog_code'] = 'IMS';
$_SESSION['depeddipolog_portal'] = 'ims';
$_SESSION['depeddipolog_activeApp'] = 'ims';
unset($_SESSION['depeddipolog_change_password']);
header('Location: /ims?v=' . urlencode(base64_encode('Disapproved RIS')));
exit;
