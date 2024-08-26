<?php
/**
 * Plugin Name:      Fastly IO
 * Description:      Set up Fastly Image Optimizer as an image editing library.
 * Author:           Tom Mount <tom.mount@pantheon.io>
 * Author URI:       https://pantheon.io
 * Text Domain:      fastly-io
 * Domain Path:      /languages
 * Version:          1.1.0
 *
 * @package          Fastly_IO
 */

function fastly_io_set_library( $editors ) {
    // If the class doesn't exist, fall back to normal.
    if (! class_exists('Fastly_IO\WP_Image_Editor_Fastly')) {
        return $editors;
    }

    // Otherwise, use this one.
    array_unshift($editors, Fastly_IO\WP_Image_Editor_Fastly::class);

    return $editors;
}

spl_autoload_register(
    function ($class) {
        $class = ltrim($class, '\\');
        if (stripos($class, 'Fastly_IO\\') !== 0) {
            return;
        }

        $parts = explode('\\', $class);
        array_shift($parts);
        $last = array_pop($parts);
        $last = "class-{$last}.php";
        $parts[] = $last;
        $file = dirname(__FILE__) . '/src/classes/' . str_replace('_', '-', strtolower(implode('/', $parts)));
        if (file_exists($file)) {
            require $file;
        }
    }
);

if ( file_exists( 'vendor/autoload.php' ) ) {
    require_once 'vendor/autoload.php';
}

add_filter('big_image_size_threshold', '__return_false');
add_filter('wp_image_editors', 'fastly_io_set_library');
