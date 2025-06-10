document.addEventListener('DOMContentLoaded', () => {
    // Validación de campos en tiempo real
    const loginForm = document.querySelector('.login-form');
    const registerForm = document.querySelector('form[action*="procesar_registro.php"]');

    // Función para validar un correo electrónico
    function validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(String(email).toLowerCase());
    }

    // Función para mostrar el mensaje de validación
    function showValidationMessage(form, isValid, message) {
        const errorContainer = form.querySelector('.error-container .error-message');
        if (errorContainer) {
            if (isValid) {
                form.querySelectorAll('input').forEach(input => {
                    input.classList.remove('input-error');
                    input.classList.add('input-success');
                });
                errorContainer.textContent = '';
                form.classList.remove('invalid');
            } else {
                form.querySelectorAll('input').forEach(input => {
                    input.classList.add('input-error');
                    input.classList.remove('input-success');
                });
                errorContainer.textContent = message;
                form.classList.add('invalid');
            }
        }
    }

    // Validar el formulario de inicio de sesión
    if (loginForm) {
        const emailInput = loginForm.querySelector('input[name="email"]');
        const passwordInput = loginForm.querySelector('input[name="contrasena"]');

        if (emailInput) {
            emailInput.addEventListener('input', () => {
                const isValid = validateEmail(emailInput.value);
                showValidationMessage(loginForm, isValid, 'Por favor, introduce un correo electrónico válido.');
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                const isValid = passwordInput.value.length >= 8;
                showValidationMessage(loginForm, isValid, 'La contraseña debe tener al menos 8 caracteres.');
            });
        }

        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const isEmailValid = validateEmail(emailInput.value);
            const isPasswordValid = passwordInput.value.length >= 8;
            if (!isEmailValid || !isPasswordValid) {
                e.preventDefault();
                loginForm.classList.add('invalid');
                const errorContainer = loginForm.querySelector('.error-container .error-message');
                if (errorContainer) {
                    errorContainer.textContent = !isEmailValid ? 'Por favor, introduce un correo electrónico válido.' : 'La contraseña debe tener al menos 8 caracteres.';
                }
            } else {
                loginForm.classList.remove('invalid');
            }
        });
    }

    // Validar el formulario de registro
    if (registerForm) {
        const usernameInput = registerForm.querySelector('input[name="nombre"]');
        const emailInput = registerForm.querySelector('input[name="email"]');
        const passwordInput = registerForm.querySelector('input[name="contrasena"]');
        const confirmPasswordInput = registerForm.querySelector('input[name="confirm_password"]');

        function validateForm() {
            const isUsernameValid = usernameInput.value.trim().length > 2;
            const isEmailValid = validateEmail(emailInput.value);
            const isPasswordValid = passwordInput.value.length >= 8;
            const isConfirmPasswordValid = confirmPasswordInput.value === passwordInput.value && confirmPasswordInput.value !== '';
            const isValid = isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid;
            let message = '';
            if (!isUsernameValid) message = 'El nombre debe tener más de 2 caracteres.';
            else if (!isEmailValid) message = 'Por favor, introduce un correo electrónico válido.';
            else if (!isPasswordValid) message = 'La contraseña debe tener al menos 8 caracteres.';
            else if (!isConfirmPasswordValid) message = 'Las contraseñas no coinciden.';
            showValidationMessage(registerForm, isValid, message);
            return isValid;
        }

        if (usernameInput) usernameInput.addEventListener('input', validateForm);
        if (emailInput) emailInput.addEventListener('input', validateForm);
        if (passwordInput) passwordInput.addEventListener('input', validateForm);
        if (confirmPasswordInput) confirmPasswordInput.addEventListener('input', validateForm);

        registerForm.addEventListener('submit', (e) => {
            const isFormValid = validateForm();
            if (!isFormValid) {
                e.preventDefault();
            }
        });
    }
});