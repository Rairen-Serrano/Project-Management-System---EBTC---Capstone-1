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
        case 'profilePage':
            handleProfilePage();
            break;
        case 'settingsPage':
            handleSettingsPage();
            break;
        case 'adminAppointmentsPage':
            handleAdminAppointmentsPage();
            break;
        case 'adminArchivedAppointmentsPage':
            handleAdminArchivedAppointmentsPage();
            break;
        case 'adminProjectsPage':
            handleAdminProjectsPage();
            break;
        case 'adminUsersPage':
            handleAdminUsersPage();
            break;
        case 'adminEmployeesPage':
            handleAdminEmployeesPage();
            break;
        case 'managerDashboardPage':
            handleManagerDashboardPage();
            break;
        case 'adminSettingsPage':
            handleAdminSettingsPage();
            break;
        case 'managerProjectsPage':
            handleManagerProjectsPage();
            break;
        case 'engineerDashboardPage':
            handleEngineerDashboardPage();
            if (document.body.dataset.needsPinSetup === 'true') {
                console.log('PIN setup needed'); // Debug log
                setupPin();
            }
            break;
        case 'taskManagementPage':
            handleTaskManagementPage();
            break;
        // Add more cases as needed
    }

    // Add password visibility toggle for reset password page
    handlePasswordToggles();

    // Initialize admin dashboard functionality if on admin dashboard page
    if (document.getElementById('adminDashboardPage')) {
        handleAdminDashboardPage();
    }

    // Add page initialization
    if (document.getElementById('engineerTasksPage')) {
        handleEngineerTasksPage();
    }
});

// Move the password toggle functionality to a separate function
function handlePasswordToggles(selector = 'input[type="password"]:not(.pin-input)') {
    const passwordFields = document.querySelectorAll(selector);
    passwordFields.forEach(field => {
        // Skip if the field already has a toggle button
        if (field.parentElement.querySelector('.ebtc-password-toggle')) {
            return;
        }

        // Create and add toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-link ebtc-password-toggle';
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye" style="color: #000000;"></i>';
        
        // Add the button in a relative positioned container
        const container = document.createElement('div');
        container.style.position = 'relative';
        field.parentNode.insertBefore(container, field);
        container.appendChild(field);
        container.appendChild(toggleBtn);

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

    // Add password visibility toggle
    const passwordFields = document.querySelectorAll('input[type="password"]');
    if (passwordFields) {
        passwordFields.forEach(passwordField => {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-link ebtc-password-toggle';
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye" style="color: #000000;"></i>';
            passwordField.parentElement.appendChild(toggleBtn);
        toggleBtn.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye" style="color: #000000;"></i>' : '<i class="fa-solid fa-eye-slash" style="color: #000000;"></i>';
        });
    });
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
        toggleBtn.className = 'btn btn-link ebtc-password-toggle';
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

    // Add password visibility toggle
    const passwordField = document.querySelector('input[type="password"]');
    if (passwordField) {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-link ebtc-password-toggle';
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye" style="color: #000000;"></i>';
        passwordField.parentElement.appendChild(toggleBtn);

        toggleBtn.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye" style="color: #000000;"></i>' : '<i class="fa-solid fa-eye-slash" style="color: #000000;"></i>';
        });
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

// Add these helper functions at the top of your script.js
function isWeekday(date) {
    const day = new Date(date).getDay();
    return day > 0 && day < 6; // 0 is Sunday, 6 is Saturday
}

function formatTimeOption(hour, minute) {
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour;
    return {
        value: `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`,
        text: `${displayHour}:${minute.toString().padStart(2, '0')}${period}`
    };
}

// Add the dashboard page handling function
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

    // Initialize PIN verification modal
    const pinModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
    const rescheduleModal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
    const pinRescheduleModal = new bootstrap.Modal(document.getElementById('pinVerificationRescheduleModal'));
    const pinInputs = document.querySelectorAll('.pin-input');
    let newDateValue, newTimeValue, appointmentIdValue;

    // Handle PIN input behavior
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                e.target.value = value[0];
                if (index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                pinInputs[index - 1].focus();
            }
        });
    });

    // Add reschedule appointment function to window object
    window.rescheduleAppointment = function(appointmentId) {
        document.getElementById('appointmentToReschedule').value = appointmentId;
        document.getElementById('newDate').value = '';
        document.getElementById('newTime').value = '';
        pinInputs.forEach(input => input.value = '');
        rescheduleModal.show();
    };

    // Handle reschedule confirmation
    document.getElementById('confirmReschedule').addEventListener('click', function() {
        newDateValue = document.getElementById('newDate').value;
        newTimeValue = document.getElementById('newTime').value;
        appointmentIdValue = document.getElementById('appointmentToReschedule').value;

        if (!newDateValue || !newTimeValue) {
            alert('Please select both date and time');
            return;
        }

        // Hide reschedule modal and show PIN modal
        rescheduleModal.hide();
        document.querySelectorAll('#pinVerificationRescheduleModal .pin-input').forEach(input => input.value = '');
        pinRescheduleModal.show();
    });

    // Handle PIN confirmation for reschedule
    document.getElementById('confirmPinReschedule').addEventListener('click', function() {
        const pinInputs = document.querySelectorAll('#pinVerificationRescheduleModal .pin-input');
        const pin = Array.from(pinInputs).map(input => input.value).join('');

        if (pin.length !== 4) {
            alert('Please enter a valid 4-digit PIN');
            return;
        }

        // First verify PIN
        fetch('verify_pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'pin=' + pin
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If PIN is correct, proceed with rescheduling
                return fetch('reschedule_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${appointmentIdValue}&new_date=${newDateValue}&new_time=${newTimeValue}`
                });
            } else {
                throw new Error('Incorrect PIN');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pinRescheduleModal.hide();
                location.reload();
            } else {
                alert(data.message || 'Error rescheduling appointment');
            }
        })
        .catch(error => {
            alert(error.message || 'Error processing request');
            pinRescheduleModal.hide();
            rescheduleModal.show(); // Show reschedule modal again if PIN verification fails
        });
    });

    // Add cancel appointment function to window object
    window.cancelAppointment = function(appointmentId) {
        // Store the appointment ID and show PIN modal
        document.getElementById('appointmentToCancel').value = appointmentId;
        pinInputs.forEach(input => input.value = ''); // Clear previous inputs
        pinInputs[0].focus(); // Focus first input
        pinModal.show();
    };

    // Add view details function to window object
    window.viewDetails = function(appointmentId) {
        fetch('get_appointment_details.php?id=' + appointmentId)
            .then(response => response.json())
            .then(data => {
                const modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
                document.querySelector('#appointmentDetailsModal .modal-body').innerHTML = `
                    <div class="appointment-details">
                        <p><strong>Date:</strong> ${data.date}</p>
                        <p><strong>Time:</strong> ${data.time}</p>
                        <p><strong>Service:</strong> ${data.service}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${data.statusClass}">${data.status}</span></p>
                        <p><strong>Booked on:</strong> ${data.created_at}</p>
                    </div>
                `;
                modal.show();
            });
    };

    // Handle confirmation button click
    document.getElementById('confirmCancellation').addEventListener('click', function() {
        const pin = Array.from(pinInputs).map(input => input.value).join('');
        const appointmentId = document.getElementById('appointmentToCancel').value;

        if (pin.length !== 4) {
            alert('Please enter a valid 4-digit PIN');
            return;
        }

        // First verify PIN
        fetch('verify_pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'pin=' + pin
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If PIN is correct, proceed with cancellation
                return fetch('cancel_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'appointment_id=' + appointmentId
                });
            } else {
                throw new Error('Incorrect PIN');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pinModal.hide();
                location.reload();
            } else {
                alert(data.message || 'Error cancelling appointment');
            }
        })
        .catch(error => {
            alert(error.message || 'Error processing request');
        });
    });

    // Add date input restrictions
    const dateInput = document.getElementById('newDate');
    if (dateInput) {
        // Set min date to today
        dateInput.min = new Date().toISOString().split('T')[0];
        
        // Add event listener to validate weekdays
        dateInput.addEventListener('input', function() {
            if (this.value && !isWeekday(this.value)) {
                alert('Please select a date between Monday and Friday');
                this.value = '';
            }
        });
    }

    // Add time input restrictions
    const timeInput = document.getElementById('newTime');
    if (timeInput) {
        // Create time options for 9 AM to 3 PM with 30-minute intervals
        timeInput.innerHTML = ''; // Clear existing options
        const select = document.createElement('select');
        select.className = 'form-select';
        select.id = 'newTime';
        
        for (let hour = 9; hour <= 15; hour++) { // 15 is 3 PM
            for (let minute = 0; minute < 60; minute += 30) {
                // Skip times after 3:00 PM
                if (hour === 15 && minute > 0) continue;
                
                const time = formatTimeOption(hour, minute);
                const option = document.createElement('option');
                option.value = time.value;
                option.text = `${time.text} - ${formatTimeOption(
                    minute === 30 ? hour + 1 : hour,
                    minute === 30 ? 0 : 30
                ).text}`;
                select.appendChild(option);
            }
        }
        
        // Replace the time input with the select
        timeInput.parentNode.replaceChild(select, timeInput);
    }
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

