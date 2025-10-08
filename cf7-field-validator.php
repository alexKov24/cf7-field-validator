<?php
/*
Plugin Name: CF7 Field Validator
Plugin URI: https://github.com/alexKov24/cf7-field-validator/tree/main
Description: Custom validation tab in CF7 editor with global settings support
Version: 1.0.3
Author: Alex Kovalev
Author URI: https://webchad.tech
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: cf7-field-validator
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class CF7_Field_Validator
{
    private $option_name = 'cf7_field_validator_global_rules';

    /**
     * Modify the constructor to include settings link
     */
    public function __construct()
    {
        // Form editor tabs
        add_filter('wpcf7_editor_panels', [$this, 'add_validator_tab']);
        add_action('wpcf7_save_contact_form', [$this, 'save_validator_settings']);

        // Validation
        add_filter('wpcf7_validate', [$this, 'validate_fields'], 10, 2);

        // Admin menu for global settings
        add_action('admin_menu', [$this, 'add_settings_page']);

        // Register settings
        add_action('admin_init', [$this, 'register_settings']);

        // Optionally add debug output
        add_action('wpcf7_before_send_mail', [$this, 'debug_validation_messages'], 10, 3);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);

        // Handle import/export actions
        add_action('admin_init', [$this, 'handle_import_export']);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=cf7-validator-settings')) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on CF7 form editor and our settings page
        if ($hook === 'toplevel_page_wpcf7' || $hook === 'contact_page_cf7-validator-settings') {
            wp_enqueue_script(
                'cf7-validator-admin',
                plugin_dir_url(__FILE__) . 'assets/cf7-validator-admin.js',
                ['jquery'],
                '1.0.3',
                true
            );
        }
    }
    
    /**
     * Debug validation messages (optional function for troubleshooting)
     */
    public function debug_validation_messages($contact_form, &$abort, $submission)
    {
        // Uncomment the line below for debugging
        // error_log('CF7 Field Validator: Validation messages - ' . print_r($submission->get_invalid_fields(), true));
    }

    /**
     * Add the validator tab to CF7 form editor
     */
    public function add_validator_tab($panels)
    {
        $panels['validator-panel'] = [
            'title' => 'Field Validator',
            'callback' => [$this, 'validator_panel_html']
        ];
        return $panels;
    }

    /**
     * Display the validator tab content in form editor
     */
    public function validator_panel_html($post)
    {
        // Get existing rules for this form
        $rules = get_post_meta($post->id(), 'validator_rules', true);
        
        // Get global rule state for this form
        $use_global_rules = get_post_meta($post->id(), 'use_global_validator_rules', true);
        $use_global_rules = $use_global_rules !== '' ? $use_global_rules : 'yes'; // Default to yes
?>
        <h2>Field Validation Rules</h2>
        
        <div class="global-rules-toggle">
            <label>
                <input type="checkbox" name="use_global_validator_rules" value="yes" <?php checked($use_global_rules, 'yes'); ?> />
                Apply global validation rules to this form
            </label>
            <p class="description">If checked, this form will also use the <a href="<?php echo esc_url(admin_url('admin.php?page=cf7-validator-settings')); ?>">global validation rules</a> in addition to form-specific rules below.</p>
        </div>
        
        <h3>Form-Specific Rules</h3>
        <fieldset>
            <legend>Will allow submission only if:</legend>
            <table class="form-table">
                <tbody id="validator-rules">
                    <?php
                    if ($rules) {
                        foreach ($rules as $index => $rule) {
                            $this->render_rule_row($index, $rule);
                        }
                    } else {
                        $this->render_rule_row(0);
                    }
                    ?>
                </tbody>
            </table>
            <p class="description">For multiple values, use comma-separated list (e.g., "red,green,blue"). The condition will be matched if ANY value in the list is matched.</p>
            <button type="button" class="button" id="add-rule">Add New Rule</button>
        </fieldset>
    <?php
    }

    /**
     * Render a single rule row
     */
    private function render_rule_row($index, $rule = null)
    {
    ?>
        <tr>
            <td>
                <input type="text"
                    name="validator_rules[<?php echo $index; ?>][field]"
                    value="<?php echo esc_attr($rule['field'] ?? ''); ?>"
                    placeholder="Field name" />
            </td>
            <td>
                <select name="validator_rules[<?php echo $index; ?>][operator]">
                    <option value="equals" <?php selected(($rule['operator'] ?? ''), 'equals'); ?>>Equals</option>
                    <option value="not_equals" <?php selected(($rule['operator'] ?? ''), 'not_equals'); ?>>Not Equals</option>
                    <option value="contains" <?php selected(($rule['operator'] ?? ''), 'contains'); ?>>Contains</option>
                    <option value="not_contains" <?php selected(($rule['operator'] ?? ''), 'not_contains'); ?>>Not Contains</option>
                </select>
            </td>
            <td>
                <input type="text"
                    name="validator_rules[<?php echo $index; ?>][value]"
                    value="<?php echo esc_attr($rule['value'] ?? ''); ?>"
                    placeholder="Value or comma-separated list (red,green,blue)" />
            </td>
            <td>
                <input type="text"
                    name="validator_rules[<?php echo $index; ?>][message]"
                    value="<?php echo esc_attr($rule['message'] ?? ''); ?>"
                    placeholder="Error message" />
            </td>
            <td>
                <button type="button" class="button remove-rule">Remove</button>
            </td>
        </tr>
<?php
    }

    /**
     * Save both validator settings and global rules toggle
     */
    public function save_validator_settings($contact_form)
    {
        // Check user capability
        if (!current_user_can('wpcf7_edit_contact_form', $contact_form->id())) {
            return;
        }

        // Save form-specific rules with sanitization
        if (isset($_POST['validator_rules'])) {
            $sanitized_rules = array();
            foreach ($_POST['validator_rules'] as $rule) {
                if (!empty($rule['field']) && !empty($rule['value'])) {
                    $sanitized_rules[] = array(
                        'field' => sanitize_text_field($rule['field']),
                        'operator' => in_array($rule['operator'], ['equals', 'not_equals', 'contains', 'not_contains'])
                            ? $rule['operator']
                            : 'equals',
                        'value' => sanitize_text_field($rule['value']),
                        'message' => sanitize_text_field($rule['message'] ?? '')
                    );
                }
            }
            update_post_meta($contact_form->id(), 'validator_rules', $sanitized_rules);
        }

        // Save global rules toggle
        $use_global_rules = isset($_POST['use_global_validator_rules']) ? 'yes' : 'no';
        update_post_meta($contact_form->id(), 'use_global_validator_rules', $use_global_rules);
    }

    /**
     * Validate fields based on both global and form-specific rules
     */
    public function validate_fields($result, $tags)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return $result;

        $form = $submission->get_contact_form();
        $form_rules = get_post_meta($form->id(), 'validator_rules', true) ?: [];
        
        // Check if global rules should be applied
        $use_global_rules = get_post_meta($form->id(), 'use_global_validator_rules', true);
        $global_rules = [];
        
        if ($use_global_rules !== 'no') {
            $global_rules = get_option($this->option_name, []);
        }
        
        // Combine form-specific and global rules
        $all_rules = array_merge($global_rules, $form_rules);
        
        if (empty($all_rules)) return $result;

        $posted_data = $submission->get_posted_data();

        foreach ($all_rules as $rule) {
            $field = $rule['field'];
            if (isset($posted_data[$field])) {
                $posted_value = $posted_data[$field];

                // Handle array values (like checkboxes)
                if (is_array($posted_value)) {
                    $posted_value = implode(',', $posted_value);
                }

                $is_invalid = false;
                
                // Convert rule value to array if it contains commas
                $rule_values = strpos($rule['value'], ',') !== false 
                    ? array_map('trim', explode(',', $rule['value'])) 
                    : [$rule['value']];
                
                if ($rule['operator'] === 'equals') {
                    // Check if posted value equals ANY of the values in the list
                    $matches_any = false;
                    foreach ($rule_values as $value) {
                        if ($posted_value === $value) {
                            $matches_any = true;
                            break;
                        }
                    }
                    $is_invalid = !$matches_any;
                } elseif ($rule['operator'] === 'not_equals') {
                    // Check if posted value equals ANY of the values in the list
                    // If it matches any, validation fails
                    foreach ($rule_values as $value) {
                        if ($posted_value === $value) {
                            $is_invalid = true;
                            break;
                        }
                    }
                } elseif ($rule['operator'] === 'contains') {
                    // Check if posted value contains ANY of the values in the list
                    $contains_any = false;
                    foreach ($rule_values as $value) {
                        if (strpos($posted_value, $value) !== false) {
                            $contains_any = true;
                            break;
                        }
                    }
                    $is_invalid = !$contains_any;
                } elseif ($rule['operator'] === 'not_contains') {
                    // Check if posted value contains ANY of the values in the list
                    // If it contains any, validation fails
                    foreach ($rule_values as $value) {
                        if (strpos($posted_value, $value) !== false) {
                            $is_invalid = true;
                            break;
                        }
                    }
                }

                if ($is_invalid) {
                    // Find the corresponding tag
                    foreach ($tags as $tag) {
                        if ($tag->name === $field) {
                            $error_message = !empty($rule['message'])
                                ? esc_html($rule['message'])
                                : sprintf("Invalid value for %s", esc_html($field));
                            $result->invalidate($tag, $error_message);
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'wpcf7', // Parent slug (Contact Form 7)
            'CF7 Field Validator Settings', // Page title
            'Field Validator', // Menu title
            'manage_options', // Capability
            'cf7-validator-settings', // Menu slug
            [$this, 'render_settings_page'] // Callback function
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(
            'cf7_field_validator_settings', // Option group
            $this->option_name, // Option name
            [$this, 'sanitize_global_rules'] // Sanitize callback
        );
    }
    
    /**
     * Sanitize global rules before saving
     */
    public function sanitize_global_rules($input)
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $rule) {
            if (!empty($rule['field']) && !empty($rule['value'])) {
                $sanitized[] = [
                    'field' => sanitize_text_field($rule['field']),
                    'operator' => in_array($rule['operator'], ['equals', 'not_equals', 'contains', 'not_contains'])
                        ? $rule['operator']
                        : 'equals',
                    'value' => sanitize_text_field($rule['value']),
                    'message' => sanitize_text_field($rule['message'] ?? '')
                ];
            }
        }

        return $sanitized;
    }
    
    /**
     * Handle import/export actions
     */
    public function handle_import_export()
    {
        // Handle export
        if (isset($_POST['cf7_validator_export']) && check_admin_referer('cf7_validator_export_nonce')) {
            // Check user capability
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            $settings = [
                'global_rules' => get_option($this->option_name, []),
                'version' => '1.0',
                'export_date' => current_time('mysql')
            ];

            $filename = 'cf7-validator-settings-' . date('Y-m-d') . '.json';

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
            echo wp_json_encode($settings, JSON_PRETTY_PRINT);
            exit;
        }

        // Handle import
        if (isset($_POST['cf7_validator_import']) && check_admin_referer('cf7_validator_import_nonce')) {
            // Check user capability
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                // Validate file size (max 1MB)
                $max_size = 1024 * 1024; // 1MB
                if ($_FILES['import_file']['size'] > $max_size) {
                    add_settings_error(
                        'cf7_validator_import',
                        'import_error',
                        'File size exceeds maximum allowed size (1MB).',
                        'error'
                    );
                    wp_safe_redirect(admin_url('admin.php?page=cf7-validator-settings'));
                    exit;
                }

                // Validate file extension
                $file_name = $_FILES['import_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if ($file_ext !== 'json') {
                    add_settings_error(
                        'cf7_validator_import',
                        'import_error',
                        'Invalid file type. Only JSON files are allowed.',
                        'error'
                    );
                    wp_safe_redirect(admin_url('admin.php?page=cf7-validator-settings'));
                    exit;
                }

                // Read and validate JSON
                $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
                $settings = json_decode($file_content, true);

                // Check for JSON errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    add_settings_error(
                        'cf7_validator_import',
                        'import_error',
                        'Invalid JSON format: ' . json_last_error_msg(),
                        'error'
                    );
                    wp_safe_redirect(admin_url('admin.php?page=cf7-validator-settings'));
                    exit;
                }

                // Validate structure and sanitize
                if ($settings && isset($settings['global_rules']) && is_array($settings['global_rules'])) {
                    // Sanitize imported rules before saving
                    $sanitized_rules = $this->sanitize_global_rules($settings['global_rules']);
                    update_option($this->option_name, $sanitized_rules);
                    add_settings_error(
                        'cf7_validator_import',
                        'import_success',
                        'Settings imported successfully!',
                        'success'
                    );
                } else {
                    add_settings_error(
                        'cf7_validator_import',
                        'import_error',
                        'Invalid import file format.',
                        'error'
                    );
                }

                wp_safe_redirect(admin_url('admin.php?page=cf7-validator-settings'));
                exit;
            }
        }
    }

    /**
     * Render the settings page
     */
    public function render_settings_page()
    {
        // Get global rules
        $global_rules = get_option($this->option_name, []);
    ?>
        <div class="wrap">
            <h1>CF7 Field Validator Global Settings</h1>
            <p>Define validation rules that will apply to all Contact Form 7 forms (unless disabled for specific forms).</p>

            <!-- Import/Export Section -->
            <div class="card" style="max-width: 800px; margin-bottom: 20px;">
                <h2>Import/Export Settings</h2>

                <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                    <!-- Export Form -->
                    <div style="flex: 1;">
                        <h3>Export Settings</h3>
                        <p>Download your current validation rules as a JSON file.</p>
                        <form method="post" action="">
                            <?php wp_nonce_field('cf7_validator_export_nonce'); ?>
                            <button type="submit" name="cf7_validator_export" class="button button-secondary">
                                Export Settings
                            </button>
                        </form>
                    </div>

                    <!-- Import Form -->
                    <div style="flex: 1;">
                        <h3>Import Settings</h3>
                        <p>Upload a previously exported JSON file to restore settings.</p>
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php wp_nonce_field('cf7_validator_import_nonce'); ?>
                            <input type="file" name="import_file" accept=".json" required style="margin-bottom: 10px;" />
                            <br>
                            <button type="submit" name="cf7_validator_import" class="button button-secondary">
                                Import Settings
                            </button>
                        </form>
                    </div>
                </div>
                <p class="description"><strong>Note:</strong> Importing will replace all current global validation rules.</p>
            </div>

            <?php settings_errors('cf7_validator_import'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('cf7_field_validator_settings'); ?>

                <h2>Global Validation Rules</h2>
                <fieldset>
                    <legend>Will allow submission only if:</legend>
                    <table class="form-table">
                        <tbody id="global-validator-rules" data-option-name="<?php echo esc_attr($this->option_name); ?>">
                            <?php
                            if ($global_rules) {
                                foreach ($global_rules as $index => $rule) {
                                    $this->render_global_rule_row($index, $rule);
                                }
                            } else {
                                $this->render_global_rule_row(0);
                            }
                            ?>
                        </tbody>
                    </table>
                    <p class="description">For "In List" and "Not In List" operators, use comma-separated values (e.g., "red,green,blue").</p>
            <button type="button" class="button" id="add-global-rule">Add New Rule</button>
                </fieldset>

                <?php submit_button('Save Global Rules'); ?>
            </form>
        </div>
    <?php
    }
    
    /**
     * Render a single global rule row
     */
    private function render_global_rule_row($index, $rule = null)
    {
    ?>
        <tr>
            <td>
                <input type="text"
                    name="<?php echo $this->option_name; ?>[<?php echo $index; ?>][field]"
                    value="<?php echo esc_attr($rule['field'] ?? ''); ?>"
                    placeholder="Field name" />
            </td>
            <td>
                <select name="<?php echo $this->option_name; ?>[<?php echo $index; ?>][operator]">
                    <option value="equals" <?php selected(($rule['operator'] ?? ''), 'equals'); ?>>Equals</option>
                    <option value="not_equals" <?php selected(($rule['operator'] ?? ''), 'not_equals'); ?>>Not Equals</option>
                    <option value="contains" <?php selected(($rule['operator'] ?? ''), 'contains'); ?>>Contains</option>
                    <option value="not_contains" <?php selected(($rule['operator'] ?? ''), 'not_contains'); ?>>Not Contains</option>
                </select>
            </td>
            <td>
                <input type="text"
                    name="<?php echo $this->option_name; ?>[<?php echo $index; ?>][value]"
                    value="<?php echo esc_attr($rule['value'] ?? ''); ?>"
                    placeholder="Value or comma-separated list (red,green,blue)" />
            </td>
            <td>
                <input type="text"
                    name="<?php echo $this->option_name; ?>[<?php echo $index; ?>][message]"
                    value="<?php echo esc_attr($rule['message'] ?? ''); ?>"
                    placeholder="Error message" />
            </td>
            <td>
                <button type="button" class="button remove-global-rule">Remove</button>
            </td>
        </tr>
<?php
    }
}

// Initialize plugin
new CF7_Field_Validator();
