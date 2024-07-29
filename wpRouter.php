<?php
/**
 * Plugin Name: wpRouter
 * Description: A custom routing plugin for WordPress.
 */

// Register the autoloader
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'wpRouter\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Include the main Route class (autoloaded by our custom autoloader)
use wpRouter\Route;

// Add any necessary hooks or initialization code here
