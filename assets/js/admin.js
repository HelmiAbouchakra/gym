// Admin Dashboard JavaScript Functions

// Modal Management
function openCreateModal() {
    document.getElementById('createModal').style.display = 'block';
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

function openCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'block';
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'none';
}

function openCreateClassModal() {
    document.getElementById('createClassModal').style.display = 'block';
}

function closeCreateClassModal() {
    document.getElementById('createClassModal').style.display = 'none';
}

function openCreateScheduleModal() {
    document.getElementById('createScheduleModal').style.display = 'block';
}

function closeCreateScheduleModal() {
    document.getElementById('createScheduleModal').style.display = 'none';
}

// Search Functionality
function initializeSearch() {
    // Search users
    const searchUsers = document.getElementById('searchUsers');
    if (searchUsers) {
        searchUsers.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            if (table) {
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            }
        });
    }
    
    // Search classes
    const searchClasses = document.getElementById('searchClasses');
    if (searchClasses) {
        searchClasses.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('classesTable');
            if (table) {
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            }
        });
    }
}

// Edit Functions (placeholders for future implementation)
function editUser(userId) {
    alert('Edit user functionality would be implemented here for user ID: ' + userId);
}

function editClass(classId) {
    alert('Edit class functionality would be implemented here for class ID: ' + classId);
}

// Chart Initialization
function initializeCharts() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx && typeof Chart !== 'undefined') {
        // Get data from PHP variables (should be set in the page)
        const revenueData = window.revenueData || [];
        
        new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: revenueData.map(item => item.month),
                datasets: [{
                    label: 'Monthly Revenue ($)',
                    data: revenueData.map(item => item.monthly_revenue),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Memberships Sold',
                    data: revenueData.map(item => item.memberships_sold),
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }
    
    // Weekly Chart
    const weeklyCtx = document.getElementById('weeklyChart');
    if (weeklyCtx && typeof Chart !== 'undefined') {
        const weeklyData = window.weeklyData || [];
        
        new Chart(weeklyCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: weeklyData.map(item => item.day_of_week),
                datasets: [{
                    label: 'Classes',
                    data: weeklyData.map(item => item.class_count),
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }, {
                    label: 'Bookings',
                    data: weeklyData.map(item => item.total_bookings),
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Attendance Chart
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx && typeof Chart !== 'undefined') {
        const attendanceData = window.attendanceData || {
            attended: 0,
            no_shows: 0,
            confirmed: 0
        };
        
        new Chart(attendanceCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Attended', 'No Shows', 'Confirmed'],
                datasets: [{
                    data: [
                        attendanceData.attended,
                        attendanceData.no_shows,
                        attendanceData.confirmed
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(0, 123, 255, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Form Validation
function validateCreateUserForm() {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    if (!username || !email || !password) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    return true;
}

function validateCreateClassForm() {
    const name = document.querySelector('input[name="name"]').value.trim();
    const capacity = parseInt(document.querySelector('input[name="capacity"]').value);
    const duration = parseInt(document.querySelector('input[name="duration"]').value);
    
    if (!name) {
        alert('Please enter a class name.');
        return false;
    }
    
    if (capacity <= 0 || capacity > 100) {
        alert('Capacity must be between 1 and 100.');
        return false;
    }
    
    if (duration < 15 || duration > 180) {
        alert('Duration must be between 15 and 180 minutes.');
        return false;
    }
    
    return true;
}

// Confirmation Dialogs
function confirmDelete(itemType, itemName) {
    return confirm(`Are you sure you want to delete this ${itemType}? ${itemName ? `(${itemName})` : ''}\n\nThis action cannot be undone.`);
}

function confirmStatusChange(itemType, currentStatus) {
    const newStatus = currentStatus ? 'deactivate' : 'activate';
    return confirm(`Are you sure you want to ${newStatus} this ${itemType}?`);
}

// Utility Functions
function formatTime(timeString) {
    const time = new Date(`2000-01-01 ${timeString}`);
    return time.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        return `${Math.floor(diffInSeconds / 60)}m ago`;
    } else if (diffInSeconds < 86400) {
        return `${Math.floor(diffInSeconds / 3600)}h ago`;
    } else {
        return `${Math.floor(diffInSeconds / 86400)}d ago`;
    }
}

// Progress Bar Animation
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
}

// Statistics Counter Animation
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 20);
    });
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    initializeSearch();
    
    // Initialize charts
    initializeCharts();
    
    // Animate progress bars and counters
    setTimeout(() => {
        animateProgressBars();
        animateCounters();
    }, 500);
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // Add form validation
    const createUserForm = document.querySelector('form[action*="create_user"]');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            if (!validateCreateUserForm()) {
                e.preventDefault();
            }
        });
    }
    
    const createClassForm = document.querySelector('form[action*="create_class"]');
    if (createClassForm) {
        createClassForm.addEventListener('submit', function(e) {
            if (!validateCreateClassForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Add confirmation to delete buttons
    const deleteButtons = document.querySelectorAll('button[name*="delete"], input[value*="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const itemType = this.closest('form').querySelector('input[name*="class_id"]') ? 'class' : 'user';
            if (!confirmDelete(itemType)) {
                e.preventDefault();
            }
        });
    });
});

// Export functions for global access
window.adminFunctions = {
    openCreateModal,
    closeCreateModal,
    openCreateUserModal,
    closeCreateUserModal,
    openCreateClassModal,
    closeCreateClassModal,
    openCreateScheduleModal,
    closeCreateScheduleModal,
    editUser,
    editClass,
    confirmDelete,
    confirmStatusChange
};
