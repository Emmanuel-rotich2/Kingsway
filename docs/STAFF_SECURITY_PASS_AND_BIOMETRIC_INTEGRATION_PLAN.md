# Staff Security Pass and Biometric Integration Plan

Status: `PASS_PREVIEW_AND_PRINT_CORRECTED_STATICALLY`; scanner/biometric integration is `DESIGN_PENDING_HARDWARE_SELECTION`  
Last updated: 2026-07-26

## 1. Purpose

Kingsway staff do not use the student horizontal ID-card workflow. Staff require a portrait security pass worn on a lanyard. The pass identifies the staff member and carries a signed QR credential that can later participate in gate access and attendance workflows.

The printed pass and biometric identity are related through the staff record, but they are different credentials:

- the security pass is replaceable and may be revoked or reissued;
- a fingerprint enrollment belongs to the staff member and selected security device, not to a printed card;
- fingerprint images or templates must never be printed, encoded in the QR value, or exposed to the browser page.

Existing compatibility identifiers remain unchanged:

- route: `staff_id_cards`
- permissions: `staff.id_cards.view`, `staff.id_cards.manage`
- API namespace: `window.API.staff`
- database registry: `staff_id_cards`

The user-facing name is **Staff Security Passes**.

## 2. Verified Existing Kingsway Architecture

```text
config/role_sidebars.php
→ route staff_id_cards
→ pages/staff_id_cards.php
→ js/pages/staff_access.js → StaffAccessController
→ js/pages/staff_id_cards.js → StaffSecurityPassesController
→ js/api.js → API.staff
→ api/controllers/StaffController.php
→ api/services/StaffRecordsService.php
→ api/modules/staff/StaffIDCardGenerator.php
→ api/services/StaffSecurityPassCredentialService.php
→ api/services/PrintService.php
→ api/services/DownloadService.php
→ staff / staff_id_cards
→ staff permissions, route authorization and audit logging
```

The page uses Bootstrap and the already-loaded `css/school-theme.css`. No page-specific admin stylesheet is required.

Physical pass layout is a separate print concern and is owned by:

```text
uploads_backup/templates/id_cards/staff_security_pass/
public/css/staff-security-pass.css
public/css/staff-security-pass-cr80.css
public/css/staff-security-pass-a4.css
api/services/PrintService.php
```

Repository templates are promoted from `uploads_backup/` into the configured runtime `uploads/templates/` tree during deployment, following the existing file-lifecycle architecture.

## 3. Verified Database Objects

### `staff`

Used fields:

- `id`
- `staff_no`
- `first_name`
- `last_name`
- `department_id`
- `position`
- `phone`
- `profile_pic_url`
- `user_id`
- `status`
- `work_start_time`
- `work_end_time`
- `late_threshold_minutes`

### `staff_id_cards`

The existing table remains the canonical pass registry. No competing `staff_security_passes` table is introduced.

Relevant fields:

- `id bigint unsigned`
- `staff_id bigint unsigned`
- `card_number varchar(64) unique`
- `generated_by`
- `generated_at`
- `issued_by`
- `issued_at`
- `expires_at` nullable legacy column; canonical staff passes write `NULL`
- `status enum('generated','issued','revoked','expired')`
- `metadata`

The database column `card_number` is retained for compatibility. Frontend and printed documents call it **Pass No.** The legacy `expired` enum value is retained to avoid a destructive schema change, but calendar expiry is no longer part of the staff-pass workflow.

### `staff_attendance`

Existing attendance destination:

- `staff_id`
- `date`
- `shift`
- `status enum('present','absent','late')`
- `check_in_time`
- `expected_check_in`
- `check_out_time`
- `marked_by`
- unique key on `staff_id, date, shift`

### `staff_attendance_profiles`

Existing schedule source:

- `staff_id` unique
- `work_start_time`
- `work_end_time`
- `late_threshold_minutes`
- `is_active`

No human biometric registry or physical access-terminal registry currently exists in the database dump.

## 4. Current Security-Pass Contract

`StaffRecordsService::idCards()` already returns every current staff member and the latest pass with one `LEFT JOIN`. The page controller must not load a second staff list and must not merge guessed response shapes.

Exact fields returned to the controller:

```text
id
staff_id
card_number
generated_by
generated_at
issued_by
issued_at
expires_at
status
metadata
created_at
updated_at
staff_no
first_name
last_name
position
email
phone
profile_pic_url
department_name
```

The signed QR credential contains:

```json
{
  "v": 1,
  "pass": "KWA-S-000033",
  "exp": null
}
```

