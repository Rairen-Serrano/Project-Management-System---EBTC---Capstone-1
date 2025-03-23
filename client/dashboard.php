<?php
session_start();
require_once '../dbconnect.php';

// Debugging
error_log('Session data: ' . print_r($_SESSION, true));

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Check if this is first login (needs PIN setup)
$stmt = $pdo->prepare("SELECT first_login FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['first_login'] == 1) {
    // Show PIN setup modal on page load
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const pinSetupModal = new bootstrap.Modal(document.getElementById('pinSetupModal'));
            pinSetupModal.show();
        });
    </script>";
} elseif (!isset($_SESSION['pin_verified'])) {
    // Show PIN verification modal on page load only if not first login
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const pinVerificationModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinVerificationModal.show();
        });
    </script>";
}

// Get user's appointments
$stmt = $pdo->prepare("
    SELECT appointment_id, service, date, time, status, created_at
    FROM appointments 
    WHERE client_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | EBTC PMS</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Add this to your existing scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle PIN setup
        document.getElementById('savePinBtn').addEventListener('click', function() {
            const setupPinInputs = Array.from(document.querySelectorAll('.setup-pin'));
            const confirmPinInputs = Array.from(document.querySelectorAll('.confirm-pin'));
            
            const setupPin = setupPinInputs.map(input => input.value).join('');
            const confirmPin = confirmPinInputs.map(input => input.value).join('');
            
            const errorElement = document.getElementById('pinSetupError');
            
            if (setupPin.length !== 4 || confirmPin.length !== 4) {
                errorElement.textContent = 'Please enter a 4-digit PIN';
                errorElement.style.display = 'block';
                return;
            }
            
            if (setupPin !== confirmPin) {
                errorElement.textContent = 'PINs do not match';
                errorElement.style.display = 'block';
                confirmPinInputs.forEach(input => input.value = '');
                return;
            }
            
            // Send PIN to server
            fetch('../api/auth/setup_first_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ pin: setupPin })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    errorElement.textContent = data.message || 'Failed to set PIN';
                    errorElement.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorElement.textContent = 'An error occurred. Please try again.';
                errorElement.style.display = 'block';
            });
        });

        // Handle PIN verification
        const verifyPinBtn = document.getElementById('verifyPinBtn');
        if (verifyPinBtn) {
            verifyPinBtn.addEventListener('click', function() {
                const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
                const pin = Array.from(pinInputs).map(input => input.value).join('');
                
                if (pin.length !== 4) {
                    document.getElementById('pinVerificationError').textContent = 'Please enter a 4-digit PIN';
                    document.getElementById('pinVerificationError').style.display = 'block';
                    return;
                }
                
                fetch('../api/auth/verify_pin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ pin })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the modal properly
                        const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                        modal.hide();
                        
                        // Clean up modal artifacts
                        document.querySelector('.modal-backdrop').remove();
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                        
                        // Show the main content
                        document.querySelector('.client-main-content').style.display = 'block';
                        
                        // Refresh the page to ensure everything is properly loaded
                        window.location.reload();
                    } else {
                        document.getElementById('pinVerificationError').textContent = data.message || 'Invalid PIN. Please try again.';
                        document.getElementById('pinVerificationError').style.display = 'block';
                        
                        if (data.lockout_duration) {
                            // Disable the verify button and show countdown
                            verifyPinBtn.disabled = true;
                            startLockoutCountdown(data.lockout_duration);
                        }
                        
                        pinInputs.forEach(input => input.value = '');
                        pinInputs[0].focus();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('pinVerificationError').textContent = 'An error occurred. Please try again.';
                    document.getElementById('pinVerificationError').style.display = 'block';
                });
            });
        }

        function startLockoutCountdown(duration) {
            const verifyBtn = document.getElementById('verifyPinBtn');
            const errorDiv = document.getElementById('pinVerificationError');
            let timeLeft = duration;

            const countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                errorDiv.textContent = `Account locked. Please try again in ${minutes}:${seconds.toString().padStart(2, '0')} minutes`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    verifyBtn.disabled = false;
                    errorDiv.textContent = 'You can now try again.';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                    }, 3000);
                }
                timeLeft--;
            }, 1000);
        }

        // Handle PIN input navigation
        function setupPinInputs(containerSelector) {
            const inputs = document.querySelectorAll(`${containerSelector} .pin-input`);
            
            inputs.forEach((input, index) => {
                input.addEventListener('keyup', function(e) {
                    // Allow only numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Move to next input if value is entered
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Handle backspace
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }

                    // Submit when last input is filled
                    if (index === inputs.length - 1 && this.value) {
                        document.getElementById('verifyPinBtn').click();
                    }
                });
            });
        }

        // Initialize PIN inputs
        setupPinInputs('#pinSetupModal');
        setupPinInputs('#pinVerificationModal');

        function cancelAppointment(appointmentId) {
            // Store appointment ID and show PIN verification modal
            document.getElementById('appointmentToCancel').value = appointmentId;
            const pinModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            
            // Clear previous PIN inputs and errors
            const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
            pinInputs.forEach(input => input.value = '');
            document.getElementById('pinError').style.display = 'none';
            
            pinModal.show();
        }

        // Handle PIN verification for cancellation
        document.getElementById('confirmCancellation').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentToCancel').value;
            const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
            const pin = Array.from(pinInputs).map(input => input.value).join('');
            const errorDiv = document.getElementById('pinError');

            if (pin.length !== 4) {
                errorDiv.textContent = 'Please enter a valid 4-digit PIN';
                errorDiv.style.display = 'block';
                return;
            }

            // Disable the confirm button and show loading state
            const confirmButton = document.getElementById('confirmCancellation');
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            // Create form data
            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            formData.append('pin', pin);

            // Send cancellation request
            fetch('cancel_appointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('pinVerificationModal'));
                    modal.hide();
                    
                    // Show success message and reload
                    alert(data.message);
                    window.location.reload();
                } else {
                    // Show error message
                    errorDiv.textContent = data.message || 'Failed to cancel appointment';
                    errorDiv.style.display = 'block';
                    
                    // Clear PIN inputs
                    pinInputs.forEach(input => input.value = '');
                    pinInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = 'An error occurred while canceling the appointment';
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                // Re-enable the confirm button
                confirmButton.disabled = false;
                confirmButton.innerHTML = 'Confirm Cancellation';
            });
        });

        function rescheduleAppointment(appointmentId) {
            // Get current appointment details
            fetch(`get_appointment_details.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    // Store current appointment date
                    document.getElementById('appointmentToReschedule').value = appointmentId;
                    
                    // Add a hidden input for the current appointment date
                    const currentDateInput = document.createElement('input');
                    currentDateInput.type = 'hidden';
                    currentDateInput.id = 'currentAppointmentDate';
                    currentDateInput.value = data.date;
                    document.getElementById('rescheduleModal').querySelector('.modal-body').appendChild(currentDateInput);
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading appointment details');
                });
        }

        // Handle reschedule confirmation
        document.getElementById('confirmReschedule').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentToReschedule').value;
            const newDate = document.getElementById('newDate').value;
            const newTime = document.getElementById('newTime').value;

            if (!newDate || !newTime) {
                alert('Please select both date and time');
                return;
            }

            // Show PIN verification modal for reschedule
            const pinModal = new bootstrap.Modal(document.getElementById('pinVerificationRescheduleModal'));
            pinModal.show();
        });

        // Handle PIN verification for reschedule
        document.getElementById('confirmPinReschedule').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentToReschedule').value;
            const newDate = document.getElementById('newDate').value;
            const newTime = document.getElementById('newTime').value;
            const pinInputs = document.querySelectorAll('#pinVerificationRescheduleModal .pin-input');
            const pin = Array.from(pinInputs).map(input => input.value).join('');

            if (pin.length !== 4) {
                alert('Please enter a valid 4-digit PIN');
                return;
            }

            // Send reschedule request with PIN
            fetch('reschedule_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `appointment_id=${appointmentId}&new_date=${newDate}&new_time=${newTime}&pin=${pin}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to reschedule appointment');
                    // Clear PIN inputs
                    pinInputs.forEach(input => input.value = '');
                    pinInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rescheduling the appointment');
            });
        });

        // Handle PIN input navigation for all PIN modals
        function setupPinInputs(modalId) {
            const inputs = document.querySelectorAll(`${modalId} .pin-input`);
            inputs.forEach((input, index) => {
                // Clear inputs when modal is shown
                const modal = document.querySelector(modalId);
                modal.addEventListener('shown.bs.modal', function() {
                    input.value = '';
                    if (index === 0) input.focus();
                });

                input.addEventListener('keyup', function(e) {
                    // Allow only numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Move to next input if value is entered
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Handle backspace
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                // Prevent non-numeric input
                input.addEventListener('keypress', function(e) {
                    if (!/[0-9]/.test(e.key)) {
                        e.preventDefault();
                    }
                });
            });
        }

        // Initialize PIN inputs for both modals
        setupPinInputs('#pinVerificationModal');
        setupPinInputs('#pinVerificationRescheduleModal');

        // Add this function to get booked times for a specific date
        function getBookedTimes(date, currentAppointmentId) {
            return fetch('get_booked_times.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    date: date,
                    appointment_id: currentAppointmentId 
                })
            })
            .then(response => response.json())
            .then(data => data.bookedTimes || []);
        }

        // Modify the date change handler
        document.getElementById('newDate').addEventListener('change', function() {
            const selectedDate = this.value;
            const appointmentId = document.getElementById('appointmentToReschedule').value;
            
            console.log('Date changed:', {
                selectedDate,
                appointmentId
            });
            
            if (!selectedDate) return;

            // Validate selected date
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selected = new Date(selectedDate);
            
            if (selected < today) {
                alert('Please select a future date');
                this.value = '';
                return;
            }

            // Update available time slots
            updateTimeSlots(selectedDate, appointmentId);
        });

        // Modify the time change handler
        document.getElementById('newTime').addEventListener('change', function() {
            const selectedTime = this.value;
            const selectedDate = document.getElementById('newDate').value;
            const bookedTimes = JSON.parse(this.dataset.bookedTimes || '[]');
            
            if (!selectedTime || !selectedDate) return;

            // Check if the selected time is in the booked times array
            if (bookedTimes.includes(selectedTime)) {
                alert('This time slot is already booked. Please select a different time.');
                this.value = '';
                return;
            }

            // Additional time validation (if needed)
            const now = new Date();
            const selected = new Date(selectedDate + ' ' + selectedTime);
            
            if (selected <= now) {
                alert('Please select a future time');
                this.value = '';
            }
        });

        function generateTimeOptions(startHour = 9, endHour = 15) {
            const options = [];
            for (let hour = startHour; hour <= endHour; hour++) {
                for (let minute of ['00', '30']) {
                    if (hour === endHour && minute === '30') continue; // Skip 15:30
                    const time = `${hour.toString().padStart(2, '0')}:${minute}`;
                    options.push(time);
                }
            }
            return options;
        }

        // Add this function to update time slots
        function updateTimeSlots(date, appointmentId) {
            const timeInput = document.getElementById('newTime');
            
            console.log('Updating time slots for date:', date, 'appointmentId:', appointmentId);
            
            // Clear current time selection
            timeInput.innerHTML = '<option value="">Select Time</option>';
            
            if (!date) {
                console.log('No date provided');
                return;
            }

            // Get booked times for the selected date
            fetch('get_booked_times.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    date: date,
                    appointment_id: appointmentId 
                })
            })
            .then(response => {
                console.log('Raw response:', response);
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to get booked times');
                }

                const bookedTimes = data.bookedTimes || [];
                const currentTime = data.currentTime;
                const currentDate = data.currentDate;
                const selectedDate = new Date(date);
                const today = new Date();
                const availableTimes = generateTimeOptions();

                console.log('Processing times:', {
                    bookedTimes,
                    currentTime,
                    currentDate,
                    selectedDate,
                    availableTimes
                });

                // Create time slot options
                availableTimes.forEach(time => {
                    const option = document.createElement('option');
                    option.value = time;
                    option.textContent = new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });

                    // Disable if time is booked or it's the current appointment's time on the same date
                    const isBooked = bookedTimes.includes(time);
                    const isCurrentTime = currentTime === time && 
                                        date === currentDate;
                    
                    if (isBooked || isCurrentTime) {
                        option.disabled = true;
                        option.classList.add('text-muted');
                        option.textContent += (isCurrentTime ? ' (Current)' : ' (Booked)');
                    }

                    timeInput.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error updating time slots:', error);
                // Show more detailed error message
                const errorMessage = error.message || 'Unknown error occurred';
                alert(`Error loading available time slots: ${errorMessage}`);
            });
        }
    });
    </script>
