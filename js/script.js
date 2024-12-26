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
        case 'appointmentPage':
            handleAppointmentPage();
            break;
        case 'dashboardPage':
            handleDashboardPage();
            break;
        // Add more cases as needed
    }

    // Add password visibility toggle for reset password page
    handlePasswordToggles();
});

// Move the password toggle functionality to a separate function
function handlePasswordToggles() {
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

// Add the appointment page handling function
function handleAppointmentPage() {
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    const selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
    const selectedServicesList = document.getElementById('selectedServicesList');

    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const serviceRow = this.closest('.service-row');
            if (this.checked) {
                serviceRow.classList.add('selected');
            } else {
                serviceRow.classList.remove('selected');
            }
            updateSelectedServices();
        });
    });

    function updateSelectedServices() {
        const selectedCheckboxes = document.querySelectorAll('.service-checkbox:checked');
        
        if (selectedCheckboxes.length > 0) {
            selectedServicesDisplay.style.display = 'block';
            selectedServicesList.innerHTML = '';
            
            selectedCheckboxes.forEach(checkbox => {
                const serviceName = checkbox.dataset.serviceName;
                const li = document.createElement('li');
                li.innerHTML = `
                    <i class="fas fa-check me-2 text-success"></i>
                    ${serviceName}
                    <i class="fas fa-times remove-service" 
                       onclick="removeService('${checkbox.id}')"></i>
                `;
                selectedServicesList.appendChild(li);
            });
        } else {
            selectedServicesDisplay.style.display = 'none';
        }
    }

    // Add this function to the global scope
    window.removeService = function(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        if (checkbox) {
            checkbox.checked = false;
            checkbox.closest('.service-row').classList.remove('selected');
            updateSelectedServices();
        }
    };

    // Initialize AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true
        });
    }

    // Add form validation
    const appointmentForm = document.getElementById('appointmentForm');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            const selectedServices = document.querySelectorAll('.service-checkbox:checked');
            
            if (selectedServices.length === 0) {
                e.preventDefault();
                alert('Please select at least one service');
                return false;
            }
            
            // Validate date and time
            const dateInput = document.querySelector('input[name="date"]');
            const timeInput = document.querySelector('input[name="time"]');
            
            if (!dateInput.value || !timeInput.value) {
                e.preventDefault();
                alert('Please select both date and time');
                return false;
            }

            // Check if selected date is not in the past
            const selectedDate = new Date(dateInput.value + ' ' + timeInput.value);
            const now = new Date();
            
            if (selectedDate < now) {
                e.preventDefault();
                alert('Please select a future date and time');
                return false;
            }
        });
    }
}

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

// Add this to your existing handleDashboardPage function or create it if it doesn't exist
function handleDashboardPage() {
    // Sidebar Toggle for Client Dashboard
    const sidebarToggle = document.getElementById('clientSidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.client-sidebar').classList.toggle('active');
            document.querySelector('.client-main-content').classList.toggle('active');
        });
    }

    // Set active menu item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.client-sidebar-menu a');
    menuItems.forEach(item => {
        if (item.getAttribute('href') === currentPage) {
            item.classList.add('active');
        }
    });
}

// Client Dashboard Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('clientSidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.client-sidebar').classList.toggle('active');
            document.querySelector('.client-main-content').classList.toggle('active');
        });
    }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.client-sidebar');
    const sidebarToggle = document.getElementById('clientSidebarToggle');
    
    if (sidebar && sidebar.classList.contains('active')) {
        if (!sidebar.contains(event.target) && event.target !== sidebarToggle) {
            sidebar.classList.remove('active');
            document.querySelector('.client-main-content').classList.remove('active');
        }
    }
});
