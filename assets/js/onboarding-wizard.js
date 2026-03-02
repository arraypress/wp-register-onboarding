/**
 * Onboarding Wizard Scripts
 *
 * Handles Select2 initialization, field dependencies,
 * navigation direction, and keyboard accessibility.
 *
 * @package ArrayPress\RegisterOnboarding
 */

(function ($) {
    'use strict';

    const form = document.querySelector('.onboarding-form');

    if (!form) {
        return;
    }

    const directionInput = form.querySelector('input[name="onboarding_direction"]');

    /* =========================================================================
     * NAVIGATION DIRECTION
     * ========================================================================= */

    const navButtons = form.querySelectorAll('button[name="onboarding_direction"]');

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (directionInput) {
                directionInput.value = btn.value;
            }
        });
    });

    /* =========================================================================
     * SELECT2 INITIALIZATION
     * ========================================================================= */

    const searchableSelects = form.querySelectorAll('.onboarding-select--searchable');

    searchableSelects.forEach(select => {
        const placeholder = select.getAttribute('data-placeholder') || '';

        $(select).select2({
            placeholder: placeholder,
            allowClear: !!placeholder,
            width: '100%',
            dropdownParent: $(select).closest('.onboarding-field__input')
        });
    });

    /* =========================================================================
     * FIELD DEPENDENCIES
     *
     * Reads the dependency map from onboardingWizard.depends (localized
     * from PHP) and watches source fields for changes.
     *
     * Each entry in the map looks like:
     * {
     *   field:    'test_mode',       // Source field key
     *   operator: '==',              // ==, !=, in, not_in
     *   value:    '1',               // Value to compare (or array for in/not_in)
     *   action:   'show',            // 'show' = show when true, 'swap' = swap attrs
     *   attrs:    { placeholder: 'pk_test_...', help: '...' },   // When condition met
     *   attrs_alt: { placeholder: 'pk_live_...', help: '...' }   // When condition not met
     * }
     * ========================================================================= */

    const dependsMap = (typeof onboardingWizard !== 'undefined' && onboardingWizard.depends)
        ? onboardingWizard.depends
        : {};

    /**
     * Get the current value of a source field
     */
    const getFieldValue = (fieldKey) => {
        // Toggle/checkbox
        const checkbox = form.querySelector(`input[name="${fieldKey}"][type="checkbox"]`);
        if (checkbox) {
            return checkbox.checked ? '1' : '0';
        }

        // Radio
        const radio = form.querySelector(`input[name="${fieldKey}"]:checked`);
        if (radio) {
            return radio.value;
        }

        // Select, text, etc.
        const input = form.querySelector(`[name="${fieldKey}"]`);
        if (input) {
            return input.value;
        }

        return '';
    };

    /**
     * Evaluate a dependency condition
     */
    const evaluateCondition = (dep) => {
        const currentValue = getFieldValue(dep.field);
        const targetValue = dep.value;

        switch (dep.operator || '==') {
            case '==':
                return String(currentValue) === String(targetValue);
            case '!=':
                return String(currentValue) !== String(targetValue);
            case 'in':
                return Array.isArray(targetValue) && targetValue.map(String).includes(String(currentValue));
            case 'not_in':
                return Array.isArray(targetValue) && !targetValue.map(String).includes(String(currentValue));
            default:
                return false;
        }
    };

    /**
     * Apply attribute overrides to a dependent field's elements
     */
    const applyAttrs = (fieldKey, attrs) => {
        if (!attrs) {
            return;
        }

        const wrapper = form.querySelector(`[data-depends="${fieldKey}"]`);
        if (!wrapper) {
            return;
        }

        // Placeholder
        if (attrs.placeholder !== undefined) {
            const input = wrapper.querySelector('.onboarding-input, .onboarding-textarea');
            if (input) {
                input.setAttribute('placeholder', attrs.placeholder);
            }
        }

        // Help text
        if (attrs.help !== undefined) {
            const helpEl = wrapper.querySelector('.onboarding-field__help');
            if (helpEl) {
                helpEl.textContent = attrs.help;
            }
        }

        // Label
        if (attrs.label !== undefined) {
            const labelEl = wrapper.querySelector('.onboarding-field__label');
            if (labelEl) {
                labelEl.textContent = attrs.label;
            }
        }
    };

    /**
     * Process all dependencies once
     */
    const processDependencies = () => {
        Object.keys(dependsMap).forEach(fieldKey => {
            const dep = dependsMap[fieldKey];
            const conditionMet = evaluateCondition(dep);
            const action = dep.action || 'show';

            const wrapper = form.querySelector(`[data-depends="${fieldKey}"]`);

            if (action === 'show') {
                // Show/hide the field
                if (wrapper) {
                    if (conditionMet) {
                        wrapper.classList.remove('onboarding-field--hidden');
                    } else {
                        wrapper.classList.add('onboarding-field--hidden');
                    }
                }
            }

            // Attribute swapping (works for both 'show' and 'swap' actions)
            if (dep.attrs || dep.attrs_alt) {
                if (conditionMet && dep.attrs) {
                    applyAttrs(fieldKey, dep.attrs);
                } else if (!conditionMet && dep.attrs_alt) {
                    applyAttrs(fieldKey, dep.attrs_alt);
                } else if (!conditionMet && !dep.attrs_alt) {
                    // Reset to defaults from data attributes
                    if (wrapper) {
                        const input = wrapper.querySelector('.onboarding-input, .onboarding-textarea');
                        if (input) {
                            const defaultPh = input.getAttribute('data-default-placeholder');
                            if (defaultPh !== null) {
                                input.setAttribute('placeholder', defaultPh);
                            }
                        }

                        const helpEl = wrapper.querySelector('.onboarding-field__help');
                        if (helpEl) {
                            const defaultHelp = helpEl.getAttribute('data-default-help');
                            if (defaultHelp !== null) {
                                helpEl.textContent = defaultHelp;
                            }
                        }
                    }
                }
            }
        });
    };

    /**
     * Collect all source field keys and bind change listeners
     */
    if (Object.keys(dependsMap).length > 0) {
        const sourceFields = new Set();

        Object.values(dependsMap).forEach(dep => {
            if (dep.field) {
                sourceFields.add(dep.field);
            }
        });

        sourceFields.forEach(fieldKey => {
            // Checkboxes/toggles
            const checkboxes = form.querySelectorAll(`input[name="${fieldKey}"][type="checkbox"]`);
            checkboxes.forEach(el => el.addEventListener('change', processDependencies));

            // Radios
            const radios = form.querySelectorAll(`input[name="${fieldKey}"][type="radio"]`);
            radios.forEach(el => el.addEventListener('change', processDependencies));

            // Selects (including Select2)
            const select = form.querySelector(`select[name="${fieldKey}"]`);
            if (select) {
                select.addEventListener('change', processDependencies);
                $(select).on('change', processDependencies);
            }

            // Text inputs
            const textInput = form.querySelector(`input[name="${fieldKey}"][type="text"], input[name="${fieldKey}"][type="email"], input[name="${fieldKey}"][type="url"], input[name="${fieldKey}"][type="number"]`);
            if (textInput) {
                textInput.addEventListener('input', processDependencies);
            }
        });

        // Initial pass
        processDependencies();
    }

    /* =========================================================================
     * FIELD FOCUS
     * ========================================================================= */

    const firstInput = form.querySelector('.onboarding-fields .onboarding-input, .onboarding-fields .onboarding-select:not(.onboarding-select--searchable)');

    if (firstInput) {
        setTimeout(() => {
            firstInput.focus();
        }, 100);
    }

    /* =========================================================================
     * ERROR SCROLL
     * ========================================================================= */

    const firstError = document.querySelector('.onboarding-errors');

    if (firstError) {
        firstError.scrollIntoView({behavior: 'smooth', block: 'center'});

        const errorField = firstError.querySelector('.onboarding-error');

        if (errorField) {
            const fieldKey = errorField.getAttribute('data-field');

            if (fieldKey && fieldKey !== '_step') {
                const field = document.getElementById('field-' + fieldKey);

                if (field) {
                    field.focus();
                }
            }
        }
    }

    /* =========================================================================
     * KEYBOARD SHORTCUTS
     * ========================================================================= */

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
            e.preventDefault();

            const nextBtn = form.querySelector('.onboarding-btn--next');

            if (nextBtn) {
                nextBtn.click();
            }
        }
    });

})(jQuery);
