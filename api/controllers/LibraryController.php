<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\library\LibraryAPI;
use Exception;

/**
 * LibraryController
 *
 * ROUTES:
 * GET  /api/library/summary                → getSummary()
 * GET  /api/library/categories             → getCategories()
 * POST /api/library/categories             → postCategories()
 * GET  /api/library/books                  → getBooks()
 * GET  /api/library/books/{id}             → getBooks($id)
 * POST /api/library/books                  → postBooks()
 * PUT  /api/library/books/{id}             → putBooks($id)
 * DELETE /api/library/books/{id}           → deleteBooks($id)
 * GET  /api/library/issues                 → getIssues()
 * POST /api/library/issues                 → postIssues()          — issue a book
 * PUT  /api/library/issues/{id}/return     → putIssuesReturn($id)  — return
 * GET  /api/library/overdue               → getOverdue()
 * GET  /api/library/fines                 → getFines()
 * PUT  /api/library/fines/{id}/pay        → putFinesPay($id)
 * PUT  /api/library/fines/{id}/waive      → putFinesWaive($id)
 */
class LibraryController extends BaseController
{
    private LibraryAPI $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new LibraryAPI();
    }

    // ----------------------------------------------------------------
    // SUMMARY
    // ----------------------------------------------------------------

    public function getSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getSummary());
    }

    // ----------------------------------------------------------------
    // CATEGORIES
    // ----------------------------------------------------------------

    public function getCategories($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getCategories());
    }

    public function postCategories($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createCategory($data));
    }

    // ----------------------------------------------------------------
    // BOOKS
    // ----------------------------------------------------------------

    public function getBooks($id = null, $data = [], $segments = [])
    {
        if ($id) {
            return $this->handleResponse($this->api->getBook((int) $id));
        }
        $filters = [
            'search'         => $_GET['search']       ?? null,
            'category_id'    => $_GET['category_id']  ?? null,
            'status'         => $_GET['status']        ?? null,
            'available_only' => !empty($_GET['available_only']),
        ];
        return $this->handleResponse($this->api->listBooks($filters));
    }

    public function postBooks($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createBook($data));
    }

    public function putBooks($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Book ID required');
        return $this->handleResponse($this->api->updateBook((int) $id, $data));
    }

    public function deleteBooks($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Book ID required');
        return $this->handleResponse($this->api->deleteBook((int) $id));
    }

    // ----------------------------------------------------------------
    // ISSUES
    // ----------------------------------------------------------------

    public function getIssues($id = null, $data = [], $segments = [])
    {
        $filters = [
            'status'        => $_GET['status']        ?? null,
            'borrower_type' => $_GET['borrower_type'] ?? null,
            'overdue_only'  => !empty($_GET['overdue_only']),
        ];
        return $this->handleResponse($this->api->listIssues($filters));
    }

    public function postIssues($id = null, $data = [], $segments = [])
    {
        // Inject current user as issued_by if not provided
        if (empty($data['issued_by']) && $this->user) {
            $data['issued_by'] = $this->user['user_id'] ?? $this->user['id'] ?? 0;
        }
        return $this->handleResponse($this->api->issueBook($data));
    }

    // PUT /api/library/issues/{id}/return  → segments = ['return']
    public function putIssues($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Issue ID required');
        $action = $segments[0] ?? '';
        if ($action === 'return') {
            if (empty($data['returned_by']) && $this->user) {
                $data['returned_by'] = $this->user['user_id'] ?? $this->user['id'] ?? 0;
            }
            return $this->handleResponse($this->api->returnBook((int) $id, $data));
        }
        return $this->badRequest("Unknown action: {$action}");
    }

    // ----------------------------------------------------------------
    // OVERDUE (convenience alias)
    // ----------------------------------------------------------------

    public function getOverdue($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listIssues(['overdue_only' => true]));
    }

    // ----------------------------------------------------------------
    // FINES
    // ----------------------------------------------------------------

    public function getFines($id = null, $data = [], $segments = [])
    {
        $filters = ['status' => $_GET['status'] ?? null];
        return $this->handleResponse($this->api->listFines($filters));
    }

    // PUT /api/library/fines/{id}/pay   → segments = ['pay']
    // PUT /api/library/fines/{id}/waive → segments = ['waive']
    public function putFines($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Fine ID required');
        $action = $segments[0] ?? '';
        if ($action === 'pay')   return $this->handleResponse($this->api->payFine((int)$id, $data));
        if ($action === 'waive') return $this->handleResponse($this->api->waiveFine((int)$id, $data));
        return $this->badRequest("Unknown action: {$action}");
    }

    // ----------------------------------------------------------------
    // FALLBACK
    // ----------------------------------------------------------------

    public function getLibrary($id = null, $data = [], $segments = [])
    {
        return $this->success(['message' => 'Library API is running']);
    }

    // ----------------------------------------------------------------
    // HELPER — normalise module response to controller response
    // ----------------------------------------------------------------

    private function handleResponse(array $result)
    {
        $status = $result['status'] ?? 'error';
        $data   = $result['data']   ?? null;
        $msg    = $result['message'] ?? ($status === 'success' ? 'OK' : 'Error');
        $code   = $result['status_code'] ?? ($status === 'success' ? 200 : 500);

        if ($status === 'success') {
            if ($code === 201) return $this->created($data, $msg);
            return $this->success($data, $msg);
        }
        if ($code === 400) return $this->badRequest($msg);
        if ($code === 404) return $this->notFound($msg);
        if ($code === 409) return $this->conflict($msg);
        return $this->serverError($msg);
    }

    // ---- extra response helpers ----
}
