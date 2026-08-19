<?php
// includes/layout/header.php
require_once('app.php');

$url = isset($_GET['v']) ? sanitize(decode($_GET['v'])) : null;
$page = isset($url) && !empty($url) ? "{$url} | {$appTitle}" : $appTitle;
$isPis = $activeApp === 'pis';
$isDts = $activeApp === 'dts';
$isHrmis = $activeApp === 'hrmis';
$isHrtdms = $activeApp === 'hrtdms';
$isDmis = $activeApp === 'dmis';
$isRace = $activeApp === 'race';
$isDtr = $activeApp === 'dtr';
$isIms = $activeApp === 'ims';

require_once(root() . '/includes/layout/components.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php require_once(root() . '/includes/layout/header.php') ?>
    <link rel="stylesheet" href="<?= uri() ?>/assets/vendor/datatables/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?= uri() ?>/assets/vendor/datatables-responsive/css/responsive.bootstrap4.min.css">
    <script>
        <?php $uri = (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>
        var SITE_URL = <?= json_encode($uri) ?>;
    </script>
</head>

<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <div class="sidebar-brand d-flex align-items-center justify-content-center">
                <a href="<?= uri() ?>" title="<?= title() ?>">
                    <img class="sidebar-brand-icon" src="<?= uri() ?>/uploads/division/division.png"
                        alt="<?= title() ?>" width="60">
                </a>
            </div>

            <?php
            sidebarDivider();
            sidebarMenuItem(uri() . '/' . $activeApp, 'Dashboard', 'fa-tachometer-alt', !isset($url));
            require_once('sidebar-menu.php');
            sidebarDivider('3', true);
            sidebarToggle();
            ?>
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once(root() . '/includes/layout/header-menu.php') ?>

                <div class="container-fluid my-4">
                    <?php require_once(root() . '/includes/layout/content.php') ?>
                </div>
            </div>

            <?php require_once(root() . '/includes/layout/footer.php') ?>
        </div>
    </div>

    <?php
    scrollToTop();
    modal();

    $currentArea = ($url === 'Monitoring Tools') ? 'monitoring_tools' : $activeApp;
    $currentArea = ($url === 'System Overview') ? 'system_overview' : $currentArea;
    $needsAgreement = empty($_SESSION["{$prefix}data_privacy_agreed_{$currentArea}"]);
    ?>

    <?php if ($needsAgreement): ?>
        <!-- Data Privacy Agreement Modal -->
        <div class="modal fade" id="dataPrivacyModal" tabindex="-1" role="dialog" aria-labelledby="dataPrivacyModalLabel"
            aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content shadow-lg border-0">
                    <form action="" method="POST" id="dataPrivacyForm" class="w-100">
                        <?= csrf_field(); ?>
                        <div class="modal-header text-dark py-3">
                            <h5 class="modal-title font-weight-bold" id="dataPrivacyModalLabel">DATA PRIVACY NOTICE AND USER
                                AGREEMENT</h5>
                        </div>
                        <div class="modal-body p-4 text-dark" style="max-height: 500px; overflow-y: auto;">
                            <p class="font-weight-bold">Department of Education Schools Division of Dipolog City</p>
                            <p class="small">This system is an official platform of the Schools Division Office of Dipolog
                                City
                                and is accessible only to authorized DepEd personnel using enterprise-issued accounts.</p>
                            <p class="small">By accessing and using this platform, you acknowledge and agree to the
                                following:
                            </p>
                            <hr class="my-3">
                            <h6 class="font-weight-bold mb-1">1. Compliance with Data Privacy Laws</h6>
                            <p class="small text-secondary mb-3">You shall comply with the provisions of Republic Act No.
                                10173
                                (Data Privacy Act of 2012), its Implementing Rules and Regulations, and applicable issuances
                                of
                                the National Privacy Commission (NPC).</p>

                            <h6 class="font-weight-bold mb-1">2. Authorized Use Only</h6>
                            <p class="small text-secondary mb-3">Access to and use of data within this platform shall be
                                strictly for legitimate, official, and work-related purposes in line with your authorized
                                functions and responsibilities.</p>

                            <h6 class="font-weight-bold mb-1">3. Confidentiality and Non-Disclosure</h6>
                            <p class="small text-secondary mb-3">You shall maintain the confidentiality of all personal
                                information and sensitive personal information accessed through the system. Unauthorized
                                disclosure, sharing, or misuse of such data is strictly prohibited.
                            </p>

                            <h6 class="font-weight-bold mb-1">4. Responsibility and Accountability</h6>
                            <p class="small text-secondary mb-3">You are accountable for all activities performed under your
                                login credentials. Any access, processing, or use of personal data shall be done in
                                accordance
                                with established policies, security protocols, and data protection principles.
                            </p>

                            <h6 class="font-weight-bold mb-1">5. Prohibited Acts</h6>
                            <p class="small text-secondary mb-1">You shall not:
                            <ul class="small text-secondary mb-3">
                                <li>Access data beyond your authorized level or function;</li>
                                <li>Use personal data for unauthorized purposes;</li>
                                <li>Copy, download, or extract data without proper authorization;</li>
                                <li>Share access credentials or allow unauthorized use of your account.</li>
                            </ul>
                            </p>

                            <h6 class="font-weight-bold mb-1">6. Monitoring and Security</h6>
                            <p class="small text-secondary mb-3">All user activities within the system may be monitored and
                                logged for security, audit, and compliance purposes.
                            </p>

                            <h6 class="font-weight-bold mb-1">7. Penalties for Violations</h6>
                            <p class="small text-secondary mb-3">Any violation of data privacy policies and applicable laws
                                may
                                result in administrative, civil, or criminal liabilities under existing laws, rules, and
                                regulations.</p>

                            <input type="hidden" name="data_privacy_area" value="<?= e($currentArea) ?>">
                            <div class="custom-control custom-checkbox mb-0 text-left">
                                <input type="checkbox" class="custom-control-input" id="agreeCheckbox" required>
                                <label class="pt-1 custom-control-label small font-weight-bold text-dark cursor-pointer"
                                    for="agreeCheckbox">
                                    I hereby confirm that I have read, understood, and agreed to comply with this Data
                                    Privacy Notice and User Agreement.
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer bg-light d-flex flex-column align-items-stretch p-3">
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="accept_data_privacy" id="acceptBtn"
                                    class="btn btn-primary px-4 font-weight-bold" disabled>
                                    <i class="fas fa-check-circle mr-2"></i>I Agree & Proceed
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="<?= uri() ?>/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?= uri() ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= uri() ?>/assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?= uri() ?>/assets/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
        integrity="sha384-Udt767MMeKelGRBxaCfxX88YDLbViYdQ7T/gkRoB197Jf+OviZ+lsaRAOpS/MIjf"
        crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"
        integrity="sha384-vCX+UFRnh1Gp0hr9dL82snXI1HvdBaApGHMjbewoGQ69VkYcHt9jvTy+Q4CAWwPX"
        crossorigin="anonymous"></script>
    <script src="<?= uri() ?>/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="<?= uri() ?>/assets/vendor/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <script src="<?= uri() ?>/assets/js/script.js?v=<?= VERSION ?>"></script>
    <script src="<?= uri() ?>/assets/js/csrf-heartbeat.js?v=<?= VERSION ?>"></script>
    <?php require_once(root() . '/includes/layout/customjs.php') ?>

    <?php if ($needsAgreement): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Show the modal immediately
                $('#dataPrivacyModal').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });

                // Enable/disable submit button based on checkbox check
                const agreeCheckbox = document.getElementById('agreeCheckbox');
                const acceptBtn = document.getElementById('acceptBtn');

                if (agreeCheckbox && acceptBtn) {
                    agreeCheckbox.addEventListener('change', function () {
                        acceptBtn.disabled = !this.checked;
                    });
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>