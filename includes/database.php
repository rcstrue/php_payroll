<?php
/**
 * RCS HRMS Pro - Database Connection Initialization
 */

// The Database class is already autoloaded via config.php
// Get the Database instance for classes using fetch(), fetchAll(), etc.
$dbWrapper = Database::getInstance();

// Get the raw PDO connection for templates using prepare(), query(), etc.
$db = $dbWrapper->getConnection();
