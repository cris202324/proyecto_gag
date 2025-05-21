document.addEventListener('DOMContentLoaded', () => {
    // Validación de campos en tiempo real
    const loginForm = document.querySelector('.login-form');
    const registerForm = document.querySelector('form[action="../php/procesar_registro.php"]');

    // Función para validar un correo electrónico
    function validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    // Función para mostrar el mensaje de error
    function showValidationMessage(input, isValid, message) {
        let errorElement = input.nextElementSibling;

        // Si no existe un mensaje de error, crearlo
        if (!errorElement || !errorElement.classList.contains('error-message')) {
            errorElement = document.createElement('p');
            errorElement.className = 'error-message';
            input.insertAdjacentElement('afterend', errorElement);
        }

        if (isValid) {
            input.classList.remove('input-error');
            input.classList.add('input-success');
            errorElement.textContent = '';
        } else {
            input.classList.add('input-error');
            input.classList.remove('input-success');
            errorElement.textContent = message;
        }
    }

    // Validar el formulario de inicio de sesión
    if (loginForm) {
        const emailInput = loginForm.querySelector('input[name="email"]');
        const passwordInput = loginForm.querySelector('input[name="contraseña"]');

        emailInput.addEventListener('input', () => {
            const isValid = validateEmail(emailInput.value);
            showValidationMessage(emailInput, isValid, 'Por favor, introduce un correo electrónico válido.');
        });

        passwordInput.addEventListener('input', () => {
            const isValid = passwordInput.value.length >= 6;
            showValidationMessage(passwordInput, isValid, 'La contraseña debe tener al menos 6 caracteres.');
        });
    }

    // Validar el formulario de registro
    if (registerForm) {
        const usernameInput = registerForm.querySelector('input[name="username"]');
        const emailInput = registerForm.querySelector('input[name="email"]');
        const passwordInput = registerForm.querySelector('input[name="contraseña"]');
        const confirmPasswordInput = registerForm.querySelector('input[name="confirm_password"]');

        usernameInput.addEventListener('input', () => {
            const isValid = usernameInput.value.trim() !== '';
            showValidationMessage(usernameInput, isValid, 'El nombre de usuario no puede estar vacío.');
        });

        emailInput.addEventListener('input', () => {
            const isValid = validateEmail(emailInput.value);
            showValidationMessage(emailInput, isValid, 'Por favor, introduce un correo electrónico válido.');
        });

        passwordInput.addEventListener('input', () => {
            const isValid = passwordInput.value.length >= 6;
            showValidationMessage(passwordInput, isValid, 'La contraseña debe tener al menos 6 caracteres.');
        });

        confirmPasswordInput.addEventListener('input', () => {
            const isValid = confirmPasswordInput.value === passwordInput.value;
            showValidationMessage(confirmPasswordInput, isValid, 'Las contraseñas no coinciden.');
        });
    }
});
