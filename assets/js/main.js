/**
 * Attendance Management System (LOGRO AMS) - Complete Non-Reloading AJAX Architecture
 */

// Modal Controls
function openAddStaffModal() {
    const modal = document.getElementById('addStaffModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        const nameInput = document.getElementById('name');
        if (nameInput) nameInput.focus();
    }
}

function closeAddStaffModal() {
    const modal = document.getElementById('addStaffModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('addStaffModal');
    if (event.target === modal) {
        closeAddStaffModal();
    }
});

window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddStaffModal();
    }
});

// Helper: Adjust KPI Counters in DOM
function adjustKpi(statKey, delta) {
    const el = document.getElementById(`kpi-${statKey}`);
    if (el) {
        let val = parseInt(el.textContent.trim(), 10) || 0;
        val = Math.max(0, val + delta);
        el.textContent = val;
    }
}

// 1. Mark Attendance (Zero Page Reload)
async function markAttendance(staffId, button) {
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Marking...';
    button.disabled = true;

    try {
        const response = await fetch('../ajax/mark_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: staffId })
        });

        const result = await response.json();

        if (result.success) {
            button.innerHTML = '<i class="fa-solid fa-check-double"></i> Marked';
            button.className = 'btn btn-marked btn-sm btn-block';
            button.disabled = true;

            const card = button.closest('.staff-card');
            if (card) {
                const isFullDay = (result.status === 'full_day');
                const badge = card.querySelector('.attendance-status-banner .badge');
                if (badge) {
                    badge.className = `badge ${isFullDay ? 'badge-success' : 'badge-warning'}`;
                    badge.innerHTML = `<i class="fa-solid ${isFullDay ? 'fa-circle-check' : 'fa-clock'}"></i> ${isFullDay ? 'Full Day' : 'Half Day'}`;
                }

                // Add or update arrival time in banner
                const banner = card.querySelector('.attendance-status-banner');
                let timeEl = banner.querySelector('.attendance-time-pill');
                const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                if (!timeEl) {
                    timeEl = document.createElement('span');
                    timeEl.className = 'attendance-time-pill';
                    timeEl.style.cssText = 'font-size: 13px; font-weight: 600; color: var(--neutral-600);';
                    banner.appendChild(timeEl);
                }
                timeEl.innerHTML = `<i class="fa-regular fa-clock"></i> ${nowTime}`;

                card.setAttribute('data-status', isFullDay ? 'present' : 'halfday');
                card.setAttribute('data-attendance-status', result.status);
                card.setAttribute('data-arrived-at', nowTime);
            }

            // Real-time KPI updates
            if (result.status === 'full_day') {
                adjustKpi('present', 1);
            } else {
                adjustKpi('halfday', 1);
            }
            adjustKpi('absent', -1);

            showNotification(result.message || 'Attendance logged successfully!', 'success');
        } else {
            button.innerHTML = originalHtml;
            button.disabled = false;
            showNotification(result.message || 'Failed to mark attendance', 'error');
        }
    } catch (error) {
        button.innerHTML = originalHtml;
        button.disabled = false;
        showNotification('Network error. Please try again.', 'error');
    }
}

