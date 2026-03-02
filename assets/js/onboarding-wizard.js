/**
 * Onboarding Wizard Scripts
 *
 * Handles navigation direction, toggle interactions,
 * and keyboard accessibility for the onboarding wizard.
 *
 * @package ArrayPress\RegisterOnboarding
 */

(function () {
    'use strict';

    /* =========================================================================
     * DOM REFERENCES
     * ========================================================================= */

    const form = document.querySelector('.onboarding-form');

    if (!form) {
        return;
    }

    const directionInput = form.querySelector('input[name="onboarding_direction"]');

    /* =========================================================================
     * NAVIGATION DIRECTION
     * ========================================================================= */

    /**
     * Handle navigation button clicks
     *
     * The back, skip, and next buttons all submit the same form but
     * set a different direction value. We intercept the click to update
     * the hidden input before the form submits.
     */
    const navButtons = form.querySelectorAll('button[name="onboarding_direction"]');

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (directionInput) {
                directionInput.value = btn.value;
            }
        });
    });

    /* =========================================================================
     * CHECKLIST TOGGLE ROWS
     * ========================================================================= */

    /**
     * Make the entire checklist row clickable
     *
     * Clicking anywhere on the row toggles the hidden checkbox,
     * which is visually represented by the toggle slider.
     */
    const checklistItems = document.querySelectorAll('.onboarding-checklist__item');

    checklistItems.forEach(item => {
        const checkbox = item.querySelector('.onboarding-checklist__input');

        if (!checkbox) {
            return;
        }

        // The label already handles click-to-toggle, but we add
        // visual feedback for the active state
        item.addEventListener('change', () => {
            if (checkbox.checked) {
                item.style.borderColor = '';
                item.style.background = '';
            }
        });
    });

    /* =========================================================================
     * FIELD FOCUS MANAGEMENT
     * ========================================================================= */

    /**
     * Auto-focus the first input field on fields steps
     */
    const firstInput = form.querySelector('.onboarding-fields .onboarding-input, .onboarding-fields .onboarding-select');

    if (firstInput) {
        // Delay to avoid interfering with page load
        setTimeout(() => {
            firstInput.focus();
        }, 100);
    }

    /* =========================================================================
     * ERROR SCROLL
     * ========================================================================= */

    /**
     * Scroll to first error on validation failure
     */
    const firstError = document.querySelector('.onboarding-errors');

    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Also highlight the corresponding field
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

    /**
     * Enter key submits the form (advances to next step)
     * unless focus is in a textarea
     */
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
            e.preventDefault();

            const nextBtn = form.querySelector('.onboarding-btn--next');

            if (nextBtn) {
                nextBtn.click();
            }
        }
    });
})();
