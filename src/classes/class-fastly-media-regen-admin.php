<?php
namespace FastlyIO\Admin;

use WP_CLI;
use WP_CLI_Command;

class FastlyMediaRegenAdmin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_fastly_media_regen', [$this, 'handle_ajax_request']);
    }

    /**
     * Adds the admin page under Tools.
     */
    public function add_admin_page()
    {
        add_submenu_page(
            'tools.php',
            'Fastly Media Regen',
            'Fastly Media Regen',
            'manage_options',
            'fastly-media-regen',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueues JavaScript for AJAX handling.
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'tools_page_fastly-media-regen') {
            return;
        }

        wp_enqueue_script(
            'fastly-media-regen-js',
            plugin_dir_url(__FILE__) . '../js/fastly-media-regen.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('fastly-media-regen-js', 'fastlyMediaRegen', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('fastly_media_regen_nonce')
        ]);
    }

    /**
     * Renders the admin page.
     */
    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Fastly Media Regeneration</h1>
            <p>Click the button below to regenerate all images.</p>
            <button id="fastly-media-regen-btn" class="button button-primary">Regenerate Media</button>
            <p>&nbsp;</p>
            <p><strong>Output:</strong></p>
            <textarea id="fastly-media-regen-output" rows="10" cols="100" readonly></textarea>
        </div>
        <?php
    }

    /**
     * Handles AJAX request to run the regeneration.
     */
   /**
 * Handles AJAX request to run the regeneration.
 */
public function handle_ajax_request()
{
    check_ajax_referer('fastly_media_regen_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    // Run WP-CLI command via shell_exec (Make sure WP-CLI is available in system path)
    $command = 'wp fastlyio media regenerate --allow-root 2>&1';
    $output = shell_exec($command);

    if (!$output) {
        wp_send_json_error(['message' => 'WP-CLI command failed to execute.']);
    } else {
        wp_send_json_success(['output' => $output]);
    }
}

}

new FastlyMediaRegenAdmin();