// 2. Toggle Pause / Unpause (Zero Page Reload)
async function togglePause(staffId, action) {
    const endpoint = action === 'pause' ? '../ajax/pause_staff.php' : '../ajax/unpause_staff.php';

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: staffId })
        });

        const result = await response.json();

        if (result.success) {
            const card = document.querySelector(`.staff-card[data-staff-id="${staffId}"]`);
            if (card) {
                const isNowPaused = (action === 'pause');
                const avatarDot = card.querySelector('.avatar-status-dot');
                const badge = card.querySelector('.attendance-status-banner .badge');
                const mainBtnContainer = card.querySelector('.staff-card-actions > div:first-child');
                const toggleBtn = card.querySelector('.card-action-more button[onclick*="togglePause"]');
                const banner = card.querySelector('.attendance-status-banner');
                let timeEl = banner ? banner.querySelector('.attendance-time-pill') : null;

                if (isNowPaused) {
                    const prevStatus = card.getAttribute('data-status') || 'absent';
                    card.classList.add('paused');
                    card.setAttribute('data-status', 'paused');

                    if (avatarDot) {
                        avatarDot.className = 'avatar-status-dot paused';
                        avatarDot.title = 'Paused';
                    }
                    if (badge) {
                        badge.className = 'badge badge-secondary';
                        badge.innerHTML = '<i class="fa-solid fa-circle-pause"></i> Account Paused';
                    }
                    if (mainBtnContainer) {
                        mainBtnContainer.innerHTML = '<button type="button" class="btn btn-secondary btn-sm btn-block" disabled><i class="fa-solid fa-ban"></i> Inactive</button>';
                    }
                    if (toggleBtn) {
                        toggleBtn.className = 'btn btn-success btn-sm';
                        toggleBtn.setAttribute('onclick', `togglePause(${staffId}, 'unpause')`);
                        toggleBtn.title = 'Unpause Staff Account';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
                    }

                    // Adjust KPIs based on status before pause
                    adjustKpi('paused', 1);
                    if (prevStatus === 'present') {
                        adjustKpi('present', -1);
                    } else if (prevStatus === 'halfday') {
                        adjustKpi('halfday', -1);
                    } else {
                        adjustKpi('absent', -1);
                    }
                } else {
                    // UNPAUSE: Check if staff has already marked attendance today
                    card.classList.remove('paused');

                    const todayAtt = result.today_attendance;
                    const attStatus = todayAtt ? todayAtt.status : (card.getAttribute('data-attendance-status') || '');
                    let arrivedAt = (todayAtt && todayAtt.arrived_at) ? todayAtt.arrived_at : (card.getAttribute('data-arrived-at') || '');
                    const hasAttended = (attStatus === 'full_day' || attStatus === 'half_day');

                    if (avatarDot) {
                        avatarDot.className = 'avatar-status-dot active';
                        avatarDot.title = 'Active';
                    }

                    if (hasAttended) {
                        const isFull = (attStatus === 'full_day');
                        card.setAttribute('data-status', isFull ? 'present' : 'halfday');
                        card.setAttribute('data-attendance-status', attStatus);

                        if (badge) {
                            badge.className = `badge ${isFull ? 'badge-success' : 'badge-warning'}`;
                            badge.innerHTML = `<i class="fa-solid ${isFull ? 'fa-circle-check' : 'fa-clock'}"></i> ${isFull ? 'Full Day' : 'Half Day'}`;
                        }

                        // Ensure arrival time pill is displayed
                        if (arrivedAt) {
                            let displayTime = arrivedAt;
                            if (arrivedAt.includes(':')) {
                                const parts = arrivedAt.split(':');
                                if (parts.length >= 2) {
                                    let h = parseInt(parts[0], 10);
                                    const m = parts[1];
                                    const ampm = h >= 12 ? 'PM' : 'AM';
                                    h = h % 12 || 12;
                                    displayTime = (h < 10 ? '0' + h : h) + ':' + m + ' ' + ampm;
                                }
                            }
                            card.setAttribute('data-arrived-at', displayTime);
                            if (!timeEl && banner) {
                                timeEl = document.createElement('span');
                                timeEl.className = 'attendance-time-pill';
                                timeEl.style.cssText = 'font-size: 13px; font-weight: 600; color: var(--neutral-600);';
                                banner.appendChild(timeEl);
                            }
                            if (timeEl) {
                                timeEl.innerHTML = `<i class="fa-regular fa-clock"></i> ${displayTime}`;
                                timeEl.style.display = 'inline';
                            }
                        }

                        // Keep button disabled as 'Marked'
                        if (mainBtnContainer) {
                            mainBtnContainer.innerHTML = '<button type="button" class="btn btn-marked btn-sm btn-block" disabled><i class="fa-solid fa-check-double"></i> Marked</button>';
                        }

                        adjustKpi('paused', -1);
                        adjustKpi(isFull ? 'present' : 'halfday', 1);
                    } else {
                        // Has not attended today -> Absent, button enabled as 'Mark Present'
                        card.setAttribute('data-status', 'absent');
                        card.setAttribute('data-attendance-status', '');

                        if (badge) {
                            badge.className = 'badge badge-danger';
                            badge.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Absent';
                        }
                        if (timeEl) {
                            timeEl.style.display = 'none';
                        }
                        if (mainBtnContainer) {
                            mainBtnContainer.innerHTML = `<button type="button" class="btn btn-mark btn-sm btn-block" onclick="markAttendance(${staffId}, this)"><i class="fa-solid fa-check"></i> Mark Present</button>`;
                        }

                        adjustKpi('paused', -1);
                        adjustKpi('absent', 1);
                    }

                    if (toggleBtn) {
                        toggleBtn.className = 'btn btn-warning btn-sm';
                        toggleBtn.setAttribute('onclick', `togglePause(${staffId}, 'pause')`);
                        toggleBtn.title = 'Pause Staff Account';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                    }
                }
            }
            showNotification(`Staff account marked as ${action === 'pause' ? 'paused' : 'active'}.`, 'success');
        } else {
            showNotification(result.message || 'Action failed', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
}

// 3. Remove Staff (Zero Page Reload)
async function removeStaff(staffId, staffName) {
    const confirmed = confirm(`Are you sure you want to remove ${staffName}?\nThis action will delete all associated attendance records and QR codes permanently.`);
    if (!confirmed) return;

    try {
        const response = await fetch('../ajax/remove_staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: staffId })
        });

        const result = await response.json();

        if (result.success) {
            const card = document.querySelector(`.staff-card[data-staff-id="${staffId}"]`);
            if (card) {
                const currentStatus = card.getAttribute('data-status') || 'absent';
                card.style.transition = 'all 0.35s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.85)';
                setTimeout(() => card.remove(), 350);

                adjustKpi('total', -1);
                if (currentStatus === 'present') adjustKpi('present', -1);
                else if (currentStatus === 'halfday') adjustKpi('halfday', -1);
                else if (currentStatus === 'paused') adjustKpi('paused', -1);
                else adjustKpi('absent', -1);
            }
            showNotification(`Staff ${staffName} removed successfully.`, 'success');
        } else {
            showNotification(result.message || 'Failed to remove staff', 'error');
        }
    } catch (error) {
        showNotification('Network error. Please try again.', 'error');
    }
}

