"""
Data Contracts - Pydantic schemas for all inter-service communication.
Versioned, validated, with Great Expectations integration.
"""

from datetime import date, datetime
from decimal import Decimal
from enum import Enum
from typing import Any, Dict, List, Optional, Union
from pydantic import BaseModel, Field, field_validator, ConfigDict
from uuid import UUID, uuid4


class JobStatus(str, Enum):
    PENDING = "pending"
    PROCESSING = "processing"
    DONE = "done"
    FAILED = "failed"
    CANCELLED = "cancelled"


class JobPriority(int, Enum):
    CRITICAL = 1
    NORMAL = 2
    BATCH = 3


class BaseJobPayload(BaseModel):
    model_config = ConfigDict(extra="forbid", use_enum_values=True)

    job_id: str = Field(default_factory=lambda: str(uuid4()))
    requested_by: str
    idempotency_key: str
    priority: JobPriority = JobPriority.NORMAL
    created_at: datetime = Field(default_factory=datetime.utcnow)


# ─── Import Contracts ───

class ImportParsePayload(BaseJobPayload):
    file_id: int
    file_type: str  # csv, excel, ods, pdf
    options: Dict[str, Any] = {}

class ImportValidatePayload(BaseJobPayload):
    file_id: int
    schema: List[Dict[str, Any]]

class ImportPreviewPayload(BaseJobPayload):
    file_id: int
    max_rows: int = 10


# ─── Admission Contracts ───

class AdmissionDocumentExtractPayload(BaseJobPayload):
    file_id: int
    doc_type: str  # birth_cert, transfer_letter, medical_form, id_doc

class AdmissionImportPreviewPayload(BaseJobPayload):
    file_id: int
    import_type: str  # applications_csv, interview_scores, documents_zip


# ─── Activities Contracts ───

class ActivitiesParticipantImportPayload(BaseJobPayload):
    file_id: int
    activity_id: int
    import_type: str  # participants_csv, registrations_excel

class ActivitiesExportPayload(BaseJobPayload):
    export_type: str  # participants, results, attendance, certificates
    activity_id: int
    output_format: str = "xlsx"

class ActivitiesAggregatePayload(BaseJobPayload):
    date_from: date
    date_to: date
    aggregates: List[str] = ["participation", "results", "attendance"]


# ─── Boarding Contracts ───

class BoardingOccupancySummaryPayload(BaseJobPayload):
    date_from: date
    date_to: date
    output_format: str = "xlsx"

class BoardingRollcallExportPayload(BaseJobPayload):
    date_from: date
    date_to: date
    dormitory_id: int = 0
    output_format: str = "xlsx"


# ─── Health Contracts ───

class HealthDocumentExtractPayload(BaseJobPayload):
    file_id: int
    doc_type: str  # medical_form, vaccination_record, incident_report, consent_form

class HealthSummarizePayload(BaseJobPayload):
    date_from: date
    date_to: date
    scope: str = "school"  # school, class, student
    output_format: str = "xlsx"


# ─── Library Contracts ───

class LibraryCatalogueImportPayload(BaseJobPayload):
    file_id: int
    import_type: str  # marc, csv, excel, isbn_batch

class LibraryExportPayload(BaseJobPayload):
    export_type: str  # catalogue, circulation, overdue, inventory
    output_format: str = "xlsx"


# ─── Maintenance Contracts ───

class MaintenanceAttachmentExtractPayload(BaseJobPayload):
    file_id: int
    work_order_id: int
    extract_type: str  # photos, documents, signatures

class MaintenanceSummaryExportPayload(BaseJobPayload):
    date_from: date
    date_to: date
    export_type: str  # work_orders, costs, preventive, equipment_history
    output_format: str = "xlsx"


# ─── Schedules Contracts ───

class SchedulesExtractPayload(BaseJobPayload):
    file_id: int
    source: str  # ministry_csv, timetable_excel, calendar_ics

class SchedulesProposeRowsPayload(BaseJobPayload):
    academic_year_id: int
    term_id: int
    constraints: List[Dict[str, Any]] = []


# ─── Staff Contracts ───

class StaffImportPayload(BaseJobPayload):
    file_id: int
    import_type: str  # staff_csv, contracts_excel, payroll_excel, qualifications_csv

class StaffExportPayload(BaseJobPayload):
    export_type: str  # staff_list, contracts, payroll, leave_balances, qualifications
    output_format: str = "xlsx"

class StaffAnalyticsPayload(BaseJobPayload):
    date_from: date
    date_to: date
    metrics: List[str] = ["workload", "leave", "performance", "contracts"]