// Add the profile page handling function
function handleProfilePage() {
    // Image preview function
    window.previewImage = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePhotoPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Phone number validation
    document.getElementById('phone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) {
            value = value.substr(0, 11);
        }
        e.target.value = value;
    });

    // Handle PIN input fields
    const pinInputs = document.querySelectorAll('.pin-input');
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                e.target.value = value[0];
                if (index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                pinInputs[index - 1].focus();
            }
        });
    });

    // Handle form submission with PIN verification
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
        pinInputs.forEach(input => input.value = '');
        modal.show();
    });

    // Handle PIN confirmation
    document.getElementById('confirmPin').addEventListener('click', function() {
        const pin = Array.from(pinInputs).map(input => input.value).join('');
        
        if (pin.length !== 4 || !/^\d{4}$/.test(pin)) {
            alert('Please enter a valid 4-digit PIN');
            return;
        }

        const form = document.getElementById('profileForm');
        
        // Remove any existing pin_code input
        const existingPin = form.querySelector('input[name="pin_code"]');
        if (existingPin) {
            existingPin.remove();
        }
        
        // Add the PIN to the form
        let pinInput = document.createElement('input');
        pinInput.type = 'hidden';
        pinInput.name = 'pin_code';
        pinInput.value = pin;
        form.appendChild(pinInput);

        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
        modal.hide();
        
        // Debug log
        console.log('Submitting form with PIN:', pin);
        console.log('Form data:', new FormData(form));
        
        // Submit the form
        form.submit();
    });
}

