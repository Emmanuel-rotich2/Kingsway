<div class="modal fade" id="newApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <div>
                    <h5 class="modal-title mb-1"><i class="bi bi-person-plus me-2"></i>New Admission Application</h5>
                    <small class="opacity-75">Create the learner record and continue it through the admissions workflow.</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newApplicationForm" enctype="multipart/form-data" novalidate>
                <div class="modal-body">
                    <ul class="nav nav-pills nav-fill gap-2 mb-4" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="tab-applicant" type="button" data-bs-toggle="tab" data-bs-target="#content-personal">1. Learner</button></li>
                        <li class="nav-item"><button class="nav-link" id="tab-academic" type="button" data-bs-toggle="tab" data-bs-target="#content-academic">2. Academic</button></li>
                        <li class="nav-item"><button class="nav-link" id="tab-parent" type="button" data-bs-toggle="tab" data-bs-target="#content-parent">3. Parent / Guardian</button></li>
                        <li class="nav-item"><button class="nav-link" id="tab-health" type="button" data-bs-toggle="tab" data-bs-target="#content-health">4. Health</button></li>
                        <li class="nav-item"><button class="nav-link" id="tab-documents" type="button" data-bs-toggle="tab" data-bs-target="#content-documents">5. Documents</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="content-personal">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold">Learner full name <span class="text-danger">*</span></label><input name="applicant_name" class="form-control" required></div>
                                <div class="col-md-3"><label class="form-label fw-semibold">Date of birth <span class="text-danger">*</span></label><input type="date" name="date_of_birth" class="form-control" required></div>
                                <div class="col-md-3"><label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label><select name="gender" class="form-select" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Previous school</label><input name="previous_school" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Previous grade</label><input name="previous_grade" class="form-control"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="content-academic">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label fw-semibold">Grade applying for <span class="text-danger">*</span></label><select name="grade_applying_for" id="gradeSelect" class="form-select grade-select-dynamic" required><option value="">Select grade</option></select></div>
                                <div class="col-md-8"><label class="form-label fw-semibold">Admission application window <span class="text-danger">*</span></label><select name="admission_window_id" id="admissionWindowSelect" class="form-select" required><option value="">Loading open windows…</option></select><small class="text-muted">Academic year and target term are supplied by the selected window.</small><input type="hidden" name="target_term_id" id="targetTermInput"><input type="hidden" name="academic_year" id="academicYearInput"></div>
                                <div class="col-md-4"><label class="form-label fw-semibold">Application source</label><select name="application_source" class="form-select"><option value="physical">Physical / Front Office</option><option value="online">Online</option><option value="referral">Referral</option></select></div>
                                <div class="col-md-4"><label class="form-label fw-semibold">Admission category</label><select name="admission_category" id="admissionCategorySelect" class="form-select"><option value="standard">Standard Admission</option><option value="nursery_term_1">Nursery Term 1 Intake</option><option value="nursery_term_3">Nursery Term 3 Intake</option></select></div>
                                <div class="col-md-4"><label class="form-label fw-semibold">Boarding preference</label><select name="boarding_preference" class="form-select"><option value="day">Day Scholar</option><option value="full_boarding">Full Boarding</option></select></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="content-parent">
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label fw-semibold">Parent / Guardian <span class="text-danger">*</span></label><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="parent_type" id="parentTypeExisting" value="existing" checked><label class="btn btn-outline-primary" for="parentTypeExisting">Existing Parent</label><input type="radio" class="btn-check" name="parent_type" id="parentTypeNew" value="new"><label class="btn btn-outline-primary" for="parentTypeNew">New Parent</label></div></div>
                                <div class="col-12" id="existingParentFields"><label class="form-label fw-semibold">Select existing parent <span class="text-danger">*</span></label><select name="parent_id" id="parentSelect" class="form-select" required><option value="">Loading parents…</option></select></div>
                                <div class="col-md-6" id="newParentFields" style="display:none"><label class="form-label fw-semibold">Parent full name <span class="text-danger">*</span></label><input name="new_parent_name" class="form-control"></div>
                                <div class="col-md-6" id="newParentIdField" style="display:none"><label class="form-label fw-semibold">National ID / passport number</label><input name="new_parent_id_number" class="form-control"></div>
                                <div class="col-md-6" id="newParentPhoneField" style="display:none"><label class="form-label fw-semibold">Phone number <span class="text-danger">*</span></label><input name="new_parent_phone" class="form-control"></div>
                                <div class="col-md-6" id="newParentEmailField" style="display:none"><label class="form-label fw-semibold">Email address</label><input type="email" name="new_parent_email" class="form-control"></div>
                                <div class="col-12" id="newParentAddressField" style="display:none"><label class="form-label fw-semibold">Residential address</label><input name="new_parent_address" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Relationship</label><select name="relationship" class="form-select"><option value="parent">Parent</option><option value="father">Father</option><option value="mother">Mother</option><option value="guardian">Guardian</option><option value="sibling">Sibling</option><option value="grandparent">Grandparent</option><option value="uncle">Uncle</option><option value="aunt">Aunt</option><option value="other">Other</option></select></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Emergency contact phone</label><input name="emergency_phone" class="form-control"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="content-health">
                            <div class="row g-3">
                                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="hasSpecialNeeds" name="has_special_needs" value="1"><label class="form-check-label fw-semibold" for="hasSpecialNeeds">Learner has special educational or medical needs</label></div></div>
                                <div class="col-12" id="specialNeedsDetailsGroup" style="display:none"><label class="form-label">Details</label><textarea name="special_needs_details" class="form-control" rows="3"></textarea></div>
                                <div class="col-12"><label class="form-label">Medical conditions / allergies</label><textarea name="medical_conditions" class="form-control" rows="3"></textarea></div>
                                <div class="col-md-6"><label class="form-label">Blood group</label><select name="blood_group" class="form-select"><option value="">Unknown</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
                                <div class="col-md-6"><label class="form-label">Birth certificate number</label><input name="birth_certificate_no" class="form-control" maxlength="64"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="content-documents">
                            <div class="alert alert-warning small"><i class="bi bi-info-circle me-1"></i>Required documents are uploaded with the application and remain available in the workflow detail view.</div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold">Birth certificate <span class="text-danger">*</span></label><input type="file" name="doc_birth_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Passport photo <span class="text-danger">*</span></label><input type="file" name="doc_passport_photo" class="form-control" accept=".jpg,.jpeg,.png" required></div>
                                <div class="col-md-6" id="newParentIdDocWrap"><label class="form-label fw-semibold">Parent / Guardian ID</label><input type="file" name="doc_parent_id" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                                <div class="col-md-6" id="immunizationDocWrap" style="display:none"><label class="form-label fw-semibold">Immunization card <span class="text-danger">*</span></label><input type="file" name="doc_immunization_card" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                                <div class="col-md-6" id="schoolReportDocWrap" style="display:none"><label class="form-label fw-semibold">Previous school report <span class="text-danger">*</span></label><input type="file" name="doc_previous_school_report" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                                <div class="col-md-6" id="medicalDocWrap" style="display:none"><label class="form-label fw-semibold">Medical test results <span class="text-danger">*</span></label><input type="file" name="doc_medical_records" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                                <div class="col-md-6"><label class="form-label">Progress report</label><input type="file" name="doc_progress_report" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                                <div class="col-md-6"><label class="form-label">Other document</label><input type="file" name="doc_other" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-secondary" id="applicationTabBack" disabled><i class="bi bi-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn btn-outline-success" id="applicationTabNext">Next<i class="bi bi-arrow-right ms-1"></i></button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send me-1"></i>Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
