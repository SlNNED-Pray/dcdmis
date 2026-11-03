<?php
// modules/documents/document-type/delete-document-type-dialog.php
require_once '../../../includes/function.php';
require_once root() . '/includes/layout/components.php';

modalConfirmDelete('This operation cannot be undone. Are you sure you want to continue and delete this?', 'Delete Document Type', 'delete-document-type', $_GET['id'] ?? null);