// Wait for DOM and API to be ready
document.addEventListener('DOMContentLoaded', () => {
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
        // Handle login form submission
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const loginError = document.getElementById('loginError');
            loginError.classList.add('d-none');
            
            try {
                const response = await window.API.auth.login(
                    formData.get('username'),
                    formData.get('password')
                );
            } catch (error) {
                loginError.textContent = error.message || 'An error occurred. Please try again.';
                loginError.classList.remove('d-none');
            }
        });
    }

    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        // Handle contact form submission
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
                const response = await fetch('/api/communications.php?action=contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('contact-success').textContent = 'Message sent successfully!';
                    e.target.reset();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                window.API.showNotification('error', error.message || 'Failed to send message');
            }
        });
    }
});