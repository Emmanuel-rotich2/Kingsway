<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Library Management Workflow
 * 
 * Manages library resource acquisition, cataloging, and distribution.
 * Supports both physical and digital learning resources.
 * 
 * Resource Types:
 * - Books (textbooks, reference books, fiction, non-fiction)
 * - Digital resources (e-books, educational software)
 * - Periodicals (magazines, journals)
 * - Multimedia (DVDs, CDs, educational videos)
 * - Teaching aids and learning materials
 * 
 * Workflow Stages:
 * 1. Acquisition Request - Submit resource requests with justification
 * 2. Review & Approve - Librarian/administrator reviews and approves
 * 3. Catalog Resources - Add approved items to library catalog
 * 4. Distribute & Track - Issue resources and track circulation
 */
class LibraryManagementWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('library_management');
    }
    
    protected function getWorkflowDefinitionCode(): string {
        return 'library_management';
    }

    /**
     * Stage 1: Acquisition request
     * 
     * @param array $request {
     *   @type string $resource_title Title/name of resource
     *   @type string $resource_type Type: book, digital, periodical, multimedia, teaching_aid
     *   @type string $author Author/creator
     *   @type string $publisher Publisher name
     *   @type string $isbn ISBN or unique identifier
     *   @type int $quantity Number of copies requested
     *   @type float $estimated_cost Estimated cost per unit
     *   @type int $subject_id Related subject/learning area
     *   @type array $grade_levels Target grade levels (e.g., [1, 2, 3])
     *   @type string $justification Reason for acquisition
     *   @type string $urgency Level: high, medium, low
     *   @type int $requested_by User ID of requester
     * }
     * @return array Response with workflow instance
     */
    public function acquisitionRequest(array $request): array {
        try {
            // Validation
            $required = ['resource_title', 'resource_type', 'quantity', 'justification'];
            foreach ($required as $field) {
                if (!isset($request[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Validate resource type
            $validTypes = ['book', 'digital', 'periodical', 'multimedia', 'teaching_aid'];
            if (!in_array($request['resource_type'], $validTypes)) {
                return formatResponse(false, null, 'Invalid resource type');
            }

            $this->db->beginTransaction();

            // Calculate total estimated cost
            $quantity = (int)$request['quantity'];
            $estimatedCost = isset($request['estimated_cost']) ? (float)$request['estimated_cost'] : 0;
            $totalCost = $quantity * $estimatedCost;

            // Prepare workflow data
            $workflowData = [
                'resource_title' => $request['resource_title'],
                'resource_type' => $request['resource_type'],
                'author' => $request['author'] ?? '',
                'publisher' => $request['publisher'] ?? '',
                'isbn' => $request['isbn'] ?? '',
                'quantity' => $quantity,
                'estimated_cost' => $estimatedCost,
                'total_cost' => $totalCost,
                'subject_id' => isset($request['subject_id']) ? (int)$request['subject_id'] : null,
                'grade_levels' => $request['grade_levels'] ?? [],
                'justification' => $request['justification'],
                'urgency' => $request['urgency'] ?? 'medium',
                'requested_by' => isset($request['requested_by']) ? (int)$request['requested_by'] : $this->user_id,
                'requested_at' => date('Y-m-d H:i:s'),
                'approval_status' => 'pending',
                'catalog_entries' => [],
                'distribution_records' => [],
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                'library_resource',
                null, // No specific reference ID yet
                $workflowData,
                "Acquisition request: {$request['resource_title']}"
            );

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance['id'],
                'workflow_data' => $workflowData,
            ], 'Acquisition request submitted successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Review and approve
     * 
     * Librarian or administrator reviews the acquisition request.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $review {
     *   @type bool $approved Approve or reject
     *   @type float $approved_cost Final approved cost (may differ from estimate)
     *   @type int $approved_quantity Final approved quantity
     *   @type string $vendor Selected vendor/supplier
     *   @type string $procurement_method Method: purchase, donation, exchange
     *   @type string $reviewer_notes Notes from reviewer
     *   @type string $rejection_reason Reason if rejected
     * }
     * @return array Response with approval status
     */
    public function reviewAndApprove(int $instance_id, array $review): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $approved = $review['approved'] ?? false;

            if (!$approved) {
                // Rejection path
                $data['approval_status'] = 'rejected';
                $data['rejection_reason'] = $review['rejection_reason'] ?? 'Not specified';
                $data['reviewed_by'] = $this->user_id;
                $data['reviewed_at'] = date('Y-m-d H:i:s');

                $this->advanceStage(
                    $instance_id,
                    json_encode($data),
                    "Acquisition request rejected"
                );

                return formatResponse(true, [
                    'approval_status' => 'rejected',
                    'rejection_reason' => $data['rejection_reason'],
                ], 'Acquisition request rejected');
            }

            // Approval path
            $approvedQuantity = isset($review['approved_quantity']) 
                ? (int)$review['approved_quantity'] 
                : (int)$data['quantity'];
            
            $approvedCost = isset($review['approved_cost']) 
                ? (float)$review['approved_cost'] 
                : (float)$data['estimated_cost'];

            $data['approval_status'] = 'approved';
            $data['approved_quantity'] = $approvedQuantity;
            $data['approved_cost'] = $approvedCost;
            $data['total_approved_cost'] = $approvedQuantity * $approvedCost;
            $data['vendor'] = $review['vendor'] ?? '';
            $data['procurement_method'] = $review['procurement_method'] ?? 'purchase';
            $data['reviewer_notes'] = $review['reviewer_notes'] ?? '';
            $data['reviewed_by'] = $this->user_id;
            $data['reviewed_at'] = date('Y-m-d H:i:s');

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Acquisition request approved: {$approvedQuantity} copies at total cost {$data['total_approved_cost']}"
            );

            return formatResponse(true, [
                'approval_status' => 'approved',
                'approved_quantity' => $approvedQuantity,
                'total_approved_cost' => $data['total_approved_cost'],
            ], 'Acquisition request approved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Catalog resources
     * 
     * Add approved resources to the library catalog with proper classification.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $catalog_info {
     *   @type array $items Array of catalog entries (for multiple copies) {
     *     @type string $accession_number Unique library ID
     *     @type string $call_number Classification number (Dewey/other)
     *     @type string $location Physical location/shelf
     *     @type string $condition Condition: new, good, fair, poor
     *     @type string $acquisition_date Date acquired
     *     @type float $actual_cost Actual cost paid
     *   }
     *   @type string $category Category/genre
     *   @type string $description Full description
     *   @type array $keywords Search keywords/tags
     * }
     * @return array Response with catalog summary
     */
    public function catalogResources(int $instance_id, array $catalog_info): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            if ($data['approval_status'] !== 'approved') {
                return formatResponse(false, null, 'Only approved requests can be cataloged');
            }

            $items = $catalog_info['items'] ?? [];
            $approvedQuantity = (int)($data['approved_quantity'] ?? 0);

            if (empty($items)) {
                return formatResponse(false, null, 'No catalog items provided');
            }

            if (count($items) > $approvedQuantity) {
                return formatResponse(false, null, "Cannot catalog more items than approved quantity ({$approvedQuantity})");
            }

            // Add cataloging metadata
            $catalogEntries = [];
            foreach ($items as $item) {
                $catalogEntries[] = [
                    'accession_number' => $item['accession_number'] ?? 'AUTO-' . uniqid(),
                    'call_number' => $item['call_number'] ?? '',
                    'location' => $item['location'] ?? 'Main Library',
                    'condition' => $item['condition'] ?? 'new',
                    'acquisition_date' => $item['acquisition_date'] ?? date('Y-m-d'),
                    'actual_cost' => isset($item['actual_cost']) ? (float)$item['actual_cost'] : $data['approved_cost'],
                    'status' => 'available',
                    'cataloged_at' => date('Y-m-d H:i:s'),
                    'cataloged_by' => $this->user_id,
                ];
            }

            $data['catalog_entries'] = $catalogEntries;
            $data['category'] = $catalog_info['category'] ?? '';
            $data['description'] = $catalog_info['description'] ?? '';
            $data['keywords'] = $catalog_info['keywords'] ?? [];
            $data['cataloged_count'] = count($catalogEntries);

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Cataloged {count($catalogEntries)} resource items"
            );

            return formatResponse(true, [
                'cataloged_count' => count($catalogEntries),
                'catalog_entries' => $catalogEntries,
            ], 'Resources cataloged successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Distribute and track
     * 
     * Tracks resource distribution to users (students, teachers).
     * Records borrowing/return transactions.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $distribution {
     *   @type string $distribution_type Type: circulation, reference_only, class_set
     *   @type array $initial_assignments Initial assignments (optional) {
     *     @type string $accession_number Item ID
     *     @type int $borrower_id User ID (student/teacher)
     *     @type string $borrowed_date Date borrowed
     *     @type string $due_date Return due date
     *   }
     *   @type bool $enable_circulation Enable borrowing (default: true)
     *   @type int $loan_period_days Default loan period
     *   @type int $max_renewals Maximum renewals allowed
     * }
     * @return array Response with distribution summary
     */
    public function distributeAndTrack(int $instance_id, array $distribution): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            if (empty($data['catalog_entries'])) {
                return formatResponse(false, null, 'No cataloged items available for distribution');
            }

            $distributionType = $distribution['distribution_type'] ?? 'circulation';
            $enableCirculation = $distribution['enable_circulation'] ?? true;
            $loanPeriod = isset($distribution['loan_period_days']) ? (int)$distribution['loan_period_days'] : 14;
            $maxRenewals = isset($distribution['max_renewals']) ? (int)$distribution['max_renewals'] : 2;

            $distributionRecords = [];

            // Process initial assignments if provided
            $initialAssignments = $distribution['initial_assignments'] ?? [];
            foreach ($initialAssignments as $assignment) {
                $distributionRecords[] = [
                    'accession_number' => $assignment['accession_number'],
                    'borrower_id' => (int)$assignment['borrower_id'],
                    'borrowed_date' => $assignment['borrowed_date'] ?? date('Y-m-d'),
                    'due_date' => $assignment['due_date'] ?? date('Y-m-d', strtotime("+{$loanPeriod} days")),
                    'status' => 'borrowed',
                    'renewal_count' => 0,
                    'issued_by' => $this->user_id,
                ];
            }

            $data['distribution_type'] = $distributionType;
            $data['enable_circulation'] = $enableCirculation;
            $data['loan_period_days'] = $loanPeriod;
            $data['max_renewals'] = $maxRenewals;
            $data['distribution_records'] = $distributionRecords;
            $data['items_in_circulation'] = count($distributionRecords);
            $data['items_available'] = count($data['catalog_entries']) - count($distributionRecords);

            // Log successful acquisition and distribution
            $this->logAction(
                'library_resource_added',
                "Library resource acquired and cataloged: {$data['resource_title']}",
                [
                    'resource_title' => $data['resource_title'],
                    'cataloged_count' => $data['cataloged_count'],
                    'total_cost' => $data['total_approved_cost'] ?? 0,
                ]
            );

            // Complete workflow
            $this->completeWorkflow(
                $instance_id,
                json_encode($data),
                "Library workflow completed: {$data['cataloged_count']} items ready for distribution"
            );

            return formatResponse(true, [
                'cataloged_count' => $data['cataloged_count'],
                'items_in_circulation' => $data['items_in_circulation'],
                'items_available' => $data['items_available'],
                'distribution_summary' => [
                    'type' => $distributionType,
                    'circulation_enabled' => $enableCirculation,
                    'loan_period' => $loanPeriod,
                    'max_renewals' => $maxRenewals,
                ],
            ], 'Library resources ready for distribution');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Record a borrowing transaction
     * 
     * @param int $instance_id Workflow instance ID
     * @param string $accession_number Item identifier
     * @param int $borrower_id User ID
     * @return array Response with transaction details
     */
    public function recordBorrowing(int $instance_id, string $accession_number, int $borrower_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $loanPeriod = (int)($data['loan_period_days'] ?? 14);

            $transaction = [
                'accession_number' => $accession_number,
                'borrower_id' => $borrower_id,
                'borrowed_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime("+{$loanPeriod} days")),
                'status' => 'borrowed',
                'renewal_count' => 0,
                'issued_by' => $this->user_id,
                'issued_at' => date('Y-m-d H:i:s'),
            ];

            $data['distribution_records'][] = $transaction;

            // Update workflow data
            $updateStmt = $this->db->prepare(
                "UPDATE workflow_instances 
                SET data_json = :data 
                WHERE id = :id"
            );
            $updateStmt->execute([
                'data' => json_encode($data),
                'id' => $instance_id,
            ]);

            return formatResponse(true, $transaction, 'Borrowing transaction recorded');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get catalog summary for the workflow instance
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with catalog details
     */
    public function getCatalogSummary(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            return formatResponse(true, [
                'resource_title' => $data['resource_title'] ?? '',
                'resource_type' => $data['resource_type'] ?? '',
                'approval_status' => $data['approval_status'] ?? 'pending',
                'cataloged_count' => $data['cataloged_count'] ?? 0,
                'items_available' => $data['items_available'] ?? 0,
                'items_in_circulation' => $data['items_in_circulation'] ?? 0,
                'catalog_entries' => $data['catalog_entries'] ?? [],
            ], 'Catalog summary retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
