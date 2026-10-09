"""Allowlisted MySQL read-model refreshes for the deterministic worker.

The database performs the SELECT and materialization; Python owns the bounded
job orchestration so long refreshes do not execute in the PHP application
worker. This module is intentionally independent of the LLM provider.
"""

from __future__ import annotations

import re
import time
import uuid
from typing import Any, Callable

PROJECTIONS: dict[str, dict[str, Any]] = {
    "academic_calendar": {
        "source": "vw_academic_calendar",
        "target": "mmv_academic_calendar",
        "indexes": {
            "idx_class": ("class_id",),
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_class_teacher": ("class_teacher_id",),
            "idx_stream": ("stream_id",),
            "idx_term": ("term_id",),
            "idx_year": ("academic_year_id",),
            "idx_year_class": ("academic_year_id", "class_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "academic_class_directory": {
        "source": "vw_academic_class_directory",
        "target": "mmv_academic_class_directory",
        "indexes": {
            "idx_class_year_id": ("id",),
            "idx_current_class": ("is_current_year", "class_id"),
            "idx_current_name": ("is_current_year", "class_name"),
            "idx_year_class": ("academic_year_id", "class_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "academic_class_progression": {
        "source": "vw_academic_class_progression",
        "target": "mmv_academic_class_progression",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_term": {
        "source": "vw_academic_term",
        "target": "mmv_academic_term",
        "indexes": {
            "idx_ayt": ("academic_year_term_id",),
            "idx_term": ("term_id",),
            "idx_year_code": ("year_code",),
            "idx_year_term": ("academic_year_id", "term_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "academic_term_summary": {
        "source": "vw_academic_term_summary",
        "target": "mmv_academic_term_summary",
        "indexes": {
            "idx_term_id": ("term_id",),
            "idx_term_year_date": ("academic_year_id", "opening_date"),
            "idx_term_year_status": ("academic_year_id", "term_period_status"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "academic_term_terms": {
        "source": "vw_academic_term_terms",
        "target": "mmv_academic_term_terms",
        "indexes": {
            "idx_pk": ("academic_year_term_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_calendar": {
        "source": "vw_academic_year_calendar",
        "target": "mmv_academic_year_calendar",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_calendar_days": {
        "source": "vw_academic_year_calendar_days",
        "target": "mmv_academic_year_calendar_days",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_calendar_days_typed": {
        "source": "vw_academic_year_calendar_days_typed",
        "target": "mmv_academic_year_calendar_days_typed",
        "indexes": {
            "idx_calendar_date": ("date",),
            "idx_calendar_day_type": ("calendar_day_type_id",),
            "idx_id": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_class_learning_area_teachers": {
        "source": "vw_academic_year_class_learning_area_teachers",
        "target": "mmv_academic_year_class_learning_area_teachers",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_class_learning_areas": {
        "source": "vw_academic_year_class_learning_areas",
        "target": "mmv_academic_year_class_learning_areas",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_class_stream_learning_area_teachers": {
        "source": "vw_academic_year_class_stream_learning_area_teachers",
        "target": "mmv_academic_year_class_stream_learning_area_teachers",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_class_stream_learning_areas": {
        "source": "vw_academic_year_class_stream_learning_areas",
        "target": "mmv_academic_year_class_stream_learning_areas",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_class_stream_learning_areas_detailed": {
        "source": "vw_academic_year_class_stream_learning_areas_detailed",
        "target": "mmv_academic_year_class_stream_learning_areas_detailed",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_classes": {
        "source": "vw_academic_year_classes",
        "target": "mmv_academic_year_classes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_classes_streams": {
        "source": "vw_academic_year_classes_streams",
        "target": "mmv_academic_year_classes_streams",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_year_fee_schedules": {
        "source": "vw_academic_year_fee_schedules",
        "target": "mmv_academic_year_fee_schedules",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "academic_years": {
        "source": "vw_academic_years",
        "target": "mmv_academic_years",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_categories": {
        "source": "vw_activity_categories",
        "target": "mmv_activity_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_category_summary": {
        "source": "vw_activity_category_summary",
        "target": "mmv_activity_category_summary",
        "indexes": {
            "idx_active_activity_count": ("is_active", "activity_count"),
            "idx_active_name": ("is_active", "name"),
            "idx_category": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_participants": {
        "source": "vw_activity_participants",
        "target": "mmv_activity_participants",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_resources": {
        "source": "vw_activity_resources",
        "target": "mmv_activity_resources",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_schedule": {
        "source": "vw_activity_schedule",
        "target": "mmv_activity_schedule",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "activity_schedule_directory": {
        "source": "vw_activity_schedule_directory",
        "target": "mmv_activity_schedule_directory",
        "indexes": {
            "idx_activity_id": ("activity_id",),
            "idx_category_status": ("category_id", "activity_status"),
            "idx_day_start": ("day_of_week", "start_time"),
            "idx_end_start_dates": ("activity_end_date", "activity_start_date"),
            "idx_schedule_id": ("id",),
            "idx_status_venue_day_start": (
                "activity_status",
                "venue",
                "day_of_week",
                "start_time",
            ),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "admission_application_workflow": {
        "source": "vw_admission_application_workflow",
        "target": "mmv_admission_application_workflow",
        "indexes": {
            "idx_application": ("application_id",),
            "idx_status": ("application_status", "workflow_status"),
            "idx_workflow": ("workflow_instance_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_applications": {
        "source": "vw_admission_applications",
        "target": "mmv_admission_applications",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_applications_interviews": {
        "source": "vw_admission_applications_interviews",
        "target": "mmv_admission_applications_interviews",
        "indexes": {
            "idx_id": ("id",),
            "idx_interview": ("interview_record_id",),
            "idx_status": ("status",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_documents": {
        "source": "vw_admission_documents",
        "target": "mmv_admission_documents",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_interview_assessment_items": {
        "source": "vw_admission_interview_assessment_items",
        "target": "mmv_admission_interview_assessment_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_placement_tests": {
        "source": "vw_admission_placement_tests",
        "target": "mmv_admission_placement_tests",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "admission_windows": {
        "source": "vw_admission_windows",
        "target": "mmv_admission_windows",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "analytics_report_metrics": {
        "source": "vw_analytics_report_metrics",
        "target": "mmv_analytics_report_metrics",
        "indexes": {
            "idx_pk": ("report_definition_id", "metric_definition_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "analytics_report_runs": {
        "source": "vw_analytics_report_runs",
        "target": "mmv_analytics_report_runs",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "announcements_bulletin": {
        "source": "vw_announcements_bulletin",
        "target": "mmv_announcements_bulletin",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_learning_outcomes": {
        "source": "vw_assessment_learning_outcomes",
        "target": "mmv_assessment_learning_outcomes",
        "indexes": {
            "idx_pk": ("assessment_id", "learning_outcome_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_policy_documents": {
        "source": "vw_assessment_policy_documents",
        "target": "mmv_assessment_policy_documents",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_policy_schedule_rules": {
        "source": "vw_assessment_policy_schedule_rules",
        "target": "mmv_assessment_policy_schedule_rules",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_results": {
        "source": "vw_assessment_results",
        "target": "mmv_assessment_results",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_results_detailed": {
        "source": "vw_assessment_results_detailed",
        "target": "mmv_assessment_results_detailed",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_rubric_criteria": {
        "source": "vw_assessment_rubric_criteria",
        "target": "mmv_assessment_rubric_criteria",
        "indexes": {
            "idx_pk": ("assessment_id", "assessment_rubric_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_rubrics": {
        "source": "vw_assessment_rubrics",
        "target": "mmv_assessment_rubrics",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "assessment_tools": {
        "source": "vw_assessment_tools",
        "target": "mmv_assessment_tools",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "attendance_sessions": {
        "source": "vw_attendance_sessions",
        "target": "mmv_attendance_sessions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "attendance_sessions_config": {
        "source": "vw_attendance_sessions_config",
        "target": "mmv_attendance_sessions_config",
        "indexes": {
            "idx_id": ("id",),
            "idx_session": ("session_id",),
            "idx_term": ("academic_year_term_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "bank_transactions": {
        "source": "vw_bank_transactions",
        "target": "mmv_bank_transactions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "blocked_devices": {
        "source": "vw_blocked_devices",
        "target": "mmv_blocked_devices",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "budget_line_items": {
        "source": "vw_budget_line_items",
        "target": "mmv_budget_line_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "budget_utilization": {
        "source": "vw_budget_utilization",
        "target": "mmv_budget_utilization",
        "indexes": {
            "idx_budget": ("budget_id",),
            "idx_year_term": ("academic_year", "term"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "budgets": {
        "source": "vw_budgets",
        "target": "mmv_budgets",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "calendar_day_type": {
        "source": "vw_calendar_day",
        "target": "mmv_calendar_day_type",
        "indexes": {
            "idx_calendar": ("academic_year_calendar_id",),
            "idx_calendar_day": ("calendar_day_id",),
            "idx_date": ("calendar_date",),
            "idx_day_type": ("calendar_day_type_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "cash_reconciliation_sessions": {
        "source": "vw_cash_reconciliation_sessions",
        "target": "mmv_cash_reconciliation_sessions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "catalog_order_items": {
        "source": "vw_catalog_order_items",
        "target": "mmv_catalog_order_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "catalog_orders": {
        "source": "vw_catalog_orders",
        "target": "mmv_catalog_orders",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "catalog_reviews": {
        "source": "vw_catalog_reviews",
        "target": "mmv_catalog_reviews",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "catalog_stock_units": {
        "source": "vw_catalog_stock_units",
        "target": "mmv_catalog_stock_units",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "catalog_wishlists": {
        "source": "vw_catalog_wishlists",
        "target": "mmv_catalog_wishlists",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "chapel_program_sessions": {
        "source": "vw_chapel_program_sessions",
        "target": "mmv_chapel_program_sessions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "chaplaincy_volunteers": {
        "source": "vw_chaplaincy_volunteers",
        "target": "mmv_chaplaincy_volunteers",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "chart_of_accounts": {
        "source": "vw_chart_of_accounts",
        "target": "mmv_chart_of_accounts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "class_learning_area_performance": {
        "source": "vw_class_learning_area_performance",
        "target": "mmv_class_learning_area_performance",
        "indexes": {
            "idx_academic_year": ("academic_year",),
            "idx_class_name": ("class_name",),
            "idx_learning_area": ("learning_area",),
            "idx_stream_name": ("stream_name",),
            "idx_term_number": ("term_number",),
            "idx_year_class_term": ("academic_year_class_id", "term_number"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "deputy_class_formative_performance": {
        "source": "vw_deputy_class_formative_performance",
        "target": "mmv_deputy_class_formative_performance",
        "indexes": {
            "idx_term_year": ("academic_year_term_id", "academic_year_id"),
            "idx_class": ("class_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "classes": {
        "source": "vw_classes",
        "target": "mmv_classes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "collection_rate_by_class": {
        "source": "vw_collection_rate_by_class",
        "target": "mmv_collection_rate_by_class",
        "indexes": {
            "idx_level_term": ("level_code", "academic_term"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "communication_attachments": {
        "source": "vw_communication_attachments",
        "target": "mmv_communication_attachments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "communication_threads": {
        "source": "vw_communication_threads",
        "target": "mmv_communication_threads",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "competency_catalog": {
        "source": "vw_competency_catalog",
        "target": "mmv_competency_catalog",
        "indexes": {
            "idx_competency": ("competency_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "core_competencies": {
        "source": "vw_core_competencies",
        "target": "mmv_core_competencies",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "core_values": {
        "source": "vw_core_values",
        "target": "mmv_core_values",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "counseling_sessions": {
        "source": "vw_counseling_sessions",
        "target": "mmv_counseling_sessions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "curriculum_taxonomy": {
        "source": "vw_curriculum_taxonomy",
        "target": "mmv_curriculum_taxonomy",
        "indexes": {
            "idx_curriculum_grade_status": ("grade_level", "status"),
            "idx_curriculum_strand": ("strand_id",),
            "idx_learning_area_grade_status": (
                "learning_area_id",
                "grade_level",
                "status",
            ),
            "idx_sub_strand_code": ("code",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 3600,
    },
    "dashboard_catalog": {
        "source": "vw_dashboard_catalog",
        "target": "mmv_dashboard_catalog",
        "indexes": {
            "idx_dashboard": ("dashboard_id",),
            "idx_role": ("role_id",),
            "idx_route_name": ("route_name",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "dashboards": {
        "source": "vw_dashboards",
        "target": "mmv_dashboards",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "departments": {
        "source": "vw_departments",
        "target": "mmv_departments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "dormitories": {
        "source": "vw_dormitories",
        "target": "mmv_dormitories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "dormitory_occupancy": {
        "source": "vw_dormitory_occupancy",
        "target": "mmv_dormitory_occupancy",
        "indexes": {
            "idx_academic_year": ("academic_year",),
            "idx_dormitory_year": ("dormitory_id", "academic_year"),
            "idx_gender": ("gender",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "duty_roster_drafts": {
        "source": "vw_duty_roster_drafts",
        "target": "mmv_duty_roster_drafts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "enrollment_membership": {
        "source": "vw_enrollment_membership",
        "target": "mmv_enrollment_membership",
        "indexes": {
            "idx_aycs": ("academic_year_class_stream_id",),
            "idx_enrollment": ("enrollment_id",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "equipment_maintenance": {
        "source": "vw_equipment_maintenance",
        "target": "mmv_equipment_maintenance",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "exam_timetable_draft_entries": {
        "source": "vw_exam_timetable_draft_entries",
        "target": "mmv_exam_timetable_draft_entries",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "exam_timetable_drafts": {
        "source": "vw_exam_timetable_drafts",
        "target": "mmv_exam_timetable_drafts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "expense_directory": {
        "source": "vw_expense_directory",
        "target": "mmv_expense_directory",
        "indexes": {
            "idx_expense_approver": ("approved_by",),
            "idx_expense_budget_line_item": ("budget_line_item_id",),
            "idx_expense_category_date": ("category_id", "expense_date"),
            "idx_expense_creator": ("created_by",),
            "idx_expense_department_status_date": (
                "department_id",
                "status",
                "expense_date",
            ),
            "idx_expense_id": ("id",),
            "idx_expense_status_date": ("status", "expense_date", "id"),
            "idx_expense_vendor": ("vendor_id",),
            "idx_expense_year_term_status": ("academic_year", "term", "status"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "expenses": {
        "source": "vw_expenses",
        "target": "mmv_expenses",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "extra_charge_classes": {
        "source": "vw_extra_charge_classes",
        "target": "mmv_extra_charge_classes",
        "indexes": {
            "idx_pk": ("extra_charge_id", "class_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "extra_charges": {
        "source": "vw_extra_charges",
        "target": "mmv_extra_charges",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "fee_collection_monthly_trend": {
        "source": "vw_fee_collection_monthly_trend",
        "target": "mmv_fee_collection_monthly_trend",
        "indexes": {
            "idx_month": ("month",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 900,
    },
    "fee_credit_notes": {
        "source": "vw_fee_credit_notes",
        "target": "mmv_fee_credit_notes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "fee_status_summary": {
        "source": "vw_fee_status_summary",
        "target": "mmv_fee_status_summary",
        "indexes": {
            "idx_academic_year": ("academic_year",),
            "idx_admission_no": ("admission_no",),
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_current_balance": ("current_balance",),
            "idx_level": ("level_id",),
            "idx_payment_status": ("payment_status",),
            "idx_student_period": ("student_id", "academic_year", "term_number"),
            "idx_student_type": ("student_type_id",),
            "idx_term_number": ("term_number",),
            "idx_year_term_class_stream": (
                "academic_year",
                "term_number",
                "class_id",
                "stream_id",
            ),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "financial_statement_lines": {
        "source": "vw_financial_statement_lines",
        "target": "mmv_financial_statement_lines",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "fixed_assets": {
        "source": "vw_fixed_assets",
        "target": "mmv_fixed_assets",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "food_consumption_records": {
        "source": "vw_food_consumption_records",
        "target": "mmv_food_consumption_records",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "grade_rules": {
        "source": "vw_grade_rules",
        "target": "mmv_grade_rules",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "grading_scales": {
        "source": "vw_grading_scales",
        "target": "mmv_grading_scales",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "guardian_link": {
        "source": "vw_guardian_link",
        "target": "mmv_guardian_link",
        "indexes": {
            "idx_admission_no": ("admission_no",),
            "idx_pair": ("student_id", "parent_id"),
            "idx_parent": ("parent_id",),
            "idx_parent_user": ("parent_user_id",),
            "idx_primary": ("parent_user_id", "is_primary_contact"),
            "idx_scope_parent": ("scope_parent_id",),
            "idx_scope_student": ("scope_student_id",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "internal_conversation_member": {
        "source": "vw_internal_conversation_member",
        "target": "mmv_internal_conversation_member",
        "indexes": {
            "idx_member_activity": ("participant_id", "activity_at"),
            "idx_member_mute_activity": ("participant_id", "is_muted", "activity_at"),
            "uq_conversation_member": ("conversation_id", "participant_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "internal_message_recipient": {
        "source": "vw_internal_message_recipient",
        "target": "mmv_internal_message_recipient",
        "indexes": {
            "idx_recipient_conversation_created": (
                "recipient_id",
                "conversation_id",
                "created_at",
                "message_id",
            ),
            "uq_message_recipient": ("message_id", "recipient_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "internal_message_recipient_directory": {
        "source": "vw_internal_message_recipient_directory",
        "target": "mmv_internal_message_recipient_directory",
        "indexes": {
            "idx_directory_status_user": ("user_status", "user_id"),
            "uq_directory_user": ("user_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "inventory_categories": {
        "source": "vw_inventory_categories",
        "target": "mmv_inventory_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "inventory_items": {
        "source": "vw_inventory_items",
        "target": "mmv_inventory_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "inventory_locations": {
        "source": "vw_inventory_locations",
        "target": "mmv_inventory_locations",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "inventory_transactions": {
        "source": "vw_inventory_transactions",
        "target": "mmv_inventory_transactions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "inventory_transactions_items": {
        "source": "vw_inventory_transactions_items",
        "target": "mmv_inventory_transactions_items",
        "indexes": {
            "idx_id": ("id",),
            "idx_item": ("item_id",),
            "idx_transaction_date": ("transaction_date",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "job_vacancies": {
        "source": "vw_job_vacancies",
        "target": "mmv_job_vacancies",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "leadership_positions": {
        "source": "vw_leadership_positions",
        "target": "mmv_leadership_positions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "leadership_positions_categories": {
        "source": "vw_leadership_positions_categories",
        "target": "mmv_leadership_positions_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "learner_competencies": {
        "source": "vw_learner_competencies",
        "target": "mmv_learner_competencies",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "learner_competency": {
        "source": "vw_learner_competency",
        "target": "mmv_learner_competency",
        "indexes": {
            "idx_competency": ("competency_id",),
            "idx_student": ("student_id",),
            "idx_student_term": ("student_id", "term_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "learner_values_acquisition": {
        "source": "vw_learner_values_acquisition",
        "target": "mmv_learner_values_acquisition",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "learner_values_acquisition_values": {
        "source": "vw_learner_values_acquisition_values",
        "target": "mmv_learner_values_acquisition_values",
        "indexes": {
            "idx_id": ("id",),
            "idx_student": ("student_id",),
            "idx_value": ("value_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "learning_areas": {
        "source": "vw_learning_areas",
        "target": "mmv_learning_areas",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "learning_outcomes": {
        "source": "vw_learning_outcomes",
        "target": "mmv_learning_outcomes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "leave_types": {
        "source": "vw_leave_types",
        "target": "mmv_leave_types",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_assessment_rubrics": {
        "source": "vw_lesson_plan_assessment_rubrics",
        "target": "mmv_lesson_plan_assessment_rubrics",
        "indexes": {
            "idx_pk": ("lesson_plan_id", "assessment_rubric_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_assessment_tools": {
        "source": "vw_lesson_plan_assessment_tools",
        "target": "mmv_lesson_plan_assessment_tools",
        "indexes": {
            "idx_pk": ("lesson_plan_id", "assessment_tool_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_competencies": {
        "source": "vw_lesson_plan_competencies",
        "target": "mmv_lesson_plan_competencies",
        "indexes": {
            "idx_pk": ("lesson_plan_id", "competency_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_learner_evidence_questions": {
        "source": "vw_lesson_plan_learner_evidence_questions",
        "target": "mmv_lesson_plan_learner_evidence_questions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_learner_evidence_resources": {
        "source": "vw_lesson_plan_learner_evidence_resources",
        "target": "mmv_lesson_plan_learner_evidence_resources",
        "indexes": {
            "idx_pk": ("learner_evidence_id", "lesson_plan_resource_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_outcomes": {
        "source": "vw_lesson_plan_outcomes",
        "target": "mmv_lesson_plan_outcomes",
        "indexes": {
            "idx_pk": ("lesson_plan_id", "learning_outcome_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plan_rubrics": {
        "source": "vw_lesson_plan_rubrics",
        "target": "mmv_lesson_plan_rubrics",
        "indexes": {
            "idx_pk": ("lesson_plan_id", "sub_strand_rubric_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "lesson_plans": {
        "source": "vw_lesson_plans",
        "target": "mmv_lesson_plans",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "library_books": {
        "source": "vw_library_books",
        "target": "mmv_library_books",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "library_categories": {
        "source": "vw_library_categories",
        "target": "mmv_library_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "library_issues": {
        "source": "vw_library_issues",
        "target": "mmv_library_issues",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "meal_plans": {
        "source": "vw_meal_plans",
        "target": "mmv_meal_plans",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "mpesa_transactions": {
        "source": "vw_mpesa_transactions",
        "target": "mmv_mpesa_transactions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "mpesa_transactions_payments": {
        "source": "vw_mpesa_transactions_payments",
        "target": "mmv_mpesa_transactions_payments",
        "indexes": {
            "idx_id": ("id",),
            "idx_normalized_reference": ("normalized_reference",),
            "idx_payment": ("payment_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "national_assessment_windows": {
        "source": "vw_national_assessment_windows",
        "target": "mmv_national_assessment_windows",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "parent_meeting_targets": {
        "source": "vw_parent_meeting_targets",
        "target": "mmv_parent_meeting_targets",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "parent_student_dashboard": {
        "source": "vw_parent_student_dashboard",
        "target": "mmv_parent_student_dashboard",
        "indexes": {
            "idx_enrollment_year": (
                "student_academic_enrollment_id",
                "academic_year_id",
            ),
            "idx_parent_scope": ("parent_id", "student_data_scope"),
            "idx_parent_student": ("parent_id", "student_id"),
            "idx_parent_user": ("parent_user_id",),
            "idx_student_year": ("student_id", "academic_year_id"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 120,
    },
    "past_papers": {
        "source": "vw_past_papers",
        "target": "mmv_past_papers",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "payment_collection_route_channels": {
        "source": "vw_payment_collection_route_channels",
        "target": "mmv_payment_collection_route_channels",
        "indexes": {
            "idx_pk": ("route_id", "channel_id"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payment_collection_routes": {
        "source": "vw_payment_collection_routes",
        "target": "mmv_payment_collection_routes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payment_directory": {
        "source": "vw_payment_directory",
        "target": "mmv_payment_directory",
        "indexes": {
            "idx_payment_method_status_date": ("method", "status", "payment_date"),
            "idx_payment_receipt": ("receipt_no",),
            "idx_payment_receiver_date": ("received_by", "payment_date"),
            "idx_payment_reference": ("reference",),
            "idx_payment_status_date": ("status", "payment_date", "id"),
            "idx_payment_student_date": ("student_id", "payment_date", "id"),
            "idx_payment_year_term_date": (
                "academic_year",
                "academic_year_term_id",
                "payment_date",
            ),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payment_pos_terminals": {
        "source": "vw_payment_pos_terminals",
        "target": "mmv_payment_pos_terminals",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payment_reconciliations": {
        "source": "vw_payment_reconciliations",
        "target": "mmv_payment_reconciliations",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payment_unmatched_cases": {
        "source": "vw_payment_unmatched_cases",
        "target": "mmv_payment_unmatched_cases",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payments": {
        "source": "vw_payments",
        "target": "mmv_payments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payroll_runs": {
        "source": "vw_payroll_runs",
        "target": "mmv_payroll_runs",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "payslip": {
        "source": "vw_payslip",
        "target": "mmv_payslip",
        "indexes": {
            "idx_period": ("payroll_month", "payroll_year"),
            "idx_staff": ("staff_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 900,
    },
    "payslips": {
        "source": "vw_payslips",
        "target": "mmv_payslips",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "performance_review_kpis": {
        "source": "vw_performance_review_kpis",
        "target": "mmv_performance_review_kpis",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "person_directory": {
        "source": "vw_person_directory",
        "target": "mmv_person_directory",
        "indexes": {
            "idx_admission_no": ("admission_no",),
            "idx_parent": ("parent_id",),
            "idx_person": ("person_id",),
            "idx_phone": ("phone",),
            "idx_staff": ("staff_id",),
            "idx_student": ("student_id",),
            "idx_user": ("user_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "portfolio_artifacts": {
        "source": "vw_portfolio_artifacts",
        "target": "mmv_portfolio_artifacts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "portfolio_artifacts_portfolios": {
        "source": "vw_portfolio_artifacts_portfolios",
        "target": "mmv_portfolio_artifacts_portfolios",
        "indexes": {
            "idx_id": ("id",),
            "idx_portfolio": ("portfolio_id",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "portfolio_hub_class": {
        "source": "vw_portfolio_hub_class",
        "target": "mmv_portfolio_hub_class",
        "indexes": {
            "idx_class_term_student": ("class_id", "filter_term_id", "student_id"),
            "idx_portfolio": ("portfolio_id",),
            "idx_term_class": ("filter_term_id", "class_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "portfolio_hub_summary": {
        "source": "vw_portfolio_hub_summary",
        "target": "mmv_portfolio_hub_summary",
        "indexes": {
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_student_year": ("student_id", "academic_year_id"),
            "idx_year_week": ("academic_year_id", "week_number"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "promotion_batches": {
        "source": "vw_promotion_batches",
        "target": "mmv_promotion_batches",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "purchase_orders": {
        "source": "vw_purchase_orders",
        "target": "mmv_purchase_orders",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "requisition_items": {
        "source": "vw_requisition_items",
        "target": "mmv_requisition_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "requisitions": {
        "source": "vw_requisitions",
        "target": "mmv_requisitions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "rooms": {
        "source": "vw_rooms",
        "target": "mmv_rooms",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "routes_registry": {
        "source": "vw_routes_registry",
        "target": "mmv_routes_registry",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "security",
        "max_age_seconds": 600,
    },
    "scheme_templates": {
        "source": "vw_scheme_templates",
        "target": "mmv_scheme_templates",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "scheme_workbook_items": {
        "source": "vw_scheme_workbook_items",
        "target": "mmv_scheme_workbook_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "scheme_workbook_weeks": {
        "source": "vw_scheme_workbook_weeks",
        "target": "mmv_scheme_workbook_weeks",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "scheme_workbooks": {
        "source": "vw_scheme_workbooks",
        "target": "mmv_scheme_workbooks",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "schemes_of_work": {
        "source": "vw_schemes_of_work",
        "target": "mmv_schemes_of_work",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "school_financial_account_channels": {
        "source": "vw_school_financial_account_channels",
        "target": "mmv_school_financial_account_channels",
        "indexes": {
            "idx_pk": ("financial_account_id", "channel_id"),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "school_financial_accounts": {
        "source": "vw_school_financial_accounts",
        "target": "mmv_school_financial_accounts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "sidebar_menu_items": {
        "source": "vw_sidebar_menu_items",
        "target": "mmv_sidebar_menu_items",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "staff_appointments": {
        "source": "vw_staff_appointments",
        "target": "mmv_staff_appointments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_categories": {
        "source": "vw_staff_categories",
        "target": "mmv_staff_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_children": {
        "source": "vw_staff_children",
        "target": "mmv_staff_children",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_daily_register": {
        "source": "vw_staff_daily_register",
        "target": "mmv_staff_daily_register",
        "indexes": {
            "idx_date": ("date",),
            "idx_date_staff": ("date", "staff_id"),
            "idx_department": ("department_id",),
            "idx_marked_status": ("marked_status",),
            "idx_staff": ("staff_id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_deductions": {
        "source": "vw_staff_deductions",
        "target": "mmv_staff_deductions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_department_assignments": {
        "source": "vw_staff_department_assignments",
        "target": "mmv_staff_department_assignments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_directory": {
        "source": "vw_staff_directory",
        "target": "mmv_staff_directory",
        "indexes": {
            "idx_person": ("person_id",),
            "idx_staff": ("staff_id",),
            "idx_staff_no": ("staff_no",),
            "idx_status": ("staff_status",),
            "idx_type_category": ("staff_type_id", "staff_category_id"),
            "idx_user": ("user_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "staff_duty_roster": {
        "source": "vw_staff_duty_roster",
        "target": "mmv_staff_duty_roster",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_meeting_member": {
        "source": "vw_staff_meeting_member",
        "target": "mmv_staff_meeting_member",
        "indexes": {
            "idx_meeting_date_start": ("meeting_date", "start_time"),
            "idx_meeting_staff": ("meeting_id", "staff_id"),
            "idx_staff_meeting": ("staff_id", "meeting_id"),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_meeting_summary": {
        "source": "vw_staff_meeting_summary",
        "target": "mmv_staff_meeting_summary",
        "indexes": {
            "idx_department_status_date_start": (
                "department_id",
                "status",
                "meeting_date",
                "start_time",
            ),
            "idx_meeting_date_start": ("meeting_date", "start_time"),
            "idx_meeting_id": ("id",),
            "idx_meeting_type_status_date_start": (
                "meeting_type",
                "status",
                "meeting_date",
                "start_time",
            ),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_offboarding": {
        "source": "vw_staff_offboarding",
        "target": "mmv_staff_offboarding",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_payroll_award_recipients": {
        "source": "vw_staff_payroll_award_recipients",
        "target": "mmv_staff_payroll_award_recipients",
        "indexes": {
            "idx_pk": ("batch_id", "staff_id"),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_profile_assignment": {
        "source": "vw_staff_profile_assignment",
        "target": "mmv_staff_profile_assignment",
        "indexes": {
            "idx_department": ("department_id",),
            "idx_person": ("staff_person_id",),
            "idx_staff": ("staff_id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 900,
    },
    "staff_qualifications": {
        "source": "vw_staff_qualifications",
        "target": "mmv_staff_qualifications",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_role_default_positions": {
        "source": "vw_staff_role_default_positions",
        "target": "mmv_staff_role_default_positions",
        "indexes": {
            "idx_pk": ("role_id", "position_id"),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_specialization_qualifications": {
        "source": "vw_staff_specialization_qualifications",
        "target": "mmv_staff_specialization_qualifications",
        "indexes": {
            "idx_pk": ("specialization_id", "qualification_id"),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "staff_types": {
        "source": "vw_staff_types",
        "target": "mmv_staff_types",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "staff_workload": {
        "source": "vw_staff_workload",
        "target": "mmv_staff_workload",
        "indexes": {
            "idx_category": ("category_name",),
            "idx_staff": ("staff_id",),
            "idx_staff_no": ("staff_no",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 900,
    },
    "strand_competency": {
        "source": "vw_strand_competency",
        "target": "mmv_strand_competency",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "strands": {
        "source": "vw_strands",
        "target": "mmv_strands",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "strands_learning_areas": {
        "source": "vw_strands_learning_areas",
        "target": "mmv_strands_learning_areas",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "strands_sub_strands": {
        "source": "vw_strands_sub_strands",
        "target": "mmv_strands_sub_strands",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "streams": {
        "source": "vw_streams",
        "target": "mmv_streams",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "student_academic_enrollments_fees": {
        "source": "vw_student_academic_enrollments_fees",
        "target": "mmv_student_academic_enrollments_fees",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "student_academic_enrollments_streams": {
        "source": "vw_student_academic_enrollments_streams",
        "target": "mmv_student_academic_enrollments_streams",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_attendance": {
        "source": "vw_student_attendance",
        "target": "mmv_student_attendance",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_attendance_analytics": {
        "source": "vw_student_attendance_analytics",
        "target": "mmv_student_attendance_analytics",
        "indexes": {
            "idx_class": ("class_name",),
            "idx_student": ("student_id",),
            "idx_student_year": ("student_id", "academic_year"),
            "idx_year_term": ("academic_year", "term_number"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_attendance_enrollment": {
        "source": "vw_student_attendance_enrollment",
        "target": "mmv_student_attendance_enrollment",
        "indexes": {
            "idx_date": ("attendance_date",),
            "idx_enrollment": ("student_academic_enrollment_id",),
            "idx_status": ("status",),
            "idx_student": ("student_id",),
            "idx_year": ("academic_year_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_attendance_summary": {
        "source": "vw_student_attendance_summary",
        "target": "mmv_student_attendance_summary",
        "indexes": {
            "idx_date": ("date",),
            "idx_date_session": ("date", "session_name"),
            "idx_status": ("status",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_award_categories": {
        "source": "vw_student_award_categories",
        "target": "mmv_student_award_categories",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_award_types": {
        "source": "vw_student_award_types",
        "target": "mmv_student_award_types",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_clearances": {
        "source": "vw_student_clearances",
        "target": "mmv_student_clearances",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_dashboard_summary": {
        "source": "vw_student_dashboard_summary",
        "target": "mmv_student_dashboard_summary",
        "indexes": {
            "idx_academic_year": ("academic_year_id",),
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_directory": {
        "source": "vw_student_directory",
        "target": "mmv_student_directory",
        "indexes": {
            "idx_admission_no": ("admission_no",),
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_person": ("person_id",),
            "idx_scope_stream": ("scope_class_stream_id",),
            "idx_scope_student": ("scope_student_id",),
            "idx_status": ("enrollment_status",),
            "idx_student": ("student_id",),
            "idx_student_year": ("student_id", "academic_year_id"),
            "idx_type": ("student_type_id",),
            "idx_year_class": ("academic_year_id", "class_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_fee_balances": {
        "source": "vw_student_fee_balances",
        "target": "mmv_student_fee_balances",
        "indexes": {
            "idx_enrollment": ("student_academic_enrollment_id",),
            "idx_status": ("payment_status",),
            "idx_student": ("student_id",),
            "idx_student_year": ("student_id", "academic_year_id"),
            "idx_term": ("term_id",),
            "idx_year": ("academic_year_id",),
            "idx_year_term": ("academic_year_term_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 120,
    },
    "student_fee_ledger": {
        "source": "vw_student_fee_ledger",
        "target": "mmv_student_fee_ledger",
        "indexes": {
            "idx_enrollment": ("student_academic_enrollment_id",),
            "idx_status": ("payment_status",),
            "idx_student": ("student_id",),
            "idx_student_term": ("student_id", "term_id"),
            "idx_term": ("term_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 120,
    },
    "student_fee_migration_snapshots": {
        "source": "vw_student_fee_migration_snapshots",
        "target": "mmv_student_fee_migration_snapshots",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_fee_obligations": {
        "source": "vw_student_fee_obligations",
        "target": "mmv_student_fee_obligations",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "student_fee_obligations_enrolled": {
        "source": "vw_student_fee_obligations_enrolled",
        "target": "mmv_student_fee_obligations_enrolled",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "student_fee_rollover_balances": {
        "source": "vw_student_fee_rollover_balances",
        "target": "mmv_student_fee_rollover_balances",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_health_records": {
        "source": "vw_student_health_records",
        "target": "mmv_student_health_records",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_health_summary": {
        "source": "vw_student_health_summary",
        "target": "mmv_student_health_summary",
        "indexes": {
            "idx_admission_no": ("admission_no",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "health",
        "max_age_seconds": 600,
    },
    "student_health_visits": {
        "source": "vw_student_health_visits",
        "target": "mmv_student_health_visits",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_learning_progress": {
        "source": "vw_student_learning_progress",
        "target": "mmv_student_learning_progress",
        "indexes": {
            "idx_learning_area": ("learning_area",),
            "idx_student": ("student_id",),
            "idx_student_year": ("student_id", "academic_year"),
            "idx_year_term": ("academic_year", "term_number"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "student_growth_trend": {
        "source": "vw_student_growth_trend",
        "target": "mmv_student_growth_trend",
        "indexes": {
            "idx_student": ("student_id",),
            "idx_student_area": ("student_id", "learning_area_id"),
            "idx_year_term": ("year", "term_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "student_timeline_subject_scores": {
        "source": "vw_student_timeline_subject_scores",
        "target": "mmv_student_timeline_subject_scores",
        "indexes": {
            "idx_student": ("student_id",),
            "idx_student_year_term": ("student_id", "academic_year_id", "term_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "student_parents": {
        "source": "vw_student_parents",
        "target": "mmv_student_parents",
        "indexes": {
            "idx_pk": ("student_id", "parent_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_permissions": {
        "source": "vw_student_permissions",
        "target": "mmv_student_permissions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_scholarship_awards": {
        "source": "vw_student_scholarship_awards",
        "target": "mmv_student_scholarship_awards",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_term_performance": {
        "source": "vw_student_term_performance",
        "target": "mmv_student_term_performance",
        "indexes": {
            "idx_student": ("student_id",),
            "idx_student_year": ("student_id", "academic_year"),
            "idx_term": ("term_number",),
            "idx_year": ("academic_year",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "student_term_placement": {
        "source": "vw_student_term_placement",
        "target": "mmv_student_term_placement",
        "indexes": {
            "idx_admission_no": ("admission_no",),
            "idx_ayt": ("academic_year_term_id",),
            "idx_enrollment": ("enrollment_id",),
            "idx_person": ("person_id",),
            "idx_scope_stream": ("scope_class_stream_id",),
            "idx_scope_student": ("scope_student_id",),
            "idx_student": ("student_id",),
            "idx_student_term": ("student_id", "academic_year_term_id"),
            "idx_term": ("term_id",),
            "idx_year_class": ("academic_year_id", "class_id"),
            "idx_year_term": ("academic_year_id", "academic_year_term_id"),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_transitions": {
        "source": "vw_student_transitions",
        "target": "mmv_student_transitions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_transport_assignments": {
        "source": "vw_student_transport_assignments",
        "target": "mmv_student_transport_assignments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_transport_attendance": {
        "source": "vw_student_transport_attendance",
        "target": "mmv_student_transport_attendance",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_transport_entitlements": {
        "source": "vw_student_transport_entitlements",
        "target": "mmv_student_transport_entitlements",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_transport_roster": {
        "source": "vw_student_transport_roster",
        "target": "mmv_student_transport_roster",
        "indexes": {
            "idx_transport_assignment": ("assignment_id",),
            "idx_transport_class_stream": ("class_id", "stream_id"),
            "idx_transport_driver": ("driver_id",),
            "idx_transport_route_status": ("route_id", "assignment_status"),
            "idx_transport_student_status": ("student_id", "assignment_status"),
            "idx_transport_vehicle": ("vehicle_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "student_transport_summary": {
        "source": "vw_student_transport_summary",
        "target": "mmv_student_transport_summary",
        "indexes": {
            "idx_billing_month": ("billing_month",),
            "idx_status": ("payment_status",),
            "idx_student": ("student_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "student_vaccinations": {
        "source": "vw_student_vaccinations",
        "target": "mmv_student_vaccinations",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "sub_strand_competencies": {
        "source": "vw_sub_strand_competencies",
        "target": "mmv_sub_strand_competencies",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "sub_strands": {
        "source": "vw_sub_strands",
        "target": "mmv_sub_strands",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "suppliers": {
        "source": "vw_suppliers",
        "target": "mmv_suppliers",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "term_subject_scores": {
        "source": "vw_term_subject_scores",
        "target": "mmv_term_subject_scores",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "time_slots": {
        "source": "vw_time_slots",
        "target": "mmv_time_slots",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "timetable_conflict": {
        "source": "vw_timetable_conflicts",
        "target": "mmv_timetable_conflict",
        "indexes": {
            "idx_schedule_pair": ("schedule_id_1", "schedule_id_2"),
            "idx_type_stream_term": (
                "conflict_type",
                "academic_year_class_stream_id",
                "academic_year_term_id",
            ),
            "idx_type_term": ("conflict_type", "academic_year_term_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "timetable_drafts": {
        "source": "vw_timetable_drafts",
        "target": "mmv_timetable_drafts",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "timetable_entries": {
        "source": "vw_timetable_entries",
        "target": "mmv_timetable_entries",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "transport_entitlement_payment_allocations": {
        "source": "vw_transport_entitlement_payment_allocations",
        "target": "mmv_transport_entitlement_payment_allocations",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "transport_monthly_bills": {
        "source": "vw_transport_monthly_bills",
        "target": "mmv_transport_monthly_bills",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "transport_payment_intents": {
        "source": "vw_transport_payment_intents",
        "target": "mmv_transport_payment_intents",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "transport_routes": {
        "source": "vw_transport_routes",
        "target": "mmv_transport_routes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "transport_vehicle_routes": {
        "source": "vw_transport_vehicle_routes",
        "target": "mmv_transport_vehicle_routes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "uniform_catalog": {
        "source": "vw_uniform_catalog",
        "target": "mmv_uniform_catalog",
        "indexes": {
            "idx_item": ("item_id",),
            "idx_product": ("product_id",),
            "idx_size": ("size_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "uniform_catalog_images": {
        "source": "vw_uniform_catalog_images",
        "target": "mmv_uniform_catalog_images",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "uniform_catalog_products": {
        "source": "vw_uniform_catalog_products",
        "target": "mmv_uniform_catalog_products",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "uniform_payment_intents": {
        "source": "vw_uniform_payment_intents",
        "target": "mmv_uniform_payment_intents",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "uniform_payment_records": {
        "source": "vw_uniform_payment_records",
        "target": "mmv_uniform_payment_records",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "uniform_sales": {
        "source": "vw_uniform_sales",
        "target": "mmv_uniform_sales",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 600,
    },
    "uniform_sizes": {
        "source": "vw_uniform_sizes",
        "target": "mmv_uniform_sizes",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "vehicles": {
        "source": "vw_vehicles",
        "target": "mmv_vehicles",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "workflow_definitions": {
        "source": "vw_workflow_definitions",
        "target": "mmv_workflow_definitions",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "workflow_instances": {
        "source": "vw_workflow_instances",
        "target": "mmv_workflow_instances",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "workflow_stage_history": {
        "source": "vw_workflow_stage_history",
        "target": "mmv_workflow_stage_history",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "workflow_stages": {
        "source": "vw_workflow_stages",
        "target": "mmv_workflow_stages",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
    "persons": {
        "source": "vw_persons",
        "target": "mmv_persons",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "student_academic_enrollments": {
        "source": "vw_student_academic_enrollments",
        "target": "mmv_student_academic_enrollments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "academic_year_class_streams": {
        "source": "vw_academic_year_class_streams",
        "target": "mmv_academic_year_class_streams",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "students": {
        "source": "vw_students",
        "target": "mmv_students",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "staff": {
        "source": "vw_staff",
        "target": "mmv_staff",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 600,
    },
    "academic_year_terms": {
        "source": "vw_academic_year_terms",
        "target": "mmv_academic_year_terms",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "assessments": {
        "source": "vw_assessments",
        "target": "mmv_assessments",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 900,
    },
    "parents": {
        "source": "vw_parents",
        "target": "mmv_parents",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "exam_schedules": {
        "source": "vw_exam_schedules",
        "target": "mmv_exam_schedules",
        "indexes": {
            "idx_pk": ("id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "learner_placement": {
        "source": "vw_learner_placement",
        "target": "mmv_learner_placement",
        "indexes": {
            "idx_pk": ("enrollment_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "exam_context": {
        "source": "vw_exam_context",
        "target": "mmv_exam_context",
        "indexes": {
            "idx_pk": ("result_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "scheme_lesson_context": {
        "source": "vw_scheme_lesson_context",
        "target": "mmv_scheme_lesson_context",
        "indexes": {
            "idx_pk": ("scheme_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "learner_guardian": {
        "source": "vw_learner_guardian",
        "target": "mmv_learner_guardian",
        "indexes": {
            "idx_pk": ("student_parent_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "staff_context": {
        "source": "vw_staff_context",
        "target": "mmv_staff_context",
        "indexes": {
            "idx_pk": ("staff_id",),
        },
        "sensitivity": "staff",
        "max_age_seconds": 900,
    },
    "attendance_register_context": {
        "source": "vw_attendance_register_context",
        "target": "mmv_attendance_register_context",
        "indexes": {
            "idx_pk": ("attendance_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 300,
    },
    "fee_statement_context": {
        "source": "vw_fee_statement_context",
        "target": "mmv_fee_statement_context",
        "indexes": {
            "idx_pk": ("student_academic_enrollment_id",),
        },
        "sensitivity": "financial",
        "max_age_seconds": 120,
    },
    "admission_workflow_context": {
        "source": "vw_admission_workflow_context",
        "target": "mmv_admission_workflow_context",
        "indexes": {
            "idx_pk": ("application_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "transport_assignment_context": {
        "source": "vw_transport_assignment_context",
        "target": "mmv_transport_assignment_context",
        "indexes": {
            "idx_pk": ("assignment_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "boarding_dorm_context": {
        "source": "vw_boarding_dorm_context",
        "target": "mmv_boarding_dorm_context",
        "indexes": {
            "idx_pk": ("assignment_id",),
        },
        "sensitivity": "personal",
        "max_age_seconds": 600,
    },
    "lesson_plan_context": {
        "source": "vw_lesson_plan_context",
        "target": "mmv_lesson_plan_context",
        "indexes": {
            "idx_pk": ("lesson_plan_id",),
        },
        "sensitivity": "normal",
        "max_age_seconds": 900,
    },
    "class_stream_directory": {
        "source": "vw_class_stream_directory",
        "target": "mmv_class_stream_directory",
        "indexes": {
            "idx_pk": ("id",),
            "idx_year_class": ("academic_year_id", "class_id"),
        },
        "sensitivity": "normal",
        "max_age_seconds": 600,
    },
}

_IDENTIFIER = re.compile(r"^[A-Za-z0-9_]{1,64}$")


class ReadModelError(RuntimeError):
    pass


def _qid(value: str) -> str:
    if not _IDENTIFIER.fullmatch(value):
        raise ReadModelError("Unsafe database identifier")
    return f"`{value}`"


def _qualified(schema: str, table: str) -> str:
    return f"{_qid(schema)}.{_qid(table)}"


class ReadModelRefresher:
    def __init__(self, config: Any, connector: Callable[..., Any] | None = None):
        self.config = config
        self.connector = connector

    def _connect(self):
        if self.connector is not None:
            return self.connector(
                host=self.config.db_host,
                port=self.config.db_port,
                user=self.config.db_user,
                password=self.config.db_password,
                database=self.config.db_master_schema,
                charset="utf8mb4",
                connect_timeout=self.config.db_connect_timeout,
                read_timeout=self.config.db_read_timeout,
                write_timeout=30,
                autocommit=True,
                ssl_ca=self.config.db_ssl_ca or None,
                ssl_verify_cert=bool(self.config.db_ssl_ca),
                ssl_verify_identity=bool(self.config.db_ssl_ca),
            )
        try:
            import pymysql
        except ImportError as error:  # fail closed when not installed
            raise ReadModelError(
                "The read-model database driver is not installed"
            ) from error
        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            database=self.config.db_master_schema,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            write_timeout=30,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def refresh(self, projection: str) -> dict[str, Any]:
        if not self.config.read_models_enabled:
            raise ReadModelError("Python read-model refresh is disabled")
        if (
            not self.config.db_host
            or not self.config.db_user
            or not self.config.db_password
        ):
            raise ReadModelError("Python read-model database settings are incomplete")
        if (
            self.config.db_host not in {"localhost", "127.0.0.1", "::1"}
            and not self.config.db_ssl_ca
        ):
            raise ReadModelError(
                "A TLS CA certificate is required for remote database access"
            )
        definition = PROJECTIONS.get(projection)
        if definition is None:
            raise ReadModelError("Unknown read projection")

        source = _qualified(self.config.db_master_schema, definition["source"])
        target = _qualified(self.config.db_reads_schema, definition["target"])
        stage_name = f"__stage_{definition['target'][:40]}_{uuid.uuid4().hex[:12]}"
        stage = _qualified(self.config.db_reads_schema, stage_name)
        lock_name = f"KingswayProjection:{projection}"
        started = time.perf_counter()
        source_ms = index_ms = 0
        rows = 0
        connection = self._connect()
        locked = False
        try:
            with connection.cursor() as cursor:
                cursor.execute("SELECT GET_LOCK(%s, 0)", (lock_name,))
                locked = bool(cursor.fetchone()[0] == 1)
                if not locked:
                    raise ReadModelError(
                        "This read projection is already being refreshed"
                    )

                cursor.execute(
                    f"SELECT COUNT(*) FROM information_schema.TABLES "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_master_schema, definition["source"]),
                )
                if int(cursor.fetchone()[0]) != 1:
                    raise ReadModelError("Read projection source is unavailable")

                cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                cursor.execute(
                    f"CREATE TABLE {stage} AS SELECT * FROM {source} WHERE 1=0"
                )
                phase = time.perf_counter()
                cursor.execute(f"INSERT INTO {stage} SELECT * FROM {source}")
                rows = max(0, int(cursor.rowcount))
                source_ms = int((time.perf_counter() - phase) * 1000)

                phase = time.perf_counter()
                cursor.execute(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_reads_schema, stage_name),
                )
                columns = {str(row[0]) for row in cursor.fetchall()}
                for name, index_columns in definition["indexes"].items():
                    if any(column not in columns for column in index_columns):
                        raise ReadModelError(
                            "Projection output does not match its registered index"
                        )
                    keys = ",".join(_qid(column) for column in index_columns)
                    cursor.execute(
                        f"ALTER TABLE {stage} ADD INDEX {_qid(name)} ({keys})"
                    )
                index_ms = int((time.perf_counter() - phase) * 1000)

                cursor.execute(
                    "SELECT COUNT(*) FROM information_schema.TABLES "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_reads_schema, definition["target"]),
                )
                exists = int(cursor.fetchone()[0]) == 1
                if exists:
                    old_name = f"__stage_old_{definition['target'][:40]}_{uuid.uuid4().hex[:8]}"
                    old = _qualified(self.config.db_reads_schema, old_name)
                    cursor.execute(
                        f"RENAME TABLE {target} TO {old}, {stage} TO {target}"
                    )
                    cursor.execute(f"DROP TABLE {old}")
                else:
                    cursor.execute(f"RENAME TABLE {stage} TO {target}")

                watermark = None
                if projection == "fee_collection_monthly_trend":
                    cursor.execute(f"SELECT MAX(`month`) FROM {source}")
                    value = cursor.fetchone()[0]
                    watermark = str(value) if value is not None else None

                cursor.execute(
                    f"INSERT INTO {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                    "(projection, source_view, rows_count, source_watermark, as_of, refreshed_at, status, "
                    "storage_mode, sensitivity, max_age_seconds, last_error) "
                    "VALUES (%s,%s,%s,%s,NOW(),NOW(),'live','materialized_table',%s,%s,NULL) "
                    "ON DUPLICATE KEY UPDATE source_view=VALUES(source_view), rows_count=VALUES(rows_count), "
                    "source_watermark=VALUES(source_watermark), as_of=VALUES(as_of), refreshed_at=VALUES(refreshed_at), "
                    "status='live', storage_mode='materialized_table', sensitivity=VALUES(sensitivity), "
                    "max_age_seconds=VALUES(max_age_seconds), last_error=NULL",
                    (
                        projection,
                        f"{self.config.db_master_schema}.{definition['source']}",
                        rows,
                        watermark,
                        definition["sensitivity"],
                        definition["max_age_seconds"],
                    ),
                )

            return {
                "status": "published",
                "projection": projection,
                "target": definition["target"],
                "rows_count": rows,
                "source_query_ms": source_ms,
                "index_build_ms": index_ms,
                "duration_ms": int((time.perf_counter() - started) * 1000),
            }
        except Exception as error:
            try:
                with connection.cursor() as cursor:
                    cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                    cursor.execute(
                        "SELECT COUNT(*) FROM information_schema.TABLES "
                        "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                        (self.config.db_reads_schema, definition["target"]),
                    )
                    target_exists = int(cursor.fetchone()[0]) == 1
                    if target_exists:
                        cursor.execute(
                            f"UPDATE {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                            "SET last_error=%s WHERE projection=%s",
                            (type(error).__name__, projection),
                        )
                    else:
                        cursor.execute(
                            f"UPDATE {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                            "SET status='failed', refreshed_at=NULL, last_error=%s WHERE projection=%s",
                            (type(error).__name__, projection),
                        )
            except Exception:
                pass
            if isinstance(error, ReadModelError):
                raise
            raise ReadModelError("Read projection refresh failed") from error
        finally:
            if locked:
                try:
                    with connection.cursor() as cursor:
                        cursor.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
                except Exception:
                    pass
            connection.close()


class PolarsReadModelRefresher:
    """Bounded Python/Polars publisher for large, allowlisted read models.

    The master view remains the canonical business definition. Python streams
    its rows in bounded batches, Polars frames each batch, and PyMySQL bulk
    writes into an indexed staging table before an atomic swap. This avoids
    reimplementing school business rules in a second engine.
    """

    def __init__(self, config: Any):
        self.config = config
        try:
            import polars as pl
        except ImportError as error:
            raise ReadModelError("polars is not installed") from error
        self.pl = pl

    def _connect_master(self):
        import pymysql

        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            database=self.config.db_master_schema,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            write_timeout=30,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def _connect_reads(self):
        import pymysql

        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            database=self.config.db_reads_schema,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            write_timeout=30,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def refresh(self, projection: str) -> dict[str, Any]:
        """Publish an allowlisted source view through bounded Polars batches."""
        import time

        if not self.config.read_models_enabled:
            raise ReadModelError("Python read-model refresh is disabled")
        definition = PROJECTIONS.get(projection)
        if definition is None:
            raise ReadModelError("Unknown read projection")
        return self._refresh_source_view(projection, definition, time.perf_counter())

    def _refresh_source_view(
        self, projection: str, definition: dict[str, Any], started: float
    ) -> dict[str, Any]:
        import pymysql
        import time
        import uuid

        source_name = str(definition["source"])
        target_name = str(definition["target"])
        if not re.fullmatch(r"[A-Za-z0-9_]+", source_name) or not re.fullmatch(
            r"[A-Za-z0-9_]+", target_name
        ):
            raise ReadModelError(
                "Read projection definition contains an invalid object name"
            )

        master = self._connect_master()
        reads = self._connect_reads()
        stage_name = f"__stage_{target_name[:40]}_{uuid.uuid4().hex[:12]}"
        old_name = f"__stage_old_{target_name[:40]}_{uuid.uuid4().hex[:8]}"
        source = f"`{self.config.db_master_schema}`.`{source_name}`"
        target = f"`{self.config.db_reads_schema}`.`{target_name}`"
        stage = f"`{self.config.db_reads_schema}`.`{stage_name}`"
        lock_name = f"KingswayProjection:{projection}"
        locked = False
        rows_written = source_ms = write_ms = 0
        try:
            with reads.cursor() as cursor:
                cursor.execute("SELECT GET_LOCK(%s, 0)", (lock_name,))
                locked = cursor.fetchone()[0] == 1
                if not locked:
                    raise ReadModelError(
                        "This read projection is already being refreshed"
                    )
                cursor.execute(
                    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_reads_schema, target_name),
                )
                if int(cursor.fetchone()[0]) != 1:
                    raise ReadModelError("Read projection target is unavailable")
                cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                cursor.execute(f"CREATE TABLE {stage} LIKE {target}")

            with master.cursor(pymysql.cursors.SSCursor) as source_cursor:
                phase = time.perf_counter()
                source_cursor.execute(f"SELECT * FROM {source}")
                source_columns = [
                    str(column[0]) for column in source_cursor.description or ()
                ]
                source_ms = int((time.perf_counter() - phase) * 1000)
                if not source_columns or any(
                    not re.fullmatch(r"[A-Za-z0-9_]+", column)
                    for column in source_columns
                ):
                    raise ReadModelError(
                        "Read projection source has an invalid column set"
                    )
                with reads.cursor() as cursor:
                    cursor.execute(f"SHOW COLUMNS FROM {target}")
                    target_columns = [str(row[0]) for row in cursor.fetchall()]
                if source_columns != target_columns:
                    raise ReadModelError(
                        "Read projection source and target schemas differ"
                    )

                names_sql = ",".join(_qid(column) for column in source_columns)
                placeholders = ",".join(["%s"] * len(source_columns))
                insert_sql = (
                    f"INSERT INTO {stage} ({names_sql}) VALUES ({placeholders})"
                )
                write_started = time.perf_counter()
                while True:
                    batch = source_cursor.fetchmany(1000)
                    if not batch:
                        break
                    frame = self.pl.DataFrame(
                        batch,
                        schema=source_columns,
                        orient="row",
                        infer_schema_length=None,
                    )
                    with reads.cursor() as cursor:
                        cursor.executemany(insert_sql, frame.rows())
                    rows_written += frame.height
                write_ms = int((time.perf_counter() - write_started) * 1000)

            with reads.cursor() as cursor:
                cursor.execute(
                    f"RENAME TABLE {target} TO `{self.config.db_reads_schema}`.`{old_name}`, {stage} TO {target}"
                )
                cursor.execute(
                    f"DROP TABLE IF EXISTS `{self.config.db_reads_schema}`.`{old_name}`"
                )
                cursor.execute(
                    f"INSERT INTO `{self.config.db_reads_schema}`.`reads_meta` "
                    "(projection, source_view, rows_count, source_watermark, as_of, refreshed_at, status, storage_mode, sensitivity, max_age_seconds, last_error) "
                    "VALUES (%s,%s,%s,NULL,NOW(),NOW(),'live','materialized_table',%s,%s,NULL) "
                    "ON DUPLICATE KEY UPDATE source_view=VALUES(source_view), rows_count=VALUES(rows_count), source_watermark=NULL, as_of=NOW(), refreshed_at=NOW(), status='live', storage_mode='materialized_table', sensitivity=VALUES(sensitivity), max_age_seconds=VALUES(max_age_seconds), last_error=NULL",
                    (
                        projection,
                        f"{self.config.db_master_schema}.{source_name}",
                        rows_written,
                        definition["sensitivity"],
                        definition["max_age_seconds"],
                    ),
                )

            return {
                "status": "published",
                "projection": projection,
                "target": target_name,
                "rows_count": rows_written,
                "source_query_ms": source_ms,
                "index_build_ms": 0,
                "duration_ms": int((time.perf_counter() - started) * 1000),
            }
        except Exception as error:
            try:
                with reads.cursor() as cursor:
                    cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                    cursor.execute(
                        f"UPDATE `{self.config.db_reads_schema}`.`reads_meta` SET last_error=%s WHERE projection=%s",
                        (type(error).__name__, projection),
                    )
            except Exception:
                pass
            if isinstance(error, ReadModelError):
                raise
            raise ReadModelError("Read projection refresh failed") from error
        finally:
            if locked:
                try:
                    with reads.cursor() as cursor:
                        cursor.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
                except Exception:
                    pass
            master.close()
            reads.close()
