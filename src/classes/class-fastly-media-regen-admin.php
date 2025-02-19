<?php
namespace FastlyIO\Admin;

use WP_CLI;
use WP_CLI_Command;

class FastlyMediaRegenAdmin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('network_admin_menu', [$this, 'add_network_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_fastly_media_regen', [$this, 'handle_ajax_request']);
    }

    /**
     * Adds the admin page in the correct location.
     */
    public function add_admin_page()
    {
        if (is_multisite()) {
            return; // Only add in the network admin if multisite
        }

        add_menu_page(
            'Fastly Media Regen',
            'Fastly Media Regen',
            'manage_options',
            'fastly-media-regen',
            [$this, 'render_admin_page'],
            'dashicons-images-alt2', // Media-related Dashicon
            25
        );
    }

    /**
     * Adds the network admin menu for multisite installations.
     */
    public function add_network_admin_page()
    {
        if (!is_multisite()) {
            return; // Only add in network admin if multisite
        }

        if (!current_user_can('manage_network')) {
            return; // Restrict to super admins
        }

        add_menu_page(
            'Fastly Media Regen',
            'Fastly Media Regen',
            'manage_network',
            'fastly-media-regen',
            [$this, 'render_admin_page'],
            'dashicons-images-alt2', // Media-related Dashicon
            25
        );
    }

    /**
     * Enqueues JavaScript for AJAX handling.
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_fastly-media-regen' && $hook !== 'settings_page_fastly-media-regen') {
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
            'nonce'    => wp_create_nonce('fastly_media_regen_nonce'),
            'site_url' => get_site_url(),
            'is_multisite' => is_multisite(),
            'sites' => is_multisite() ? get_sites(['fields' => 'id,url']) : []
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
            <?php if (is_multisite()) : ?>
                <label for="fastly-media-regen-sites">Select Subsites:</label>
                <select id="fastly-media-regen-sites" name="selected_sites" multiple style="width:100%; display:block; max-width: none;">
                    <?php foreach (get_sites(['fields' => 'ids']) as $site_id) : ?>
                        <?php $site_details = get_blog_details($site_id); ?>
                        <option value="<?php echo esc_attr($site_id); ?>">
                            <?php echo "(" . $site_id . ") " . esc_html($site_details->blogname . ' (' . $site_details->siteurl . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br />
            <?php endif; ?>
            <label for="image-batch-size">Image Batch Size (default - 50):</label>
            <input type="text" id="image-batch-size" size="5" value="50">
            <br />
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
    public function handle_ajax_request()
    {
        check_ajax_referer('fastly_media_regen_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
    
        $selected_sites = isset($_POST['selected_sites']) ? array_map('intval', $_POST['selected_sites']) : [];    
   
        $response = wp_remote_post(admin_url('admin-ajax.php?action=fastly_media_regen_background'), [
            'method'    => 'POST',
            'body'      => [
                'action'        => 'fastly_media_regen_background', 
                'selected_sites' => json_encode($selected_sites),
                'nonce'          => wp_create_nonce('fastly_media_regen_nonce') 
            ],
            'timeout'   => 0.01,
            'blocking'  => false,
            'headers'   => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
        
   

    }
    
    
}

new FastlyMediaRegenAdmin();
