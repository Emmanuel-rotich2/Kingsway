/**
 * Kingsway global utilities.
 * Core managers are initialized only by app_bootstrap.js.
 */
(() => {
  'use strict';
  let initialized = false;

  function initializeMain() {
    if (initialized) return;
    initialized = true;

    const contactForm = document.getElementById('contact-form');
    if (contactForm && contactForm.dataset.handlerBound !== 'true') {
      contactForm.dataset.handlerBound = 'true';
      contactForm.addEventListener('submit', handleContactFormSubmit);
    }
  }

  async function handleContactFormSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
      console.debug('[Main] Contact form submitted', payload);
      window.showNotification?.('Message sent successfully!', 'success');
      form.reset();
    } catch (error) {
      console.error('[Main] Contact form failed', error);
      window.showNotification?.('Failed to send message. Please try again.', 'error');
    }
  }

  window.utils = Object.freeze({
    formatDate(value) {
      if (!value) return '';
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-GB');
    },
    formatDateTime(value) {
      if (!value) return '';
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en-GB');
    },
    formatCurrency(value) {
      const amount = Number(value);
      return new Intl.NumberFormat('en-KE', {
        style: 'currency', currency: 'KES', minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(Number.isFinite(amount) ? amount : 0);
    },
    debounce(callback, delay = 300) {
      let timer = null;
      return function debounced(...args) {
        const context = this;
        clearTimeout(timer);
        timer = window.setTimeout(() => callback.apply(context, args), delay);
      };
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMain, { once: true });
  } else {
    initializeMain();
  }
})();
