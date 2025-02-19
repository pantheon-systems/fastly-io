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

add_filter('wp_image_editors', 'fastly_io_set_library');


if (!class_exists('FastlyIO\Admin\FastlyMediaRegenAdmin')) {
    require_once __DIR__ . '/src/classes/class-fastly-media-regen-admin.php';
}

if (defined('WP_CLI') && WP_CLI) {
    new FastlyIO\Admin\FastlyMediaRegenAdmin();
}

add_action('wp_ajax_fastly_media_regen_output', function () {
    $selected_sites = $_POST['selected_sites'];
    $output = '';

    if (is_multisite() && !empty($selected_sites)) {
        foreach ($selected_sites as $site_id) {
            $current_site_transient = get_transient("fastly_media_regen_output_{$site_id}");
            $site_output = $current_site_transient;
            if ($site_output !== false) {
                $output .= "\n[Site {$site_id} Output]:\n" . $site_output;
            }
        }
    } else {
        $output = get_transient('fastly_media_regen_output') ?: 'Waiting for output...';
    }

    // Ensure "Regeneration completed." is visible before deletion
    if (strpos($output, 'All batches completed') !== false) {
        wp_schedule_single_event(time() + 30, 'fastly_media_regen_cleanup');
    }

    wp_send_json_success(['output' => $output]);
});


add_action('wp_ajax_fastly_media_regen_background', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    $selected_sites = isset($_POST['selected_sites']) ? $_POST['selected_sites'] : [];
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;

    if (is_multisite() && !empty($selected_sites)) {
        foreach ($selected_sites as $site_id) {
            $site_url = get_site_url($site_id);
            $attachment_ids = get_all_attachment_ids($site_id);
            $batches = array_chunk($attachment_ids, $batch_size); 

            foreach ($batches as $index => $batch) {
                $is_final_batch = ($index === array_key_last($batches)); // Check if it's the last batch
                $batch_command = "wp media regenerate " . implode(' ', $batch) . " --yes --url=" . escapeshellarg($site_url);
                run_wp_cli_command($batch_command, $site_id,$is_final_batch);
            }
        }
    } else {
        $attachment_ids = get_all_attachment_ids();
        $batches = array_chunk($attachment_ids, $batch_size);

        foreach ($batches as $index => $batch) {
            $is_final_batch = ($index === array_key_last($batches)); // Check if it's the last batch
            $batch_command = "wp media regenerate " . implode(' ', $batch) . " --yes";
            run_wp_cli_command($batch_command,null,$is_final_batch);
        }
    }

    wp_send_json_success(['message' => 'Media regeneration started in batches.']);
});



function run_wp_cli_command($command, $site_id = null, $is_final_batch = false)
{
    $descriptorspec = [
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"], // stderr
    ];

    // Start the process
    $process = proc_open($command, $descriptorspec, $pipes, null, null);

    if (!is_resource($process)) {
        return "Failed to start WP-CLI process.";
    }

    // Get previous output if it exists
    if ($site_id) {
        $output = get_transient("fastly_media_regen_output_{$site_id}") ?: '';
    } else {
        $output = get_transient('fastly_media_regen_output') ?: '';
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read = [$pipes[1]];
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 0, 10000)) { // 10ms delay
            $line = fgets($pipes[1]);
            if ($line === false) {
                break;
            }

            $output .= $line;

            // Append output instead of replacing
            if ($site_id) {
                set_transient("fastly_media_regen_output_{$site_id}", $output, 60 * 10);
            } else {
                set_transient('fastly_media_regen_output', $output, 60 * 10);
            }

            flush();
            usleep(10000);
        }
    }

    // If this is the last batch, append "All batches completed."
    if ($is_final_batch) {
        $output .= "\nAll batches completed!";
    }

    if ($site_id) {
        set_transient("fastly_media_regen_output_{$site_id}", $output, 60 * 10);
    } else {
        set_transient('fastly_media_regen_output', $output, 60 * 10);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    proc_close($process);

    return $output;
}




add_action('fastly_media_regen_cleanup', function () {
    delete_transient('fastly_media_regen_output');

    if (is_multisite()) {
        $sites = get_sites(['fields' => 'ids']);
        foreach ($sites as $site_id) {
            delete_transient("fastly_media_regen_output_{$site_id}");
        }
    }
});

function get_all_attachment_ids($site_id = null)
{
    if ($site_id) {
        switch_to_blog($site_id);
    }

    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    if ($site_id) {
        restore_current_blog();
    }

    return $attachments;
}




