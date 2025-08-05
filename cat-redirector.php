<?php
/*
Plugin Name: Cat Redirector
Description: Creates a URL that redirects to the most recent post in a specified category.
Version: 1.2
Author: impshum
Author URI: https://recycledrobot.co.uk
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LatestPostRedirect {
    private $option_name = 'latest_post_redirect_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_rewrite_rule']);
        add_action('template_redirect', [$this, 'handle_redirect']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Cat Redirector',
            'Cat Redirector',
            'manage_options',
            'latest-post-redirect',
            [$this, 'settings_page'],
            'dashicons-pets',
            80                       
        );
    }

    public function register_settings() {
        register_setting('latest_post_redirect_group', $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'category' => 0,
                'redirect_slug' => 'latest',
                'cache_duration' => 3600
            ]
        ]);

        add_settings_section('main_section', 'Redirect Settings', [$this, 'settings_section_callback'], 'latest-post-redirect');

        add_settings_field('category', 'Select Category', [$this, 'category_field_callback'], 'latest-post-redirect', 'main_section');
        add_settings_field('redirect_slug', 'Redirect Slug', [$this, 'redirect_slug_field_callback'], 'latest-post-redirect', 'main_section');
        add_settings_field('cache_duration', 'Cache Duration (seconds)', [$this, 'cache_duration_field_callback'], 'latest-post-redirect', 'main_section');
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        $sanitized['category'] = absint($input['category'] ?? 0);
        $sanitized['redirect_slug'] = sanitize_title($input['redirect_slug'] ?? 'latest');
        $sanitized['cache_duration'] = absint($input['cache_duration'] ?? 3600);
        return $sanitized;
    }

    public function settings_section_callback() {
        echo '<p>Configure the URL that redirects to the most recent post in a category.</p>';
    }

    public function category_field_callback() {
        $options = get_option($this->option_name, []);
        $category = $options['category'] ?? 0;
        $categories = get_categories(['hide_empty' => false]);
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[category]">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat) : ?>
                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>>
                    <?php echo esc_html($cat->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the category for the most recent post.</p>
        <?php
    }

    public function redirect_slug_field_callback() {
        $options = get_option($this->option_name, []);
        $redirect_slug = $options['redirect_slug'] ?? 'latest';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[redirect_slug]" value="<?php echo esc_attr($redirect_slug); ?>" />
        <p class="description">The slug for the redirect URL (e.g., "latest" for yoursite.com/latest).</p>
        <?php
    }

    public function cache_duration_field_callback() {
        $options = get_option($this->option_name, []);
        $cache_duration = $options['cache_duration'] ?? 3600;
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" min="0" />
        <p class="description">Cache duration in seconds (0 to disable).</p>
        <?php
    }

    public function register_rewrite_rule() {
        $options = get_option($this->option_name, []);
        $slug = !empty($options['redirect_slug']) ? $options['redirect_slug'] : 'latest';
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?latest_post_redirect=1',
            'top'
        );
        error_log('Cat Redirector: Registered rewrite rule for slug: ' . $slug);
        flush_rewrite_rules(); // Temporary: force flush for testing
    }

    public function handle_redirect() {
        if (get_query_var('latest_post_redirect')) {
            $url = $this->get_latest_post_url();
            if ($url && $url !== 'No posts found.') {
                wp_redirect($url, 301);
                exit;
            } else {
                wp_redirect(home_url(), 302);
                exit;
            }
        }
    }

    public function get_latest_post_url() {
        $options = get_option($this->option_name, []);
        $category = $options['category'] ?? 0;
        $cache_duration = $options['cache_duration'] ?? 3600;
        $cache_key = 'latest_post_redirect_' . $category;
        $cached_url = get_transient($cache_key);

        if (false !== $cached_url && $cache_duration > 0) {
            return $cached_url;
        }

        $args = [
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        if ($category) {
            $args['cat'] = $category;
        }

        $query = new WP_Query($args);
        $url = '';

        if ($query->have_posts()) {
            $post = $query->posts[0];
            $url = get_permalink($post->ID);
        }

        wp_reset_postdata();

        if ($url && $cache_duration > 0) {
            set_transient($cache_key, $url, $cache_duration);
        }

        return $url ?: 'No posts found.';
    }

    public function settings_page() {
        $options = get_option($this->option_name, []);
        $redirect_url = home_url('/' . ($options['redirect_slug'] ?? 'latest'));
        ?>
        <div class="wrap">
            <h1>Cat Redirector</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('latest_post_redirect_group');
                do_settings_sections('latest-post-redirect');
                submit_button();
                ?>
            </form>
            <h2>Redirect URL</h2>
            <p><a href="<?php echo esc_url($redirect_url); ?>" target="_blank"><?php echo esc_html($redirect_url); ?></a></p>
            <p class="description">This URL redirects to the most recent post in the selected category.</p>
            <h2>Debug Info</h2>
            <?php
            $args = [
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            if (!empty($options['category'])) {
                $args['cat'] = $options['category'];
            }
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $post = $query->posts[0];
                echo '<p>Found post: ' . esc_html($post->post_title) . ' (ID: ' . $post->ID . ')</p>';
                echo '<p>Redirects to: ' . esc_url(get_permalink($post->ID)) . '</p>';
            } else {
                echo '<p>No posts found in the selected category. The redirect will go to the homepage.</p>';
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=latest-post-redirect') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

new LatestPostRedirect();

register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

add_filter('query_vars', function($vars) {
    $vars[] = 'latest_post_redirect';
    return $vars;
});
