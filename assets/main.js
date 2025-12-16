/**
 * Taskly Toast Notification System
 */

// Create container if it doesn't exist
function ensureToastContainer() {
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return document.querySelector('.toast-container');
}

/**
 * Show a toast notification
 * @param {string} message The message to display
 * @param {string} type 'success', 'error', 'warning', 'info'
 * @param {number} duration Duration in ms (default 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    const container = ensureToastContainer();
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Icon based on type
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    toast.innerHTML = `
        <i class="fas ${icon}" style="font-size: 1.25rem;"></i>
        <div style="flex: 1;">
            <p style="margin: 0; font-weight: 500; font-size: 0.9rem;">${message}</p>
        </div>
    `;
    
    // Add to container
    container.appendChild(toast);
    
    // Remove after duration
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300); // Match CSS transition
    }, duration);
}
