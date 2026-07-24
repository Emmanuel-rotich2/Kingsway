# File Permission and Lifecycle Matrix

| Category | Creator/manager | Storage | Public delivery | Private access | Print behavior |
|---|---|---|---|---|---|
| Public school document | Website content manager/admin | `SCHOOL_ASSETS_DOCUMENTS` | Permanent token through `/api/download/public` | Not required while published | Download then print locally |
| Generated report | User with report permission | `PRINT_OUTPUT_PATH` | No | Temporary token through `/api/download/print` | Opens inline PDF |
| Generated CSV/export | User with export permission | `PRINT_OUTPUT_PATH` | No | Temporary token through `/api/download/generated` | Attachment |
| Student/admission document | Registrar/admin with record permission | Student/admission document roots | No | To be issued after record-level authorization | PDF/image preview only |
| Staff document | HR/admin permission | `STAFF_DOCUMENTS` | No | To be issued after record-level authorization | PDF preview where supported |
| Teaching material | Teacher/academic manager | `uploads/teaching_materials` | No | Existing academic approval/assignment rules | Download; backend conversion not assumed |
| Student/staff photos and QR codes | Existing role-specific workflow | Existing media roots | Existing URL behavior retained | Existing application policy | Used as image assets |
| PHP templates | Backend only | `TEMPLATES_PATH` | Never | Never | Rendered internally by PrintService |
