# Delegation Admin: UI & CLI

What this provides
- Web UI: `pages/admin_delegations.php` — list, filter, create, edit (expiry/activate) and delete user-level delegations.
- Client JS: `js/pages/admin_delegations.js` — interacts with the API endpoints.
- API: `DelegationsController` (`/api/delegations`) — supports GET, POST, PUT, DELETE.
- CLI: `scripts/manage_delegations.php` — list, create, delete delegations from shell.

Notes
- Only users with `manage_delegations` permission can use the UI/API; the CLI can be run by a system admin shell user.
- Deleting or deactivating a delegation runs a best-effort permission revocation: it will remove direct `user_permissions` that were granted specifically for the delegation if no other active delegation requires them.

How to use
- Web: Visit `home.php?route=admin_delegations` (ensure the route is registered in `route_registry.php` if needed).
- CLI examples:
  - `php scripts/manage_delegations.php list`
  - `php scripts/manage_delegations.php create 4 12 99999`
  - `php scripts/manage_delegations.php delete 123`

Next steps (optional)
- Add an audit view in the UI to show `delegation_audit` records.
- Add role/user pickers to the create modal (instead of raw IDs).
- Add bulk import/export for delegations.
