document.addEventListener('DOMContentLoaded', () => {
    // --- Lógica para el Carrusel del Index ---
    const indexImages = document.querySelectorAll('.carousel img');
    let currentIndex = 0;

    function showNextIndexImage() {
        if (indexImages.length > 0) {
            indexImages[currentIndex].classList.remove('visible');
            currentIndex = (currentIndex + 1) % indexImages.length;
            indexImages[currentIndex].classList.add('visible');
        }
    }

    if (indexImages.length > 1) {
        setInterval(showNextIndexImage, 3000);
    }

    // --- Lógica para el Carrusel de Fondo (Login y Registro) ---
    const backgroundImages = document.querySelectorAll('.background-carousel img');
    let currentBackgroundIndex = 0;

    function showNextBackgroundImage() {
        if (backgroundImages.length > 0) {
            console.log("Rotando imagen de fondo..."); // Para depuración
            backgroundImages[currentBackgroundIndex].classList.remove('visible');
            currentBackgroundIndex = (currentBackgroundIndex + 1) % backgroundImages.length;
            backgroundImages[currentBackgroundIndex].classList.add('visible');
        }
    }

    if (backgroundImages.length > 1) {
        console.log("Iniciando carrusel de fondo con", backgroundImages.length, "imágenes"); // Para depuración
        setInterval(showNextBackgroundImage, 3000);
    }

    // --- Lógica para el Navbar Toggle ---
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarLinks = document.querySelector('.navbar-links');

    if (navbarToggle && navbarLinks) {
        navbarToggle.addEventListener('click', () => {
            navbarLinks.classList.toggle('active');
        });
    }

    // --- Lógica para el Banner de Consentimiento de Cookies y Términos ---
    const consentBanner = document.getElementById('cookie-consent-banner');
    const acceptButton = document.getElementById('accept-cookies');
    const rejectButton = document.getElementById('reject-cookies');

    if (!localStorage.getItem('gagCookieConsent')) {
        if (consentBanner) {
            consentBanner.classList.add('show');
        }
    }

    if (acceptButton) {
        acceptButton.addEventListener('click', () => {
            localStorage.setItem('gagCookieConsent', 'accepted');
            if (consentBanner) {
                consentBanner.classList.remove('show');
            }
        });
    }

    if (rejectButton) {
        rejectButton.addEventListener('click', () => {
            alert('Para utilizar este sitio, es necesario aceptar los términos y condiciones y el uso de cookies.');
            window.location.href = 'about:blank';
        });
    }
});