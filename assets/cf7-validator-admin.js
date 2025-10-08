jQuery(document).ready(function($) {
    // Form-specific rules (validator panel)
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
                <select name="${globalOptionName}[${globalRuleCount}][operator]">
                    <option value="equals">Equals</option>
                    <option value="not_equals">Not Equals</option>
                    <option value="contains">Contains</option>
                    <option value="not_contains">Not Contains</option>
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
    });

    $(document).on('click', '.remove-global-rule', function() {
        $(this).closest('tr').remove();
    });
});