// Add settings page handling function
function handleSettingsPage() {
    // Handle password fields in the change password modal
    handlePasswordToggles('#changePasswordModal input[type="password"]');
    
    // Handle PIN input fields for verification
    const pinInputs = document.querySelectorAll('.pin-input');
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                e.target.value = value[0];
                if (index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                pinInputs[index - 1].focus();
            }
        });
    });

    // Handle verification code inputs
    const verificationInputs = document.querySelectorAll('.verification-digit');
    verificationInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                e.target.value = value[0];
                if (index < verificationInputs.length - 1) {
                    verificationInputs[index + 1].focus();
                }
                updateVerificationCode();
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                verificationInputs[index - 1].focus();
            }
        });
    });

    // PIN input validation for all pattern inputs
    document.querySelectorAll('input[pattern]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substr(0, 4);
            }
            e.target.value = value;
        });
    });

    // Setup PIN inputs for change PIN modal
    function setupPinInputs(inputClass, hiddenInputId) {
        const pinInputs = document.querySelectorAll(`.${inputClass}`);
        pinInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value) {
                    e.target.value = value[0];
                    if (index < pinInputs.length - 1) {
                        pinInputs[index + 1].focus();
                    }
                }
                updatePinCode(inputClass, hiddenInputId);
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    pinInputs[index - 1].focus();
                }
            });
        });
    }

    function updatePinCode(inputClass, hiddenInputId) {
        const pin = Array.from(document.querySelectorAll(`.${inputClass}`))
            .map(input => input.value)
            .join('');
        document.getElementById(hiddenInputId).value = pin;
    }

    // Initialize PIN inputs
    setupPinInputs('current-pin', 'current_pin');
    setupPinInputs('new-pin', 'new_pin');
    setupPinInputs('confirm-pin', 'confirm_pin');

    // PIN form validation
    const changePinForm = document.getElementById('changePinForm');
    if (changePinForm) {
        changePinForm.addEventListener('submit', function(e) {
            const newPin = document.getElementById('new_pin').value;
            const confirmPin = document.getElementById('confirm_pin').value;

            if (newPin.length !== 4 || !/^\d{4}$/.test(newPin)) {
                e.preventDefault();
                alert('PIN must be exactly 4 digits');
                return;
            }

            if (newPin !== confirmPin) {
                e.preventDefault();
                alert('PINs do not match');
                return;
            }
        });
    }

    function updateVerificationCode() {
        const code = Array.from(verificationInputs)
            .map(input => input.value)
            .join('');
        document.getElementById('verificationCode').value = code;
    }

    // Initialize toast
    const toast = new bootstrap.Toast(document.getElementById('messageToast'));

    // Function to show toast message
    function showToast(title, message, isError = false) {
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        document.getElementById('messageToast').classList.toggle('bg-danger', isError);
        document.getElementById('messageToast').classList.toggle('text-white', isError);
        toast.show();
    }

    // Handle password change process
    const changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    const verificationModal = new bootstrap.Modal(document.getElementById('verificationCodeModal'));
    let passwordData = {};

    // Password form validation
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmNewPassword').value;

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
        });
    }

    // Send verification code button click
    document.getElementById('sendVerificationBtn').addEventListener('click', function() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmNewPassword = document.getElementById('confirmNewPassword').value;

        // Validate passwords
        if (!currentPassword || !newPassword || !confirmNewPassword) {
            showToast('Error', 'Please fill in all password fields', true);
            return;
        }

        if (newPassword !== confirmNewPassword) {
            showToast('Error', 'New passwords do not match', true);
            return;
        }

        if (newPassword.length < 8) {
            showToast('Error', 'New password must be at least 8 characters long', true);
            return;
        }

        // Directly change password
        const formData = new FormData();
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmNewPassword);
        formData.append('change_password', '1');

        fetch('change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                changePasswordModal.hide();
                showToast('Success', 'Password changed successfully');
                // Clear form
                document.getElementById('changePasswordForm').reset();
            } else {
                showToast('Error', data.message || 'Failed to change password', true);
            }
        })
        .catch(error => {
            showToast('Error', error.message || 'Failed to change password', true);
        });
    });

    // Resend code button click
    document.getElementById('resendCodeBtn').addEventListener('click', function() {
        fetch('send_password_verification.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            showToast('Success', data.message);
            // Clear verification inputs
            verificationInputs.forEach(input => input.value = '');
            verificationInputs[0].focus();
        })
        .catch(error => {
            showToast('Error', 'Failed to resend verification code', true);
        });
    });

    // Verify code and change password
    document.getElementById('verifyCodeBtn').addEventListener('click', function() {
        const verificationCode = document.getElementById('verificationCode').value;

        if (!verificationCode || verificationCode.length !== 6) {
            showToast('Error', 'Please enter a valid 6-digit verification code', true);
            return;
        }

        // First verify the code
        fetch('verify_password_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `verification_code=${verificationCode}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If verification successful, proceed with password change
                const formData = new FormData();
                formData.append('current_password', passwordData.currentPassword);
                formData.append('new_password', passwordData.newPassword);
                formData.append('confirm_password', passwordData.confirmNewPassword);
                formData.append('change_password', '1');

                return fetch('change_password.php', {
                    method: 'POST',
                    body: formData
                });
            } else {
                throw new Error(data.message);
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                verificationModal.hide();
                showToast('Success', 'Password changed successfully');
                // Clear form
                document.getElementById('changePasswordForm').reset();
                // Clear stored password data
                passwordData = {};
            } else {
                showToast('Error', data.message, true);
            }
        })
        .catch(error => {
            showToast('Error', error.message || 'Failed to change password', true);
        });
    });

    // Clear stored password data when modals are closed
    document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function () {
        passwordData = {};
        document.getElementById('changePasswordForm').reset();
    });

    document.getElementById('verificationCodeModal').addEventListener('hidden.bs.modal', function () {
        verificationInputs.forEach(input => input.value = '');
        document.getElementById('verificationCode').value = '';
    });
}

// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const headerToggleBtn = document.getElementById('headerSidebarToggle');
    if (headerToggleBtn) {
        headerToggleBtn.addEventListener('click', function() {
            const sidebar = document.querySelector('.client-sidebar, .admin-sidebar');
            const mainContent = document.querySelector('.client-main-content, .admin-main-content');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Store the state in localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
        });

        // Check localStorage on page load
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            const sidebar = document.querySelector('.client-sidebar, .admin-sidebar');
            const mainContent = document.querySelector('.client-main-content, .admin-main-content');
            if (sidebar && mainContent) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }
    }
});

// Add the admin appointments page handling function
function handleAdminAppointmentsPage() {
    // Initialize view appointment modal
    window.viewModal = new bootstrap.Modal(document.getElementById('viewAppointmentModal'));
    window.currentAppointmentId = null;
}

// Confirm appointment function
function confirmAppointment(appointmentId) {
    fetch('../admin/confirm_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            appointment_id: appointmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            console.error('Server response:', data);
            alert(data.message || 'Error confirming appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error confirming appointment. Please try again.');
    });
}

// Archive appointment function
function archiveAppointment(appointmentId) {
    fetch('../admin/archive_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            appointment_id: appointmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            console.error('Server response:', data);
            alert(data.message || 'Error archiving appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error archiving appointment. Please try again.');
    });
}

