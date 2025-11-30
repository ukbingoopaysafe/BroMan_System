// Session timeout warning system
class SessionTimeoutManager {
    constructor() {
        this.warningShown = false;
        this.checkInterval = 60000; // Check every 60 seconds (was 30s)
        this.sessionTimeout = 1800000; // 30 minutes in milliseconds
        this.warningThreshold = 300000; // Show warning 5 minutes before expiry
        this.warningDialog = null;
        this.lastActivity = Date.now();
        this.lastExtendAt = 0;          // last time we pinged extend_session.php
        this.extendCooldown = 120000;   // min gap between background extend calls (2 minutes)
        this.init();
    }

    init() {
        // Start checking session status
        this.startSessionCheck();
        
        // Add event listeners for user activity
        this.addActivityListeners();
    }

    startSessionCheck() {
        setInterval(() => {
            this.checkSessionStatus();
        }, this.checkInterval);
    }

    addActivityListeners() {
        // Reset warning when user is active
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
                if (this.warningShown) {
                    this.hideWarning();
                }
                // Throttle background keep-alive to avoid spamming on mousemove/scroll
                const now = Date.now();
                if (!document.hidden && (now - this.lastExtendAt) > this.extendCooldown) {
                    this.extendSession();
                }
            }, { passive: true });
        });
    }

    async checkSessionStatus() {
        try {
            const timeSinceLastActivity = Date.now() - this.lastActivity;
            
            // Show warning 5 minutes before session expires
            if (timeSinceLastActivity >= (this.sessionTimeout - this.warningThreshold) && !this.warningShown) {
                this.showWarning();
                return;
            }
            
            // Check if session has expired
            if (timeSinceLastActivity >= this.sessionTimeout) {
                this.handleSessionExpired();
                return;
            }
            
            const response = await fetch('session_status.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.expired) {
                // Session has expired, handle expiration
                this.handleSessionExpired();
                window.location.href = 'login.php?timeout=' + data.timeout_type;
                return;
            }
            
            if (data.warning && !this.warningShown) {
                this.showWarning(data.time_remaining, data.timeout_type);
            }
            
        } catch (error) {
            console.error('Error checking session status:', error);
        }
    }

    showWarning(timeRemaining, timeoutType) {
        this.warningShown = true;
        
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        const message = timeoutType === 'idle' 
            ? `Your session will expire due to inactivity in ${timeString}. Click anywhere to continue.`
            : `Your session will expire in ${timeString} due to maximum session time. Please save your work.`;
        
        // Create warning dialog
        this.warningDialog = document.createElement('div');
        this.warningDialog.className = 'session-warning-dialog';
        this.warningDialog.innerHTML = `
            <div class="session-warning-content">
                <div class="session-warning-icon">⚠️</div>
                <div class="session-warning-message">${message}</div>
                <div class="session-warning-buttons">
                    <button onclick="sessionManager.extendSession()" class="btn btn-primary">Extend Session</button>
                    <button onclick="sessionManager.logout()" class="btn btn-secondary">Logout</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.warningDialog);
        
        // Add CSS if not already added
        if (!document.getElementById('session-warning-styles')) {
            const style = document.createElement('style');
            style.id = 'session-warning-styles';
            style.textContent = `
                .session-warning-dialog {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                }
                .session-warning-content {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    text-align: center;
                    max-width: 400px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                }
                .session-warning-icon {
                    font-size: 48px;
                    margin-bottom: 15px;
                }
                .session-warning-message {
                    font-size: 16px;
                    margin-bottom: 20px;
                    color: #333;
                }
                .session-warning-buttons {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                }
                .session-warning-buttons .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .session-warning-buttons .btn-primary {
                    background: #007bff;
                    color: white;
                }
                .session-warning-buttons .btn-secondary {
                    background: #6c757d;
                    color: white;
                }
                .session-warning-buttons .btn:hover {
                    opacity: 0.9;
                }
            `;
            document.head.appendChild(style);
        }
    }

    hideWarning() {
        if (this.warningDialog) {
            this.warningDialog.remove();
            this.warningDialog = null;
        }
        this.warningShown = false;
    }

    async extendSession() {
        try {
            // record the attempt time immediately to enforce cooldown even if network is slow
            this.lastExtendAt = Date.now();
            const response = await fetch('extend_session.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                this.hideWarning();
            }
        } catch (error) {
            console.error('Error extending session:', error);
        }
    }

    logout() {
        window.location.href = 'logout.php';
    }
}

// Initialize session manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionTimeoutManager();
});

