-- Complete Staff Management Migration
-- Adds tables for staff onboarding, lifecycle, appointments, and import functionality

-- Step 1: Create staff_onboarding table
CREATE TABLE IF NOT EXISTS staff_onboarding (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    probation_months INTEGER DEFAULT 3,
    contract_type VARCHAR(50) DEFAULT 'probation',
    mentor_id INTEGER,
    status VARCHAR(50) DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (mentor_id) REFERENCES staff(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 2: Create staff_lifecycle table for promotions, demotions, transfers, etc.
CREATE TABLE IF NOT EXISTS staff_lifecycle (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- promotion, demotion, transfer, contract_change, suspension, reinstatement, termination
    effective_date DATE NOT NULL,
    to_position VARCHAR(255),
    to_department_id INTEGER,
    to_salary DECIMAL(10,2),
    reason TEXT NOT NULL,
    notes TEXT,
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, rejected
    approved_by INTEGER,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (to_department_id) REFERENCES departments(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 3: Create staff_appointments table
CREATE TABLE IF NOT EXISTS staff_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER NOT NULL,
    position VARCHAR(255) NOT NULL,
    department_id INTEGER,
    appointment_date DATE NOT NULL,
    contract_type VARCHAR(50) DEFAULT 'permanent',
    salary DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, rejected, active
    approved_by INTEGER,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 4: Create staff_onboarding_documents table
CREATE TABLE IF NOT EXISTS staff_onboarding_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    onboarding_id INTEGER NOT NULL,
    staff_id INTEGER NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(255),
    original_seen BOOLEAN DEFAULT 0,
    copy_filed BOOLEAN DEFAULT 0,
    notes TEXT,
    collected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    collected_by INTEGER,
    FOREIGN KEY (onboarding_id) REFERENCES staff_onboarding(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

-- Step 5: Create staff_onboarding_tasks table
CREATE TABLE IF NOT EXISTS staff_onboarding_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    onboarding_id INTEGER NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    due_date DATE,
    status VARCHAR(50) DEFAULT 'pending', -- pending, in_progress, completed, overdue
    completed_at DATETIME,
    completed_by INTEGER,
    FOREIGN KEY (onboarding_id) REFERENCES staff_onboarding(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- Step 6: Create staff_probation_reviews table
CREATE TABLE IF NOT EXISTS staff_probation_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    onboarding_id INTEGER NOT NULL,
    staff_id INTEGER NOT NULL,
    review_month INTEGER NOT NULL,
    review_date DATE NOT NULL,
    overall_rating VARCHAR(50),
    attendance_score INTEGER,
    performance_score INTEGER,
    conduct_score INTEGER,
    strengths TEXT,
    areas_improvement TEXT,
    outcome VARCHAR(50), -- continue, extend_probation, confirm_permanent, terminate
    extend_months INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    FOREIGN KEY (onboarding_id) REFERENCES staff_onboarding(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 7: Create staff_performance_reviews table if not exists
CREATE TABLE IF NOT EXISTS staff_performance_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER NOT NULL,
    review_date DATE NOT NULL,
    review_period VARCHAR(100),
    overall_score DECIMAL(5,2),
    performance_grade VARCHAR(50),
    comments TEXT,
    reviewer_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 8: Create staff_academic_kpis table
CREATE TABLE IF NOT EXISTS staff_academic_kpis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER NOT NULL,
    kpi_name VARCHAR(255) NOT NULL,
    kpi_code VARCHAR(50),
    target_value DECIMAL(10,2),
    actual_value DECIMAL(10,2),
    achievement_percentage DECIMAL(5,2),
    period VARCHAR(100),
    academic_year_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Step 9: Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_staff_onboarding_staff_id ON staff_onboarding(staff_id);
CREATE INDEX IF NOT EXISTS idx_staff_onboarding_status ON staff_onboarding(status);
CREATE INDEX IF NOT EXISTS idx_staff_lifecycle_staff_id ON staff_lifecycle(staff_id);
CREATE INDEX IF NOT EXISTS idx_staff_lifecycle_action_type ON staff_lifecycle(action_type);
CREATE INDEX IF NOT EXISTS idx_staff_lifecycle_status ON staff_lifecycle(status);
CREATE INDEX IF NOT EXISTS idx_staff_appointments_staff_id ON staff_appointments(staff_id);
CREATE INDEX IF NOT EXISTS idx_staff_appointments_status ON staff_appointments(status);
CREATE INDEX IF NOT EXISTS idx_staff_onboarding_docs_onboarding_id ON staff_onboarding_documents(onboarding_id);
CREATE INDEX IF NOT EXISTS idx_staff_onboarding_tasks_onboarding_id ON staff_onboarding_tasks(onboarding_id);
CREATE INDEX IF NOT EXISTS idx_staff_probation_reviews_onboarding_id ON staff_probation_reviews(onboarding_id);
CREATE INDEX IF NOT EXISTS idx_staff_performance_reviews_staff_id ON staff_performance_reviews(staff_id);
CREATE INDEX IF NOT EXISTS idx_staff_academic_kpis_staff_id ON staff_academic_kpis(staff_id);

-- Step 10: Verify the migration
SELECT 'staff_onboarding' as table_name, COUNT(*) as record_count FROM staff_onboarding
UNION ALL
SELECT 'staff_lifecycle', COUNT(*) FROM staff_lifecycle
UNION ALL
SELECT 'staff_appointments', COUNT(*) FROM staff_appointments
UNION ALL
SELECT 'staff_onboarding_documents', COUNT(*) FROM staff_onboarding_documents
UNION ALL
SELECT 'staff_onboarding_tasks', COUNT(*) FROM staff_onboarding_tasks
UNION ALL
SELECT 'staff_probation_reviews', COUNT(*) FROM staff_probation_reviews
UNION ALL
SELECT 'staff_performance_reviews', COUNT(*) FROM staff_performance_reviews
UNION ALL
SELECT 'staff_academic_kpis', COUNT(*) FROM staff_academic_kpis;