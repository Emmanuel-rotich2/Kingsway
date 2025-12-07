// Main JavaScript file for Kingsway School Management System
// Handles global utilities and common functions

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    // Contact form handler (if exists on page)
    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                subject: formData.get('subject'),
                message: formData.get('message')
            };
            
            try {
                // Implement contact form submission
                console.log('Contact form submitted:', data);
                showNotification('Message sent successfully!', 'success');
                contactForm.reset();
            } catch (error) {
                console.error('Contact form error:', error);
                showNotification('Failed to send message. Please try again.', 'error');
            }
        });
    }
});

// Global utility functions
window.utils = {
    formatDate: (date) => {
        return new Date(date).toLocaleDateString('en-GB');
    },
    
    formatDateTime: (date) => {
        return new Date(date).toLocaleString('en-GB');
    },
    
    formatCurrency: (amount) => {
        return 'KES ' + parseFloat(amount).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

console.log('Main.js loaded successfully');
