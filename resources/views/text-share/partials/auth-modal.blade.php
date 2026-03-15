<!-- Auth Modal -->
<div class="auth-overlay" id="authOverlay" style="display: none;" onclick="if(event.target === this) closeAuthModal()">
    <div class="auth-modal">
        <div class="auth-header">
            <h2>Welcome</h2>
            <button class="auth-close" onclick="closeAuthModal()">✕</button>
        </div>

        <div class="auth-tabs">
            <button class="auth-tab active" id="loginTab" onclick="switchAuthTab('login')">Login</button>
            <button class="auth-tab" id="registerTab" onclick="switchAuthTab('register')">Register</button>
        </div>

        <!-- Login Form -->
        <div class="auth-form-container" id="loginFormContainer">
            <form class="auth-form" id="loginForm" onsubmit="loginSubmit(event)">
                <input
                    type="email"
                    id="loginEmail"
                    class="auth-input"
                    placeholder="Email"
                    required>
                <input
                    type="password"
                    id="loginPassword"
                    class="auth-input"
                    placeholder="Password"
                    required>
                <button type="submit" class="auth-btn primary">Login</button>
                <div class="auth-error" id="loginError" style="display:none"></div>
            </form>
        </div>

        <!-- Register Form -->
        <div class="auth-form-container" id="registerFormContainer" style="display: none;">
            <form class="auth-form" id="registerForm" onsubmit="registerSubmit(event)">
                <input
                    type="text"
                    id="registerFullName"
                    class="auth-input"
                    placeholder="Full Name"
                    required>
                <input
                    type="email"
                    id="registerEmail"
                    class="auth-input"
                    placeholder="Email"
                    required>
                <input
                    type="password"
                    id="registerPassword"
                    class="auth-input"
                    placeholder="Password (min 6 characters)"
                    minlength="6"
                    required>
                <button type="submit" class="auth-btn primary">Register</button>
                <div class="auth-error" id="registerError" style="display:none"></div>
            </form>
        </div>
    </div>
</div>

<style>
    .auth-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        backdrop-filter: blur(5px);
    }

    .auth-modal {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .auth-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .auth-header h2 {
        margin: 0;
        font-size: 24px;
        color: var(--text);
    }

    .auth-close {
        padding: 4px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: transparent;
        color: var(--text);
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
    }

    .auth-close:hover {
        background: var(--accent);
        color: var(--bg);
        border-color: var(--accent);
    }

    .auth-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border);
    }

    .auth-tab {
        flex: 1;
        padding: 10px;
        border: none;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: var(--text-dim);
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }

    .auth-tab:hover {
        color: var(--text);
    }

    .auth-tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
    }

    .auth-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .auth-input {
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg);
        color: var(--text);
        font-size: 14px;
        outline: none;
        font-family: inherit;
    }

    .auth-input:focus {
        border-color: var(--accent);
    }

    .auth-btn {
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: transparent;
        color: var(--text);
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 500;
    }

    .auth-btn:hover {
        background: var(--accent);
        color: var(--bg);
        border-color: var(--accent);
    }

    .auth-btn.primary {
        background: var(--accent);
        color: var(--bg);
        border-color: var(--accent);
    }

    .auth-error {
        color: #ff6b6b;
        font-size: 13px;
        margin-top: -5px;
    }

    .user-dropdown {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 5px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 6px;
        min-width: 150px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        z-index: 1000;
    }

    .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 15px;
        border: none;
        background: transparent;
        color: var(--text);
        text-align: left;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
    }

    .dropdown-item:hover {
        background: var(--bg);
    }
</style>
