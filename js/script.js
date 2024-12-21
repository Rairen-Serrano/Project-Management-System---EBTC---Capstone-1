document.addEventListener('DOMContentLoaded', function() {
    // Get the body ID to determine which page we're on
    const bodyId = document.body.id;

    switch(bodyId) {
        case 'registerPage':
            handleRegisterPage();
            break;
        case 'loginPage':
            handleLoginPage();
            break;
        case 'adminLoginPage':
            handleAdminLoginPage();
            break;
        // Add more cases as needed
    }
});

// Registration page functions
function handleRegisterPage() {
    const registerForm = document.getElementById('registerForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthIndicator = document.createElement('div');
    strengthIndicator.className = 'password-strength mt-2';
    
    if (registerForm) {
        registerForm.addEventListener('submit', validateRegisterForm);
    }

    if (passwordInput) {
        // Password strength indicator
        passwordInput.parentElement.appendChild(strengthIndicator);
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updateStrengthIndicator(strength, strengthIndicator);
            
            // Check password match if confirm password has value
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        });
    }

    if (confirmPasswordInput) {
        // Add password match validation
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }

    // Function to validate password match
    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        let matchIndicator = confirmPasswordInput.parentElement.querySelector('.password-match');
        if (!matchIndicator) {
            matchIndicator = document.createElement('div');
            matchIndicator.className = 'password-match mt-2 small';
            confirmPasswordInput.parentElement.appendChild(matchIndicator);
        }

        if (confirmPassword) {
            if (password === confirmPassword) {
                matchIndicator.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
                matchIndicator.className = 'password-match mt-2 small text-success';
            } else {
                matchIndicator.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
                matchIndicator.className = 'password-match mt-2 small text-danger';
            }
        } else {
            matchIndicator.innerHTML = '';
        }
    }

    // Add password visibility toggle
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        // Create and add toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-link password-toggle';
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye" style="color: #000000;"></i>';
        field.parentElement.appendChild(toggleBtn);

        // Add click event
        toggleBtn.addEventListener('click', function() {
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
            this.innerHTML = type === 'password' 
                ? '<i class="fa-solid fa-eye" style="color: #000000;"></i>' 
                : '<i class="fa-solid fa-eye-slash" style="color: #000000;"></i>';
        });
    });
}

// Login page functions
function handleLoginPage() {
    const loginForm = document.querySelector('form');
    if (loginForm) {
        loginForm.addEventListener('submit', validateLoginForm);
    }

    // Add password visibility toggle
    const passwordField = document.querySelector('input[type="password"]');
    if (passwordField) {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-link password-toggle';
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye" style="color: #000000;"></i>';
        passwordField.parentElement.appendChild(toggleBtn);

        toggleBtn.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye" style="color: #000000;"></i>' : '<i class="fa-solid fa-eye-slash" style="color: #000000;"></i>';
        });
    }
}

// Admin login page functions
function handleAdminLoginPage() {
    const adminLoginForm = document.querySelector('form');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', validateLoginForm);
    }
}

// Utility functions
function validateLoginForm(e) {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    
    if (!email || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
    }
}

function validateRegisterForm(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    let errors = [];

    // Password validation
    if (password.length < 12) {
        errors.push('Password must be at least 12 characters long');
    }
    if (!password.match(/[A-Z]/)) {
        errors.push('Password must contain at least one uppercase letter');
    }
    if (!password.match(/[a-z]/)) {
        errors.push('Password must contain at least one lowercase letter');
    }
    if (!password.match(/[0-9]/)) {
        errors.push('Password must contain at least one number');
    }
    if (!password.match(/[!@#$%^&*()\-_=+{};:,<.>]/)) {
        errors.push('Password must contain at least one special character');
    }

    // Password match validation
    if (password !== confirmPassword) {
        errors.push('Passwords do not match');
    }

    // If there are errors, prevent form submission and show errors
    if (errors.length > 0) {
        e.preventDefault();
        
        // Create or update error alert
        let errorDiv = document.querySelector('.alert-danger');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            const form = document.getElementById('registerForm');
            form.parentNode.insertBefore(errorDiv, form);
        }
        
        // Add icon and error messages
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>
            ${errors.join('<br>')}
        `;
        
        // Scroll to error message
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 12) {
        strength += 25;
    } else if (password.length >= 8) {
        strength += 10;
    }

    // Uppercase check
    if (password.match(/[A-Z]/)) {
        strength += 20;
    }

    // Lowercase check
    if (password.match(/[a-z]/)) {
        strength += 20;
    }

    // Number check
    if (password.match(/[0-9]/)) {
        strength += 20;
    }

    // Special character check
    if (password.match(/[!@#$%^&*()\-_=+{};:,<.>]/)) {
        strength += 15;
    }

    return strength;
}

function updateStrengthIndicator(strength, indicator) {
    let strengthText, strengthClass;

    if (strength >= 100) {
        strengthText = 'Strong';
        strengthClass = 'strong';
    } else if (strength >= 80) {
        strengthText = 'Good';
        strengthClass = 'good';
    } else if (strength >= 50) {
        strengthText = 'Moderate';
        strengthClass = 'moderate';
    } else if (strength >= 30) {
        strengthText = 'Weak';
        strengthClass = 'weak';
    } else {
        strengthText = 'Very Weak';
        strengthClass = 'very-weak';
    }

    indicator.innerHTML = `
        <div class="progress" style="height: 5px;">
            <div class="progress-bar bg-${strengthClass}" 
                 role="progressbar" 
                 style="width: ${strength}%" 
                 aria-valuenow="${strength}" 
                 aria-valuemin="0" 
                 aria-valuemax="100"></div>
        </div>
        <small class="text-${strengthClass} mt-1 d-block">${strengthText}</small>
    `;
}
