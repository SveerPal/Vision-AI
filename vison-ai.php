<?php
/**
 * Plugin Name: Vison AI
 * Plugin URI: https://wordpress.org/plugins/vison-ai/
 * Description: A plugin to create Vison AI endpoints for WordPress posts with admin settings for Token and Domain.
 * Version: 1.4
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Author: Yashvir Pal
 * Author URI: https://yashvirpal.com
 */

// Exit if accessed directly
namespace vison\visonai;

if (!defined('ABSPATH')) {
    exit;
}

class VISON_Admin
{
    public function __construct()
    {
        // Initialize hooks
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_head', [$this, 'visonai_add_script_to_head']);
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
            'Vison AI', // Menu title
            'manage_options', // Capability required
            'vison-ai-settings', // Menu slug
            [$this, 'settings_page_html'], // Callback function to display settings page
            'dashicons-admin-settings', // Icon for the menu
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
            <h1><?php esc_html_e('Vison AI Settings', 'vison-ai'); ?></h1>

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
        register_setting('vison_ai_settings', 'vison_ai_script', 'sanitize_custom_script');
        register_setting('vison_ai_settings', 'vison_ai_script_option', [
            'type' => 'array',
            'sanitize_callback' => 'vison_ai_sanitize_script_option',
            'default' => [],
        ]);

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
            'vison_ai_script',
            'Add Script',
            function () {
                $vison_ai_script = get_option('vison_ai_script', '');
                echo '<textarea name="vison_ai_script" class="regular-text">' . esc_attr($vison_ai_script) . '</textarea>';
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
    public function vison_ai_sanitize_script_option($input)
    {
        return array_map('sanitize_text_field', (array) $input);
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
            'permission_callback' => [$this, 'vison_ai_permission_check'],
        ]);

        // Retrieve a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'vison_ai_get_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'],
        ]);

        // Update a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'vison_ai_update_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'],
        ]);

        // Delete a post
        register_rest_route('vison-ai/v1', '/post/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'vison_ai_delete_post'],
            'permission_callback' => [$this, 'vison_ai_permission_check'],
        ]);
         // Get user list
         register_rest_route('vison-ai/v1', '/users', [
            'methods' => 'GET',
            'callback' => [$this, 'visonai_users_list'],
            'permission_callback' => [$this, 'visonai_permission_check'],
        ]);
    }

    /**
     * Permission Callback: Validate Token and Domain
     */
    public function vison_ai_permission_check($request)
    {
        
        $headers = $request->get_headers();
        $token = isset($headers['authorization'][0]) ? $headers['authorization'][0] : '';
        $referrer = isset($headers['referer'][0]) ? $headers['referer'][0] : '';

        $stored_token = get_option('vison_ai_token', '');
        $allowed_domain = get_option('vison_ai_domain', '');

        if ($stored_token && $token !== $stored_token) {
            return new WP_Error('unauthorized', 'Invalid API token.', ['status' => 401]);
        }

        if ($allowed_domain && stripos($referrer, $allowed_domain) === false) {
            return new WP_Error('forbidden', 'Requests from this domain are not allowed.', ['status' => 403]);
        }

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
        $visonai_script = get_option('vison_ai_script');

        if (in_array('All', (array) $visonai_script_option)) {
            echo wp_kses($visonai_script);
        } elseif (in_array('post', (array) $visonai_script_option) && is_single()) {
            echo wp_kses($visonai_script);
        } elseif (in_array('categories', (array) $visonai_script_option) && is_category()) {
            echo wp_kses($visonai_script);
        } elseif (in_array('page', (array) $visonai_script_option) && is_page()) {
            echo wp_kses($visonai_script);
        }
    }
    public function visonai_users_list($request) {
        $params = $request->get_params();
    
        $args = [
            'number' => isset($params['per_page']) ? (int) $params['per_page'] : -1,
            'paged'  => isset($params['page']) ? (int) $params['page'] : 1,
        ];
    
        // Correct the usage of WP_User_Query by prefixing with global namespace
        $user_query = new \WP_User_Query($args);  // Notice the backslash before WP_User_Query
        $users = $user_query->get_results();
    
        if (empty($users)) {
            return new WP_Error('no_users', 'No users found.', ['status' => 404]);
        }
    
        $response = array_map(function ($user) {
            return [
                'id'       => $user->ID,
                'username' => $user->user_login,
                'email'    => $user->user_email,
                'name'     => $user->display_name,
                'role'     => implode(', ', $user->roles),
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
