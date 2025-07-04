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
            const isEmailValid = validateEmail(emailInput.value);
            const isPasswordValid = passwordInput.value.length >= 8;
            if (!isEmailValid || !isPasswordValid) {
                e.preventDefault();
                showValidationMessage(loginForm, false, !isEmailValid ? 'Por favor, introduce un correo electrónico válido.' : 'La contraseña debe tener al menos 8 caracteres.');
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
document.addEventListener('DOMContentLoaded', function() {
    const registrationForm = document.querySelector('.login-form');
    const nombreInput = document.getElementById('nombre');
    const emailInput = document.getElementById('email');
    const contrasenaInput = document.getElementById('contrasena');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const feedbackDiv = document.querySelector('.validation-feedback');

    // Función para mostrar mensajes de error
    function showFeedback(message, isError = true) {
        feedbackDiv.textContent = message;
        feedbackDiv.style.color = isError ? '#d9534f' : '#5cb85c'; // Rojo para error, verde para éxito
        feedbackDiv.style.display = message ? 'block' : 'none';
    }

    // --- NUEVA VALIDACIÓN: Prevenir números en el campo de nombre ---
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            const value = nombreInput.value;
            // Usamos una expresión regular para encontrar cualquier dígito (número)
            if (/\d/.test(value)) {
                // Si se encuentra un número, lo eliminamos inmediatamente
                nombreInput.value = value.replace(/\d/g, '');
                // Y mostramos un mensaje de advertencia
                showFeedback('El nombre no puede contener números.');
            } else {
                // Si el campo es válido (sin números), limpiamos el mensaje de error
                // si el error era específicamente sobre los números.
                if (feedbackDiv.textContent === 'El nombre no puede contener números.') {
                    showFeedback('');
                }
            }
        });
    }

    // Validación al enviar el formulario (como una última comprobación del lado del cliente)
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(event) {
            // Limpiar feedback anterior
            showFeedback('');

            // Validar que las contraseñas coincidan
            if (contrasenaInput.value !== confirmPasswordInput.value) {
                event.preventDefault(); // Detener el envío del formulario
                showFeedback('Las contraseñas no coinciden.');
                return;
            }
            
            // Validar la longitud de la contraseña
            if (contrasenaInput.value.length < 8) {
                event.preventDefault();
                showFeedback('La contraseña debe tener al menos 8 caracteres.');
                return;
            }

            // Re-validar el nombre por si acaso
            if (/\d/.test(nombreInput.value)) {
                event.preventDefault();
                showFeedback('El nombre no puede contener números. Por favor, corríjalo.');
                return;
            }
        });
    }
});