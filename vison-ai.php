<?php
/**
 * Plugin Name: Vison AI
 * Plugin URI: https://wordpress.org/plugins/vison-ai/
 * Description: A plugin to create Vison AI endpoints for WordPress posts with admin settings for Token and Domain.
 * Version: 1.5
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Author: Yashvir Pal
 * Author URI: https://yashvirpal.com
 * Text Domain: visonai
 */

// Exit if accessed directly
namespace vison\visonai;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}
class VISON_Admin
{
    public function __construct()
    {
        // Initialize hooks
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'visonai_add_script_to_head']);
        add_action('script_loader_tag', [$this, 'visonai_add_async_attribute'], 10, 2);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add settings page to the admin menu
     */
    public function add_admin_menu()
    {
        // Add main menu page for the plugin
        add_menu_page(
            'Vison AI Settings', // Page title
            'Vison AI Settings', // Menu title
            'manage_options', // Capability required
            'vison-ai-settings', // Menu slug
            [$this, 'settings_page_html'], // Callback function to display settings page
            'dashicons-rest-api', // Icon for the menu
            100 // Position
        );
    }

    /**
     * Settings page content
     */
    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Vison AI Settings', 'visonai'); ?></h1>

            <form action="options.php" method="POST">
                <?php
                // Output settings fields
                settings_fields('vison_ai_settings');
                do_settings_sections('vison-ai-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings()
    {

        register_setting('vison_ai_settings', 'vison_ai_token', 'sanitize_text_field');
        register_setting('vison_ai_settings', 'vison_ai_domain', 'sanitize_custom_domain');
        register_setting('vison_ai_settings', 'vison_ai_analysis_url', 'sanitize_custom_domain');

        register_setting('vison_ai_settings', 'vison_ai_script_option', 'vison_ai_sanitize_checkbox_field');

        // Add settings section
        add_settings_section(
            'vison_ai_main_section',
            'Main Settings',
            function () {
                echo '<p>Configure the settings below:</p>';
            },
            'vison-ai-settings'
        );

        // Add API Token field
        add_settings_field(
            'vison_ai_token',
            'API Token',
            function () {
                $token = get_option('vison_ai_token', '');
                echo '<input type="text" name="vison_ai_token" value="' . esc_attr($token) . '" class="regular-text">';
            },
            'vison-ai-settings',
            'vison_ai_main_section'
        );

        // Add Allowed Domain field
        add_settings_field(
            'vison_ai_domain',
            'Allowed Domain',
            function () {
                $domain = get_option('vison_ai_domain', '');
                echo '<input type="text" name="vison_ai_domain" value="' . esc_attr($domain) . '" class="regular-text" placeholder="e.g., https://example.com">';
            },
            'vison-ai-settings',
            'vison_ai_main_section'
        );

        // Add Script Option field
        add_settings_field(
            'vison_ai_script_option',
            'Select Option',
            function () {
                $vison_ai_script_option = get_option('vison_ai_script_option', []);
                $options = [
                    'All' => 'All',
                    'categories' => 'Categories',
                    'post' => 'Post',
                    'page' => 'Page',
                ];

                foreach ($options as $value => $label) {
                    $checked = in_array($value, (array) $vison_ai_script_option) ? 'checked' : '';
                    echo '<label>';
                    echo '<input type="checkbox" name="vison_ai_script_option[]" value="' . esc_attr($value) . '" ' . esc_attr($checked) . '>';
                    echo esc_html($label);
                    echo '</label><br>';
                }
            },
            'vison-ai-settings',
            'vison_ai_main_section'
        );

        // Add Script field
        add_settings_field(
            'vison_ai_analysis_url',
            'Analysis Url',
            function () {
                $vison_ai_analysis_url = get_option('vison_ai_analysis_url', '');
                echo '<input type="text" name="vison_ai_analysis_url" value="' . esc_attr($vison_ai_analysis_url) . '" class="regular-text" placeholder="e.g., https://example.com?v=1234">';

            },
            'vison-ai-settings',
            'vison_ai_main_section'
        );
    }

    /**
     * Sanitize custom domain
     */
    public function sanitize_custom_domain($input)
    {
        return esc_url_raw($input);
    }

    /**
     * Sanitize custom script
     */
    public function sanitize_custom_script($input)
    {
        return wp_kses_post($input); // Allow only safe HTML tags
    }

    /**
     * Sanitize script options
     */

    public static function sanitize_checkbox_field($input)
    {
        // Make sure $input is an array
        if (!is_array($input)) {
            return [];
        }

        // Define valid checkbox options
        $valid_options = [
            'all',
            'categories',
            'post',
            'page', // These should match the values in your checkbox options
        ];

        // Sanitize each selected checkbox value
        $input = array_map(function ($item) use ($valid_options) {
            return in_array($item, $valid_options) ? sanitize_text_field($item) : '';
        }, $input);

        // Return the sanitized array
        return array_filter($input); // Filter out any empty values
    }


    /**
     * Register the custom REST API routes.
     */
    public function register_routes()
    {
        // Create a new post
        register_rest_route('vison-ai/v1', '/post', [
            'methods' => 'POST',
            'callback' => [$this, 'vison_ai_create_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'], // Correct method name
        ]);

        // Retrieve a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'vison_ai_get_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'], // Correct method name
        ]);

        // Update a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'vison_ai_update_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'], // Correct method name
        ]);

        // Delete a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'vison_ai_delete_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'], // Correct method name
        ]);

        // Get user list
        register_rest_route('vison-ai/v1', '/users', [
            'methods' => 'GET',
            'callback' => [$this, 'visonai_users_list'],
            'permission_callback' => [$this, 'vison_ai_permission_check'], // Correct method name
        ]);
    }


    /**
     * Permission Callback: Validate Token and Domain
     */
    public function vison_ai_permission_check($request)
    {
        // Retrieve headers from the request
        $headers = $request->get_headers();
        $token = isset($headers['authorization'][0]) ? $headers['authorization'][0] : '';
        $referrer = isset($headers['referer'][0]) ? $headers['referer'][0] : '';

        // Get the stored token and allowed domain from the settings
        $stored_token = get_option('vison_ai_token', '');
        $allowed_domain = get_option('vison_ai_domain', '');

        // Debug output to check the retrieved values (for development only, remove in production)
        // error_log("Stored Token: " . $stored_token); // This will log to your error_log file
        // error_log("Allowed Domain: " . $allowed_domain); // This will log to your error_log file

        // Check if token is blank
        if (empty($stored_token)) {
            return new WP_Error('missing_token', 'API token is missing in the settings.', ['status' => 400]);
        }

        // Check if provided token matches the stored token
        if ($token !== $stored_token) {
            return new WP_Error('unauthorized', 'Invalid API token.', ['status' => 401]);
        }

        // Check if domain is blank
        if (empty($allowed_domain)) {
            return new WP_Error('missing_domain', 'Allowed domain is missing in the settings.', ['status' => 400]);
        }

        // Check if referrer is allowed (matches stored domain)
        if (stripos($referrer, $allowed_domain) === false) {
            return new WP_Error('forbidden', 'Requests from this domain are not allowed.', ['status' => 403]);
        }

        // If all checks pass, allow the request
        return true;
    }


    /**
     * Create a new post
     */
    public function vison_ai_create_post($request)
    {
        $params = $request->get_json_params();

        if (empty($params['title']) || empty($params['content'])) {
            return new WP_Error('missing_fields', 'Title and Content are required.', ['status' => 400]);
        }

        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        if ($user_id && !get_user_by('ID', $user_id)) {
            return new WP_Error('invalid_user', 'The specified user does not exist.', ['status' => 400]);
        }

        $author_id = $user_id ?: get_current_user_id();

        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($params['title']),
            'post_content' => sanitize_textarea_field($params['content']),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $author_id,
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('post_creation_failed', 'Failed to create post', ['status' => 500]);
        }

        return rest_ensure_response([
            'id' => $post_id,
            'message' => 'Post created successfully',
            'author' => $author_id,
        ]);
    }

    /**
     * Retrieve a post
     */
    public function vison_ai_get_post($request)
    {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
        ]);
    }

    /**
     * Update a post
     */
    public function vison_ai_update_post($request)
    {
        $post_id = (int) $request['id'];
        $params = $request->get_json_params();

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => sanitize_text_field($params['title']),
            'post_content' => sanitize_textarea_field($params['content']),
        ], true);

        if (is_wp_error($updated)) {
            return new WP_Error('post_update_failed', 'Failed to update post', ['status' => 500]);
        }

        return rest_ensure_response(['id' => $post_id, 'message' => 'Post updated successfully']);
    }

    /**
     * Delete a post
     */
    public function vison_ai_delete_post($request)
    {
        $post_id = (int) $request['id'];
        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return new WP_Error('post_deletion_failed', 'Failed to delete post', ['status' => 500]);
        }

        return rest_ensure_response(['id' => $post_id, 'message' => 'Post deleted successfully']);
    }

    /**
     * Add custom script to the head
     */
    public function visonai_add_script_to_head()
    {
        $visonai_script_option = get_option('vison_ai_script_option', []);
        $vison_ai_analysis_url = get_option('vison_ai_analysis_url');

        if (in_array('All', (array) $visonai_script_option)) {
            if (!empty($vison_ai_analysis_url)) {
                wp_enqueue_script('vison-ai-analysis', esc_url($vison_ai_analysis_url), array(), 1.0, false);
            }
        } elseif (in_array('post', (array) $visonai_script_option) && is_single()) {
            if (!empty($vison_ai_analysis_url)) {
                wp_enqueue_script('vison-ai-analysis', esc_url($vison_ai_analysis_url), array(), 1.0, false);
            }

        } elseif (in_array('categories', (array) $visonai_script_option) && is_category()) {
            if (!empty($vison_ai_analysis_url)) {
                wp_enqueue_script('vison-ai-analysis', esc_url($vison_ai_analysis_url), array(), 1.0, false);
            }

        } elseif (in_array('page', (array) $visonai_script_option) && is_page()) {
            if (!empty($vison_ai_analysis_url)) {
                wp_enqueue_script('vison-ai-analysis', esc_url($vison_ai_analysis_url), array(), 1.0, false);
            }
        }
    }
    public function visonai_add_async_attribute($tag, $handle)
    {
        if ('vison-ai-analysis' === $handle) {
            return str_replace('<script ', '<script async ', $tag);
        }
        return $tag;
    }

    public function visonai_users_list($request)
    {
        $params = $request->get_params();

        $args = [
            'number' => isset($params['per_page']) ? (int) $params['per_page'] : -1,
            'paged' => isset($params['page']) ? (int) $params['page'] : 1,
        ];

        // Correct the usage of WP_User_Query by prefixing with global namespace
        $user_query = new \WP_User_Query($args);  // Notice the backslash before WP_User_Query
        $users = $user_query->get_results();

        if (empty($users)) {
            return new WP_Error('no_users', 'No users found.', ['status' => 404]);
        }

        $response = array_map(function ($user) {
            return [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'name' => $user->display_name,
                'role' => implode(', ', $user->roles),
            ];
        }, $users);

        return rest_ensure_response([
            'users' => $response,
            'total' => $user_query->get_total(),
            'pages' => ceil($user_query->get_total() / $args['number']),
        ]);
    }

}

// Initialize the class
new VISON_Admin();
