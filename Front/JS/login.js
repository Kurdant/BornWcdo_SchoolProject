/* ============================================================
LOGIN - HANDLE AUTHENTICATION
============================================================ */

const API_BASE = window.location.hostname === 'localhost'
    ? '/api'
    : 'https://wakdo-back.acadenice.fr/api';

const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const errorMessage = document.getElementById('errorMessage');
const guestButton = document.getElementById('guestButton');

/* ============================================================
LOGIN FORM SUBMISSION
============================================================ */

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = emailInput.value.trim();
    const password = passwordInput.value;

    // Clear previous error
    errorMessage.textContent = '';
    errorMessage.classList.remove('show');

    // Validate inputs
    if (!email || !password) {
        showError('Veuillez remplir tous les champs');
        return;
    }

    if (password.length < 6) {
        showError('Le mot de passe doit contenir au moins 6 caractères');
        return;
    }

    // Show loading state
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Connexion en cours...';

    try {
        const response = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                email: email,
                mot_de_passe: password
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showError(data.error || 'Erreur lors de la connexion');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }

        // Connexion réussie
        console.log('Connexion réussie:', data.data.client);

        // Store client info in sessionStorage
        if (data.data.client && data.data.client.id) {
            sessionStorage.setItem('client_id', data.data.client.id);
            sessionStorage.setItem('client_email', data.data.client.email);
            sessionStorage.setItem('client_name', data.data.client.prenom); // Pseudo/prénom
            sessionStorage.setItem('client_points', data.data.client.points_fidelite); // Points fidélité
            sessionStorage.setItem('is_connected', 'true');
            console.log('Client connecté - ID:', data.data.client.id, 'Nom:', data.data.client.prenom, 'Points:', data.data.client.points_fidelite);
        }

        // Redirect to menu selection
        setTimeout(() => {
            window.location.href = 'menu-selection.html';
        }, 500);

    } catch (error) {
        console.error('Erreur réseau:', error);
        showError('Erreur réseau - Veuillez réessayer');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
});

/* ============================================================
GUEST MODE - CONTINUE WITHOUT LOGIN
============================================================ */

guestButton.addEventListener('click', () => {
    // Clear any existing client session
    sessionStorage.removeItem('client_id');
    sessionStorage.removeItem('client_email');
    sessionStorage.setItem('is_connected', 'false');
    
    console.log('Mode invité - commande anonyme');
    
    // Redirect to menu selection
    window.location.href = 'menu-selection.html';
});

/* ============================================================
HELPER FUNCTION - SHOW ERROR
============================================================ */

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.add('show');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorMessage.classList.remove('show');
    }, 5000);
}

/* ============================================================
FORM INPUT CLEANUP ON FOCUS
============================================================ */

emailInput.addEventListener('focus', () => {
    errorMessage.classList.remove('show');
});

passwordInput.addEventListener('focus', () => {
    errorMessage.classList.remove('show');
});
