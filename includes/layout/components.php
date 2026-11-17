<?php
// includes/layout/components.php
function displayLogo($width, $height, $marginBottom = '3', $url = '', $text = '')
{ ?>
    <a href="<?= e($url) ?>" title="<?= e($text) ?>" class="d-inline-block mx-auto mb-<?= e($marginBottom) ?>">
        <img src="<?= uri() ?>/uploads/division/division.png" width="<?= e($width) ?>" height="<?= e($height) ?>"
            alt="<?= e($text) ?>">
    </a>
<?php }

function messageAlert($show, $message, $success = true, $align = 'left')
{
    if ($show): ?>
        <div
            class="alert alert-<?= $success ? 'success' : 'danger' ?> text-<?= e($align) ?> p-2 d-flex align-items-start small">
            <i class="fa fas fa-<?= $success ? 'info' : 'exclamation' ?>-circle mt-1 mr-1"></i>
            <div>
                <?= $message ?>
            </div>
        </div>
    <?php endif;
}

function message($text, $color = 'info', $icon = 'info-circle', $marginBottom = 0, $marginTop = 0)
{ ?>
    <div class="alert alert-<?= $color ?> p-2 d-flex align-items-start small mb-<?= $marginBottom ?> mt-<?= $marginTop ?>">
        <i class="fa fas fa-<?= $icon ?> mt-1 mr-1"></i>
        <div>
            <?= $text ?>
        </div>
    </div> <?php
}

function roundPill($text, $bgColor = 'primary', $textColor = 'light')
{
    if ($bgColor === 'primary') {
        switch (strtolower($text)) {
            case 'active':
                $bgColor = 'success';
                break;
            case 'transferred':
                $bgColor = 'info';
                break;
            case 'suspended':
                $bgColor = 'warning';
                $textColor = 'secondary';
                break;
            case 'resigned':
            case 'retired':
            case 'dismissed':
                $bgColor = 'danger';
                break;
            case 'deceased':
                $bgColor = 'dark';
                break;
            default:
                $bgColor = 'secondary';
                break;
        }
    } ?>
    <span class="py-1 px-3 small bg-<?= e($bgColor) ?> rounded-pill text-<?= e($textColor) ?>"><?= e($text) ?></span>
<?php }

function newFeatureMark()
{ ?>
    <span class="new-feature bg-danger px-2 small ml-1 text-light font-weight-light text-capitalize rounded-pill">New</span>
<?php }

function sidebarDivider($marginBottom = '0', $autoHide = false)
{ ?>
    <hr class="sidebar-divider mb-<?= e($marginBottom) ?> <?= $autoHide ? 'd-none d-md-block' : '' ?>">
<?php }

function sidebarHeading($text)
{ ?>
    <div class="sidebar-heading mt-3"><?= e($text) ?></div>
<?php }

function sidebarToggle()
{ ?>
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
<?php }

function sex($sex)
{
    $sign = strtolower($sex) === 'male' ? 'mars' : 'venus' ?>
    <i class="<?= "fas fa-{$sign} text-{$sign} fa-2x" ?>"></i>
<?php }

function card($title, $link, $icon, $color = 'primary', $counter = null, $newFeature = false)
{ ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-<?= e($color) ?> shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="font-weight-bold text-<?= e($color) ?> text-uppercase mb-1">
                            <?= $title;
                            if ($newFeature) {
                                newFeatureMark();
                            } ?>
                        </div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800"><?= $counter !== null ? $counter : '&nbsp;' ?>
                        </div>
                    </div>

                    <div class="col-auto">
                        <i class="fas <?= e($icon) ?> fa-3x text-<?= e($color) ?>" aria="hidden"></i>
                    </div>
                </div>
            </div>

            <div class="card-footer py-1 text-right">
                <a class="small text-<?= e($color) ?>" href="<?= e($link) ?>">View Details</a>
            </div>
        </div>
    </div>
<?php }

function cardMini($title, $link, $icon, $color = 'primary', $newTab = false)
{ ?>
    <div class="col-xl-2 col-lg-3 col-md-4 mb-4">
        <div class="card border-left-<?= e($color) ?> shadow h-100">
            <div class="card-body">
                <div class="row">
                    <div class="col-auto">
                        <i class="fas <?= e($icon) ?> fa-3x text-<?= e($color) ?>" aria="hidden"></i>
                    </div>

                    <div class="col">
                        <div class="font-weight-bold text-uppercase mb-1">
                            <a class="text-<?= e($color) ?>" href="<?= e($link) ?>"
                                target="<?= $newTab ? '_blank' : '_self' ?>">
                                <?= e($title) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php }

