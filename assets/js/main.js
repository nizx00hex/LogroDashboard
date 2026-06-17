// Modal functions
function openAddStaffModal() {
    document.getElementById('addStaffModal').style.display = 'block';
}

function closeAddStaffModal() {
    document.getElementById('addStaffModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addStaffModal');
    if (event.target === modal) {
        closeAddStaffModal();
    }
}

// Mark Attendance
async function markAttendance(staffId, button) {
    const originalText = button.textContent;
    button.textContent = 'Processing...';
    button.disabled = true;
    
    try {
        const response = await fetch('../ajax/mark_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ staff_id: staffId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the button and status badge
            button.textContent = 'Marked';
            button.classList.remove('btn-mark');
            button.classList.add('btn-marked');
            
            // Update the status badge
            const card = button.closest('.staff-card');
            const badgeSpan = card.querySelector('.attendance-status .badge');
            const statusText = result.status === 'full_day' ? 'Full Day' : 'Half Day';
            const badgeClass = result.status === 'full_day' ? 'badge-success' : 'badge-warning';
            
            badgeSpan.textContent = statusText;
            badgeSpan.className = `badge ${badgeClass}`;
            
            showNotification('Attendance marked successfully!', 'success');
        } else {
            button.textContent = originalText;
            button.disabled = false;
            showNotification(result.message || 'Failed to mark attendance', 'error');
        }
    } catch (error) {
        button.textContent = originalText;
        button.disabled = false;
        showNotification('Network error. Please try again.', 'error');
    }
}

// Remove Staff
async function removeStaff(staffId, staffName) {
    const confirmed = confirm(`Are you sure you want to remove ${staffName}? This action cannot be undone.`);
    
    if (!confirmed) return;
    
    try {
        const response = await fetch('../ajax/remove_staff.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ staff_id: staffId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Remove the staff card from DOM
            const card = document.querySelector(`.staff-card[data-staff-id="${staffId}"]`);
            if (card) {
                card.remove();
            }
            showNotification('Staff removed successfully!', 'success');
        } else {
            showNotification(result.message || 'Failed to remove staff', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
}

// Toggle Pause/Unpause
async function togglePause(staffId, action) {
    const endpoint = action === 'pause' ? '../ajax/pause_staff.php' : '../ajax/unpause_staff.php';
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ staff_id: staffId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload the page to reflect changes
            location.reload();
        } else {
            showNotification(result.message || 'Failed to update staff status', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
}

// View Staff Details
function viewDetails(staffId) {
    window.location.href = `staff_details.php?id=${staffId}`;
}

// Notification System
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        background: ${type === 'success' ? '#48bb78' : '#f56565'};
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add animation styles
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
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);