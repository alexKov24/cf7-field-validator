<?php
/*
Plugin Name: CF7 Field Validator
Plugin URI: https://github.com/alexKov24/cf7-field-validator/tree/main
Description: Custom validation tab in CF7 editor with global settings support
Version: 1.0.2
Author: Alex Kovalev
Author URI: https://webchad.tech
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: cf7-field-validator
*/

class CF7_Field_Validator
{
    private $option_name = 'cf7_field_validator_global_rules';

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
            <p class="description">If checked, this form will also use the <a href="<?php echo admin_url('admin.php?page=cf7-validator-settings'); ?>">global validation rules</a> in addition to form-specific rules below.</p>
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

        <script>
            jQuery(document).ready(function($) {
                let ruleCount = $('#validator-rules tr').length;

                $('#add-rule').on('click', function() {
                    const template = `
                    <tr>
                        <td>
                            <input type="text" name="validator_rules[${ruleCount}][field]" placeholder="Field name" />
                        </td>
                        <td>
                            <select name="validator_rules[${ruleCount}][operator]">
                                <option value="equals">Equals</option>
                                <option value="not_equals">Not Equals</option>
                                <option value="contains">Contains</option>
                                <option value="not_contains">Not Contains</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="validator_rules[${ruleCount}][value]" placeholder="Value or comma-separated list (red,green,blue)" />
                        </td>
                        <td>
                            <input type="text" name="validator_rules[${ruleCount}][message]" placeholder="Error message" />
                        </td>
                        <td>
                            <button type="button" class="button remove-rule">Remove</button>
                        </td>
                    </tr>
                `;
                    $('#validator-rules').append(template);
                    ruleCount++;
                });

                $(document).on('click', '.remove-rule', function() {
                    $(this).closest('tr').remove();
                });
            });
        </script>
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
        // Save form-specific rules
        if (isset($_POST['validator_rules'])) {
            $rules = array_values(array_filter($_POST['validator_rules'], function ($rule) {
                return !empty($rule['field']) && !empty($rule['value']);
            }));
            update_post_meta($contact_form->id(), 'validator_rules', $rules);
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
                            $result->invalidate($tag, $rule['message'] ?: "Invalid value for $field");
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
        
        return array_values(array_filter($input, function ($rule) {
            return !empty($rule['field']) && !empty($rule['value']);
        }));
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
            
            <form method="post" action="options.php">
                <?php settings_fields('cf7_field_validator_settings'); ?>
                
                <h2>Global Validation Rules</h2>
                <fieldset>
                    <legend>Will allow submission only if:</legend>
                    <table class="form-table">
                        <tbody id="global-validator-rules">
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

        <script>
            jQuery(document).ready(function($) {
                let globalRuleCount = $('#global-validator-rules tr').length;

                $('#add-global-rule').on('click', function() {
                    const template = `
                    <tr>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[${globalRuleCount}][field]" placeholder="Field name" />
                        </td>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[${globalRuleCount}][operator]">
                                <option value="equals">Equals</option>
                                <option value="not_equals">Not Equals</option>
                                <option value="contains">Contains</option>
                                <option value="not_contains">Not Contains</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[${globalRuleCount}][value]" placeholder="Value or comma-separated list (red,green,blue)" />
                        </td>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[${globalRuleCount}][message]" placeholder="Error message" />
                        </td>
                        <td>
                            <button type="button" class="button remove-global-rule">Remove</button>
                        </td>
                    </tr>
                `;
                    $('#global-validator-rules').append(template);
                    globalRuleCount++;
                });

                $(document).on('click', '.remove-global-rule', function() {
                    $(this).closest('tr').remove();
                });
            });
        </script>
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