function cardMiniModal($title, $link, $icon, $color = 'primary')
{ ?>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-<?= e($color) ?> shadow h-100">
            <div class="card-body">
                <div class="row">
                    <div class="col-auto">
                        <i class="fas <?= e($icon) ?> fa-3x text-<?= e($color) ?>" aria="hidden"></i>
                    </div>

                    <div class="col">
                        <div class="font-weight-bold text-uppercase mb-1">
                            <a class="text-<?= e($color) ?>" href="#" data-toggle="modal" data-target="#modal"
                                onclick="loadData('<?= e($link) ?>')">
                                <?= e($title) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php }

function scrollToTop()
{ ?>
    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>
<?php }

function showAsterisk($show = true)
{
    if ($show): ?>
        <span class="text-danger small"> *</span>
    <?php endif;
}

function modal()
{ ?>
    <div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-hidden="true" aria-modal="true"
        data-backdrop="static">
        <div class="modal-dialog d-none">
            <div class="modal-content">
                <?php modalHeader('') ?>

                <div class="modal-body"></div>

                <div class="modal-footer">
                    <form action="" method="POST" role="form">
                        <?= csrf_field(); ?>
                        <?php cancelModalButton() ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php }

function contentTitle($title)
{ ?>
    <div class="d-sm-flex">
        <h3 class="h3 mb-0 text-gray-800"><?= e($title) ?></h3>
    </div>
<?php }

function contentTitleWithLink($title, $link, $text = 'Back', $icon = 'fa-arrow-circle-left', $color = 'primary')
{ ?>
    <div class="d-sm-flex align-items-center justify-content-between">
        <h3 class="h3 mb-0 text-gray-800"><?= e($title) ?></h3>
        <?php linkButtonSplit($link, $text, $icon, $text, $color) ?>
    </div>
<?php }

function contentTitleWithModal($title, $link, $text, $icon, $color = 'primary')
{ ?>
    <div class="d-sm-flex align-items-center justify-content-between">
        <h3 class="h3 mb-0 text-gray-800"><?= e($title) ?></h3>
        <?php modalButtonSplit($link, $text, $icon, $text, $color) ?>
    </div>
<?php }

function sidebarMenuItem($link, $title, $icon, $condition = false, $counter = null, $newFeature = false, $target = null)
{ ?>
    <li class="nav-item <?= $condition ? ' active' : '' ?>">
        <a class="nav-link d-flex align-items-center justify-content-between" href="<?= e($link) ?>"<?= $target !== null ? ' target="' . e($target) . '"' : '' ?>>
            <div class="menu-item">
                <i class="fas fa-fw <?= e($icon) ?>"></i>
                <span>
                    <?= $title;
                    if ($newFeature) {
                        newFeatureMark();
                    } ?>
                </span>
            </div>
            <?php if ($counter !== null): ?>
                <span class="bg-dark px-2 rounded-pill font-weight-bold"><?= e($counter) ?></span>
            <?php endif ?>
        </a>
    </li>
<?php }

function sidebarModalItem($link, $title, $icon, $counter = null, $newFeature = false)
{ ?>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center justify-content-between" href="#" data-toggle="modal"
            data-target="#modal" onclick="loadData('<?= e($link) ?>')">
            <div class="menu-item">
                <i class="fas fa-fw <?= e($icon) ?>"></i>
                <span>
                    <?= $title;
                    if ($newFeature) {
                        newFeatureMark();
                    } ?>
                </span>
            </div>
            <?php if ($counter !== null): ?>
                <span class="bg-dark px-2 rounded-pill font-weight-bold"><?= e($counter) ?></span>
            <?php endif ?>
        </a>
    </li>
<?php }

function linkItem($link, $text, $newTab = false)
{ ?>
    <a href="<?= e($link) ?>" class="text-uppercase" target="<?= $newTab ? '_blank' : '_self' ?>"><?= e($text) ?></a>
<?php }

