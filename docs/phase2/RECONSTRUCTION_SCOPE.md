# Phase 2 Reconstruction

This affected-files package reconciles the existing staff module instead of bypassing it.

- `manage_staff.php` remains the canonical staff directory.
- `staff_onboarding.php` remains the onboarding/probation workspace.
- `staff_appointments.php` remains the appointment/interview workspace.
- `import_existing_staff.php` provides bulk migration.
- First-login reset and profile-completion pages complete onboarding.
- Sidebar labels for appointments, interviews, and onboarding now point to their dedicated existing routes instead of overloading `manage_staff`.