// Function to view appointment details
function viewAppointment(appointmentData) {
    window.currentAppointmentId = appointmentData.appointment_id;

    // Update modal content
    document.getElementById('modalClientName').textContent = appointmentData.client_name;
    document.getElementById('modalClientEmail').textContent = appointmentData.client_email;
    document.getElementById('modalClientPhone').textContent = appointmentData.client_phone;
    document.getElementById('modalService').textContent = appointmentData.service;
    document.getElementById('modalDate').textContent = new Date(appointmentData.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('modalTime').textContent = new Date('1970-01-01T' + appointmentData.time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    // Set status with badge
    document.getElementById('modalStatus').innerHTML = getStatusBadge(appointmentData.status);
    
    document.getElementById('modalCreated').textContent = new Date(appointmentData.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    // Show/hide confirm button based on status
    const confirmButtonContainer = document.getElementById('modalConfirmButton');
    if (appointmentData.status === 'pending') {
        confirmButtonContainer.innerHTML = `
            <button type="button" class="btn btn-success" onclick="confirmAppointmentFromModal()">
                <i class="fas fa-check me-2"></i>Confirm
            </button>
        `;
    } else {
        confirmButtonContainer.innerHTML = '';
    }

    // Show/hide archive button based on status
    const archiveButtonContainer = document.getElementById('modalArchiveButton');
    if (appointmentData.status !== 'archived') {
        archiveButtonContainer.style.display = 'block';
    } else {
        archiveButtonContainer.style.display = 'none';
    }

    // Show modal
    window.viewModal.show();
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'pending': 'warning',
        'confirmed': 'success',
        'cancelled': 'danger',
        'archived': 'secondary'
    };
    const badgeColor = badges[status] || 'secondary';
    return `<span class="badge bg-${badgeColor}">${status.toUpperCase()}</span>`;
}

// Confirm appointment from modal
function confirmAppointmentFromModal() {
    if (!window.currentAppointmentId) return;
    confirmAppointment(window.currentAppointmentId);
}

// Archive appointment from modal
function archiveAppointmentFromModal() {
    if (!window.currentAppointmentId) return;
    archiveAppointment(window.currentAppointmentId);
}

// Confirm appointment function
function confirmAppointment(appointmentId) {
    fetch('../admin/confirm_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            appointment_id: appointmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.viewModal) {
                window.viewModal.hide();
            }
            location.reload();
        } else {
            console.error('Server response:', data);
            alert(data.message || 'Error confirming appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error confirming appointment. Please try again.');
    });
}

// Archive appointment function
function archiveAppointment(appointmentId) {
    fetch('../admin/archive_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            appointment_id: appointmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.viewModal) {
                window.viewModal.hide();
            }
            location.reload();
        } else {
            console.error('Server response:', data);
            alert(data.message || 'Error archiving appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error archiving appointment. Please try again.');
    });
}

// Function to handle archived appointments page
function handleAdminArchivedAppointmentsPage() {
    // Initialize the view archived appointment modal
    window.archivedModal = new bootstrap.Modal(document.getElementById('viewArchivedAppointmentModal'));
}

// Function to view archived appointment details
function viewArchivedAppointment(appointmentData) {
    // Store the appointment ID in the window object
    window.currentArchivedAppointmentId = appointmentData.arc_appointment_id;

    // Update modal content
    document.getElementById('modalClientName').textContent = appointmentData.client_name;
    document.getElementById('modalClientEmail').textContent = appointmentData.client_email;
    document.getElementById('modalClientPhone').textContent = appointmentData.client_phone;
    document.getElementById('modalService').textContent = appointmentData.service;
    document.getElementById('modalDate').textContent = new Date(appointmentData.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('modalTime').textContent = new Date('1970-01-01T' + appointmentData.time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    // Set status with badge
    document.getElementById('modalStatus').innerHTML = getStatusBadge(appointmentData.status);
    
    document.getElementById('modalCreated').textContent = new Date(appointmentData.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('modalArchived').textContent = new Date(appointmentData.archived_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    // Show modal
    window.archivedModal.show();
}

// Handle admin projects page
function handleAdminProjectsPage() {
    // Initialize project modal
    window.viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
    window.currentProjectId = null;
}

// View project details
function viewProject(projectData) {
    window.currentProjectId = projectData.project_id;

    // Update modal content with null checks
    const elements = {
        'modalClientName': projectData.client_name,
        'modalService': projectData.service,
        'modalDate': new Date(projectData.date).toLocaleDateString(),
        'modalStatus': projectData.status.replace('_', ' ').toUpperCase(),
        'modalNotes': projectData.notes || ''
    };

    // Update each element if it exists
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            if (id === 'modalNotes') {
                element.value = elements[id];
            } else {
                element.textContent = elements[id];
            }
        }
    });

    // Handle quotation file display
    const currentQuotationFile = document.getElementById('currentQuotationFile');
    const uploadQuotationFile = document.getElementById('uploadQuotationFile');
    const quotationFileLink = document.getElementById('quotationFileLink');

    if (currentQuotationFile && uploadQuotationFile && quotationFileLink) {
        if (projectData.quotation_file) {
            currentQuotationFile.style.display = 'block';
            uploadQuotationFile.style.display = 'none';
            quotationFileLink.href = '../uploads/quotations/' + projectData.quotation_file;
            quotationFileLink.textContent = projectData.quotation_file;
        } else {
            currentQuotationFile.style.display = 'none';
            uploadQuotationFile.style.display = 'block';
            const fileInput = document.getElementById('quotationFileInput');
            if (fileInput) {
                fileInput.value = '';
            }
        }
    }

    // Show modal
    if (window.viewProjectModal) {
        window.viewProjectModal.show();
    } else {
        const modal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
        window.viewProjectModal = modal;
        modal.show();
    }
}

// Update project
function updateProject() {
    const projectId = window.currentProjectId;
    if (!projectId) return;

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('notes', document.getElementById('modalNotes').value);

    // Add file if one is selected
    const fileInput = document.getElementById('quotationFileInput');
    if (fileInput.files.length > 0) {
        formData.append('quotation_file', fileInput.files[0]);
    }

    fetch('update_project.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and reload page
            window.viewProjectModal.hide();
            location.reload();
        } else {
            alert('Failed to update project: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update project');
    });
}

// Remove quotation file
function removeQuotationFile() {
    if (!confirm('Are you sure you want to remove this file?')) return;

    const projectId = window.currentProjectId;
    if (!projectId) return;

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('remove_quotation', 'true');

    fetch('update_project.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the file display
            document.getElementById('currentQuotationFile').style.display = 'none';
            document.getElementById('uploadQuotationFile').style.display = 'block';
            document.getElementById('quotationFileInput').value = '';
        } else {
            alert('Failed to remove file: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to remove file');
    });
}

// Add appointment to projects
function addToProjects(appointmentId) {
    if (!confirm('Are you sure you want to add this appointment as a project?')) {
        return;
    }

    const formData = new FormData();
    formData.append('appointment_id', appointmentId);

    fetch('add_project.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('selectAppointmentModal'));
            modal.hide();
            
            // Show success message and reload
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the project');
    });
}

// Handle admin users page
function handleAdminUsersPage() {
    // Initialize modals
    window.editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    window.addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
}

