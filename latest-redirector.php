<?php
/*
Plugin Name: Latest Redirector
Description: Redirects multiple chosen slugs to the latest post in a specified category or tag.
Version: 2.2
Author: impshum
License: GPL2
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin styles and scripts
 */
function lr_enqueue_admin_assets() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_lr-settings') {
        wp_enqueue_style(
            'lr-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            [],
            '2.2'
        );
        wp_enqueue_script(
            'lr-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'],
            '2.2',
            true
        );
        // Prepare category and tag options for JavaScript
        $categories = get_categories(['hide_empty' => false]);
        $tags = get_tags(['hide_empty' => false]);
        $category_options = '<option value="">Select a category</option>';
        foreach ($categories as $category) {
            $category_options .= '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
        }
        $tag_options = '<option value="">Select a tag</option>';
        foreach ($tags as $tag) {
            $tag_options .= '<option value="' . esc_attr($tag->slug) . '">' . esc_html($tag->name) . '</option>';
        }
        wp_localize_script(
            'lr-admin',
            'lrAjax',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'homeurl' => home_url('/'),
                'categoryOptions' => $category_options,
                'tagOptions' => $tag_options
            ]
        );
    }
}
add_action('admin_enqueue_scripts', 'lr_enqueue_admin_assets');

/**
 * Admin Settings Page (Top-Level Menu)
 */
function lr_register_settings() {
    add_menu_page(
        'Latest Redirector',
        'Latest Redirector',
        'manage_options',
        'lr-settings',
        'lr_settings_page',
        'dashicons-redo',
        80
    );
}
add_action('admin_menu', 'lr_register_settings');

/**
 * Register settings
 */
function lr_settings_init() {
    register_setting('lr_settings_group', 'lr_rules', [
        'sanitize_callback' => 'lr_sanitize_settings',
        'default' => [],
    ]);

    add_settings_section(
        'lr_main_section',
        'Redirect Rules',
        'lr_section_callback',
        'lr-settings'
    );
}
add_action('admin_init', 'lr_settings_init');

/**
 * Section callback for help text
 */
function lr_section_callback() {
    echo '<p style="max-width: 600px;">Easily set up redirects for custom URLs (slugs) to the latest post in a chosen category or tag:</p>
    <ul style="max-width: 600px; list-style-type: disc; margin-left: 20px;">
        <li>Enter a slug (e.g., <code>meow</code>) to create a custom URL like <code>yoursite.com/meow</code>.</li>
        <li>Choose <code>Category</code> or <code>Tag</code> from the dropdown and select a specific category or tag.</li>
        <li>View the redirect path (e.g., <code>/meow &rarr; latest post URL</code>) with clickable links to test.</li>
        <li>Use <strong>Add New Rule</strong> to create more redirects or <strong>Remove</strong> to delete a rule.</li>
        <li>To start over, remove all rules and save.</li>
    </ul>';
}

/**
 * Sanitize settings
 */
function lr_sanitize_settings($input) {
    $sanitized = [];
    if (!is_array($input)) {
        return $sanitized;
    }

    foreach ($input as $index => $rule) {
        $sanitized[$index] = [
            'slug' => sanitize_text_field($rule['slug'] ?? ''),
            'type' => in_array($rule['type'] ?? '', ['category', 'tag']) ? $rule['type'] : 'category',
            'category' => sanitize_text_field($rule['category'] ?? ''),
            'tag' => sanitize_text_field($rule['tag'] ?? ''),
        ];
    }
    return $sanitized;
}

/**
 * Settings page callback
 */
function lr_settings_page() {
    ?>
    <div class="wrap">
        <h1>Latest Redirector</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('lr_settings_group');
            ?>

            <?php
            lr_rules_field_callback(); // Rules directly under title
            do_settings_sections('lr-settings'); // Instructions below rules
            submit_button('Save Rules', 'primary', 'submit', true, ['style' => 'margin-top: 15px;']);
            ?>
        </form>
    </div>
    <?php
}

/**
 * Rules field callback
 */
