<?php
/*
Plugin Name: CF7 Field Validator
Description: Custom validation tab in CF7 editor
Version: 1.0
*/

class CF7_Field_Validator
{
    public function __construct()
    {
        add_filter('wpcf7_editor_panels', [$this, 'add_validator_tab']);
        add_action('wpcf7_save_contact_form', [$this, 'save_validator_settings']);
        add_filter('wpcf7_validate', [$this, 'validate_fields'], 10, 2);
    }

    public function add_validator_tab($panels)
    {
        $panels['validator-panel'] = [
            'title' => 'Field Validator',
            'callback' => [$this, 'validator_panel_html']
        ];
        return $panels;
    }

    public function validator_panel_html($post)
    {
        // Get existing rules for this form
        $rules = get_post_meta($post->id(), 'validator_rules', true);
?>
        <h2>Field Validation Rules</h2>
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
                            </select>
                        </td>
                        <td>
                            <input type="text" name="validator_rules[${ruleCount}][value]" placeholder="Expected value" />
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
                </select>
            </td>
            <td>
                <input type="text"
                    name="validator_rules[<?php echo $index; ?>][value]"
                    value="<?php echo esc_attr($rule['value'] ?? ''); ?>"
                    placeholder="Expected value" />
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

    public function save_validator_settings($contact_form)
    {
        if (isset($_POST['validator_rules'])) {
            $rules = array_values(array_filter($_POST['validator_rules'], function ($rule) {
                return !empty($rule['field']) && !empty($rule['value']);
            }));
            update_post_meta($contact_form->id(), 'validator_rules', $rules);
        }
    }

    public function validate_fields($result, $tags)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return $result;

        $form = $submission->get_contact_form();
        $rules = get_post_meta($form->id(), 'validator_rules', true);

        if (!$rules) return $result;

        $posted_data = $submission->get_posted_data();

        foreach ($rules as $rule) {
            $field = $rule['field'];
            if (isset($posted_data[$field])) {
                $posted_value = $posted_data[$field];

                // Handle array values (like checkboxes)
                if (is_array($posted_value)) {
                    $posted_value = implode(',', $posted_value);
                }

                $is_invalid = false;
                if ($rule['operator'] === 'equals') {
                    $is_invalid = ($posted_value !== $rule['value']);
                } elseif ($rule['operator'] === 'not_equals') {
                    $is_invalid = ($posted_value === $rule['value']);
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
}

// Initialize plugin
new CF7_Field_Validator();
