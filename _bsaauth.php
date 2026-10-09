<?php
session_name('DCDMIS_SESSION');
session_id($_GET['sid'] ?? '');
session_start();
session_write_close();
header('Location: /bsa/');
