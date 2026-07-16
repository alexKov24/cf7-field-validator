(function () {
    'use strict';

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
    const rulesByForm = (window.CF7FieldValidatorConfig || {}).rules || {};
    const numericValuePattern = /^[+-]?(?:(?:\d+\.?\d*)|(?:\.\d+))(?:[eE][+-]?\d+)?$/;

    debugLog('Frontend validator initialized.', { configuredForms: Object.keys(rulesByForm).length });

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
            debugLog('Regex is not JavaScript-compatible; PHP remains authoritative.', { pattern });
            return null;
        }

        try {
            return new RegExp(match[2], match[3].replace(/[dgv]/g, ''));
        } catch (error) {
            debugLog('Regex could not be compiled by the browser; PHP remains authoritative.', { pattern });
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
            debugLog('No validator rules configured for submitted form.');
            return;
        }

        debugLog('Validating submitted form.', { formId: formIdControl.value, ruleCount: rules.length });

        for (const rule of rules) {
            const value = valuesForField(form, rule.field);
            if (value === null) {
                debugLog('Configured field is not present in this form.', { field: rule.field });
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
            debugLog('Evaluated rule.', { field: rule.field, operator: normalizedRule.operator, negate: normalizedRule.negate, matches });
            if (!isInvalid) {
                continue;
            }

            const field = Array.from(form.elements).find((element) => element.name === rule.field);
            const message = rule.message || `Invalid value for ${rule.field}`;

            event.preventDefault();
            event.stopImmediatePropagation();
            debugLog('Form submission blocked by rule.', { field: rule.field, message });
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