function lr_rules_field_callback() {
    $rules = get_option('lr_rules', []);
    $categories = get_categories(['hide_empty' => false]);
    $tags = get_tags(['hide_empty' => false]);
    ?>
    <div id="lr-rules-container">
        <?php if (empty($rules)) : ?>
            <p class="lr-empty-message">No rules defined. Click "Add New Rule" to start.</p>
        <?php else : ?>
            <?php foreach ($rules as $index => $rule) : ?>
                <div class="lr-rule">
                    <div class="lr-rule-grid">
                        <p>
                            <label>Slug</label>
                            <input type="text" name="lr_rules[<?php echo esc_attr($index); ?>][slug]" value="<?php echo esc_attr($rule['slug']); ?>" placeholder="e.g., meow" class="lr-slug-input">
                        </p>
                        <p>
                            <label>Type</label>
                            <select name="lr_rules[<?php echo esc_attr($index); ?>][type]" class="lr-type-select">
                                <option value="category" <?php selected($rule['type'], 'category'); ?>>Category</option>
                                <option value="tag" <?php selected($rule['type'], 'tag'); ?>>Tag</option>
                            </select>
                        </p>
                        <p class="lr-category-field" style="<?php echo $rule['type'] === 'tag' ? 'display:none;' : ''; ?>">
                            <label>Category</label>
                            <select name="lr_rules[<?php echo esc_attr($index); ?>][category]" class="lr-category-select">
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($rule['category'], $category->slug); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="lr-tag-field" style="<?php echo $rule['type'] === 'category' ? 'display:none;' : ''; ?>">
                            <label>Tag</label>
                            <select name="lr_rules[<?php echo esc_attr($index); ?>][tag]" class="lr-tag-select">
                                <option value="">Select a tag</option>
                                <?php foreach ($tags as $tag) : ?>
                                    <option value="<?php echo esc_attr($tag->slug); ?>" <?php selected($rule['tag'], $tag->slug); ?>>
                                        <?php echo esc_html($tag->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <button type="button" class="button lr-remove-rule">Remove</button>
                        </p>
                    </div>
                    <p class="lr-redirect-flow
                    <?php
                    $flow_text = 'No redirect set';
                    $flow_class = ' disabled';
                    if ($rule['slug'] && $rule['type'] === 'category' && $rule['category']) {
                        $args = [
                            'post_type' => 'post',
                            'posts_per_page' => 1,
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'category',
                                    'field' => 'slug',
                                    'terms' => $rule['category'],
                                ],
                            ],
                        ];
                        $query = new WP_Query($args);
                        if ($query->have_posts()) {
                            $slug_url = esc_url(home_url('/' . $rule['slug']));
                            $post_url = esc_url(get_permalink($query->posts[0]->ID));
                            $flow_text = '<a href="' . $slug_url . '">/' . esc_html($rule['slug']) . '</a> &rarr; <a href="' . $post_url . '">' . $post_url . '</a>';
                            $flow_class = '';
                        } else {
                            $flow_text = '<a href="' . esc_url(home_url('/' . $rule['slug'])) . '">/' . esc_html($rule['slug']) . '</a> &rarr; (no posts found)';
                        }
                        wp_reset_postdata();
                    } elseif ($rule['slug'] && $rule['type'] === 'tag' && $rule['tag']) {
                        $args = [
                            'post_type' => 'post',
                            'posts_per_page' => 1,
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'post_tag',
                                    'field' => 'slug',
                                    'terms' => $rule['tag'],
                                ],
                            ],
                        ];
                        $query = new WP_Query($args);
                        if ($query->have_posts()) {
                            $slug_url = esc_url(home_url('/' . $rule['slug']));
                            $post_url = esc_url(get_permalink($query->posts[0]->ID));
                            $flow_text = '<a href="' . $slug_url . '">/' . esc_html($rule['slug']) . '</a> &rarr; <a href="' . $post_url . '">' . $post_url . '</a>';
                            $flow_class = '';
                        } else {
                            $flow_text = '<a href="' . esc_url(home_url('/' . $rule['slug'])) . '">/' . esc_html($rule['slug']) . '</a> &rarr; (no posts found)';
                        }
                        wp_reset_postdata();
                    }
                    echo esc_attr($flow_class);
                    ?>
                    "><?php echo $flow_text; ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="button lr-add-rule">Add New Rule</button>
    <?php
}

/**
 * AJAX handler for preview URL
 */
function lr_get_preview_url() {
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

    if (!$type || !$term || !in_array($type, ['category', 'tag'])) {
        wp_send_json_error();
        wp_die();
    }

    $taxonomy = $type === 'category' ? 'category' : 'post_tag';
    $args = [
        'post_type' => 'post',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => [
            [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $term,
            ],
        ],
    ];

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $url = get_permalink($query->posts[0]->ID);
        wp_send_json_success(['url' => $url]);
    } else {
        wp_send_json_error();
    }

    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_lr_get_preview_url', 'lr_get_preview_url');

/**
 * Handle redirection
 */
function lr_redirect() {
    $rules = get_option('lr_rules', []);
    if (empty($rules)) {
        return;
    }

    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    foreach ($rules as $rule) {
        $slug = trim($rule['slug'], '/');
        if ($slug && $request_uri === $slug) {
            $latest_post = null;

            if ($rule['type'] === 'category' && $rule['category']) {
                $args = [
                    'post_type' => 'post',
                    'posts_per_page' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'slug',
                            'terms' => $rule['category'],
                        ],
                    ],
                ];
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    $latest_post = $query->posts[0];
                }
                wp_reset_postdata();
            } elseif ($rule['type'] === 'tag' && $rule['tag']) {
                $args = [
                    'post_type' => 'post',
                    'posts_per_page' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'tax_query' => [
                        [
                            'taxonomy' => 'post_tag',
                            'field' => 'slug',
                            'terms' => $rule['tag'],
                        ],
                    ],
                ];
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    $latest_post = $query->posts[0];
                }
                wp_reset_postdata();
            }

            if ($latest_post) {
                $permalink = get_permalink($latest_post->ID);
                wp_redirect($permalink, 301);
                exit;
            }
        }
    }
}
add_action('template_redirect', 'lr_redirect');
