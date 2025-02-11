<?php
namespace Fastly_IO\CLI;

use WP_CLI;
use WP_CLI_Command;

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Class FastlyMediaRegen
 *
 * Hooks into `wp media regenerate` to output metadata before regeneration.
 *
 * @package Fastly_IO\CLI
 */
class FastlyMediaRegen extends WP_CLI_Command
{
    /**
     * Regenerates media thumbnails while outputting metadata beforehand.
     *
     * ## OPTIONS
     *
     * [--verbose]
     * : Show detailed output.
     *
     * ## EXAMPLES
     *
     *     wp media regenerate
     *     wp media regenerate --verbose
     *
     * @param array $args        Positional arguments (attachment IDs or empty).
     * @param array $assoc_args  Associative arguments.
     */
    public function regenerate($args, $assoc_args)
    {
        $verbose = isset($assoc_args['verbose']);

        // Get media IDs from args or default to all attachments
        $attachment_ids = !empty($args) ? array_map('intval', $args) : $this->get_all_attachment_ids();

        if (empty($attachment_ids)) {
            WP_CLI::warning('No media attachments found.');
            return;
        }

        $filtered_ids = [];
        foreach ($attachment_ids as $attachment_id) {
            if ($this->has_image_meta($attachment_id)) {
                // Output metadata before regenerating
                $this->output_metadata($attachment_id, $verbose);
                $filtered_ids[] = $attachment_id;
            }
        }

        if (!empty($filtered_ids)) {
            // Call the original `wp media regenerate` command only on filtered images
            WP_CLI::runcommand('media regenerate ' . implode(' ', $filtered_ids) . ($verbose ? ' --verbose' : ''));
        } else {
            WP_CLI::warning('No valid images with image_meta found for regeneration.');
        }
    }


    /**
     * Outputs media metadata.
     *
     * @param int  $attachment_id The attachment ID.
     * @param bool $verbose       Whether to show detailed output.
     */
    private function output_metadata($attachment_id, $verbose)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);

        if (!$metadata) {
            WP_CLI::warning("No metadata found for attachment ID: $attachment_id");
            return;
        }

        WP_CLI::log("Metadata for Attachment ID: $attachment_id");
        WP_CLI::log("MIME Type: " . ($mime_type ?: 'Unknown'));

        if (isset($metadata['image_meta'])) {
            WP_CLI::log("Image metadata found. Proceeding with regeneration.");
        } else {
            WP_CLI::log("No image meta data is present. The mime type is " . $mime_type . " This is likely not an image, skipping regeneration.");
        }

    }

    /**
     * Checks if an attachment has image_meta.
     *
     * @param int $attachment_id The attachment ID.
     * @return bool True if image_meta is present, false otherwise.
     */
    private function has_image_meta($attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        return isset($metadata['image_meta']);
    }

    /**
     * Gets all attachment IDs.
     *
     * @return array Attachment IDs.
     */
    private function get_all_attachment_ids()
    {
        return get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
    }
}

// Register the command, overriding `wp media regenerate`
WP_CLI::add_command('fastlyio media', __NAMESPACE__ . '\\FastlyMediaRegen');