function modalItem($link, $text)
{ ?>
    <a href="#" data-toggle="modal" data-target="#modal" class="text-uppercase"
        onclick="loadData('<?= e($link) ?>')"><?= e($text) ?></a>
<?php }

function linkButtonSplit($link, $text, $icon, $title = '', $color = 'primary', $newTab = false)
{ ?>
    <a href="<?= e($link) ?>"
        class="btn btn-<?= e($color) ?> btn-icon-split btn-sm my-1 p-0 d-inline-flex align-items-stretch overflow-hidden"
        title="<?= e($title) ?>" target="<?= $newTab ? '_blank' : '_self' ?>">
        <span class="icon text-white-50 d-inline-flex align-items-center justify-content-center"><i
                class="fas <?= e($icon) ?> fa-fw"></i></span>
        <span class="text d-inline-flex align-items-center"><?= e($text) ?></span>
    </a>
<?php }

function modalButtonSplit($link, $text, $icon, $title = '', $color = 'primary')
{ ?>
    <a href="#" data-toggle="modal" data-target="#modal"
        class="btn btn-<?= e($color) ?> btn-icon-split btn-sm my-1 p-0 d-inline-flex align-items-stretch overflow-hidden"
        title="<?= e($title) ?>" onclick="loadData('<?= e($link) ?>')">
        <span class="icon text-white-50 d-inline-flex align-items-center justify-content-center">
            <i class="fas <?= e($icon) ?> fa-fw"></i>
        </span>
        <?php if (!empty($text)): ?>
            <span class="text d-inline-flex align-items-center"><?= e($text) ?></span>
        <?php endif ?>

    </a>
<?php }

function linkDropdownItem($link, $text, $icon, $title = '', $newTab = false, $newFeature = false)
{ ?>
    <a href="<?= e($link) ?>" class="dropdown-item" title="<?= e($title) ?>" target="<?= $newTab ? '_blank' : '_self' ?>">
        <i class="fas <?= e($icon) ?> fa-sm fa-fw mr-1"></i>
        <?= $text;
        if ($newFeature) {
            newFeatureMark();
        } ?>
    </a>
<?php }

function downloadLinkDropdownItem($link, $text, $icon, $title, $fileName, $newTab = false, $newFeature = false)
{ ?>
    <a href="<?= e($link) ?>" class="dropdown-item" download="<?= e($fileName) ?>" title="<?= e($title) ?>"
        target="<?= $newTab ? '_blank' : '_self' ?>">
        <i class="fas <?= e($icon) ?> fa-sm fa-fw mr-1"></i>
        <?= $text;
        if ($newFeature) {
            newFeatureMark();
        } ?>
    </a>
<?php }

function previewLinkDropdownItem($link, $text, $icon, $title, $newFeature = false)
{ ?>
    <a href="<?= e($link) ?>" class="dropdown-item" title="<?= e($title) ?>" target="_new">
        <i class="fas <?= e($icon) ?> fa-sm fa-fw mr-1"></i>
        <?= $text;
        if ($newFeature) {
            newFeatureMark();
        } ?>
    </a>
<?php }

function modalDropdownItem($link, $text, $icon, $title = '', $newFeature = false)
{ ?>
    <a href="#" data-toggle="modal" data-target="#modal" class="dropdown-item" title="<?= e($title) ?>"
        onclick="loadData('<?= e($link) ?>')">
        <i class="fas <?= e($icon) ?> fa-sm fa-fw mr-1"></i>
        <?= $text;
        if ($newFeature) {
            newFeatureMark();
        } ?>
    </a>
<?php }

function dropdownEllipsis()
{ ?>
    <a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-600"></i>
    </a>
<?php }

function modalHeader($title)
{ ?>
    <div class="modal-header">

        <h5 class="modal-title"><?= e($title) ?></h5>
        <button id="close-modal-button" type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
    </div>
<?php }

function modalConfirmDelete($message, $title = 'Delete', $buttonName = 'Delete', $verifier = null, $dataVerifier = null)
{ ?>
    <div class="modal-dialog">
        <div class="modal-content">
            <?php modalHeader($title) ?>

            <div class="modal-body">
                <?= e($message) ?>
            </div>

            <div class="modal-footer">
                <form action="" method="POST" role="form">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="verifier" value="<?= e($verifier) ?>">
                    <input type="hidden" name="data-verifier" value="<?= e($dataVerifier) ?>">
                    <input type="submit" class="btn btn-danger" name="<?= e($buttonName) ?>" value="Yes, Continue">
                    <?php cancelModalButton() ?>
                </form>
            </div>
        </div>
    </div>
<?php }

