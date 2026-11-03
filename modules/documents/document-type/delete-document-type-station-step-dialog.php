<?php
// modules/documents/document-type/delete-document-type-station-step-dialog.php
require_once('../../../includes/function.php');
require_once(root() . '/includes/layout/components.php');

modalConfirmDelete('This operation cannot be undone. Are you sure you want to continue and delete this step and all checklist requirements under it?', 'Delete Document Type Step', 'delete-document-type-station-step', $_GET['id'] ?? null);