# ─── Finance Contracts ───

class FinanceParseBankFilePayload(BaseJobPayload):
    provider: str  # kcb, mpesa, coop, equity, family, abs, standard_chartered, ncba
    file_id: int
    account_id: int
    payment_type: str = "fees"  # fees, transport, uniforms, admission, catering, inventory

class FinanceReconciliationPrepPayload(BaseJobPayload):
    provider: str
    date_from: date
    date_to: date
    account_id: int

class FinanceLargeReportPayload(BaseJobPayload):
    report_type: str  # trial_balance, pnl, fee_ledger, budget_util, cash_flow, ar_aging, ap_aging
    date_from: date
    date_to: date
    filters: Dict[str, Any] = {}
    output_format: str = "xlsx"


# ─── Payments Contracts ───

class PaymentsParseProviderFilePayload(BaseJobPayload):
    provider: str
    file_id: int
    payment_type: str  # fees, transport, uniforms, admission, catering, inventory

class PaymentsExceptionPrepPayload(BaseJobPayload):
    date_from: date
    date_to: date
    payment_type: str  # fees, transport, uniforms, admission, catering, inventory


# ─── Attendance Contracts ───

class AttendanceParseRegisterFilePayload(BaseJobPayload):
    file_id: int
    session_id: int
    source: str  # biometric, manual_csv, mobile_app, rfid

class AttendanceTrendRefreshPayload(BaseJobPayload):
    date_from: date
    date_to: date
    scope: str = "school"  # school, class, stream, student


# ─── Transport Contracts ───

class TransportRouteImportPayload(BaseJobPayload):
    file_id: int
    import_type: str  # routes, vehicles, stops, assignments

class TransportAggregateReportPayload(BaseJobPayload):
    report_type: str  # occupancy, punctuality, fuel_economy, incidents, route_economics
    date_from: date
    date_to: date
    output_format: str = "xlsx"


# ─── Academic Contracts ───

class AcademicReportCardBatchPayload(BaseJobPayload):
    student_ids: List[int]
    term_id: int
    result_mode: str = "both"  # summative, formative, both
    output_format: str = "pdf"


# ─── Communications Contracts ───

class CommunicationsBatchRenderPayload(BaseJobPayload):
    template_id: int
    recipient_ids: List[int]
    channel: str  # sms, email, whatsapp
    context: Dict[str, Any] = {}

class CommunicationsReconcileDeliveryPayload(BaseJobPayload):
    provider: str
    date_from: date
    date_to: date
    channel: str  # sms, email, whatsapp


# ─── Inventory Contracts ───

class InventorySupplierImportPayload(BaseJobPayload):
    file_id: int
    supplier_id: int
    import_type: str  # catalog, pricelist, invoice

class InventoryBulkExportPayload(BaseJobPayload):
    export_type: str  # stock_valuation, reorder_list, movement_log, full_catalog
    filters: Dict[str, Any] = {}
    output_format: str = "xlsx"

class InventoryAggregateRefreshPayload(BaseJobPayload):
    aggregates: List[str] = ["stock_valuation", "reorder_alerts", "category_summary"]


# ─── Students Contracts ───

class StudentsImportPreviewPayload(BaseJobPayload):
    file_id: int
    import_type: str  # admission_csv, student_csv, document_pdf, excel
    options: Dict[str, Any] = {}

class StudentsBulkArtifactPayload(BaseJobPayload):
    artifact_type: str  # id_cards, report_cards, certificates, transcripts
    student_ids: List[int]
    options: Dict[str, Any] = {}


# ─── Forecasting Contracts ───

class ForecastFeeCollectionPayload(BaseJobPayload):
    months: int = 12

class ForecastEnrollmentPayload(BaseJobPayload):
    years: int = 3

class ForecastCapacityPayload(BaseJobPayload):
    months: int = 12

class ForecastCashFlowPayload(BaseJobPayload):
    months: int = 12


# ─── Anomaly Detection Contracts ───

class AnomalyDetectPayload(BaseJobPayload):
    domain: str  # attendance, payments, grades, inventory, transport
    date_from: date
    date_to: date


# ─── Feature Store Contracts ───

class FeaturesMaterializePayload(BaseJobPayload):
    entities: List[str] = ["student", "staff", "vehicle", "item", "route"]

class FeaturesGetPayload(BaseJobPayload):
    entity: str
    entity_id: int
    features: List[str] = []


# ─── Model Registry Contracts ───

class ModelRegisterPayload(BaseJobPayload):
    model_name: str
    version: str = "1.0.0"
    framework: str = "onnx"  # onnx, sklearn, pytorch, tensorflow
    artifact_path: str
    metadata: Dict[str, Any] = {}

