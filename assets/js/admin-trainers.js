// Global variables
let currentTrainerId = null;
let currentClientId = null;
let currentFromTrainerId = null;

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => button.classList.remove('active'));
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Modal management
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Trainer schedule management
function manageSchedule(trainerId, trainerName) {
    currentTrainerId = trainerId;
    document.getElementById('trainerName').textContent = trainerName;
    
    // Load existing schedule data
    loadTrainerSchedule(trainerId);
    
    openModal('scheduleModal');
}

function loadTrainerSchedule(trainerId) {
    // Send AJAX request to get existing schedule
    const formData = new FormData();
    formData.append('action', 'get_trainer_schedule');
    formData.append('trainer_id', trainerId);
    
    fetch('admin-trainers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear all fields first
            const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            days.forEach(day => {
                document.getElementById(day + '_start').value = '';
                document.getElementById(day + '_end').value = '';
                document.getElementById(day + '_available').checked = false;
            });
            
            // Fill in existing schedule data
            data.schedule.forEach(schedule => {
                const dayMap = {
                    'Monday': 'mon',
                    'Tuesday': 'tue',
                    'Wednesday': 'wed',
                    'Thursday': 'thu',
                    'Friday': 'fri',
                    'Saturday': 'sat',
                    'Sunday': 'sun'
                };
                
                const dayKey = dayMap[schedule.day_of_week];
                if (dayKey) {
                    document.getElementById(dayKey + '_start').value = schedule.start_time;
                    document.getElementById(dayKey + '_end').value = schedule.end_time;
                    document.getElementById(dayKey + '_available').checked = schedule.is_available == 1;
                }
            });
        } else {
            // Set default values if no schedule exists
            const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            days.forEach(day => {
                document.getElementById(day + '_start').value = '09:00';
                document.getElementById(day + '_end').value = '17:00';
                document.getElementById(day + '_available').checked = true;
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading schedule data', 'error');
    });
}

function saveSchedule() {
    if (!currentTrainerId) {
        showAlert('Error: No trainer selected', 'error');
        return;
    }
    
    const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const scheduleData = [];
    
    days.forEach((day, index) => {
        const startTime = document.getElementById(day + '_start').value;
        const endTime = document.getElementById(day + '_end').value;
        const isAvailable = document.getElementById(day + '_available').checked;
        
        // Save schedule data if both start and end times are provided
        if (startTime && endTime) {
            scheduleData.push({
                day: dayNames[index],
                start_time: startTime,
                end_time: endTime,
                is_available: isAvailable
            });
        }
    });
    
    // Send AJAX request to save schedule
    const formData = new FormData();
    formData.append('action', 'update_trainer_schedule');
    formData.append('trainer_id', currentTrainerId);
    formData.append('schedule_data', JSON.stringify(scheduleData));
    
    fetch('admin-trainers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeModal('scheduleModal');
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while saving the schedule', 'error');
    });
}

// Client switching functionality
function switchClientTrainer(clientId, fromTrainerId, clientName) {
    currentClientId = clientId;
    currentFromTrainerId = fromTrainerId;
    document.getElementById('clientName').textContent = clientName;
    
    // Reset trainer selection
    document.getElementById('newTrainerSelect').value = '';
    
    openModal('switchModal');
}

function confirmSwitch() {
    const newTrainerId = document.getElementById('newTrainerSelect').value;
    
    if (!newTrainerId) {
        showAlert('Please select a new trainer', 'error');
        return;
    }
    
    if (newTrainerId == currentFromTrainerId) {
        showAlert('Client is already assigned to this trainer', 'error');
        return;
    }
    
    // Send AJAX request to switch client
    const formData = new FormData();
    formData.append('action', 'switch_client');
    formData.append('client_id', currentClientId);
    formData.append('from_trainer_id', currentFromTrainerId);
    formData.append('to_trainer_id', newTrainerId);
    
    fetch('admin-trainers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeModal('switchModal');
            // Reload page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while switching the client', 'error');
    });
}

// Trainer status toggle
function toggleTrainerStatus(trainerId, newStatus) {
    const action = newStatus === 'true' ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this trainer?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'toggle_trainer_status');
    formData.append('trainer_id', trainerId);
    formData.append('is_active', newStatus);
    
    fetch('admin-trainers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // Reload page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while updating trainer status', 'error');
    });
}

// View trainer clients
function viewClients(trainerId) {
    // Switch to clients tab and filter by trainer
    switchTab('clients');
    
    // Scroll to clients section
    document.getElementById('clients-tab').scrollIntoView({ behavior: 'smooth' });
    
    // You could add filtering functionality here
    showAlert('Switched to Client Management tab', 'info');
}

// Alert system
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
    `;
    
    // Set background color based on type
    switch (type) {
        case 'success':
            alert.style.backgroundColor = '#28a745';
            break;
        case 'error':
            alert.style.backgroundColor = '#dc3545';
            break;
        case 'warning':
            alert.style.backgroundColor = '#ffc107';
            alert.style.color = '#333';
            break;
        case 'info':
        default:
            alert.style.backgroundColor = '#007bff';
            break;
    }
    
    alert.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; margin-left: 10px;">&times;</button>
        </div>
    `;
    
    document.body.appendChild(alert);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

// Add CSS animation for alerts
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .alert {
        animation: slideIn 0.3s ease-out;
    }
`;
document.head.appendChild(style);

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Trainer Management System initialized');
    
    // Add any initialization code here
    setupEventListeners();
});

function setupEventListeners() {
    // Add event listeners for any dynamic content
    
    // Example: Search functionality
    const searchInput = document.getElementById('trainerSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterTrainers(this.value);
        });
    }
}

function filterTrainers(searchTerm) {
    const trainerCards = document.querySelectorAll('.trainer-card');
    
    trainerCards.forEach(card => {
        const trainerName = card.querySelector('.trainer-info h3').textContent.toLowerCase();
        const trainerEmail = card.querySelector('.trainer-info p').textContent.toLowerCase();
        
        if (trainerName.includes(searchTerm.toLowerCase()) || trainerEmail.includes(searchTerm.toLowerCase())) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Export functions for global access
window.switchTab = switchTab;
window.manageSchedule = manageSchedule;
window.saveSchedule = saveSchedule;
window.switchClientTrainer = switchClientTrainer;
window.confirmSwitch = confirmSwitch;
window.toggleTrainerStatus = toggleTrainerStatus;
window.viewClients = viewClients;
window.closeModal = closeModal;