// 4. Add Staff Form via AJAX (Zero Page Reload)
document.addEventListener('DOMContentLoaded', function() {
    const addStaffForm = document.getElementById('addStaffForm');
    if (addStaffForm) {
        addStaffForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = addStaffForm.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            const formData = new FormData(addStaffForm);

            try {
                const response = await fetch('../ajax/add_staff_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success && result.staff) {
                    const staff = result.staff;
                    const staffGrid = document.getElementById('staffGrid');

                    if (staffGrid) {
                        const avatarImg = staff.profile_picture ? `../uploads/staff/${staff.profile_picture}` : '../uploads/staff/default.png';
                        const newCard = document.createElement('article');
                        newCard.className = 'staff-card';
                        newCard.setAttribute('data-staff-id', staff.id);
                        newCard.setAttribute('data-branch-id', staff.branch_id || '1');
                        newCard.setAttribute('data-name', (staff.name || '').toLowerCase());
                        newCard.setAttribute('data-phone', staff.phone || '');
                        newCard.setAttribute('data-email', (staff.email || '').toLowerCase());
                        newCard.setAttribute('data-status', 'absent');
                        newCard.style.animation = 'modalSlide 0.4s ease';

                        const bName = staff.branch_name || 'Logro Mens';
                        let bClass = 'badge-branch-default';
                        let bIcon = 'fa-shop';
                        if (bName.toLowerCase().includes('mens')) { bClass = 'badge-branch-mens'; bIcon = 'fa-user-tie'; }
                        else if (bName.toLowerCase().includes('elite')) { bClass = 'badge-branch-elite'; bIcon = 'fa-crown'; }
                        else if (bName.toLowerCase().includes('kids')) { bClass = 'badge-branch-kids'; bIcon = 'fa-child'; }
                        const bOpen = staff.branch_opening_time ? staff.branch_opening_time.substring(0, 5) : '08:00';

                        newCard.innerHTML = `
                            <div>
                                <div class="staff-card-header">
                                    <div class="staff-avatar-wrapper">
                                        <div class="staff-avatar">
                                            <img src="${avatarImg}" alt="${escapeHtml(staff.name)}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(staff.name)}&background=4f46e5&color=fff';">
                                        </div>
                                        <span class="avatar-status-dot active" title="Active"></span>
                                    </div>
                                    <div class="staff-info">
                                        <h3 class="staff-name" title="${escapeHtml(staff.name)}">${escapeHtml(staff.name)}</h3>
                                        <div class="staff-meta-row">
                                            <i class="fa-solid fa-phone staff-meta-icon"></i>
                                            <span>${escapeHtml(staff.phone || 'No phone')}</span>
                                        </div>
                                        <div class="staff-meta-row">
                                            <i class="fa-solid fa-envelope staff-meta-icon"></i>
                                            <span>${escapeHtml(staff.email || 'No email')}</span>
                                        </div>
                                        <div class="badge-branch ${bClass}">
                                            <i class="fa-solid ${bIcon}"></i>
                                            <span>${escapeHtml(bName)}</span>
                                            <span style="font-weight: 500; opacity: 0.8; font-size: 10px;">• Opens ${bOpen}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="attendance-status-banner" style="margin-top: 16px;">
                                    <span class="status-indicator-group">
                                        <span class="badge badge-danger">
                                            <i class="fa-solid fa-circle-xmark"></i> Absent
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div class="staff-card-actions">
                                <div>
                                    <button type="button" class="btn btn-mark btn-sm btn-block" onclick="markAttendance(${staff.id}, this)">
                                        <i class="fa-solid fa-check"></i> Mark Present
                                    </button>
                                </div>
                                <div class="card-action-more">
                                    <a href="staff_details.php?id=${staff.id}" class="btn btn-secondary btn-sm" title="View Full Attendance History">
                                        <i class="fa-solid fa-chart-line"></i> Details
                                    </a>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="togglePause(${staff.id}, 'pause')" title="Pause Staff Account">
                                        <i class="fa-solid fa-pause"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeStaff(${staff.id}, '${escapeHtml(staff.name)}')" title="Remove Staff Member">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;

                        staffGrid.prepend(newCard);
                        adjustKpi('total', 1);
                        adjustKpi('absent', 1);
                    }

                    addStaffForm.reset();
                    closeAddStaffModal();
                    showNotification('New staff member added and QR badge created!', 'success');
                } else {
                    showNotification(result.message || 'Failed to add staff', 'error');
                }
            } catch (error) {
                showNotification('Network error while saving staff', 'error');
            } finally {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            }
        });
    }
});

// Utility: HTML Escape
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Toast Notification
function showNotification(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 99999;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const isSuccess = (type === 'success');
    const icon = isSuccess ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-exclamation"></i>';

    toast.style.cssText = `
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 280px;
        max-width: 400px;
        padding: 14px 18px;
        background: #ffffff;
        color: #1e293b;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border-left: 5px solid ${isSuccess ? '#10b981' : '#ef4444'};
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        pointer-events: auto;
        transform: translateX(120%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
        opacity: 0;
    `;

    toast.innerHTML = `
        <span style="color: ${isSuccess ? '#10b981' : '#ef4444'}; font-size: 18px;">${icon}</span>
        <span style="flex: 1;">${message}</span>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}