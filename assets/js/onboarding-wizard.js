/**
 * Onboarding Wizard Scripts
 *
 * Handles Select2 initialization, field dependencies, sync step
 * integration, navigation direction, confetti, and keyboard accessibility.
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
     * Each field key maps to an ARRAY of dependency rule objects. This
     * allows a single field to depend on multiple source fields for
     * different purposes (e.g., visibility from one field, attribute
     * swapping from another).
     *
     * Each rule object looks like:
     * {
     *   field:     'test_mode',       // Source field key to watch
     *   operator:  '==',              // ==, !=, in, not_in
     *   value:     '1',               // Value to compare (or array for in/not_in)
     *   action:    'show',            // 'show' = toggle visibility, 'swap' = swap attrs only
     *   attrs:     { ... },           // Attributes to apply when condition is met
     *   attrs_alt: { ... }            // Attributes to apply when condition is NOT met
     * }
     *
     * Visibility logic: if ANY rule with action='show' evaluates to false,
     * the field is hidden. Attribute swapping is applied independently per
     * rule regardless of visibility.
     * ========================================================================= */

    const dependsMap = (typeof onboardingWizard !== 'undefined' && onboardingWizard.depends)
        ? onboardingWizard.depends
        : {};

    /**
     * Get the current value of a source field.
     *
     * Checks checkbox/toggle, radio, select, and text inputs
     * in that order, returning the first match found.
     *
     * @param {string} fieldKey The name attribute of the source field.
     * @returns {string} The current field value.
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
     * Evaluate a single dependency condition.
     *
     * Compares the current value of the source field against the
     * target value using the specified operator.
     *
     * @param {Object} dep The dependency rule object.
     * @returns {boolean} Whether the condition is met.
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
     * Apply attribute overrides to a dependent field's elements.
     *
     * Updates placeholder, help text, and/or label on the field
     * wrapper identified by the data-depends attribute.
     *
     * @param {string} fieldKey The dependent field key.
     * @param {Object} attrs    Object with optional placeholder, help, label keys.
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
     * Process all field dependencies.
     *
     * Iterates every field in the depends map, evaluates all of its
     * dependency rules, and applies visibility and attribute changes.
     *
     * Visibility: any 'show' rule that fails hides the field.
     * Attributes: each rule with attrs/attrs_alt is applied independently.
     */
    const processDependencies = () => {
        Object.keys(dependsMap).forEach(fieldKey => {
            const rules = dependsMap[fieldKey];
            const wrapper = form.querySelector(`[data-depends="${fieldKey}"]`);

            let isVisible = true;

            rules.forEach(dep => {
                const conditionMet = evaluateCondition(dep);
                const action = dep.action || 'show';

                // Visibility: any 'show' rule that fails hides the field
                if (action === 'show' && !conditionMet) {
                    isVisible = false;
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

            // Apply visibility after evaluating all rules
            if (wrapper) {
                if (isVisible) {
                    wrapper.classList.remove('onboarding-field--hidden');
                } else {
                    wrapper.classList.add('onboarding-field--hidden');
                }
            }
        });
    };

    /**
     * Collect all source field keys from the dependency map and
     * bind change listeners so dependencies re-evaluate on input.
     */
    if (Object.keys(dependsMap).length > 0) {
        const sourceFields = new Set();

        Object.values(dependsMap).forEach(rules => {
            rules.forEach(dep => {
                if (dep.field) {
                    sourceFields.add(dep.field);
                }
            });
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
     * SYNC STEP INTEGRATION
     *
     * When the current step is a sync step, the Continue button starts
     * disabled. It's enabled when the inline-sync library fires its
     * completion event. This ensures users complete the sync before
     * advancing (or they can skip if the step is skippable).
     * ========================================================================= */

    if (typeof onboardingWizard !== 'undefined' && onboardingWizard.syncStep) {
        $(document).on('inline-sync:complete', function (e, syncId, totals) {
            const nextBtn = form.querySelector('.onboarding-btn--next');

            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.classList.add('onboarding-btn--sync-ready');
            }

            // Hide the sync button on success (no failed items)
            if (!totals || !totals.failed) {
                const syncBtn = form.querySelector('.inline-sync-trigger');

                if (syncBtn) {
                    syncBtn.style.display = 'none';
                }
            }
        });
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
     * CONFETTI
     *
     * Lightweight canvas-based confetti burst. Triggered when the
     * localized config has confetti: true (set on complete steps).
     * ========================================================================= */

    if (window.onboardingWizard && window.onboardingWizard.confetti) {
        (function () {
            const canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:999999';
            document.body.appendChild(canvas);

            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;

            window.addEventListener('resize', () => {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
            });

            const colors = [
                '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b',
                '#10b981', '#3b82f6', '#ef4444', '#14b8a6'
            ];

            const particles = [];
            const count = 120;

            for (let i = 0; i < count; i++) {
                particles.push({
                    x: width * 0.5 + (Math.random() - 0.5) * width * 0.4,
                    y: height * 0.4,
                    vx: (Math.random() - 0.5) * 12,
                    vy: -(Math.random() * 10 + 4),
                    color: colors[Math.floor(Math.random() * colors.length)],
                    size: Math.random() * 6 + 3,
                    rotation: Math.random() * 360,
                    rotationSpeed: (Math.random() - 0.5) * 12,
                    opacity: 1,
                    gravity: 0.15 + Math.random() * 0.1,
                    drag: 0.98 + Math.random() * 0.015,
                    shape: Math.random() > 0.5 ? 'rect' : 'circle'
                });
            }

            let frame = 0;
            const maxFrames = 180;

            function animate() {
                frame++;

                if (frame > maxFrames) {
                    canvas.remove();
                    return;
                }

                ctx.clearRect(0, 0, width, height);

                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];

                    p.vx *= p.drag;
                    p.vy += p.gravity;
                    p.x += p.vx;
                    p.y += p.vy;
                    p.rotation += p.rotationSpeed;

                    // Fade out in the last third
                    if (frame > maxFrames * 0.66) {
                        p.opacity = Math.max(0, 1 - (frame - maxFrames * 0.66) / (maxFrames * 0.34));
                    }

                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rotation * Math.PI) / 180);
                    ctx.globalAlpha = p.opacity;
                    ctx.fillStyle = p.color;

                    if (p.shape === 'rect') {
                        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                    } else {
                        ctx.beginPath();
                        ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                        ctx.fill();
                    }

                    ctx.restore();
                }

                requestAnimationFrame(animate);
            }

            // Small delay so the page has a moment to render
            setTimeout(() => requestAnimationFrame(animate), 300);
        })();
    }

    /* =========================================================================
     * KEYBOARD SHORTCUTS
     * ========================================================================= */

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
            e.preventDefault();

            const nextBtn = form.querySelector('.onboarding-btn--next');

            if (nextBtn && !nextBtn.disabled) {
                nextBtn.click();
            }
        }
    });

})(jQuery);