The JSON payload is base64url encoded and HMAC signed. It contains neither `staff.id` nor fingerprint data. At scan time, a server-side verifier must:

1. validate the signature;
2. resolve `pass` against `staff_id_cards.card_number`;
3. confirm the current database status is generated or issued;
4. confirm the linked staff employment relationship is current;
5. apply checkpoint policy;
6. record the event and, where configured, synchronize attendance.

A static QR can be photographed or copied. It is therefore suitable for identification and online status validation, but high-assurance access should require a second factor such as a device-local fingerprint match.


### Employment-based validity

A staff security pass has no fixed calendar expiry. It remains valid only while the linked staff employment relationship is current.

- regeneration writes `staff_id_cards.expires_at = NULL`;
- resignation, retirement, termination, suspension and completed offboarding revoke the latest pass through the canonical staff lifecycle services;
- reinstatement requires an authorised replacement/regeneration workflow;
- an internal department or position transfer does not revoke the pass because the staff employment relationship remains active;
- an external transfer out of Kingsway must use the offboarding/exit workflow, which revokes the pass.

### Preview, open and print flow

```text
StaffSecurityPassesController
→ API.staff
→ StaffController
→ StaffIDCardGenerator
→ PrintService renders templates and PDF
→ DownloadService returns secure file descriptors
```

The backend returns `preview_html` generated from the same templates and CSS used by Dompdf. The modal displays that server-rendered HTML through `iframe.srcdoc`; it does not embed the PDF viewer. The Open action is owned by `KingswayFileLifecycle`, and the Print action is owned by `PrintManager.handleGeneratedFiles()`. Page controllers never call `window.print()` or `iframe.contentWindow.print()` directly.

## 5. Hardware Integration Decision Gate

No vendor-specific scanner code or biometric migration should be implemented until Kingsway selects the exact hardware.

Required purchasing information:

- manufacturer and model;
- QR/barcode support and host mode;
- fingerprint enrollment and matching model;
- communication protocol: USB HID, TCP/IP push, REST API, SDK, ADMS, Wiegand, OSDP or another protocol;
- whether biometric templates remain inside the device;
- whether the device exposes a stable external user/enrollment identifier;
- LAN/offline behavior and event replay support;
- signed webhook/event support;
- door relay, anti-passback and direction support;
- vendor licensing requirements;
- supported operating system for any gateway service.

A plain USB QR scanner can usually operate as keyboard input. That supports an initial browser checkpoint screen with one permanently focused input. Fingerprint terminals normally require a vendor SDK, push protocol or local gateway and must not be treated as ordinary browser input.

## 6. Recommended Future Device Architecture

```text
QR scanner or biometric terminal
→ trusted on-premises device/gateway
→ signed device event
→ canonical Kingsway security endpoint
→ pass credential verification
→ staff/pass/device resolution
→ access policy decision
→ immutable access event
→ attendance synchronization service
→ existing staff_attendance record
→ audit log and administrator reporting
```

### QR-only phase

For a USB scanner configured as HID keyboard input:

```text
pages/staff_security_checkpoint.php
→ js/pages/staff_security_checkpoint.js
   → StaffSecurityCheckpointController
→ API.staffSecurity.verifyPass(...)
→ SecurityCheckpointController
→ StaffSecurityPassService
→ StaffSecurityPassCredentialService
→ staff_id_cards + staff
```

The page controller would receive scanner keystrokes in a focused input and submit on the scanner suffix, normally Enter. It must still use `api.js`; direct `fetch()` is not permitted.

The final API namespace should be chosen after the Security Domain ownership audit. It must not be added under `API.staff` merely for convenience if physical access is owned by an existing security/integration namespace.

### Biometric phase

Preferred pattern:

- enroll fingerprints on the selected terminal or its approved local management service;
- keep biometric templates inside the device/vendor subsystem where supported;
- store only the external enrollment reference and device relationship in Kingsway;
- let the terminal perform fingerprint comparison locally;
- send Kingsway the resulting subject/device/time/method/result event;
- verify the device signature before accepting the event.

The printed pass may be used together with fingerprint verification, but the fingerprint enrollment must link to `staff.id`, not to one replaceable pass row.

## 7. Proposed Future Database Objects

These are design candidates only. Do not create them before hardware and privacy approval.

### `security_access_devices`

Purpose: register approved physical scanners/terminals.

Candidate fields:

- `id`
- `device_code` unique
- `name`
- `vendor`
- `model`
- `serial_number`
- `device_type`
- `protocol`
- `location`
- `direction_capability`
- `credential_key_id` or public-key reference
- `status`
- `last_seen_at`
- `metadata`
- timestamps

Never store plaintext device shared secrets.

