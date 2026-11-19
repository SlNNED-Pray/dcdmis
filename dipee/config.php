<?php
// dipee/config.php - configuration for the rebuilt DIPEE payroll system.

const PAYROLL_VERSION = '1.0.0';
const PAYROLL_DB = 'dipee_payroll';
const PAYROLL_SESSION = 'DIPEE_SESSION';
const PAYROLL_TITLE = 'DIPEE Payroll System';
const PAYROLL_SUBTITLE = 'Schools Division of Dipolog City';

// MySQL credentials. These mirror includes/config.php of the parent system;
// the rebuilt payroll keeps its own database so the live dcdmis data is untouched.
const PAYROLL_HOST = 'localhost';
const PAYROLL_USER = 'root';
const PAYROLL_PASSWORD = '';

// Legacy data can be read (read-only) from here by the importer.
const LEGACY_PATH = 'C:/Users/Admin/Desktop/MIssion/DIPEE';
