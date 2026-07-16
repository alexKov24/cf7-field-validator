jQuery(document).ready(function($) {
    window.cf7FieldValidatorDebug = window.cf7FieldValidatorDebug || function(message, context) {
        if (!window.CF7FieldValidatorConfig || !window.CF7FieldValidatorConfig.debug) {
            return;
        }

        if (typeof context === 'undefined') {
            console.debug('[CF7 Field Validator]', message);
        } else {
            console.debug('[CF7 Field Validator]', message, context);
        }
    };

    const debugLog = window.cf7FieldValidatorDebug;
    const lengthOperators = ['length_more_than', 'length_less_than', 'length_is'];
    const numberOperators = ['number_less_than', 'number_more_than', 'number_equals'];

    debugLog('Admin validator initialized.', {
        formRules: $('#validator-rules tr').length,
        globalRules: $('#global-validator-rules tr').length
    });

    function ruleError(row) {
        const field = row.find('input[name$="[field]"]');
        const operator = row.find('select[name$="[operator]"]').val();
        const value = row.find('input[name$="[value]"]');

        field[0].setCustomValidity('');
        value[0].setCustomValidity('');

        if (/[\[\]]/.test(field.val())) {
            field[0].setCustomValidity('Field names cannot contain square brackets.');
            debugLog('Rule rejected: field name contains square brackets.', { field: field.val() });
            return field[0];
        }

        if (lengthOperators.includes(operator) && !/^\d+$/.test(value.val())) {
            value[0].setCustomValidity('Length rules require a non-negative whole number.');
            debugLog('Rule rejected: invalid length value.', { operator, value: value.val() });
            return value[0];
        }

        if (numberOperators.includes(operator) && !/^[+-]?(?:(?:\d+\.?\d*)|(?:\.\d+))(?:[eE][+-]?\d+)?$/.test(value.val())) {
            value[0].setCustomValidity('Number rules require a valid number.');
            debugLog('Rule rejected: invalid number value.', { operator, value: value.val() });
            return value[0];
        }

        if (operator === 'custom_regex') {
            try {
                const match = value.val().match(/^(.)([\s\S]*)\1([dgimsuvy]*)$/);
                if (!match) {
                    throw new Error('Invalid pattern');
                }
                new RegExp(match[2], match[3].replace(/[dgv]/g, ''));
            } catch (error) {
                value[0].setCustomValidity('Regex must be valid in both PHP PCRE and JavaScript.');
                debugLog('Rule rejected: invalid browser-compatible regex.', { value: value.val() });
                return value[0];
            }
        }

        return null;
    }

    $(document).on('submit', 'form', function(event) {
        let invalidInput = null;
        const rows = $(this).find('#validator-rules tr, #global-validator-rules tr');

        if (rows.length) {
            debugLog('Validating rules before admin form submission.', { ruleCount: rows.length });
        }

        rows.each(function() {
            if (!invalidInput) {
                invalidInput = ruleError($(this));
            }
        });

        if (invalidInput) {
            event.preventDefault();
            event.stopImmediatePropagation();
            debugLog('Admin form submission blocked by invalid rule.');
            invalidInput.reportValidity();
            return false;
        }

        if (rows.length) {
            debugLog('Admin rule validation passed; submission allowed.');
        }
    });

    // Form-specific rules (validator panel)
    let ruleCount = $('#validator-rules tr').length;

    $('#add-rule').on('click', function() {
        const template = `
        <tr>
            <td>
                <input type="text" name="validator_rules[${ruleCount}][field]" placeholder="Field name" />
            </td>
            <td>
                <select name="validator_rules[${ruleCount}][negate]">
                    <option value="no">Is</option>
                    <option value="yes">Is Not</option>
                </select>
            </td>
            <td>
                <select name="validator_rules[${ruleCount}][operator]">
                    <option value="equals">Equals</option>
                    <option value="contains">Contains</option>
                    <option value="length_more_than">Length More Than</option>
                    <option value="length_less_than">Length Less Than</option>
                    <option value="length_is">Length Is</option>
                    <option value="number_less_than">Number Less Than</option>
                    <option value="number_more_than">Number More Than</option>
                    <option value="number_equals">Number Equals</option>
                    <option value="custom_regex">Custom Regex</option>
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
        debugLog('Added form-specific rule row.', { ruleCount });
    });

    $(document).on('click', '.remove-rule', function() {
        $(this).closest('tr').remove();
        debugLog('Removed form-specific rule row.');
    });

    // Global rules (settings page)
    let globalRuleCount = $('#global-validator-rules tr').length;
    const globalOptionName = $('#global-validator-rules').data('option-name');

    $('#add-global-rule').on('click', function() {
        const template = `
        <tr>
            <td>
                <input type="text" name="${globalOptionName}[${globalRuleCount}][field]" placeholder="Field name" />
            </td>
            <td>
                <select name="${globalOptionName}[${globalRuleCount}][negate]">
                    <option value="no">Is</option>
                    <option value="yes">Is Not</option>
                </select>
            </td>
            <td>
                <select name="${globalOptionName}[${globalRuleCount}][operator]">
                    <option value="equals">Equals</option>
                    <option value="contains">Contains</option>
                    <option value="length_more_than">Length More Than</option>
                    <option value="length_less_than">Length Less Than</option>
                    <option value="length_is">Length Is</option>
                    <option value="number_less_than">Number Less Than</option>
                    <option value="number_more_than">Number More Than</option>
                    <option value="number_equals">Number Equals</option>
                    <option value="custom_regex">Custom Regex</option>
                </select>
            </td>
            <td>
                <input type="text" name="${globalOptionName}[${globalRuleCount}][value]" placeholder="Value or comma-separated list (red,green,blue)" />
            </td>
            <td>
                <input type="text" name="${globalOptionName}[${globalRuleCount}][message]" placeholder="Error message" />
            </td>
            <td>
                <button type="button" class="button remove-global-rule">Remove</button>
            </td>
        </tr>
        `;
        $('#global-validator-rules').append(template);
        globalRuleCount++;
        debugLog('Added global rule row.', { globalRuleCount });
    });

    $(document).on('click', '.remove-global-rule', function() {
        $(this).closest('tr').remove();
        debugLog('Removed global rule row.');
    });
});
