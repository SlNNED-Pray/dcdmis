<?php
// Entry point for the application folder.
//
// The application is normally served from its own virtual host
// (http://depeddipolog.test). This file makes the same folder usable when it
// is opened through the htdocs directory, for example
// http://localhost/depeddipolog.test/.
header('Location: login/');
exit;
