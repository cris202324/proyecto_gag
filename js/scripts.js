document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll('.carousel img');
    let currentIndex = 0;

    function showNextImage() {
        // Ocultar la imagen actual
        images[currentIndex].classList.remove('visible');

        // Calcular el siguiente índice
        currentIndex = (currentIndex + 1) % images.length;

        // Mostrar la siguiente imagen
        images[currentIndex].classList.add('visible');
    }

    // Cambiar de imagen cada 2 segundos
    setInterval(showNextImage, 4000);
});
// Alternar menú en dispositivos pequeños
document.querySelector('.navbar-toggle').addEventListener('click', function() {
    const navbarLinks = document.querySelector('.navbar-links');
    navbarLinks.classList.toggle('active');
});
document.addEventListener('DOMContentLoaded', function() {

    // --- Lógica para el Navbar Toggle (si ya la tienes, mantenla) ---
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarLinks = document.querySelector('.navbar-links');

    if (navbarToggle && navbarLinks) {
        navbarToggle.addEventListener('click', () => {
            navbarLinks.classList.toggle('active');
        });
    }

    // --- Lógica para el Carrusel (si ya la tienes, mantenla) ---
    const carouselImages = document.querySelectorAll('.carousel img');
    let currentImageIndex = 0;

    function showNextImage() {
        if (carouselImages.length > 0) {
            carouselImages[currentImageIndex].classList.remove('visible');
            currentImageIndex = (currentImageIndex + 1) % carouselImages.length;
            carouselImages[currentImageIndex].classList.add('visible');
        }
    }

    if (carouselImages.length > 1) { // Solo activar si hay más de una imagen
        setInterval(showNextImage, 3000); // Cambia cada 3 segundos
    }


    // --- Lógica para el Banner de Consentimiento de Cookies y Términos ---
    const consentBanner = document.getElementById('cookie-consent-banner');
    const acceptButton = document.getElementById('accept-cookies');
    const rejectButton = document.getElementById('reject-cookies');

    // Verificar si el consentimiento ya fue otorgado
    if (!localStorage.getItem('gagCookieConsent')) {
        if (consentBanner) {
            consentBanner.style.display = 'block'; // Mostrar el banner
        }
    }

    if (acceptButton) {
        acceptButton.addEventListener('click', function() {
            localStorage.setItem('gagCookieConsent', 'accepted');
            if (consentBanner) {
                consentBanner.style.display = 'none';
            }
        });
    }

    if (rejectButton) {
        rejectButton.addEventListener('click', function() {
            // Opcional: Guardar la preferencia de rechazo
            // localStorage.setItem('gagCookieConsent', 'rejected');

            alert('Para utilizar este sitio, es necesario aceptar los términos y condiciones y el uso de cookies.');
            // Redirigir fuera de la página o a una página específica de "acceso denegado"
            // `about:blank` es una página en blanco.
            window.location.href = 'about:blank';
            // O podrías redirigir a una página específica:
            // window.location.href = 'acceso-denegado.html';
        });
    }

});
