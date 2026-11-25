(() => {
  document.querySelectorAll('.password-toggle').forEach(button => {
    button.addEventListener('click', () => {
      const input = button.parentElement.querySelector('input');
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Hide' : 'Show';
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    });
  });

  // Browser-side checks improve usability; PHP validates every submitted value again.
  document.querySelectorAll('[data-auth-form]').forEach(form => {
    form.addEventListener('submit', event => {
      let valid = true;
      form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
      form.querySelectorAll('input[required]').forEach(input => {
        let message = '';
        if (input.type === 'checkbox' && !input.checked) message = 'Please accept this to continue.';
        else if (input.type !== 'checkbox' && !input.value.trim()) message = 'This field is required.';
        else if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())) message = 'Enter a valid email address.';
        else if (input.name === 'password' && input.value.length < 8) message = 'Use at least 8 characters.';
        else if (input.name === 'confirm_password' && input.value !== form.querySelector('[name="password"]').value) message = 'Passwords do not match.';
        if (message) {
          valid = false;
          input.setAttribute('aria-invalid', 'true');
          const error = input.closest('.form-row')?.querySelector('.field-error');
          if (error) error.textContent = message;
        } else input.removeAttribute('aria-invalid');
      });
      if (!valid) {
        event.preventDefault();
        const message = form.querySelector('.form-message');
        if (message) message.textContent = 'Please review the highlighted fields.';
      }
    });
  });
})();