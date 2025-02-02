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
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes
 */
add_action('rest_api_init', function () {
    // Create a new post
    register_rest_route('vision-ai/v1', '/post', [
        'methods' => 'POST',
        'callback' => 'vision_ai_create_post',
        'permission_callback' => 'vision_ai_permission_check',
    ]);

    // Retrieve a post
    register_rest_route('vision-ai/v1', '/post/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'vision_ai_get_post',
        'permission_callback' => 'vision_ai_permission_check',
    ]);

    // Update a post
    register_rest_route('vision-ai/v1', '/post/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'vision_ai_update_post',
        'permission_callback' => 'vision_ai_permission_check',
    ]);

    // Delete a post
    register_rest_route('vision-ai/v1', '/post/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'vision_ai_delete_post',
        'permission_callback' => 'vision_ai_permission_check',
    ]);

    // Get user list
    register_rest_route('vision-ai/v1', '/users', [
        'methods' => 'GET',
        'callback' => 'usersList',
        'permission_callback' => 'vision_ai_permission_check',
    ]);
});

/**
 * Permission Callback: Validate Token and Domain
 */
if (!function_exists('vision_ai_permission_check')) {
    function vision_ai_permission_check($request)
    {
        $headers = $request->get_headers();
        $token = isset($headers['authorization'][0]) ? $headers['authorization'][0] : '';
        $referrer = isset($headers['referer'][0]) ? $headers['referer'][0] : '';

        $stored_token = get_option('vision_ai_token', '');
        $allowed_domain = get_option('vision_ai_domain', '');

        if ($stored_token && $token !== $stored_token) {
            return new WP_Error('unauthorized', 'Invalid API token.', ['status' => 401]);
        }

        if ($allowed_domain && stripos($referrer, $allowed_domain) === false) {
            return new WP_Error('forbidden', 'Requests from this domain are not allowed.', ['status' => 403]);
        }

        return true;
    }
}

/**
 * Create a new post
 */
if (!function_exists('vision_ai_create_post')) {
    function vision_ai_create_post($request)
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
}
/**
 * Retrieve a post
 */
if (!function_exists('vision_ai_get_post')) {
    function vision_ai_get_post($request)
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
}

/**
 * Update a post
 */
if (!function_exists('vision_ai_update_post')) {
    function vision_ai_update_post($request)
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
}
/**
 * Delete a post
 */
if (!function_exists('vision_ai_delete_post')) {
    function vision_ai_delete_post($request)
    {
        $post_id = (int) $request['id'];
        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return new WP_Error('post_deletion_failed', 'Failed to delete post', ['status' => 500]);
        }

        return rest_ensure_response(['id' => $post_id, 'message' => 'Post deleted successfully']);
    }
}
/**
 * Get all users
 */
if (!function_exists('usersList')) {
    function usersList($request)
    {
        $params = $request->get_params();

        $args = [
            'number' => isset($params['per_page']) ? (int) $params['per_page'] : -1,
            'paged' => isset($params['page']) ? (int) $params['page'] : 1,
        ];

        $user_query = new WP_User_Query($args);
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
/**
 * Admin menu for settings
 */
add_action('admin_menu', function () {
    add_menu_page(
        'Vison AI Settings',
        'Vison AI Settings',
        'manage_options',
        'vision-ai-settings',
        'vision_ai_settings_page',
        'dashicons-rest-api',
        100
    );
});

/**
 * Settings page content
 */
if (!function_exists('vision_ai_settings_page')) {
    function vision_ai_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Vision AI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('vision_ai_settings');
                do_settings_sections('vision-ai-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
if (!function_exists('vision_ai_sanitize_script_option')) {
    function vision_ai_sanitize_script_option($input)
    {
        if (!is_array($input)) {
            return [];
        }
        return array_map('sanitize_text_field', $input);
    }
}
/**
 * Register settings
 */
add_action('admin_init', function () {
    
    register_setting('vision_ai_settings', 'vision_ai_token', 'sanitize_text_field');
    register_setting('vision_ai_settings', 'vision_ai_domain', 'sanitize_custom_domain');
    register_setting('vision_ai_settings', 'vision_ai_script', 'sanitize_custom_script');
    // register_setting('vision_ai_settings', 'vision_ai_script_option', [
    //     'type' => 'array',
    //     'sanitize_callback' => function ($input) {
    //         return array_map('sanitize_text_field', (array) $input);
    //     },
    //     'default' => [],
    // ]);
    register_setting('vision_ai_settings', 'vision_ai_script_option', [
        'type' => 'array',  // Setting type
        'sanitize_callback' => 'vision_ai_sanitize_script_option',  // Use the named function for sanitization
        'default' => [],
    ]);

    add_settings_section(
        'vision_ai_main_section',
        'Main Settings',
        function () {
            echo '<p>Configure the settings below:</p>';
        },
        'vision-ai-settings'
    );

    add_settings_field(
        'vision_ai_token',
        'API Token',
        function () {
            $token = get_option('vision_ai_token', '');
            echo '<input type="text" name="vision_ai_token" value="' . esc_attr($token) . '" class="regular-text">';
        },
        'vision-ai-settings',
        'vision_ai_main_section'
    );

    add_settings_field(
        'vision_ai_domain',
        'Allowed Domain',
        function () {
            $domain = get_option('vision_ai_domain', '');
            echo '<input type="text" name="vision_ai_domain" value="' . esc_attr($domain) . '" class="regular-text" placeholder="e.g., https://example.com">';
        },
        'vision-ai-settings',
        'vision_ai_main_section'
    );

    add_settings_field(
        'vision_ai_script_option',
        'Select Option',
        function () {
            $vision_ai_script_option = get_option('vision_ai_script_option', []); // Fetch saved options or default to an empty array.
            $options = [
                'All' => 'All',
                'categories' => 'Categories',
                'post' => 'Post',
                'page' => 'Page',
            ];

            foreach ($options as $value => $label) {
                $checked = in_array($value, (array) $vision_ai_script_option) ? 'checked' : ''; // Check if the value is saved.
                echo '<label>';
                echo '<input type="checkbox" name="vision_ai_script_option[]" value="' . esc_attr($value) . '" ' . esc_attr($checked) . '>';
                echo esc_html($label);
                echo '</label><br>';
            }
        },
        'vision-ai-settings',
        'vision_ai_main_section'
    );


    add_settings_field(
        'vision_ai_script',
        'Add Script',
        function () {
            $vision_ai_script = get_option('vision_ai_script', '');
            echo '<textarea name="vision_ai_script" class="regular-text">' . esc_attr($vision_ai_script) . '</textarea>';
        },
        'vision-ai-settings',
        'vision_ai_main_section'
    );

});

add_action('wp_head', function () {
    // Retrieve the saved options for script placement
    $vision_ai_script_option = get_option('vision_ai_script_option', []);
    $vision_ai_script = get_option('vision_ai_script');

    // Check if "All" is selected, or any specific option is selected with the corresponding page type
    if (in_array('All', (array) $vision_ai_script_option)) {
        echo wp_kses($vision_ai_script);
    } elseif (in_array('post', (array) $vision_ai_script_option) && is_single()) {
        echo wp_kses($vision_ai_script);
    } elseif (in_array('categories', (array) $vision_ai_script_option) && is_category()) {
        echo wp_kses($vision_ai_script);
    } elseif (in_array('page', (array) $vision_ai_script_option) && is_page()) {
        echo wp_kses($vision_ai_script);
    }
});