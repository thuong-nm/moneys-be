// Authentication functionality
let currentUser = null;

// Check if user is logged in on page load
async function checkAuth() {
    try {
        const response = await fetch('/api/auth/me', {
            headers: {
                'Accept': 'application/json',
            }
        });
        const data = await response.json();

        if (data.success && data.user) {
            currentUser = data.user;
            updateAuthUI(true);
        } else {
            currentUser = null;
            updateAuthUI(false);
        }
    } catch (e) {
        console.error('Auth check failed:', e);
        currentUser = null;
        updateAuthUI(false);
    }
}

// Update UI based on auth status
function updateAuthUI(isLoggedIn) {
    const authSection = document.getElementById('authSection');
    const expiresSelect = document.getElementById('expiresIn');

    if (isLoggedIn && currentUser) {
        // Show user info with dropdown
        authSection.innerHTML = `
            <div class="user-dropdown">
                <button class="btn" onclick="toggleUserMenu()" id="userMenuBtn">
                    👤 <span id="userName">${escapeHtmlAuth(currentUser.full_name)}</span>
                </button>
                <div class="dropdown-menu" id="userMenu" style="display:none">
                    <button class="dropdown-item" onclick="logout()">Logout</button>
                </div>
            </div>
        `;

        // Hide expires dropdown for logged-in users
        if (expiresSelect) {
            expiresSelect.style.display = 'none';
        }
    } else {
        // Show login button
        authSection.innerHTML = `<button class="btn" onclick="openAuthModal()">Login</button>`;

        // Show expires dropdown for guests
        if (expiresSelect) {
            expiresSelect.style.display = 'block';
        }
    }
}

// Toggle user menu dropdown
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    if (userMenu && userMenuBtn && !userMenuBtn.contains(e.target) && !userMenu.contains(e.target)) {
        userMenu.style.display = 'none';
    }
});

// Open auth modal
function openAuthModal(defaultTab = 'login') {
    const overlay = document.getElementById('authOverlay');
    overlay.style.display = 'flex';
    switchAuthTab(defaultTab);
}

// Close auth modal
function closeAuthModal() {
    const overlay = document.getElementById('authOverlay');
    overlay.style.display = 'none';
    // Clear forms
    document.getElementById('loginForm').reset();
    document.getElementById('registerForm').reset();
    // Clear errors
    document.getElementById('loginError').style.display = 'none';
    document.getElementById('registerError').style.display = 'none';
}

// Switch between login/register tabs
function switchAuthTab(tab) {
    const loginTab = document.getElementById('loginTab');
    const registerTab = document.getElementById('registerTab');
    const loginForm = document.getElementById('loginFormContainer');
    const registerForm = document.getElementById('registerFormContainer');

    if (tab === 'login') {
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
    } else {
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.style.display = 'block';
        loginForm.style.display = 'none';
    }
}

// Login
async function loginSubmit(event) {
    event.preventDefault();

    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const errorDiv = document.getElementById('loginError');

    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            closeAuthModal();
            updateAuthUI(true);
            if (typeof showToast === 'function') {
                showToast('Login successful!');
            }
            // Reload history with user context
            if (typeof loadHistory === 'function') {
                loadHistory();
            }
        } else {
            errorDiv.textContent = data.message || 'Login failed';
            errorDiv.style.display = 'block';
        }
    } catch (e) {
        console.error(e);
        errorDiv.textContent = 'Login error';
        errorDiv.style.display = 'block';
    }
}

// Register
async function registerSubmit(event) {
    event.preventDefault();

    const fullName = document.getElementById('registerFullName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const errorDiv = document.getElementById('registerError');

    try {
        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ full_name: fullName, email, password })
        });

        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            closeAuthModal();
            updateAuthUI(true);
            if (typeof showToast === 'function') {
                showToast('Registration successful!');
            }
            // Reload history with user context
            if (typeof loadHistory === 'function') {
                loadHistory();
            }
        } else {
            const errorMsg = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Registration failed');
            errorDiv.textContent = errorMsg;
            errorDiv.style.display = 'block';
        }
    } catch (e) {
        console.error(e);
        errorDiv.textContent = 'Registration error';
        errorDiv.style.display = 'block';
    }
}

// Logout
async function logout() {
    try {
        const response = await fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
            }
        });

        const data = await response.json();

        if (data.success) {
            currentUser = null;
            updateAuthUI(false);
            if (typeof showToast === 'function') {
                showToast('Logout successful!');
            }
            // Reload history as guest
            if (typeof loadHistory === 'function') {
                loadHistory();
            }
        }
    } catch (e) {
        console.error('Logout error:', e);
    }
}

// Helper to escape HTML
function escapeHtmlAuth(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Initialize auth on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
});
