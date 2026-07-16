(function () {
    'use strict';

    const rulesByForm = window.CF7FieldValidatorRules || {};
    const numericValuePattern = /^[+-]?(?:(?:\d+\.?\d*)|(?:\.\d+))(?:[eE][+-]?\d+)?$/;

    function valuesForField(form, fieldName) {
        const controls = Array.from(form.elements).filter((element) => element.name === fieldName);
        if (!controls.length) {
            return null;
        }

        return controls
            .filter((element) => !['checkbox', 'radio'].includes(element.type) || element.checked)
            .map((element) => element.value)
            .join(',');
    }

    function parseRegex(pattern) {
        const match = pattern.match(/^(.)([\s\S]*)\1([dgimsuvy]*)$/);
        if (!match) {
            return null;
        }

        try {
            return new RegExp(match[2], match[3].replace(/[dgv]/g, ''));
        } catch (error) {
            return null;
        }
    }

    function matchesRule(value, rule) {
        const values = rule.value.includes(',') ? rule.value.split(',').map((item) => item.trim()) : [rule.value];

        switch (rule.operator) {
            case 'equals':
                return values.includes(value);
            case 'contains':
                return values.some((item) => value.includes(item));
            case 'length_more_than':
                return Array.from(value).length > Number(rule.value);
            case 'length_less_than':
                return Array.from(value).length < Number(rule.value);
            case 'length_is':
                return Array.from(value).length === Number(rule.value);
            case 'number_less_than':
                return numericValuePattern.test(value) && Number(value) < Number(rule.value);
            case 'number_more_than':
                return numericValuePattern.test(value) && Number(value) > Number(rule.value);
            case 'number_equals':
                return numericValuePattern.test(value) && Number(value) === Number(rule.value);
            case 'custom_regex': {
                const regex = parseRegex(rule.value);
                return regex ? regex.test(value) : true;
            }
            default:
                return false;
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.closest('.wpcf7')) {
            return;
        }

        const formIdControl = form.querySelector('input[name="_wpcf7"]');
        const rules = formIdControl ? rulesByForm[formIdControl.value] : null;
        if (!rules) {
            return;
        }

        for (const rule of rules) {
            const value = valuesForField(form, rule.field);
            if (value === null) {
                continue;
            }

            const normalizedRule = Object.assign({}, rule);
            if (normalizedRule.operator === 'not_equals') {
                normalizedRule.operator = 'equals';
                normalizedRule.negate = true;
            } else if (normalizedRule.operator === 'not_contains') {
                normalizedRule.operator = 'contains';
                normalizedRule.negate = true;
            }

            const matches = matchesRule(value, normalizedRule);
            const isInvalid = normalizedRule.negate ? matches : !matches;
            if (!isInvalid) {
                continue;
            }

            const field = Array.from(form.elements).find((element) => element.name === rule.field);
            const message = rule.message || `Invalid value for ${rule.field}`;

            event.preventDefault();
            event.stopImmediatePropagation();
            if (field) {
                field.setCustomValidity(message);
                field.reportValidity();
                const clearValidationMessage = function () {
                    field.setCustomValidity('');
                    field.removeEventListener('input', clearValidationMessage);
                    field.removeEventListener('change', clearValidationMessage);
                };
                field.addEventListener('input', clearValidationMessage);
                field.addEventListener('change', clearValidationMessage);
            }
            return;
        }
    }, true);
}());
