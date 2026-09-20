const resetDefaultPasswordController = {
    async init() {
        const password = document.getElementById('rdpPassword');
        const rules = {
            length: value => value.length >= 6,
            upper: value => /[A-Z]/.test(value),
            lower: value => /[a-z]/.test(value),
            number: value => /[0-9]/.test(value),
            symbol: value => /[^A-Za-z0-9]/.test(value),
        };
        password?.addEventListener('input', () => {
            Object.entries(rules).forEach(([name, passes]) => {
                const row = document.querySelector(`[data-rule="${name}"]`);
                const ok = passes(password.value);
                row?.classList.toggle('ok', ok);
                const icon = row?.querySelector('i');
                if (icon) icon.className = `bi ${ok ? 'bi-check-circle-fill' : 'bi-circle'} me-1`;
            });
        });
        document.getElementById('rdpToggle')?.addEventListener('click', event => {
            password.type = password.type === 'password' ? 'text' : 'password';
            event.currentTarget.querySelector('i').className = `bi ${password.type === 'password' ? 'bi-eye' : 'bi-eye-slash'}`;
        });

        document.getElementById('rdpForm')?.addEventListener('submit', async e => {
            e.preventDefault();
            const p = document.getElementById('rdpPassword').value,
                c = document.getElementById('rdpConfirm').value,
                s = document.getElementById('rdpState');
            if (p !== c) {
                s.className = 'alert alert-danger';
                s.textContent = 'Passwords do not match.';
                return;
            }
            try {
                const result = await apiCall('/auth/reset-default-password', 'POST', {
                    token: document.getElementById('rdpToken').value,
                    password: p,
                    password_confirmation: c
                });
                s.className = 'alert alert-success';
                s.textContent = 'Password saved securely. Taking you to sign in; profile completion is the next step.';
                document.getElementById('rdpForm').querySelector('button[type="submit"]').disabled = true;
                const accountType = result?.account_type || result?.data?.account_type || window.KINGSWAY_SETUP_ACCOUNT_TYPE;
                const destination = accountType === 'parent'
                    ? `${window.APP_BASE || ''}/parent_portal.php?route=dashboard&login=1`
                    : `${window.APP_BASE || ''}/index.php?route=r6d394ab20b0b`;
                window.setTimeout(() => window.location.replace(destination), 1600);
            } catch (err) {
                s.className = 'alert alert-danger';
                s.textContent = err.message;
            }
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => resetDefaultPasswordController.init().catch(() => {}));
} else {
    resetDefaultPasswordController.init().catch(() => {});
}

window.resetDefaultPasswordController = resetDefaultPasswordController;
