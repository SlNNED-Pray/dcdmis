<?php
// includes/layout/header.php
?>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="description" content="<?= description() ?>">
<meta name="author" content="<?= author() ?>">
<title><?= title($page) ?></title>
<script>
    var SITE_URL = <?= json_encode(uri()) ?>;
</script>
<link rel="shortcut icon" href="<?= uri() ?>/uploads/division/division.png">
<link rel="stylesheet" href="<?= uri() ?>/assets/vendor/fontawesome-free/css/all.min.css">
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i">
<link rel="stylesheet" href="<?= uri() ?>/assets/css/sb-admin-2.min.css">
<link rel="stylesheet" href="<?= uri() ?>/assets/css/custom.css?v=1.5">