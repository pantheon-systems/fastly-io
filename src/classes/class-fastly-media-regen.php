<?php
namespace Fastly_IO\CLI;

use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;


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
     * [--<field>=<value>]
     * : Allow unlimited number of associative parameters.
     * ## EXAMPLES
     *
     *     wp media regenerate
     *     wp media regenerate --verbose
     *
     * @param array $args        Positional arguments (attachment IDs or empty).
     * @param array $assoc_args  Associative arguments.
     */
    public function regenerate( $args, $assoc_args )
    {
        $site = $assoc_args['blogid'];
        $url_flag = '';

        if ( $site ) {
            if ( is_numeric( $site ) ) {
                $blog_id = intval( $site );
                $site_url = get_site_url( $blog_id );
            } else {
                $site_url = esc_url_raw( $site );
            }
            
            if ( !empty( $site_url ) ) {
                WP_CLI::log( "Regenerating media for site: {$site_url}" );
                $url_flag = ' --url=' . escapeshellarg( $site_url );
            } else {
                WP_CLI::error( "Invalid site parameter: {$site}. Must be either blog ID or site URL." );
                return;
            }
        }


        // Get media IDs from args or default to all attachments
        $attachment_ids = !empty($args) ? array_map('intval', $args) : $this->get_all_attachment_ids();

        if (empty($attachment_ids)) {
            WP_CLI::warning('No media attachments found.');
            return;
        }

        $filtered_ids = [];
        foreach ( $attachment_ids as $attachment_id ) {
            if ( $this->has_image_meta( $attachment_id ) ) {
                $filtered_ids[] = $attachment_id;
            }
        }

        if ( !empty( $filtered_ids ) ) {
            // Call the original `wp media regenerate` command only on filtered images
           WP_CLI::runcommand( 'media regenerate ' . implode(' ', $filtered_ids) . $url_flag . ($verbose ? ' --verbose' : '' ));
        } else {
            WP_CLI::warning( 'No valid images found for regeneration.' );
        }
    }



    /**
     * Checks if an attachment has image_meta.
     *
     * @param int $attachment_id The attachment ID.
     * @return bool True if image_meta is present, false otherwise.
     */
    private function has_image_meta( $attachment_id )
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        return isset( $metadata['image_meta'] );
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

WP_CLI::add_command( 'fastlyio media', __NAMESPACE__ . '\\FastlyMediaRegen' );