function cancelModalButton()
{ ?>
    <button id="cancel-modal-button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
<?php }

function missingAlert($text, $icon = 'fa-times-circle', $color = 'text-danger')
{ ?>
    <div class="error mx-auto text-center <?= e($color) ?>"><i class="fas <?= e($icon) ?> fa-fw"></i></div>
    <p class="lead text-center text-gray-800 mt-1 mb-0"><?= e($text) ?></p>
    <p class="text-center text-gray-600 mb-0">Sorry, we couldn't find what you're looking for...</p>
<?php }

function requiredLegend($marginBottom = 2)
{ ?>
    <div class="text-danger small mb-<?= e($marginBottom) ?>">* Required field</div>
<?php }

function progressBar($value, $min = 50)
{ ?>
    <div class="progress mt-1" title="<?= e($value) ?>% Complete">

        <div class="progress-bar bg-<?= $value > $min ? 'success' : 'danger' ?>" role="progressbar"
            aria-valuenow="<?= e($value) ?>" aria-valuemin="0" aria-valuemax="100" style="width:<?= e($value) ?>%"></div>
    </div>
<?php }

function employeeProfile($picture, $name, $sex, $email, $position, $station, $status)
{ ?>
    <div class="image-container">
        <span class="d-flex justify-content-center align-middle employee-photo photo-4x rounded-circle overflow-hidden">
            <img height="100%" src="<?= e($picture) ?>" alt="<?= e($name) ?>">
        </span>
        <div class="sex-sign"><?php sex($sex) ?></div>
    </div>

    <div class="text-center text-uppercase h4 mt-3 mb-0"><?= e($name) ?></div>
    <div class="text-center text-lowercase m-0 small"><?= e($email) ?></div>
    <div class="text-center text-uppercase my-1"><?php roundPill($status) ?></div>
    <div class="text-center text-uppercase h5 mt-3 mb-1"><?= e($position) ?></div>
    <div class="text-center text-uppercase h6 my-1"><?= e($station) ?></div>
<?php }

function profilePhotoUpload($file, $photo, $label, $uri, $preview)
{ ?>
    <script>
        let currentPreviewUrl = null;

        document.getElementById('<?= e($file) ?>').addEventListener('change', (event) => {
            let preview = document.getElementById('<?= e($photo) ?>');
            const file = event.target.files[0];

            if (!file) return;

            const name = file.name;
            const lastDot = name.lastIndexOf('.');
            const ext = name.substring(lastDot + 1).toLowerCase();
            let label = document.getElementById('<?= e($label) ?>');
            label.innerText = name;

            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
            }

            switch (ext) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                    currentPreviewUrl = URL.createObjectURL(file);
                    preview.src = currentPreviewUrl;
                    break;
                default:
                    preview.src = '<?= e($uri) . $preview ?>';
                    currentPreviewUrl = null;
                    break;
            }
        });
    </script>
<?php }

function dateFilterForm($from_date, $to_date)
{ ?>
    <form action="" method="POST" class="mb-3">
        <?= csrf_field(); ?>
        <div class="row">
            <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
                <div class="form-group">
                    <div class="row">
                        <div class="col-2 d-flex align-items-center">
                            <label for="date-from" class="font-weight-bold m-0">From:</label>
                        </div>
                        <div class="col-10">
                            <input class="form-control" id="date-from" type="date" name="date-from"
                                value="<?= e($from_date) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
                <div class="form-group">
                    <div class="row">
                        <div class="col-2 d-flex align-items-center">
                            <label for="date-to" class="font-weight-bold m-0">To:</label>
                        </div>

                        <div class="col-10">
                            <input class="form-control" id="date-to" type="date" name="date-to" value="<?= e($to_date) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-lg-2 col-md-12 col-sm-12">
                <button type="submit" class="btn btn-primary btn-block" name="transactions-summary-filter">Filter Result
                    <i class="fa fa-filter"></i></button>
            </div>
        </div>
    </form>
<?php }