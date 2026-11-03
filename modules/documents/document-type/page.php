<?php
// modules/documents/document-type/page.php
if (!$isDts && !($isICT || $isRecordsPortal || $isAdminPortal)) {
    require_once(root() . '/modules/error/403.php');
    return;
}

messageAlert($showAlert, $message, $success);
?>

<div class="d-flex align-items-center justify-content-between flex-row mt-2 mb-3">
    <nav class="d-flex align-items-center flex-row m-0">
        <ol class="breadcrumb m-0 p-0 bg-transparent">
            <li class="breadcrumb-item">
                <a href="<?= "{$baseUri}/{$activeApp}" ?>">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Document Types</li>
        </ol>
    </nav>
</div>

<div class="card border-left-primary shadow mb-4">
    <div class="card-header py-3">
        <?php contentTitleWithModal('Document Types', "{$baseUri}/modules/documents/document-type/save-document-type-dialog.php", 'Add', 'fa-plus'); ?>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-center" id="data-table" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="align-middle" width="85%">Name / Description</th>
                        <th class="align-middle" width="10%">For School</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = documentTypes();
                    foreach ($query as $row) { ?>
                        <tr class="text-uppercase">
                            <td class="align-middle text-left">
                                <div><a
                                        href="<?= customUri('dts', 'Document Type Details', $row['id']) ?>"><?= $row['name'] ?></a>
                                </div>
                                <?php $docTypeDescription = $row['description'];
                                if (!empty($docTypeDescription)): ?>
                                    <div class="small"><?= $row['description'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <?php $forSchool = $row['for_school'];
                                if ($forSchool): ?>
                                    <div class="small">
                                        <i class="fas fa-check"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle text-capitalize">
                                <div class="dropdown no-arrow">
                                    <?php dropdownEllipsis() ?>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                        <?php modalDropdownItem(uri() . '/modules/documents/document-type/save-document-type-dialog.php?id=' . cipher($row['id']), 'Edit', 'fa-edit', 'Edit Document Type');
                                        modalDropdownItem(uri() . '/modules/documents/document-type/save-document-type-dialog.php?id=' . cipher($row['id']), 'Copy', 'fa-copy', 'Copy Document Type') ?>
                                        <div class="dropdown-divider"></div>
                                        <?php modalDropdownItem(uri() . '/modules/documents/document-type/delete-document-type-dialog.php?id=' . cipher($row['id']), 'Delete', 'fa-trash', 'Delete Document Type'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>

                <tfoot>
                    <tr class="small">
                        <th class="align-middle" width="85%">Name / Description</th>
                        <th class="align-middle" width="10%">For School</th>
                        <th class="align-middle" width="5%">Action</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>