class ModelPredictPayload(BaseJobPayload):
    model_id: str
    inputs: Dict[str, Any]


# ─── A/B Testing Contracts ───

class ABTestCreatePayload(BaseJobPayload):
    name: str
    model_a: str
    model_b: str
    traffic_split: float = 0.5

class ABTestRecordPayload(BaseJobPayload):
    variant: str  # "A" or "B"
    outcome: float


# ─── Drift Detection Contracts ───

class DriftDetectPayload(BaseJobPayload):
    model_id: str
    reference_data: Dict[str, Any] = {}
    current_data: Dict[str, Any] = {}


# ─── Feature Store Contracts ───

class FeaturesMaterializePayload(BaseJobPayload):
    entities: List[str] = ["student", "staff", "vehicle", "item", "route"]

class FeaturesGetPayload(BaseJobPayload):
    entity: str
    entity_id: int
    features: List[str] = []


# ─── Model Registry Contracts ───

class ModelRegisterPayload(BaseJobPayload):
    model_name: str
    version: str = "1.0.0"
    framework: str = "onnx"  # onnx, sklearn, pytorch, tensorflow
    artifact_path: str
    metadata: Dict[str, Any] = {}

class ModelPredictPayload(BaseJobPayload):
    model_id: str
    inputs: Dict[str, Any]


# ─── A/B Testing Contracts ───

class ABTestCreatePayload(BaseJobPayload):
    name: str
    model_a: str
    model_b: str
    traffic_split: float = 0.5

class ABTestRecordPayload(BaseJobPayload):
    variant: str  # "A" or "B"
    outcome: float


# ─── Drift Detection Contracts ───

class DriftDetectPayload(BaseJobPayload):
    model_id: str
    reference_data: Dict[str, Any] = {}
    current_data: Dict[str, Any] = {}


# ─── Feature Store Contracts ───

class FeaturesMaterializePayload(BaseJobPayload):
    entities: List[str] = ["student", "staff", "vehicle", "item", "route"]

class FeaturesGetPayload(BaseJobPayload):
    entity: str
    entity_id: int
    features: List[str] = []


# ─── Observability Contracts ───

class ObservabilityMetricsPayload(BaseJobPayload):
    pass  # No input needed

class ObservabilityHealthPayload(BaseJobPayload):
    pass


# ─── Data Contracts ───

class ContractValidatePayload(BaseJobPayload):
    contract_name: str
    data: Dict[str, Any]


# ─── PII Scanner Contracts ───

class PIIScanPayload(BaseJobPayload):
    text: str


# ─── Audit Contracts ───

class AuditAnalyzePayload(BaseJobPayload):
    date_from: date
    date_to: date
    categories: List[str] = ["auth", "permission", "data_access", "export"]


# ─── Backup/Restore Contracts ───

class BackupSnapshotPayload(BaseJobPayload):
    include: List[str] = ["parquet", "models", "configs"]

class RestoreSnapshotPayload(BaseJobPayload):
    snapshot_id: str


# ─── Result Models ───

class JobResult(BaseModel):
    success: bool
    data: Optional[Dict[str, Any]] = None
    message: Optional[str] = None
    job_id: Optional[str] = None


class JobMeta(BaseModel):
    job_id: str
    job_type: str
    status: JobStatus
    created_at: datetime
    updated_at: datetime
    attempts: int
    max_attempts: int
    payload: Dict[str, Any]
    meta: Dict[str, Any] = {}


# ─── Contract Registry ───

