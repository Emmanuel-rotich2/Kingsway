# Acceptance Tests

- [ ] All 38 System Administrator routes resolve without PHP or JavaScript errors.
- [ ] A non-System Administrator receives HTTP 403 from every new control-plane endpoint.
- [ ] System dashboard contains no fabricated fallback metrics.
- [ ] Account activate, disable, lock, unlock, and force-reset actions are audited.
- [ ] Session revocation takes effect immediately.
- [ ] Feature flags, modules, IP rules, policies, route rules, maintenance,
      retention, incidents, and webhooks persist through canonical registries.
- [ ] Invalid registry names and unsupported fields are rejected.
- [ ] School provisioning can resume after interruption.
- [ ] Provisioning cannot finalize before required steps are saved.
- [ ] Finalization activates the school atomically.
- [ ] Provisioning creates no students, staff, finance, or attendance records.
- [ ] Initial School Administrator invitation/password workflow is tested after
      email infrastructure is configured.
- [ ] Every write appears in `audit_logs`.