// View user details
function viewUser(userData) {
    // Placeholder for view functionality
    console.log('View user:', userData);
}

// Edit user
function editUser(userData) {
    document.getElementById('editUserId').value = userData.user_id;
    document.getElementById('editUserName').value = userData.name;
    document.getElementById('editUserEmail').value = userData.email;
    document.getElementById('editUserPhone').value = userData.phone;
    window.editUserModal.show();
}

// Handle admin employees page
function handleAdminEmployeesPage() {
    // Initialize modals
    window.editEmployeeModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    window.addEmployeeModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
}

// Edit employee
function editEmployee(employeeData) {
    document.getElementById('editEmployeeId').value = employeeData.user_id;
    document.getElementById('editEmployeeName').value = employeeData.name;
    document.getElementById('editEmployeeEmail').value = employeeData.email;
    document.getElementById('editEmployeePhone').value = employeeData.phone;
    document.getElementById('editEmployeeRole').value = employeeData.role;
    window.editEmployeeModal.show();
}

// Update employee
function updateEmployee() {
    const userId = document.getElementById('editEmployeeId').value;
    const name = document.getElementById('editEmployeeName').value;
    const email = document.getElementById('editEmployeeEmail').value;
    const phone = document.getElementById('editEmployeePhone').value;
    const role = document.getElementById('editEmployeeRole').value;

    if (!name || !email || !phone || !role) {
        alert('Please fill in all fields');
        return;
    }

    fetch('../admin/update_employee.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            name: name,
            email: email,
            phone: phone,
            role: role
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.editEmployeeModal.hide();
            location.reload();
        } else {
            alert(data.message || 'Error updating employee');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating employee');
    });
}

// Deactivate employee
function deactivateEmployee(userId) {
    if (!confirm('Are you sure you want to deactivate this employee?')) return;

    fetch('../admin/toggle_employee_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            status: 'inactive'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error deactivating employee');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deactivating employee');
    });
}

// Activate employee
function activateEmployee(userId) {
    if (!confirm('Are you sure you want to activate this employee?')) return;

    fetch('../admin/toggle_employee_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            status: 'active'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error activating employee');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error activating employee');
    });
}

function handleAdminDashboardPage() {
    const mainContent = document.querySelector('.admin-main-content');
    const pinSetupModal = document.getElementById('pinSetupModal');
    const pinVerificationModal = document.getElementById('pinVerificationModal');

    // Only proceed with PIN setup if the modal exists and we need PIN setup
    if (pinSetupModal && mainContent.style.display === 'none' && document.body.dataset.needsPinSetup === 'true') {
        const setupModal = new bootstrap.Modal(pinSetupModal);
        const setupPinInputs = document.querySelectorAll('.setup-pin');
        const confirmPinInputs = document.querySelectorAll('.confirm-pin');
        
        setupModal.show();

        // Handle PIN input navigation for setup
        [setupPinInputs, confirmPinInputs].forEach(inputGroup => {
            inputGroup.forEach((input, index) => {
                input.addEventListener('input', function() {
                    if (this.value && index < inputGroup.length - 1) {
                        inputGroup[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputGroup[index - 1].focus();
                    }
                });
            });
        });

        // Handle PIN setup submission
        document.getElementById('savePinBtn').addEventListener('click', function() {
            const setupPin = Array.from(setupPinInputs).map(input => input.value).join('');
            const confirmPin = Array.from(confirmPinInputs).map(input => input.value).join('');

            if (setupPin !== confirmPin) {
                document.getElementById('pinSetupError').textContent = 'PINs do not match';
                document.getElementById('pinSetupError').style.display = 'block';
                return;
            }

            if (setupPin.length !== 4) {
                document.getElementById('pinSetupError').textContent = 'PIN must be 4 digits';
                document.getElementById('pinSetupError').style.display = 'block';
                return;
            }

            // Make an AJAX call to save the PIN
            fetch('setup_pin.php', {
        method: 'POST',
        headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'pin=' + setupPin
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mainContent.style.display = 'block';
                    setupModal.hide();
                } else {
                    document.getElementById('pinSetupError').textContent = data.message || 'Error setting PIN';
                    document.getElementById('pinSetupError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('pinSetupError').textContent = 'Error setting PIN';
                document.getElementById('pinSetupError').style.display = 'block';
            });
        });
    }

    // Show PIN verification modal if needed (only if we don't need PIN setup)
    if (pinVerificationModal && mainContent.style.display === 'none' && document.body.dataset.needsPinSetup !== 'true') {
        const verifyModal = new bootstrap.Modal(pinVerificationModal);
        verifyModal.show();

        // Handle PIN input navigation
        const pinInputs = document.querySelectorAll('.pin-input:not(.setup-pin):not(.confirm-pin)');
        pinInputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value && index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    pinInputs[index - 1].focus();
                }
            });
        });

        // Handle PIN verification
        document.getElementById('verifyPinBtn').addEventListener('click', function() {
            let pin = Array.from(pinInputs).map(input => input.value).join('');
            
            // Make an AJAX call to verify the PIN
            fetch('verify_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'pin=' + pin
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
                    mainContent.style.display = 'block';
                    verifyModal.hide();
        } else {
                    document.getElementById('pinError').style.display = 'block';
                    pinInputs.forEach(input => input.value = '');
                    pinInputs[0].focus();
        }
    })
    .catch(error => {
        console.error('Error:', error);
            });
        });
    }

    // Password visibility toggle function
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Simple password match check
    const passwordInput = document.getElementById('addEmployeePassword');
    const confirmPasswordInput = document.getElementById('addEmployeeConfirmPassword');
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const matchIndicator = this.parentElement.nextElementSibling;
            if (this.value) {
                if (this.value === passwordInput.value) {
                    matchIndicator.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
                    matchIndicator.className = 'password-match mt-2 small text-success';
                } else {
                    matchIndicator.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
                    matchIndicator.className = 'password-match mt-2 small text-danger';
                }
            } else {
                matchIndicator.innerHTML = '';
            }
        });
    }
}

