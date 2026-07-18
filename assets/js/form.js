/**
 * Custom Form Handler - Multi-Step-Formular JavaScript
 * Kein jQuery; unterstützt mehrere Formularinstanzen pro Seite.
 */
(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    function initAll() {
        var wraps = Array.from(document.querySelectorAll('.cfh-form-container[data-cfh-form]'));
        if (wraps.length === 0) return;

        wraps.forEach(function (wrap, index) {
            initForm(wrap, index);
        });
    }

    function initForm(wrap, instanceIndex) {
        var form = wrap.querySelector('.cfh-multi-step-form');
        var steps = Array.from(wrap.querySelectorAll('.cfh-step'));
        var fill = wrap.querySelector('.cfh-progress-fill');
        var counter = wrap.querySelector('.cfh-step-counter');
        var errorBox = wrap.querySelector('.cfh-error-msg');
        var modal = wrap.querySelector('.cfh-modal');
        var modalMessage = wrap.querySelector('.cfh-modal__message');
        var modalCloseButtons = Array.from(wrap.querySelectorAll('[data-cfh-modal-close]'));
        var submitBtn = form ? form.querySelector('.cfh-btn--submit') : null;
        var formType = wrap.dataset.formType || 'window';
        var sessionKey = buildSessionKey(formType, instanceIndex);
        var inlineErrorMessages = parseErrorMessages(wrap.dataset.inlineErrorMessages);
        var popupErrorMessages = parseErrorMessages(wrap.dataset.popupErrorMessages);
        var errorCodeByField = {
            windowMaterial: 'invalid_material',
            propertyType: 'invalid_property',
            windowCount: 'invalid_count',
            buildingType: 'invalid_building_type',
            ownershipStatus: 'invalid_ownership_status',
            windowProjectType: 'invalid_window_project_type',
            location: 'invalid_location',
            name: 'invalid_name',
            email: 'invalid_email',
            phone: 'invalid_phone',
            gdpr_consent: 'gdpr_missing'
        };

        if (!form || steps.length === 0) return;

        var currentIndex = 0;
        var previousActiveElement = null;
        var autoAdvanceTimer = null;

        populateTrackingFields();
        restoreState();
        updateSummary();

        steps.forEach(function (step) {
            syncNextButton(step);
        });

        dispatchFunnelEvent('form_view');

        form.addEventListener('click', function (e) {
            var target = e.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.classList.contains('cfh-btn--next')) {
                advance();
            } else if (target.classList.contains('cfh-btn--prev')) {
                retreat();
            }
        });

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.matches('input[type="radio"], input[type="checkbox"]')) {
                var step = target.closest('.cfh-step');
                if (step) syncNextButton(step);
                clearFieldError(target.name);
                clearInvalidState(target);
            }

            if (target.matches('input[type="radio"]')) {
                var activeStep = target.closest('.cfh-step');
                scheduleAutoAdvance(activeStep);
            }

            saveState();
            updateSummary();
        });

        form.addEventListener('input', function (e) {
            var target = e.target;
            if (target instanceof HTMLElement && target.name && isFieldVisiblyValid(target)) {
                clearFieldError(target.name);
                clearInvalidState(target);
            }

            saveState();
            updateSummary();
        });

        form.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;

            var active = steps[currentIndex];
            var hasRadios = active.querySelectorAll('input[type="radio"]').length > 0;
            var activeEl = document.activeElement;
            var isTextField = activeEl && ['text', 'email', 'tel'].includes(activeEl.type || '');

            if (hasRadios && !isTextField) {
                e.preventDefault();
                advance();
            }
        });

        form.addEventListener('submit', function (e) {
            if (!validateStep(currentIndex)) {
                e.preventDefault();
                dispatchFunnelEvent('validation_error');
                showError('Bitte füllen Sie alle Pflichtfelder korrekt aus.');
                return;
            }

            hideError();
            clearState();
            dispatchFunnelEvent('form_submit');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Wird gesendet...';
            }
        });

        modalCloseButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', handleDocumentKeydown);

        function advance() {
            clearAutoAdvance();

            if (!validateStep(currentIndex)) {
                dispatchFunnelEvent('validation_error');
                showError('Bitte wählen Sie eine Option oder füllen Sie alle Pflichtfelder korrekt aus.');
                return;
            }

            hideError();
            dispatchFunnelEvent('step_complete');
            steps[currentIndex].classList.remove('active');
            currentIndex = Math.min(currentIndex + 1, steps.length - 1);
            steps[currentIndex].classList.add('active');
            syncNextButton(steps[currentIndex]);
            focusFirstField(steps[currentIndex]);
            updateUI();
            updateSummary();
            saveState();
        }

        function retreat() {
            clearAutoAdvance();
            hideError();
            dispatchFunnelEvent('step_back');
            steps[currentIndex].classList.remove('active');
            currentIndex = Math.max(currentIndex - 1, 0);
            steps[currentIndex].classList.add('active');
            updateUI();
            updateSummary();
            saveState();
        }

        function validateStep(index) {
            var step = steps[index];
            var radioGroups = {};
            var fields = step.querySelectorAll('input, select, textarea');
            var valid = true;

            fields.forEach(function (field) {
                if (!field.name || field.type === 'hidden') return;

                if (field.type === 'radio') {
                    if (field.required) {
                        radioGroups[field.name] = radioGroups[field.name] || [];
                        radioGroups[field.name].push(field);
                    }
                    return;
                }

                var shouldValidate = field.required || field.value !== '';
                if (!shouldValidate) {
                    clearFieldError(field.name);
                    clearInvalidState(field);
                    return;
                }

                if (!field.checkValidity()) {
                    valid = false;
                    markInvalidState(field);
                    showFieldError(field.name, getValidationMessage(field.name, field.validationMessage));
                } else {
                    clearInvalidState(field);
                    clearFieldError(field.name);
                }
            });

            Object.keys(radioGroups).forEach(function (name) {
                var group = radioGroups[name];
                var anyChecked = group.some(function (radio) {
                    return radio.checked;
                });

                if (!anyChecked) {
                    valid = false;
                    markInvalidState(group[0]);
                    showFieldError(name, getValidationMessage(name, 'Bitte wählen Sie eine Option.'));
                } else {
                    clearInvalidState(group[0]);
                    clearFieldError(name);
                }
            });

            return valid;
        }

        function updateUI() {
            var total = steps.length;
            var humanIndex = currentIndex + 1;
            var pct = (humanIndex / total) * 100;

            if (fill) {
                fill.style.width = pct + '%';
                var bar = fill.closest('[role="progressbar"]');
                if (bar) bar.setAttribute('aria-valuenow', String(Math.round(pct)));
            }

            if (counter) {
                counter.textContent = 'Schritt ' + humanIndex + ' von ' + total;
            }
        }

        function scheduleAutoAdvance(step) {
            clearAutoAdvance();

            if (!step || steps[currentIndex] !== step || currentIndex >= steps.length - 1) return;
            if (!isAutoAdvanceStep(step)) return;

            autoAdvanceTimer = window.setTimeout(function () {
                if (steps[currentIndex] !== step) return;
                if (validateStep(currentIndex)) {
                    advance();
                }
            }, 260);
        }

        function clearAutoAdvance() {
            if (!autoAdvanceTimer) return;
            window.clearTimeout(autoAdvanceTimer);
            autoAdvanceTimer = null;
        }

        function isAutoAdvanceStep(step) {
            var requiredRadios = Array.from(step.querySelectorAll('input[type="radio"][required]'));
            if (requiredRadios.length === 0) return false;
            if (!areRequiredRadioGroupsComplete(step)) return false;

            var otherRequired = Array.from(step.querySelectorAll('input[required], select[required], textarea[required]')).filter(function (field) {
                return field.type !== 'radio';
            });

            return otherRequired.every(function (field) {
                return field.checkValidity();
            });
        }

        function focusFirstField(stepEl) {
            var selector = 'input:not([type="hidden"]):not([tabindex="-1"]), select, textarea, button:not([disabled])';
            var first = stepEl.querySelector(selector);
            if (first) {
                setTimeout(function () {
                    first.focus();
                }, 50);
            }
        }

        function syncNextButton(stepEl) {
            var nextBtn = stepEl.querySelector('.cfh-btn--next');
            if (!nextBtn) return;

            var radios = stepEl.querySelectorAll('input[type="radio"][required]');
            if (radios.length === 0) {
                nextBtn.disabled = false;
                return;
            }

            var complete = areRequiredRadioGroupsComplete(stepEl);
            nextBtn.disabled = !complete;
            nextBtn.title = complete ? '' : 'Bitte wählen Sie eine Option';
        }

        function areRequiredRadioGroupsComplete(stepEl) {
            var groups = {};
            Array.from(stepEl.querySelectorAll('input[type="radio"][required]')).forEach(function (radio) {
                groups[radio.name] = groups[radio.name] || [];
                groups[radio.name].push(radio);
            });

            return Object.keys(groups).every(function (name) {
                return groups[name].some(function (radio) {
                    return radio.checked;
                });
            });
        }

        function showFieldError(name, message) {
            if (!name) return;

            var error = wrap.querySelector('[data-cfh-error-for="' + cssEscape(name) + '"]');
            if (!error) return;

            error.textContent = message;
            error.style.display = 'block';
        }

        function clearFieldError(name) {
            if (!name) return;

            var error = wrap.querySelector('[data-cfh-error-for="' + cssEscape(name) + '"]');
            if (!error) return;

            error.textContent = '';
            error.style.display = 'none';
        }

        function getValidationMessage(name, fallback) {
            var code = getErrorCodeForField(name);
            return inlineErrorMessages[code] || fallback || 'Bitte prüfen Sie dieses Feld.';
        }

        function showReturnFieldError(errorCode) {
            var fieldName = '';

            Object.keys(errorCodeByField).some(function (name) {
                if (errorCodeByField[name] === errorCode) {
                    fieldName = name;
                    return true;
                }
                return false;
            });

            if (!fieldName && formType === 'energy_funding' && errorCode === 'invalid_energy_window_count') {
                fieldName = 'windowCount';
            }

            if (fieldName) {
                goToFieldStep(fieldName);
                markInvalidState(form.querySelector('[name="' + cssEscape(fieldName) + '"]'));
                showFieldError(fieldName, inlineErrorMessages[errorCode]);
            }
        }

        function getErrorCodeForField(name) {
            if (formType === 'energy_funding' && name === 'windowCount') {
                return 'invalid_energy_window_count';
            }

            return errorCodeByField[name] || '';
        }

        function goToFieldStep(fieldName) {
            var field = form.querySelector('[name="' + cssEscape(fieldName) + '"]');
            var step = field ? field.closest('.cfh-step') : null;
            var targetIndex = step ? steps.indexOf(step) : -1;

            if (targetIndex < 0 || targetIndex === currentIndex) return;

            steps[currentIndex].classList.remove('active');
            currentIndex = targetIndex;
            steps[currentIndex].classList.add('active');
            syncNextButton(steps[currentIndex]);
            updateUI();
            updateSummary();
            focusFirstField(steps[currentIndex]);
        }

        function markInvalidState(field) {
            var target = getInvalidStateTarget(field);
            if (target) target.classList.add('cfh-invalid');
        }

        function clearInvalidState(field) {
            var target = getInvalidStateTarget(field);
            if (target) target.classList.remove('cfh-invalid');
        }

        function getInvalidStateTarget(field) {
            if (!field) return null;

            if (field.type === 'radio') {
                return field.closest('.cfh-btn-group');
            }

            if (field.type === 'checkbox') {
                return field.closest('.cfh-checkbox-label');
            }

            return field;
        }

        function isFieldVisiblyValid(field) {
            if (!field || !field.name || field.type === 'hidden') return true;
            if (field.type === 'radio') {
                return Array.from(form.querySelectorAll('input[type="radio"][name="' + cssEscape(field.name) + '"]')).some(function (radio) {
                    return radio.checked;
                });
            }
            if (!field.required && field.value === '') return true;
            return field.checkValidity();
        }

        function updateSummary() {
            var summaryValues = Array.from(wrap.querySelectorAll('[data-cfh-summary-value]'));
            if (summaryValues.length === 0) return;

            summaryValues.forEach(function (item) {
                var name = item.getAttribute('data-cfh-summary-value') || '';
                item.textContent = getDisplayValue(name) || '-';
            });
        }

        function getDisplayValue(name) {
            if (!name) return '';

            var checked = form.querySelector('input[type="radio"][name="' + cssEscape(name) + '"]:checked');
            if (checked) {
                return checked.getAttribute('data-cfh-label') || checked.value;
            }

            var field = form.querySelector('[name="' + cssEscape(name) + '"]');
            if (!field || field.type === 'hidden') return '';

            if (field.type === 'checkbox') {
                return field.checked ? 'Ja' : '';
            }

            return field.value || '';
        }

        function populateTrackingFields() {
            var params = new URLSearchParams(window.location.search);
            var trackingValues = {
                landingPage: buildCleanPageUrl(),
                referrer: document.referrer || '',
                utm_source: params.get('utm_source') || '',
                utm_medium: params.get('utm_medium') || '',
                utm_campaign: params.get('utm_campaign') || '',
                utm_term: params.get('utm_term') || '',
                utm_content: params.get('utm_content') || '',
                gclid: params.get('gclid') || '',
                fbclid: params.get('fbclid') || ''
            };

            Object.keys(trackingValues).forEach(function (name) {
                var field = form.querySelector('input[data-cfh-tracking="' + cssEscape(name) + '"]');
                if (field) field.value = trackingValues[name];
            });
        }

        function buildCleanPageUrl() {
            var cleanedParams = new URLSearchParams(window.location.search);
            cleanedParams.delete('cfh_error');
            cleanedParams.delete('cfh_form_type');

            var nextQuery = cleanedParams.toString();
            return window.location.origin + window.location.pathname + (nextQuery ? '?' + nextQuery : '') + window.location.hash;
        }

        function showError(msg) {
            if (!errorBox) return;
            errorBox.textContent = msg;
            errorBox.style.display = 'block';
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideError() {
            if (!errorBox) return;
            errorBox.style.display = 'none';
            errorBox.textContent = '';
        }

        function openModal(message) {
            if (!modal || !modalMessage) return;

            previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            modalMessage.textContent = message;
            modal.hidden = false;
            document.body.classList.add('cfh-modal-open');

            var focusTarget = modal.querySelector('.cfh-modal__button, .cfh-modal__close');
            if (focusTarget instanceof HTMLElement) {
                focusTarget.focus();
            }
        }

        function closeModal() {
            if (!modal || modal.hidden) return;

            modal.hidden = true;
            document.body.classList.remove('cfh-modal-open');

            if (previousActiveElement instanceof HTMLElement) {
                previousActiveElement.focus();
            }
        }

        function handleDocumentKeydown(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }

        function saveState() {
            try {
                var state = { step: currentIndex, fields: {} };
                var inputs = form.querySelectorAll('input:not([type="hidden"]):not([name="cfh_nonce"]):not([name="cfh_hp_name"]), select, textarea');
                inputs.forEach(function (el) {
                    if (!el.name) return;

                    if (el.type === 'checkbox') {
                        state.fields[el.name] = el.checked ? el.value : '';
                    } else if (el.type === 'radio') {
                        if (el.checked) state.fields[el.name] = el.value;
                    } else {
                        state.fields[el.name] = el.value;
                    }
                });
                sessionStorage.setItem(sessionKey, JSON.stringify(state));
            } catch (e) {
                // sessionStorage kann deaktiviert sein.
            }
        }

        function restoreState() {
            try {
                var raw = sessionStorage.getItem(sessionKey);
                if (!raw) return;

                var state = JSON.parse(raw);
                if (typeof state.step !== 'number') return;

                Object.keys(state.fields || {}).forEach(function (name) {
                    var value = state.fields[name];
                    var fields = form.querySelectorAll('[name="' + cssEscape(name) + '"]');

                    fields.forEach(function (el) {
                        if (el.type === 'checkbox') {
                            el.checked = value === el.value;
                        } else if (el.type === 'radio') {
                            el.checked = el.value === value;
                        } else {
                            el.value = value;
                        }
                    });
                });

                var targetStep = Math.min(state.step, steps.length - 1);
                steps[currentIndex].classList.remove('active');
                currentIndex = targetStep;
                steps[currentIndex].classList.add('active');
            } catch (e) {
                // Korrupter State - ignorieren.
            }

            updateUI();
        }

        function clearState() {
            try {
                sessionStorage.removeItem(sessionKey);
            } catch (e) {}
        }

        var urlParams = new URLSearchParams(window.location.search);
        var returnError = urlParams.get('cfh_error');
        var returnFormType = urlParams.get('cfh_form_type');

        if (returnError && (!returnFormType || returnFormType === formType)) {
            if (inlineErrorMessages[returnError]) {
                showError(inlineErrorMessages[returnError]);
                showReturnFieldError(returnError);
            } else if (popupErrorMessages[returnError]) {
                openModal(popupErrorMessages[returnError]);
            } else if (popupErrorMessages.unknown) {
                openModal(popupErrorMessages.unknown);
            }

            removeHandledQueryParams();
        }

        updateUI();

        function removeHandledQueryParams() {
            var cleanedParams = new URLSearchParams(window.location.search);
            cleanedParams.delete('cfh_error');
            cleanedParams.delete('cfh_form_type');

            var nextQuery = cleanedParams.toString();
            var cleanUrl = window.location.pathname + (nextQuery ? '?' + nextQuery : '') + window.location.hash;
            window.history.replaceState(null, '', cleanUrl);
        }

        function dispatchFunnelEvent(eventName) {
            wrap.dispatchEvent(new CustomEvent('cfh:form-event', {
                bubbles: true,
                detail: {
                    event: eventName,
                    formType: formType,
                    step: currentIndex + 1,
                    totalSteps: steps.length
                }
            }));
        }
    }

    function buildSessionKey(formType, instanceIndex) {
        return 'cfh_form_state::' + formType + '::' + window.location.pathname + '::' + instanceIndex;
    }

    function parseErrorMessages(raw) {
        if (!raw) return {};

        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }
}());