</head>
<body id="dashboardPage">
    
    <?php include 'client_header.php'; ?>
    
    <div class="client-dashboard-wrapper">
        <!-- Main Content -->
        <div class="client-main-content">
            <!-- Mobile Toggle Button -->
            <button class="btn btn-primary d-md-none mb-3" id="clientSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Appointments Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">My Appointments</h5>
                            <a href="../book_appointment.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Book New Appointment
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No Appointments Found</h5>
                                    <p class="text-muted">You haven't booked any appointments yet.</p>
                                    <a href="../book_appointment.php" class="btn btn-primary">
                                        Book Your First Appointment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr>
                                                    <td style="min-width: 150px;"><?php echo htmlspecialchars($appointment['service']); ?></td>
                                                    <td style="min-width: 120px;"><?php echo date('M d, Y', strtotime($appointment['date'])); ?></td>
                                                    <td style="min-width: 100px;"><?php echo date('h:i A', strtotime($appointment['time'])); ?></td>
                                                    <td style="min-width: 100px;">
                                                        <?php
                                                        $statusClass = '';
                                                        switch($appointment['status']) {
                                                            case 'pending':
                                                                $statusClass = 'bg-warning';
                                                                break;
                                                            case 'confirmed':
                                                                $statusClass = 'bg-success';
                                                                break;
                                                            case 'cancelled':
                                                                $statusClass = 'bg-danger';
                                                                break;
                                                            case 'completed':
                                                                $statusClass = 'bg-info';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?> px-3 py-2">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center actions-cell">
                                                        <?php if ($appointment['status'] === 'pending'): ?>
                                                            <div class="action-buttons-wrapper">
                                                                <button class="btn btn-secondary btn-action" 
                                                                        onclick="rescheduleAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                    <h1>Reschedule</h1>
                                                                </button>
                                                                <button class="btn btn-danger btn-action" 
                                                                        onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                    <h1>Cancel</h1>
                                                                </button>
                                                                <button class="btn btn-info btn-action" 
                                                                        onclick="viewDetails(<?php echo $appointment['appointment_id']; ?>)">
                                                                    <h1>View</h1>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="btn btn-info btn-action" 
                                                                    onclick="viewDetails(<?php echo $appointment['appointment_id']; ?>)">
                                                                <h1>View</h1>
                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                <div class="modal-body">
                    <!-- Details will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
                    </div>

    <!-- PIN Setup Modal -->
    <div class="modal fade" id="pinSetupModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Up Your PIN</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Please set up a 4-digit PIN code for your account security.
                    </div>
                    <form id="pinSetupForm">
                        <div class="mb-4">
                            <label class="form-label">Enter New PIN</label>
                            <div class="d-flex justify-content-center gap-2">
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input setup-pin text-center" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm PIN</label>
                            <div class="d-flex justify-content-center gap-2">
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                                <input type="password" class="form-control pin-input confirm-pin text-center" maxlength="1" pattern="[0-9]" required>
                            </div>
                        </div>
                        <div id="pinSetupError" class="text-danger text-center mt-2" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="savePinBtn">Save PIN</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Verification Modal -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enter PIN to Cancel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to confirm cancellation.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="form-control pin-input text-center" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <input type="hidden" id="appointmentToCancel">
                    <div id="pinError" class="text-danger text-center mt-2" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmCancellation">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="appointmentToReschedule">
                    <div class="mb-3">
                        <label class="form-label">New Date</label>
                        <input type="date" class="form-control" id="newDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Time</label>
                        <select class="form-control" id="newTime" required>
                            <option value="">Select Time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmReschedule">Confirm Reschedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Verification Modal for Reschedule -->
    <div class="modal fade" id="pinVerificationRescheduleModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enter PIN to Reschedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN code to confirm rescheduling.</p>
                    <div class="pin-input-group">
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmPinReschedule">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .pin-input {
        width: 50px !important;
        height: 50px;
        font-size: 24px;
        border-radius: 8px;
    }

    .pin-input:focus {
        border-color: #235347;
        box-shadow: 0 0 0 0.2rem rgba(35, 83, 71, 0.25);
    }

    select option:disabled {
        color: #6c757d;
        background-color: #e9ecef;
    }
    
    select option.text-muted {
        font-style: italic;
    }
    </style>

</body>
</html> 