function handleManagerProjectsPage() {
    // Initialize the view project modal
    window.viewProjectModal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
    window.currentProjectId = null;

    // Add event listener for when modal is hidden
    document.getElementById('viewProjectModal').addEventListener('hidden.bs.modal', function () {
        // Clear modal content
        document.getElementById('modalClientName').textContent = '';
        document.getElementById('modalClientEmail').textContent = '';
        document.getElementById('modalClientPhone').textContent = '';
        document.getElementById('modalService').textContent = '';
        document.getElementById('modalDate').textContent = '';
        document.getElementById('modalTime').textContent = '';
        document.getElementById('modalStatus').textContent = '';
        document.getElementById('modalNotes').value = '';
        document.getElementById('quotationFileInput').value = '';
        document.getElementById('currentQuotationFile').style.display = 'none';
        window.currentProjectId = null;
    });
}

function viewProject(project) {
    // Store current project ID
    window.currentProjectId = project.project_id;

    // Update modal content with project details
    document.getElementById('modalClientName').textContent = project.client_name;
    document.getElementById('modalClientEmail').textContent = project.client_email;
    document.getElementById('modalClientPhone').textContent = project.client_phone;
    document.getElementById('modalService').textContent = project.service;
    
    // Format date and time
    const date = new Date(project.date);
    const time = new Date(`2000-01-01T${project.time}`);
    
    document.getElementById('modalDate').textContent = date.toLocaleDateString('en-US', { 
        month: 'long', 
        day: 'numeric', 
        year: 'numeric' 
    });
    document.getElementById('modalTime').textContent = time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit', 
        hour12: true 
    });
    
    // Update status with badge
    const statusBadge = document.createElement('span');
    statusBadge.className = 'badge bg-primary px-3 py-2';
    statusBadge.textContent = 'Ongoing';
    document.getElementById('modalStatus').innerHTML = '';
    document.getElementById('modalStatus').appendChild(statusBadge);
    
    // Update notes
    document.getElementById('modalNotes').value = project.notes || '';
    
    // Update quotation file display
    if (project.quotation_file) {
        document.getElementById('currentQuotationFile').style.display = 'block';
        document.getElementById('quotationFileLink').href = '../uploads/quotations/' + project.quotation_file;
        document.getElementById('uploadQuotationFile').style.display = 'none';
    } else {
        document.getElementById('currentQuotationFile').style.display = 'none';
        document.getElementById('uploadQuotationFile').style.display = 'block';
    }
    
    // Show the modal
    window.viewProjectModal.show();
}

function updateProjectStatus(projectId, currentStatus) {
    if (!confirm('Are you sure you want to ' + (currentStatus === 'ongoing' ? 'complete' : 'reopen') + ' this project?')) {
        return;
    }

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('current_status', currentStatus);

    fetch('update_project_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the project status');
    });
}

function archiveEmployee(userId) {
    if (confirm('Are you sure you want to archive this employee?')) {
        fetch('archive_employee.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Error archiving employee');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error archiving employee');
        });
    }
}

function restoreEmployee(userId) {
    if (confirm('Are you sure you want to restore this employee?')) {
        fetch('restore_employee.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Error restoring employee');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error restoring employee');
        });
    }
}

function deleteEmployee(userId) {
    if (!confirm('Are you sure you want to permanently delete this employee? This action cannot be undone.')) {
        return;
    }

    fetch('delete_employee.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Error deleting employee');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting employee');
    });
}

// Mobile Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const engineerSidebar = document.querySelector('.engineer-sidebar');
    
    if (mobileSidebarToggle && engineerSidebar) {
        mobileSidebarToggle.addEventListener('click', function() {
            engineerSidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992 && // Only on mobile
                !engineerSidebar.contains(e.target) && // Not clicking inside sidebar
                !mobileSidebarToggle.contains(e.target) && // Not clicking toggle button
                engineerSidebar.classList.contains('show')) { // Sidebar is open
                engineerSidebar.classList.remove('show');
            }
        });
    }
});

// Engineer Dashboard Functions
function handleEngineerDashboardPage() {
    // Initialize sidebar toggle
    const sidebarToggle = document.getElementById('headerSidebarToggle');
    const sidebar = document.querySelector('.engineer-sidebar');
    const mainContent = document.querySelector('.engineer-main-content');
    
    if (sidebarToggle && sidebar && mainContent) {
        // Check if PIN setup/verification is needed
        if (document.body.querySelector('[data-needs-pin-setup="true"]')) {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
            setupPin();
        } else if (document.querySelector('.engineer-main-content[style*="display: none"]') && !sessionStorage.getItem('pin_verified')) {
            const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinVerificationModal.show();
            setupPinVerification();
        }

        // Rest of the sidebar toggle code...
    }

    // Load dashboard data
    loadEngineerStats();
    loadCurrentTasks();
    loadUpcomingDeadlines();
    loadProjectProgress();
}

async function loadEngineerStats() {
    try {
        const response = await fetch('../engineer/api/engineer_stats.php');
        const data = await response.json();

        if (response.ok) {
            document.getElementById('activeTasks').textContent = data.active_tasks;
            document.getElementById('completedTasks').textContent = data.completed_tasks;
            document.getElementById('pendingTasks').textContent = data.pending_tasks;
            document.getElementById('totalProjects').textContent = data.total_projects;
        } else {
            throw new Error(data.error || 'Failed to load statistics');
        }
    } catch (error) {
        console.error('Error loading engineer stats:', error);
    }
}

async function loadCurrentTasks() {
    try {
        const response = await fetch('../engineer/api/engineer_tasks.php');
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to load tasks');
        }

        const tasksTable = document.getElementById('currentTasksTable');
        tasksTable.innerHTML = '';

        if (data.tasks.length === 0) {
            tasksTable.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-tasks fa-2x mb-3 d-block"></i>
                        No tasks assigned
                    </td>
                </tr>
            `;
            return;
        }

        data.tasks.forEach(task => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="d-flex flex-column">
                        <strong>${escapeHtml(task.task_name)}</strong>
                        <small class="text-muted text-truncate" style="max-width: 300px;" title="${escapeHtml(task.description || '')}">${escapeHtml(task.description || '')}</small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span>${escapeHtml(task.service)}</span>
                        <small class="text-muted">${escapeHtml(task.category_name || 'No Category')}</small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span>${formatDate(task.due_date)}</span>
                        <small class="text-${getDaysUntilDue(task.due_date) <= 2 ? 'danger' : 'muted'}">
                            ${getDaysUntilDue(task.due_date)} day${getDaysUntilDue(task.due_date) !== 1 ? 's' : ''} left
                        </small>
                    </div>
                </td>
                <td>
                    <span class="badge ${getStatusBadgeClass(task.status)}">
                        ${capitalizeFirst(task.status)}
                    </span>
                </td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewTaskDetails(${task.task_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${task.status !== 'completed' ? `
                            <button class="btn btn-sm btn-outline-success" onclick="toggleTaskStatus(${task.task_id}, '${task.status}')" title="Mark as Completed">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : `
                            <button class="btn btn-sm btn-outline-warning" onclick="toggleTaskStatus(${task.task_id}, '${task.status}')" title="Mark as Pending">
                                <i class="fas fa-undo"></i>
                            </button>
                        `}
                    </div>
                </td>
            `;
            tasksTable.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading current tasks:', error);
        tasksTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-circle fa-2x mb-3 d-block"></i>
                    Failed to load tasks. Please try again later.
                </td>
            </tr>
        `;
    }
}

