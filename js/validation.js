document.addEventListener('DOMContentLoaded', () => {
    // Validación de campos en tiempo real
    const loginForm = document.querySelector('.login-form');
    const registerForm = document.querySelector('form[action*="procesar_registro.php"]');

    // Función para validar un correo electrónico
    function validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(String(email).toLowerCase());
    }

    // Función para mostrar el mensaje de validación (error/éxito)
    function showValidationMessage(inputElement, isValid, message) {
        let feedbackElement = inputElement.nextElementSibling;

        // Crear o actualizar el elemento de feedback
        if (!feedbackElement || !feedbackElement.classList.contains('validation-feedback')) {
            if (feedbackElement && (feedbackElement.classList.contains('error-message') || feedbackElement.classList.contains('success-message') || feedbackElement.classList.contains('validation-feedback'))) {
                feedbackElement.remove();
            }
            feedbackElement = document.createElement('p');
            feedbackElement.className = 'validation-feedback';
            inputElement.parentNode.insertBefore(feedbackElement, inputElement.nextSibling);
        }

        console.log(`Validating ${inputElement.name}: isValid = ${isValid}, message = ${message}`); // Depuración

        if (isValid) {
            inputElement.classList.remove('input-error');
            inputElement.classList.add('input-success');
            feedbackElement.textContent = ''; // Limpiar mensaje si es válido
            feedbackElement.classList.remove('error-message');
        } else {
            inputElement.classList.add('input-error');
            inputElement.classList.remove('input-success');
            feedbackElement.textContent = message;
            feedbackElement.classList.add('error-message');
            feedbackElement.classList.remove('success-message');
        }
    }

    // Validar el formulario de inicio de sesión
    if (loginForm) {
        const emailInput = loginForm.querySelector('input[name="email"]');
        const passwordInput = loginForm.querySelector('input[name="contrasena"]');

        if (emailInput) {
            emailInput.addEventListener('input', () => {
                const isValid = validateEmail(emailInput.value);
                showValidationMessage(emailInput, isValid, 'Por favor, introduce un correo electrónico válido.');
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                const isValid = passwordInput.value.length >= 8;
                showValidationMessage(passwordInput, isValid, 'La contraseña debe tener al menos 8 caracteres.');
            });
        }
    }

    // Validar el formulario de registro
    if (registerForm) {
        const usernameInput = registerForm.querySelector('input[name="nombre"]');
        const emailInput = registerForm.querySelector('input[name="email"]');
        const passwordInput = registerForm.querySelector('input[name="contrasena"]');
        const confirmPasswordInput = registerForm.querySelector('input[name="confirm_password"]');

        let isFormValid = false;

        // Función para validar todo el formulario
        function validateForm() {
            const isUsernameValid = usernameInput.value.trim().length > 2;
            const isEmailValid = validateEmail(emailInput.value);
            const isPasswordValid = passwordInput.value.length >= 8;
            const isConfirmPasswordValid = confirmPasswordInput.value === passwordInput.value && confirmPasswordInput.value !== '';

            isFormValid = isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid;

            // Mostrar mensajes para cada campo
            showValidationMessage(usernameInput, isUsernameValid, 'El nombre debe tener más de 2 caracteres.');
            showValidationMessage(emailInput, isEmailValid, 'Por favor, introduce un correo electrónico válido.');
            showValidationMessage(passwordInput, isPasswordValid, 'La contraseña debe tener al menos 8 caracteres.');
            showValidationMessage(confirmPasswordInput, isConfirmPasswordValid, 'Las contraseñas no coinciden.');
        }

        if (usernameInput) {
            usernameInput.addEventListener('input', validateForm);
        }

        if (emailInput) {
            emailInput.addEventListener('input', validateForm);
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', validateForm);
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validateForm);
        }

        // Prevenir el envío del formulario si no es válido
        registerForm.addEventListener('submit', (e) => {
            validateForm();
            if (!isFormValid) {
                e.preventDefault();
                alert('Por favor, corrige los errores antes de enviar el formulario.');
            }
        });
    }
});