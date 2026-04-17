<?php
namespace App\API\Modules\library;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * LibraryAPI
 * Business logic for the Library Management module.
 *
 * Tables used:
 *   library_books, library_categories, library_issues, library_fines
 */
class LibraryAPI extends BaseAPI
{
    private const FINE_PER_DAY = 5.00; // KES 5 per overdue day

    public function __construct()
    {
        parent::__construct('library');
    }

    // ----------------------------------------------------------------
    // STATS / SUMMARY
    // ----------------------------------------------------------------

    public function getSummary(): array
    {
        try {
            $total     = (int) $this->db->query("SELECT COUNT(*) FROM library_books WHERE deleted_at IS NULL")->fetchColumn();
            $available = (int) $this->db->query("SELECT SUM(available_copies) FROM library_books WHERE deleted_at IS NULL AND status='active'")->fetchColumn();
            $issued    = (int) $this->db->query("SELECT COUNT(*) FROM library_issues WHERE status='issued'")->fetchColumn();
            $overdue   = (int) $this->db->query("SELECT COUNT(*) FROM library_issues WHERE status='overdue' OR (status='issued' AND due_date < CURDATE())")->fetchColumn();
            $categories = (int) $this->db->query("SELECT COUNT(*) FROM library_categories WHERE deleted_at IS NULL")->fetchColumn();
            $finesPending = (float) $this->db->query("SELECT COALESCE(SUM(fine_amount),0) FROM library_fines WHERE fine_status='pending'")->fetchColumn();

            return $this->response(['status' => 'success', 'data' => [
                'total_books'     => $total,
                'available_copies'=> $available,
                'currently_issued'=> $issued,
                'overdue_items'   => $overdue,
                'categories'      => $categories,
                'pending_fines_kes' => $finesPending,
            ]]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // CATEGORIES
    // ----------------------------------------------------------------

    public function getCategories(): array
    {
        try {
            $rows = $this->db->query(
                "SELECT c.*, COUNT(b.id) AS book_count
                 FROM library_categories c
                 LEFT JOIN library_books b ON b.category_id = c.id AND b.deleted_at IS NULL
                 WHERE c.deleted_at IS NULL
                 GROUP BY c.id
                 ORDER BY c.name"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $rows]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function createCategory(array $data): array
    {
        try {
            $name = trim($data['name'] ?? '');
            if (!$name) return $this->errorResponse('Category name is required', 400);

            $stmt = $this->db->prepare(
                "INSERT INTO library_categories (name, description) VALUES (:name, :description)"
            );
            $stmt->execute([':name' => $name, ':description' => $data['description'] ?? '']);
            $id = (int) $this->db->lastInsertId();

            return $this->response(['status' => 'success', 'data' => ['id' => $id], 'message' => 'Category created']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // BOOKS CRUD
    // ----------------------------------------------------------------

    public function listBooks(array $filters = []): array
    {
        try {
            $where = ['b.deleted_at IS NULL'];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = "(b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            if (!empty($filters['category_id'])) {
                $where[] = "b.category_id = :category_id";
                $params[':category_id'] = (int) $filters['category_id'];
            }
            if (!empty($filters['status'])) {
                $where[] = "b.status = :status";
                $params[':status'] = $filters['status'];
            }
            if (isset($filters['available_only']) && $filters['available_only']) {
                $where[] = "b.available_copies > 0";
            }

            $sql = "SELECT b.*, c.name AS category_name
                    FROM library_books b
                    LEFT JOIN library_categories c ON c.id = b.category_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY b.title
                    LIMIT 500";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $books]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getBook(int $id): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT b.*, c.name AS category_name
                 FROM library_books b
                 LEFT JOIN library_categories c ON c.id = b.category_id
                 WHERE b.id = :id AND b.deleted_at IS NULL LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$book) return $this->errorResponse('Book not found', 404);

            return $this->response(['status' => 'success', 'data' => $book]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function createBook(array $data): array
    {
        try {
            $title  = trim($data['title']  ?? '');
            $author = trim($data['author'] ?? '');
            if (!$title || !$author) return $this->errorResponse('Title and author are required', 400);

            $copies = max(1, (int) ($data['total_copies'] ?? 1));
            $stmt = $this->db->prepare(
                "INSERT INTO library_books
                    (isbn, title, author, publisher, edition, publication_year, category_id,
                     location_shelf, total_copies, available_copies, description, status)
                 VALUES
                    (:isbn, :title, :author, :publisher, :edition, :pub_year, :cat_id,
                     :shelf, :total, :available, :desc, 'active')"
            );
            $stmt->execute([
                ':isbn'      => $data['isbn']      ?? null,
                ':title'     => $title,
                ':author'    => $author,
                ':publisher' => $data['publisher'] ?? null,
                ':edition'   => $data['edition']   ?? null,
                ':pub_year'  => !empty($data['publication_year']) ? (int)$data['publication_year'] : null,
                ':cat_id'    => !empty($data['category_id'])      ? (int)$data['category_id']      : null,
                ':shelf'     => $data['location_shelf'] ?? null,
                ':total'     => $copies,
                ':available' => $copies,
                ':desc'      => $data['description'] ?? null,
            ]);
            $id = (int) $this->db->lastInsertId();

            return $this->response(['status' => 'success', 'data' => ['id' => $id], 'message' => 'Book added successfully']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function updateBook(int $id, array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE library_books SET
                    isbn = :isbn, title = :title, author = :author, publisher = :publisher,
                    edition = :edition, publication_year = :pub_year, category_id = :cat_id,
                    location_shelf = :shelf, total_copies = :total, description = :desc,
                    status = :status, updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':isbn'      => $data['isbn']      ?? null,
                ':title'     => trim($data['title']  ?? ''),
                ':author'    => trim($data['author'] ?? ''),
                ':publisher' => $data['publisher'] ?? null,
                ':edition'   => $data['edition']   ?? null,
                ':pub_year'  => !empty($data['publication_year']) ? (int)$data['publication_year'] : null,
                ':cat_id'    => !empty($data['category_id'])      ? (int)$data['category_id']      : null,
                ':shelf'     => $data['location_shelf'] ?? null,
                ':total'     => max(1, (int)($data['total_copies'] ?? 1)),
                ':desc'      => $data['description'] ?? null,
                ':status'    => $data['status'] ?? 'active',
                ':id'        => $id,
            ]);

            return $this->response(['status' => 'success', 'message' => 'Book updated']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function deleteBook(int $id): array
    {
        try {
            // Check for active issues
            $active = $this->db->prepare("SELECT COUNT(*) FROM library_issues WHERE book_id=:id AND status IN ('issued','overdue')");
            $active->execute([':id' => $id]);
            if ((int)$active->fetchColumn() > 0) {
                return $this->errorResponse('Cannot delete a book with active loans. Return all copies first.', 409);
            }
            $this->db->prepare("UPDATE library_books SET deleted_at=NOW() WHERE id=:id")->execute([':id' => $id]);
            return $this->response(['status' => 'success', 'message' => 'Book removed from library']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // ISSUES (borrow / return)
    // ----------------------------------------------------------------

    public function listIssues(array $filters = []): array
    {
        try {
            $where = ["1=1"];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = "li.status = :status";
                $params[':status'] = $filters['status'];
            }
            if (!empty($filters['borrower_type'])) {
                $where[] = "li.borrower_type = :btype";
                $params[':btype'] = $filters['borrower_type'];
            }
            if (!empty($filters['overdue_only'])) {
                $where[] = "(li.status='overdue' OR (li.status='issued' AND li.due_date < CURDATE()))";
            }

            $sql = "SELECT li.*,
                        b.title AS book_title, b.isbn,
                        CASE li.borrower_type
                            WHEN 'student' THEN CONCAT(s.first_name,' ',s.last_name)
                            WHEN 'staff'   THEN CONCAT(st.first_name,' ',st.last_name)
                        END AS borrower_name,
                        CASE li.borrower_type
                            WHEN 'student' THEN s.admission_no
                            WHEN 'staff'   THEN st.staff_no
                        END AS borrower_ref,
                        DATEDIFF(CURDATE(), li.due_date) AS days_overdue
                    FROM library_issues li
                    JOIN library_books b ON b.id = li.book_id
                    LEFT JOIN students s  ON li.borrower_type='student' AND s.id=li.borrower_id
                    LEFT JOIN staff st    ON li.borrower_type='staff'   AND st.id=li.borrower_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY li.issued_date DESC
                    LIMIT 500";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function issueBook(array $data): array
    {
        try {
            $bookId       = (int) ($data['book_id']      ?? 0);
            $borrowerType = $data['borrower_type'] ?? 'student';
            $borrowerId   = (int) ($data['borrower_id']  ?? 0);
            $issuedBy     = (int) ($data['issued_by']    ?? 0);
            $dueDate      = $data['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

            if (!$bookId || !$borrowerId || !$issuedBy) {
                return $this->errorResponse('book_id, borrower_id and issued_by are required', 400);
            }

            // Check availability
            $book = $this->db->prepare("SELECT available_copies FROM library_books WHERE id=:id AND status='active' AND deleted_at IS NULL FOR UPDATE");
            $book->execute([':id' => $bookId]);
            $row = $book->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['available_copies'] < 1) {
                return $this->errorResponse('No copies available for this book', 409);
            }

            // Check borrower does not already have this book
            $existing = $this->db->prepare(
                "SELECT id FROM library_issues WHERE book_id=:bid AND borrower_type=:btype AND borrower_id=:brid AND status IN ('issued','overdue')"
            );
            $existing->execute([':bid' => $bookId, ':btype' => $borrowerType, ':brid' => $borrowerId]);
            if ($existing->fetch()) {
                return $this->errorResponse('This borrower already has an active loan for this book', 409);
            }

            $this->db->beginTransaction();
            $ins = $this->db->prepare(
                "INSERT INTO library_issues (book_id, borrower_type, borrower_id, issued_by, issued_date, due_date, status)
                 VALUES (:bid, :btype, :brid, :iby, CURDATE(), :due, 'issued')"
            );
            $ins->execute([':bid'=>$bookId,':btype'=>$borrowerType,':brid'=>$borrowerId,':iby'=>$issuedBy,':due'=>$dueDate]);
            $issueId = (int) $this->db->lastInsertId();

            $this->db->prepare("UPDATE library_books SET available_copies = available_copies - 1 WHERE id=:id")->execute([':id'=>$bookId]);
            $this->db->commit();

            return $this->response(['status' => 'success', 'data' => ['issue_id' => $issueId], 'message' => 'Book issued successfully']);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    public function returnBook(int $issueId, array $data): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT li.*, b.id AS book_id FROM library_issues li
                 JOIN library_books b ON b.id = li.book_id
                 WHERE li.id=:id AND li.status IN ('issued','overdue')"
            );
            $stmt->execute([':id' => $issueId]);
            $issue = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$issue) return $this->errorResponse('Active loan not found', 404);

            $this->db->beginTransaction();

            // Mark returned
            $this->db->prepare(
                "UPDATE library_issues SET status='returned', returned_date=CURDATE(), returned_by=:rby, updated_at=NOW() WHERE id=:id"
            )->execute([':rby' => $data['returned_by'] ?? null, ':id' => $issueId]);

            // Restore available copy
            $this->db->prepare("UPDATE library_books SET available_copies = available_copies + 1 WHERE id=:id")->execute([':id' => $issue['book_id']]);

            // Auto-calculate fine if overdue
            $daysOverdue = max(0, (int)((time() - strtotime($issue['due_date'])) / 86400));
            if ($daysOverdue > 0) {
                $fineAmt = round($daysOverdue * self::FINE_PER_DAY, 2);
                $this->db->prepare(
                    "INSERT INTO library_fines (issue_id, fine_amount, days_overdue, fine_status) VALUES (:iid, :amt, :days, 'pending')
                     ON DUPLICATE KEY UPDATE fine_amount=:amt, days_overdue=:days"
                )->execute([':iid'=>$issueId, ':amt'=>$fineAmt, ':days'=>$daysOverdue]);
            }

            $this->db->commit();
            return $this->response(['status' => 'success', 'message' => 'Book returned' . ($daysOverdue > 0 ? " — fine of KES ".number_format($daysOverdue * self::FINE_PER_DAY, 2)." applied" : "")]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // FINES
    // ----------------------------------------------------------------

    public function listFines(array $filters = []): array
    {
        try {
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['status'])) {
                $where[] = "f.fine_status=:status";
                $params[':status'] = $filters['status'];
            }

            $sql = "SELECT f.*, li.issued_date, li.due_date, li.returned_date,
                        b.title AS book_title,
                        CASE li.borrower_type
                            WHEN 'student' THEN CONCAT(s.first_name,' ',s.last_name)
                            WHEN 'staff'   THEN CONCAT(st.first_name,' ',st.last_name)
                        END AS borrower_name
                    FROM library_fines f
                    JOIN library_issues li ON li.id = f.issue_id
                    JOIN library_books b   ON b.id  = li.book_id
                    LEFT JOIN students s   ON li.borrower_type='student' AND s.id=li.borrower_id
                    LEFT JOIN staff st     ON li.borrower_type='staff'   AND st.id=li.borrower_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY f.created_at DESC LIMIT 500";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function payFine(int $fineId, array $data): array
    {
        try {
            $this->db->prepare(
                "UPDATE library_fines SET fine_status='paid', paid_date=CURDATE(), updated_at=NOW() WHERE id=:id AND fine_status='pending'"
            )->execute([':id' => $fineId]);
            return $this->response(['status' => 'success', 'message' => 'Fine marked as paid']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function waiveFine(int $fineId, array $data): array
    {
        try {
            $this->db->prepare(
                "UPDATE library_fines SET fine_status='waived', waived_by=:by, waived_reason=:reason, updated_at=NOW() WHERE id=:id"
            )->execute([':by' => $data['waived_by'] ?? null, ':reason' => $data['reason'] ?? null, ':id' => $fineId]);
            return $this->response(['status' => 'success', 'message' => 'Fine waived']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    private function errorResponse(string $msg, int $code = 500): array
    {
        return $this->response(['status' => 'error', 'message' => $msg], $code);
    }
}
