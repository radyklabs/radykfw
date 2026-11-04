/**
 * Login Module JavaScript
 * Handles form interactions and enhancements
 */

(function() {
    'use strict';

    // Wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[method="post"]');
        if (!form) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        const inputs = form.querySelectorAll('input.form-input');

        // Add loading state on submit
        form.addEventListener('submit', function(e) {
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-loading');

                const originalText = submitBtn.innerHTML;
                submitBtn.dataset.originalText = originalText;
                submitBtn.innerHTML = `
                    <svg class="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"></path>
                    </svg>
                    Signing in...
                `;

                // Add spinner animation if not exists
                if (!document.getElementById('radykfw-spinner-style')) {
                    const style = document.createElement('style');
                    style.id = 'radykfw-spinner-style';
                    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }
            }
        });

        // Input focus/blur effects
        inputs.forEach(input => {
            const formGroup = input.closest('.form-group');
            if (!formGroup) return;

            input.addEventListener('focus', function() {
                formGroup.classList.add('focused');
            });

            input.addEventListener('blur', function() {
                formGroup.classList.remove('focused');
                if (this.value) {
                    formGroup.classList.add('has-value');
                } else {
                    formGroup.classList.remove('has-value');
                }
            });

            // Check initial value
            if (input.value) {
                formGroup.classList.add('has-value');
            }
        });

        // Password visibility toggle (if needed)
        const passwordField = form.querySelector('input[type="password"]');
        if (passwordField) {
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle';
            toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
            toggleBtn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            `;
            toggleBtn.style.cssText = `
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px;
                color: #7f8c8d;
                transition: color 0.2s;
            `;

            const wrapper = passwordField.parentElement;
            wrapper.style.position = 'relative';
            wrapper.appendChild(toggleBtn);
            passwordField.style.paddingRight = '48px';

            let visible = false;
            toggleBtn.addEventListener('click', function() {
                visible = !visible;
                passwordField.type = visible ? 'text' : 'password';
                toggleBtn.innerHTML = visible ? `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                ` : `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
                toggleBtn.style.color = visible ? '#3498db' : '#7f8c8d';
            });
        }

        // Form validation feedback
        inputs.forEach(input => {
            input.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.classList.add('error');
                this.setCustomValidity('');
            });

            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    });

    // Expose for potential external use
    window.RadykFWLogin = {
        init: function() {
            // Re-initialize if needed
        }
    };
})();