CONTRACT_REGISTRY: Dict[str, type[BaseJobPayload]] = {
    # Import
    "import.parse": ImportParsePayload,
    "import.validate": ImportValidatePayload,
    "import.preview": ImportPreviewPayload,
    # Admission
    "admission.document_extract": AdmissionDocumentExtractPayload,
    "admission.import_preview": AdmissionImportPreviewPayload,
    # Activities
    "activities.participant_import": ActivitiesParticipantImportPayload,
    "activities.export": ActivitiesExportPayload,
    "activities.aggregate": ActivitiesAggregatePayload,
    # Boarding
    "boarding.occupancy_summary": BoardingOccupancySummaryPayload,
    "boarding.rollcall_export": BoardingRollcallExportPayload,
    # Health
    "health.document_extract": HealthDocumentExtractPayload,
    "health.summarize": HealthSummarizePayload,
    # Library
    "library.catalogue_import": LibraryCatalogueImportPayload,
    "library.bulk_export": LibraryExportPayload,
    # Maintenance
    "maintenance.attachment_extract": MaintenanceAttachmentExtractPayload,
    "maintenance.summary_export": MaintenanceSummaryExportPayload,
    # Schedules
    "schedules.extract": SchedulesExtractPayload,
    "schedules.propose_rows": SchedulesProposeRowsPayload,
    # Staff
    "staff.bulk_import": StaffImportPayload,
    "staff.export": StaffExportPayload,
    "staff.analytics": StaffAnalyticsPayload,
    # Finance
    "finance.parse_bank_file": FinanceParseBankFilePayload,
    "finance.reconciliation_prep": FinanceReconciliationPrepPayload,
    "finance.large_report": FinanceLargeReportPayload,
    # Payments
    "payments.parse_provider_file": PaymentsParseProviderFilePayload,
    "payments.exception_prep": PaymentsExceptionPrepPayload,
    # Attendance
    "attendance.parse_register_file": AttendanceParseRegisterFilePayload,
    "attendance.trend_refresh": AttendanceTrendRefreshPayload,
    # Transport
    "transport.route_import": TransportRouteImportPayload,
    "transport.aggregate_report": TransportAggregateReportPayload,
    # Academic
    "academic.report_card_batch": AcademicReportCardBatchPayload,
    # Communications
    "communications.batch_render": CommunicationsBatchRenderPayload,
    "communications.reconcile_delivery": CommunicationsReconcileDeliveryPayload,
    # Inventory
    "inventory.supplier_import": InventorySupplierImportPayload,
    "inventory.bulk_export": InventoryBulkExportPayload,
    "inventory.aggregate_refresh": InventoryAggregateRefreshPayload,
    # Attendance
    "attendance.parse_register_file": AttendanceParseRegisterFilePayload,
    "attendance.trend_refresh": AttendanceTrendRefreshPayload,
    # Transport
    "transport.route_import": TransportRouteImportPayload,
    "transport.aggregate_report": TransportAggregateReportPayload,
    # Academic
    "academic.report_card_batch": AcademicReportCardBatchPayload,
    # Communications
    "communications.batch_render": CommunicationsBatchRenderPayload,
    "communications.reconcile_delivery": CommunicationsReconcileDeliveryPayload,
    # Inventory
    "inventory.supplier_import": InventorySupplierImportPayload,
    "inventory.bulk_export": InventoryBulkExportPayload,
    "inventory.aggregate_refresh": InventoryAggregateRefreshPayload,
    # Students
    "students.import_preview": StudentsImportPreviewPayload,
    "students.bulk_artifact": StudentsBulkArtifactPayload,
    # Admission
    "admission.document_extract": AdmissionDocumentExtractPayload,
    "admission.import_preview": AdmissionImportPreviewPayload,
    # Activities
    "activities.participant_import": ActivitiesParticipantImportPayload,
    "activities.export": ActivitiesExportPayload,
    "activities.aggregate": ActivitiesAggregatePayload,
    # Boarding
    "boarding.occupancy_summary": BoardingOccupancySummaryPayload,
    "boarding.rollcall_export": BoardingRollcallExportPayload,
    # Health
    "health.document_extract": HealthDocumentExtractPayload,
    "health.summarize": HealthSummarizePayload,
    # Library
    "library.catalogue_import": LibraryCatalogueImportPayload,
    "library.bulk_export": LibraryExportPayload,
    # Maintenance
    "maintenance.attachment_extract": MaintenanceAttachmentExtractPayload,
    "maintenance.summary_export": MaintenanceSummaryExportPayload,
    # Schedules
    "schedules.extract": SchedulesExtractPayload,
    "schedules.propose_rows": SchedulesProposeRowsPayload,
    # Staff
    "staff.bulk_import": StaffImportPayload,
    "staff.export": StaffExportPayload,
    "staff.analytics": StaffAnalyticsPayload,
    # Finance
    "finance.parse_bank_file": FinanceParseBankFilePayload,
    "finance.reconciliation_prep": FinanceReconciliationPrepPayload,
    "finance.large_report": FinanceLargeReportPayload,
    # Payments
    "payments.parse_provider_file": PaymentsParseProviderFilePayload,
    "payments.exception_prep": PaymentsExceptionPrepPayload,
    # Attendance
    "attendance.parse_register_file": AttendanceParseRegisterFilePayload,
    "attendance.trend_refresh": AttendanceTrendRefreshPayload,
    # Transport
    "transport.route_import": TransportRouteImportPayload,
    "transport.aggregate_report": TransportAggregateReportPayload,
    # Academic
    "academic.report_card_batch": AcademicReportCardBatchPayload,
    # Communications
    "communications.batch_render": CommunicationsBatchRenderPayload,
    "communications.reconcile_delivery": CommunicationsReconcileDeliveryPayload,
    # Inventory
    "inventory.supplier_import": InventorySupplierImportPayload,
    "inventory.bulk_export": InventoryBulkExportPayload,
    "inventory.aggregate_refresh": InventoryAggregateRefreshPayload,
    # Students
    "students.import_preview": StudentsImportPreviewPayload,
    "students.bulk_artifact": StudentsBulkArtifactPayload,
    # Admission
    "admission.document_extract": AdmissionDocumentExtractPayload,
    "admission.import_preview": AdmissionImportPreviewPayload,
    # Activities
    "activities.participant_import": ActivitiesParticipantImportPayload,
    "activities.export": ActivitiesExportPayload,
    "activities.aggregate": ActivitiesAggregatePayload,
    # Boarding
    "boarding.occupancy_summary": BoardingOccupancySummaryPayload,
    "boarding.rollcall_export": BoardingRollcallExportPayload,
    # Health
    "health.document_extract": HealthDocumentExtractPayload,
    "health.summarize": HealthSummarizePayload,
    # Library
    "library.catalogue_import": LibraryCatalogueImportPayload,
    "library.bulk_export": LibraryExportPayload,
    # Maintenance
    "maintenance.attachment_extract": MaintenanceAttachmentExtractPayload,
    "maintenance.summary_export": MaintenanceSummaryExportPayload,
    # Schedules
    "schedules.extract": SchedulesExtractPayload,
    "schedules.propose_rows": SchedulesProposeRowsPayload,
    # Staff
    "staff.bulk_import": StaffImportPayload,
    "staff.export": StaffExportPayload,
    "staff.analytics": StaffAnalyticsPayload,
    # Import
    "import.parse": ImportParsePayload,
    "import.validate": ImportValidatePayload,
    "import.preview": ImportPreviewPayload,
    # Admission
    "admission.document_extract": AdmissionDocumentExtractPayload,
    "admission.import_preview": AdmissionImportPreviewPayload,
    # Activities
    "activities.participant_import": ActivitiesParticipantImportPayload,
    "activities.export": ActivitiesExportPayload,
    "activities.aggregate": ActivitiesAggregatePayload,
    # Boarding
    "boarding.occupancy_summary": BoardingOccupancySummaryPayload,
    "boarding.rollcall_export": BoardingRollcallExportPayload,
    # Health
    "health.document_extract": HealthDocumentExtractPayload,
    "health.summarize": HealthSummarizePayload,
    # Library
    "library.catalogue_import": LibraryCatalogueImportPayload,
    "library.bulk_export": LibraryExportPayload,
    # Maintenance
    "maintenance.attachment_extract": MaintenanceAttachmentExtractPayload,
    "maintenance.summary_export": MaintenanceSummaryExportPayload,
    # Schedules
    "schedules.extract": SchedulesExtractPayload,
    "schedules.propose_rows": SchedulesProposeRowsPayload,
    # Staff
    "staff.bulk_import": StaffImportPayload,
    "staff.export": StaffExportPayload,
    "staff.analytics": StaffAnalyticsPayload,
    # Forecasting
    "forecast.fee_collection": ForecastFeeCollectionPayload,
    "forecast.enrollment": ForecastEnrollmentPayload,
    "forecast.capacity": ForecastCapacityPayload,
    "forecast.cash_flow": ForecastCashFlowPayload,
    # Anomaly
    "anomaly.detect": AnomalyDetectPayload,
    # Feature Store
    "features.materialize": FeaturesMaterializePayload,
    "features.get": FeaturesGetPayload,
    # Model Registry
    "model.register": ModelRegisterPayload,
    "model.predict": ModelPredictPayload,
    # A/B Testing
    "ab_test.create": ABTestCreatePayload,
    "ab_test.record": ABTestRecordPayload,
    # Drift
    "drift.detect": DriftDetectPayload,
    # Feature Store
    "features.materialize": FeaturesMaterializePayload,
    "features.get": FeaturesGetPayload,
    # Observability
    "observability.metrics": ObservabilityMetricsPayload,
    "observability.health": ObservabilityHealthPayload,
    # Contracts
    "contracts.validate": ContractValidatePayload,
    # PII
    "pii.scan": PIIScanPayload,
    # Audit
    "audit.analyze": AuditAnalyzePayload,
    # Backup/Restore
    "backup.snapshot": BackupSnapshotPayload,
    "restore.snapshot": RestoreSnapshotPayload,
}
