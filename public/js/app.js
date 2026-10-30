const errorSummary = document.querySelector('[data-error-summary]');

if (errorSummary) {
    errorSummary.focus();
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);

        if (!input) return;

        const shouldShow = input.type === 'password';
        input.type = shouldShow ? 'text' : 'password';
        button.textContent = shouldShow ? 'Hide' : 'Show';
        button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(shouldShow));
    });
});