### `staff_biometric_enrollments`

Purpose: map a staff member to an enrollment held by a device/vendor subsystem.

Candidate fields:

- `id`
- `staff_id`
- `device_id`
- `external_enrollment_ref`
- `biometric_type`
- `finger_position` where operationally required
- `status`
- `enrolled_at`
- `revoked_at`
- `enrolled_by`
- `metadata`
- timestamps

Do not store raw fingerprint images. Do not store vendor templates in the Kingsway database unless the selected device cannot support safer local matching and a separately approved encrypted-template design is completed.

### `staff_security_access_events`

Purpose: immutable gate/security event ledger before attendance projection.

Candidate fields:

- `id`
- `event_uuid` unique
- `device_id`
- `staff_id` nullable until resolution
- `pass_id` nullable
- `event_time`
- `direction`
- `authentication_method`
- `decision`
- `reason_code`
- `external_event_id`
- `payload_hash`
- `attendance_sync_status`
- `attendance_id` nullable
- `received_at`
- `processed_at`
- `metadata`

The raw vendor payload should be minimized and retained only when justified. Idempotency must use `event_uuid` or the device's stable external event ID.

## 8. Attendance Projection Rules

A successful access event is not automatically an attendance record without policy.

The attendance synchronization service should:

1. load `staff_attendance_profiles` or the staff schedule fallback;
2. resolve the configured shift and direction;
3. create/update the existing unique `staff_attendance(staff_id,date,shift)` row transactionally;
4. set first valid entry as `check_in_time`;
5. calculate `present` or `late` using expected start and threshold;
6. set the last valid exit as `check_out_time`;
7. retain the source access-event reference;
8. reject duplicate/replayed device events idempotently;
9. never overwrite approved leave or manual corrections without an explicit conflict policy;
10. record system/audit metadata for every automated attendance mutation.

Because `staff_attendance` currently has no source-event foreign key, the final migration must decide whether to add one optional source reference or maintain the relationship from the event table.

## 9. Security and Privacy Controls

Before biometric rollout:

- complete a Data Protection Impact Assessment;
- define lawful purpose, retention and staff notice;
- document enrollment, revocation and employee-exit procedures;
- provide an approved fallback for staff unable to use fingerprint authentication;
- restrict enrollment and device administration permissions;
- encrypt secrets and sensitive references at rest;
- use TLS or an isolated trusted LAN plus message signing;
- reject replayed events;
- synchronize device clocks;
- log administrator changes and device configuration;
- define offline queue and event-reconciliation behavior;
- test false reject, false accept, duress and outage scenarios;
- never expose biometric details in QR codes, browser pages, logs or reports.

## 10. Implementation Phases

### Phase A — Staff security-pass printing

Corrected and statically verified; local Dompdf runtime acceptance remains required:

- canonical list endpoint;
- named page controllers;
- Bootstrap/shared admin UI;
- portrait front/back templates;
- signed QR credential;
- direct-card and A4 PDF layouts;
- PrintService and DownloadService lifecycle;
- generate, preview, print and issue workflow;
- permission and audit chain.

### Phase B — QR checkpoint

Blocked until checkpoint ownership and scanner deployment are approved.

Required deliverables:

- scanner input workflow;
- server-side credential verification endpoint;
- pass/status and current-employment lookup;
- access-event table and service;
- checkpoint page controller through `api.js`;
- device identity and replay protection;
- runtime testing with the purchased scanner.

### Phase C — Biometric terminal integration

Blocked until exact terminal model, protocol and DPIA approval are available.

Required deliverables:

- device registry;
- enrollment-reference registry;
- signed/polled event adapter;
- local matching confirmation;
- attendance projection and conflict handling;
- offline reconciliation;
- security, privacy and operational acceptance tests.

## 11. Runtime Acceptance Checklist

### Pass printing

- list all current staff from the real database;
- generate one pass;
- generate A4 bulk passes;
- verify front/back duplex alignment;
- verify school logo and staff portrait resolution on production hosting;
- scan the QR with at least two scanner models/apps;
- verify signed credential validation;
- revoke/expire a pass and confirm a future verifier rejects it;
- mark a pass issued and confirm registry state;
- verify audit records and role restrictions.

### QR/fingerprint integration

- test every selected device model;
- test network loss and replay;
- test clock drift;
- test duplicate scans;
- test entry/exit direction;
- test late calculation;
- test approved leave conflicts;
- test terminated/inactive staff;
- test lost/revoked/expired passes;
- test fingerprint false rejection and approved fallback;
- verify no fingerprint image/template leaks into Kingsway responses, logs, QR values or reports.
