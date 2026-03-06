<?php
/**
 * RCS HRMS Pro - Logout
 */

define('RCS_HRMS', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

$auth = new Auth();
$auth->logout();

redirect('index.php?page=auth/login');