async function loadUpcomingDeadlines() {
    try {
        const response = await fetch('../engineer/api/engineer_deadlines.php');
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to load deadlines');
        }

        const deadlinesList = document.getElementById('upcomingDeadlines');
        deadlinesList.innerHTML = '';

        if (data.deadlines.length === 0) {
            deadlinesList.innerHTML = `
                <div class="list-group-item text-center text-muted">
                    No upcoming deadlines
                </div>
            `;
            return;
        }

        data.deadlines.forEach(task => {
            const daysUntil = getDaysUntilDue(task.due_date);
            const item = document.createElement('a');
            item.href = '#';
            item.className = 'list-group-item list-group-item-action';
            item.onclick = (e) => {
                e.preventDefault();
                viewTaskDetails(task.task_id);
            };
            item.innerHTML = `
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${escapeHtml(task.task_name)}</h6>
                        <small class="text-muted">${escapeHtml(task.project_name)}</small>
                    </div>
                    <small class="text-${daysUntil <= 2 ? 'danger' : 'warning'} fw-bold">
                        ${daysUntil} day${daysUntil !== 1 ? 's' : ''} left
                    </small>
                </div>
            `;
            deadlinesList.appendChild(item);
        });
    } catch (error) {
        console.error('Error loading upcoming deadlines:', error);
    }
}

