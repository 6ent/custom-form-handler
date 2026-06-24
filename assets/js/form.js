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
        var submitBtn = form ? form.querySelector('.cfh-btn--submit') : null;
        var formType = wrap.dataset.formType || 'window';
        var sessionKey = buildSessionKey(formType, instanceIndex);
        var errorMessages = parseErrorMessages(wrap.dataset.errorMessages);

        if (!form || steps.length === 0) return;

        var currentIndex = 0;

        restoreState();

        steps.forEach(function (step) {
            syncNextButton(step);
        });

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
            }
            saveState();
        });

        form.addEventListener('input', function () {
            saveState();
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
                showError('Bitte füllen Sie alle Pflichtfelder korrekt aus.');
                return;
            }

            hideError();
            clearState();

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Wird gesendet...';
            }
        });

        function advance() {
            if (!validateStep(currentIndex)) {
                showError('Bitte wählen Sie eine Option oder füllen Sie alle Pflichtfelder korrekt aus.');
                return;
            }

            hideError();
            steps[currentIndex].classList.remove('active');
            currentIndex = Math.min(currentIndex + 1, steps.length - 1);
            steps[currentIndex].classList.add('active');
            syncNextButton(steps[currentIndex]);
            focusFirstField(steps[currentIndex]);
            updateUI();
            saveState();
        }

        function retreat() {
            hideError();
            steps[currentIndex].classList.remove('active');
            currentIndex = Math.max(currentIndex - 1, 0);
            steps[currentIndex].classList.add('active');
            updateUI();
            saveState();
        }

        function validateStep(index) {
            var step = steps[index];
            var fields = step.querySelectorAll('input[required], select[required], textarea[required]');
            var valid = true;

            fields.forEach(function (field) {
                if (!field.checkValidity()) {
                    valid = false;
                    field.classList.add('cfh-invalid');
                    field.addEventListener('input', function clearInvalid() {
                        field.classList.remove('cfh-invalid');
                    }, { once: true });
                    field.addEventListener('change', function clearInvalid() {
                        field.classList.remove('cfh-invalid');
                    }, { once: true });
                } else {
                    field.classList.remove('cfh-invalid');
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

            var anyChecked = Array.from(radios).some(function (radio) {
                return radio.checked;
            });
            nextBtn.disabled = !anyChecked;
            nextBtn.title = anyChecked ? '' : 'Bitte wählen Sie eine Option';
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

        if (returnError && (!returnFormType || returnFormType === formType) && errorMessages[returnError]) {
            showError(errorMessages[returnError]);
            var cleanUrl = window.location.pathname + window.location.hash;
            window.history.replaceState(null, '', cleanUrl);
        }

        updateUI();
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
