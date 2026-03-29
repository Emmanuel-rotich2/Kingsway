# Security Policy

We take security seriously and appreciate reports that help keep Kingsway safe for schools and their data.

## Reporting a Vulnerability
- **Do not open public issues** for security problems.
- Email the maintainers at **security@kingsway.example.com** (replace with your team’s security inbox if different).
- Include a clear description, affected endpoints/areas, reproduction steps, and any logs or responses (remove secrets/tokens).
- If you believe the issue is actively exploitable, mark it as **HIGH** in the subject.

## What to Expect
- We aim to acknowledge reports within **3 business days**.
- We will work with you to reproduce, triage severity, and agree on a coordinated disclosure timeline.
- Once fixed, we will publish release notes and credit reporters who consent.

## Scope
- REST API endpoints under `/api/*`
- Authentication, authorization, and permission enforcement
- Data storage and handling in MySQL/MariaDB
- Client-side logic in `js/api.js` and related UI flows

## Out of Scope
- Social engineering against maintainers or users
- Findings without actionable security impact
- Denial-of-service via unrealistic resource usage patterns

Thank you for helping keep Kingsway secure.