async function loadProjectProgress() {
    try {
        const response = await fetch('../engineer/api/engineer_projects.php');
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to load project progress');
        }

        const progressList = document.getElementById('projectProgressList');
        progressList.innerHTML = '';

        if (data.projects.length === 0) {
            progressList.innerHTML = `
                <div class="text-center text-muted">
                    No active projects
                </div>
            `;
            return;
        }

        data.projects.forEach(project => {
            const div = document.createElement('div');
            div.className = 'mb-3';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <h6 class="mb-0">${escapeHtml(project.service)}</h6>
                        <small class="text-muted">${escapeHtml(project.project_name)}</small>
                    </div>
                    <span class="text-muted">${project.progress || 0}%</span>
                </div>
                <div class="progress" style="height: 5px;">
                    <div class="progress-bar bg-success" 
                         role="progressbar" 
                         style="width: ${project.progress || 0}%" 
                         aria-valuenow="${project.progress || 0}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
            `;
            progressList.appendChild(div);
        });
    } catch (error) {
        console.error('Error loading project progress:', error);
    }
}

async function toggleTaskStatus(taskId, currentStatus) {
    try {
        const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';
        const response = await fetch('../engineer/api/update_task_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                current_status: currentStatus,
                status: newStatus
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to update task status');
        }

        // Reload tasks and statistics
        loadTasks();
        if (typeof loadEngineerStats === 'function') {
            loadEngineerStats();
            loadProjectProgress();
        }

        showAlert('success', `Task marked as ${newStatus}`);
    } catch (error) {
        console.error('Error updating task status:', error);
        showAlert('error', 'Failed to update task status');
    }
}

function viewTaskDetails(taskId) {
    // Implement task details view functionality
    console.log('View task details:', taskId);
}

function setupPinVerification() {
    const inputs = document.querySelectorAll('#pinVerificationModal .pin-input');
    setupPinInputBehavior(inputs);

    const verifyPinBtn = document.getElementById('verifyPinBtn');
    if (!verifyPinBtn) return;

    verifyPinBtn.addEventListener('click', async function() {
        const pin = Array.from(inputs).map(input => input.value).join('');
        
        if (pin.length !== 4) {
            showVerificationError('Please enter all 4 digits');
            return;
        }

        try {
            const response = await fetch('../api/auth/verify_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pin: pin })
            });

            const data = await response.json();

            if (data.success) {
                // Store verification in sessionStorage
                sessionStorage.setItem('pin_verified', 'true');
                
                // Hide modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                modal.hide();

                // Show main content
                document.querySelector('.engineer-main-content').style.display = 'block';
                
                // Show success message
                showAlert('success', 'PIN verified successfully!');
            } else {
                showVerificationError('Invalid PIN');
                inputs.forEach(input => input.value = '');
                inputs[0].focus();
            }
        } catch (error) {
            console.error('Error verifying PIN:', error);
            showVerificationError('Error verifying PIN');
        }
    });
}

function setupPinInputBehavior(inputs) {
    inputs.forEach((input, index) => {
        // Only allow numbers
        input.addEventListener('input', function(e) {
            if (e.target.value.length > 0) {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value.slice(-1);
                
                // Move to next input if available
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            }
        });

        // Handle backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });
}

function showSetupError(message) {
    const errorDiv = document.getElementById('pinSetupError');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Hide error after 3 seconds
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 3000);
    }
}

function setupPin() {
    console.log('Setting up PIN functionality');
    
    const setupInputs = document.querySelectorAll('.setup-pin');
    const confirmInputs = document.querySelectorAll('.confirm-pin');
    const pinSetupModal = document.getElementById('pinSetupModal');
    
    if (!setupInputs.length || !confirmInputs.length) {
        console.error('PIN input elements not found');
        return;
    }

    console.log('Found PIN inputs:', setupInputs.length, confirmInputs.length);
    
    setupPinInputBehavior(setupInputs);
    setupPinInputBehavior(confirmInputs);

    const savePinBtn = document.getElementById('savePinBtn');
    if (!savePinBtn) {
        console.error('Save PIN button not found');
        return;
    }

    savePinBtn.addEventListener('click', async function() {
        console.log('Save PIN button clicked');
        
        const pin = Array.from(setupInputs)
            .map(input => input.value)
            .join('');
        
        const confirmPin = Array.from(confirmInputs)
            .map(input => input.value)
            .join('');
        
        console.log('PIN:', pin.length, 'Confirm PIN:', confirmPin.length);

        if (pin.length !== 4 || confirmPin.length !== 4) {
            showSetupError('Please enter all 4 digits for both PINs');
            return;
        }

        if (pin !== confirmPin) {
            showSetupError('PINs do not match');
            return;
        }

        try {
            console.log('Sending PIN setup request...');
            const response = await fetch('../api/auth/setup_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pin: pin })
            });

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                // Hide the modal
                const bsModal = bootstrap.Modal.getInstance(pinSetupModal);
                if (bsModal) {
                    bsModal.hide();
                }

                // Show success message
                showAlert('success', 'PIN setup successful!');
                
                // Clear inputs
                setupInputs.forEach(input => input.value = '');
                confirmInputs.forEach(input => input.value = '');
                
                // Show the main content
                document.querySelector('.engineer-main-content').style.display = 'block';
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showSetupError(data.error || 'Failed to set up PIN');
            }
        } catch (error) {
            console.error('Error setting up PIN:', error);
            showSetupError('Failed to set up PIN. Please try again.');
        }
    });
}

// ... existing code ...

// Helper Functions
function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    
    // Add message
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Find or create alert container
    let alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alertContainer';
        alertContainer.style.position = 'fixed';
        alertContainer.style.top = '20px';
        alertContainer.style.right = '20px';
        alertContainer.style.zIndex = '1050';
        document.body.appendChild(alertContainer);
    }
    
    // Add alert to container
    alertContainer.appendChild(alertDiv);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric'
    });
}

function getDaysUntilDue(dateString) {
    const dueDate = new Date(dateString);
    const today = new Date();
    const diffTime = dueDate - today;
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-warning',
        'in_progress': 'bg-primary',
        'completed': 'bg-success',
        'overdue': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1).replace('_', ' ');
}

function handleEngineerTasksPage() {
    // Check if PIN setup/verification is needed
    if (document.body.querySelector('[data-needs-pin-setup="true"]')) {
        const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
        pinSetupModal.show();
        setupPin();
    } else if (document.querySelector('.engineer-main-content[style*="display: none"]') && !sessionStorage.getItem('pin_verified')) {
        const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
        pinVerificationModal.show();
        setupPinVerification();
    }

    // Load initial tasks
    loadTasks();

    // Set up event listeners for filters
    const statusFilter = document.getElementById('statusFilter');
    const projectFilter = document.getElementById('projectFilter');

    if (statusFilter) {
        statusFilter.addEventListener('change', () => loadTasks());
    }
    if (projectFilter) {
        projectFilter.addEventListener('change', () => loadTasks());
    }
}

async function loadTasks() {
    const tasksTableBody = document.getElementById('tasksTableBody');
    const loadingRow = document.getElementById('loadingTasks');
    const noTasksMessage = document.getElementById('noTasksMessage');

    if (!tasksTableBody || !loadingRow || !noTasksMessage) {
        console.error('Required elements not found');
        return;
    }

    try {
        const statusFilter = document.getElementById('statusFilter');
        const projectFilter = document.getElementById('projectFilter');
        
        const status = statusFilter ? statusFilter.value : 'all';
        const projectId = projectFilter ? projectFilter.value : 'all';
        
        // Show loading state
        loadingRow.style.display = 'table-row';
        noTasksMessage.style.display = 'none';
        
        const response = await fetch(`../engineer/api/get_tasks.php?status=${status}&project_id=${projectId}`);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to load tasks');
        }

        // Update project filter options if available
        if (data.projects && projectFilter) {
            const currentValue = projectFilter.value;
            projectFilter.innerHTML = '<option value="all">All Projects</option>';
            data.projects.forEach(project => {
                projectFilter.innerHTML += `<option value="${escapeHtml(project.project_id)}">${escapeHtml(project.project_name)}</option>`;
            });
            projectFilter.value = currentValue;
        }

        // Hide loading state
        loadingRow.style.display = 'none';

        // Clear existing tasks except for the loading and no tasks messages
        const existingRows = tasksTableBody.querySelectorAll('tr:not(#loadingTasks):not(#noTasksMessage)');
        existingRows.forEach(row => row.remove());

        if (!data.tasks || data.tasks.length === 0) {
            noTasksMessage.style.display = 'table-row';
            return;
        }

        // Add tasks to the table
        data.tasks.forEach(task => {
            const daysUntilDue = getDaysUntilDue(task.due_date);
            const isUrgent = daysUntilDue <= 2 && task.status !== 'completed';
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="d-flex flex-column">
                        <span class="fw-medium ${isUrgent ? 'text-danger' : ''}">${escapeHtml(task.task_name)}</span>
                        <small class="text-muted text-truncate" style="max-width: 300px;" title="${escapeHtml(task.description)}">
                            ${escapeHtml(task.description)}
                        </small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span>${escapeHtml(task.project_name)}</span>
                        <small class="text-muted">${escapeHtml(task.client_name || '')}</small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span>${formatDate(task.due_date)}</span>
                        <small class="${isUrgent ? 'text-danger' : 'text-muted'}">
                            ${daysUntilDue === 0 ? 'Due today' : 
                              daysUntilDue < 0 ? `${Math.abs(daysUntilDue)} days overdue` :
                              `${daysUntilDue} days left`}
                        </small>
                    </div>
                </td>
                <td>
                    <span class="badge ${getStatusBadgeClass(task.status)}">
                        ${capitalizeFirst(task.status.replace('_', ' '))}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="viewTaskDetails(${task.task_id})"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm ${task.status === 'completed' ? 'btn-outline-warning' : 'btn-outline-success'}" 
                                onclick="toggleTaskStatus(${task.task_id}, '${task.status}')"
                                title="${task.status === 'completed' ? 'Mark as Pending' : 'Mark as Completed'}">
                            <i class="fas ${task.status === 'completed' ? 'fa-undo' : 'fa-check'}"></i>
                        </button>
                    </div>
                </td>
            `;
            tasksTableBody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading tasks:', error);
        loadingRow.style.display = 'none';
        noTasksMessage.style.display = 'table-row';
        noTasksMessage.querySelector('p').textContent = 'Error loading tasks. Please try again.';
    }
}
