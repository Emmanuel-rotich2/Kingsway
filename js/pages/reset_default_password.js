const resetDefaultPasswordController = {
    async init() {
        const password = document.getElementById('rdpPassword');
        const rules = {
            length: value => value.length >= 10,
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
                const accountType = result?.account_type || result?.data?.account_type || window.KINGSWAY_SETUP_ACCOUNT_TYPE;
                if (accountType === 'staff' && (result?.requires_otp || result?.data?.requires_otp)) {
                    const otpSent = result?.otp_sent ?? result?.data?.otp_sent ?? true;
                    s.className = 'alert alert-success';
                    s.textContent = otpSent
                        ? 'Password saved. Check your email for a verification code; after verification, you will continue to your staff profile.'
                        : 'Password saved, but the verification email could not be sent. Select Resend code below; after verification, you will continue to your staff profile.';
                    document.getElementById('rdpForm').classList.add('d-none');
                    document.getElementById('rdpOtpForm').classList.remove('d-none');
                    document.getElementById('rdpOtpCode').focus();
                    return;
                }
                s.className = 'alert alert-success';
                s.textContent = 'Password saved securely. Taking you to sign in.';
                document.getElementById('rdpForm').querySelector('button[type="submit"]').disabled = true;
                const destination = accountType === 'parent'
                    ? `${window.APP_BASE || ''}/parent_portal.php?route=dashboard&login=1`
                    : `${window.APP_BASE || ''}/index.php?route=r6d394ab20b0b`;
                window.setTimeout(() => window.location.replace(destination), 1600);
            } catch (err) {
                s.className = 'alert alert-danger';
                s.textContent = err.message;
            }
        });

        document.getElementById('rdpOtpForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const state = document.getElementById('rdpState');
            const button = document.getElementById('rdpOtpSubmit');
            const code = document.getElementById('rdpOtpCode').value.trim();
            if (!/^\d{6}$/.test(code)) {
                state.className = 'alert alert-danger';
                state.textContent = 'Enter the six-digit code from your email.';
                return;
            }
            button.disabled = true;
            try {
                const token = document.getElementById('rdpToken').value;
                const session = await window.API.auth.verifyInvitationSetupOtp(token, code);
                if (!session?.token) throw new Error(session?.message || 'Verification succeeded, but your session could not be started. Please sign in.');
                window.AuthContext.setPersistence(true);
                window.AuthContext.setTokens(session.token);
                window.AuthContext.setUser(session.user || {}, session, true);
                state.className = 'alert alert-success';
                state.textContent = 'Email verified. Opening your staff profile…';
                window.setTimeout(() => window.location.replace(`${window.APP_BASE || ''}/home.php?route=complete_staff_profile`), 500);
            } catch (error) {
                state.className = 'alert alert-danger';
                state.textContent = error.message || 'The code could not be verified.';
            } finally {
                button.disabled = false;
            }
        });

        document.getElementById('rdpOtpResend')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            const state = document.getElementById('rdpState');
            button.disabled = true;
            try {
                await window.API.auth.resendInvitationSetupOtp(document.getElementById('rdpToken').value);
                state.className = 'alert alert-success';
                state.textContent = 'A new verification code was sent. Previous codes are no longer valid.';
            } catch (error) {
                if (/wait 60 seconds/i.test(error.message || '')) {
                    state.className = 'alert alert-info';
                    state.textContent = 'A verification code was recently sent. Check your email and use the latest code.';
                } else {
                    state.className = 'alert alert-danger';
                    state.textContent = error.message || 'A new code could not be sent. Try again shortly.';
                }
            } finally {
                button.disabled = false;
            }
        });

        if (window.KINGSWAY_SETUP_RESUME_OTP) {
            document.getElementById('rdpOtpResend')?.click();
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => resetDefaultPasswordController.init().catch(() => {}));
} else {
    resetDefaultPasswordController.init().catch(() => {});
}

window.resetDefaultPasswordController = resetDefaultPasswordController;
