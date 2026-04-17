<?php
/**
 * Library Admin/Librarian View — PARTIAL
 * Full CRUD: books catalogue, issue/return, overdue tracker, fines
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3" id="adminLibraryRoot">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-book-half me-2 text-primary"></i>Library Management</h2>
      <small class="text-muted">Catalogue · Loans · Overdue · Fines</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="libraryController.loadStats()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
      <button class="btn btn-primary" onclick="libraryController.showAddBookModal()">
        <i class="bi bi-plus-circle me-1"></i> Add Book
      </button>
      <button class="btn btn-success" onclick="libraryController.showIssueModal()">
        <i class="bi bi-arrow-bar-right me-1"></i> Issue Book
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="libTotalBooks">—</div>
          <div class="text-muted small">Total Titles</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="libAvailCopies">—</div>
          <div class="text-muted small">Available</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="libIssuedCount">—</div>
          <div class="text-muted small">On Loan</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="libOverdueCount">—</div>
          <div class="text-muted small">Overdue</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="libCatCount">—</div>
          <div class="text-muted small">Categories</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="libPendingFines">—</div>
          <div class="text-muted small">Fines Due</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="libraryTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBooks">Books</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIssues">Current Loans</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOverdue">Overdue</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFines">Fines</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCategories">Categories</button></li>
  </ul>

  <div class="tab-content">

    <!-- BOOKS TAB -->
    <div class="tab-pane fade show active" id="tabBooks">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <input type="text" id="bookSearch" class="form-control" placeholder="Search title, author, ISBN…">
            </div>
            <div class="col-md-3">
              <select id="bookCatFilter" class="form-select">
                <option value="">All Categories</option>
              </select>
            </div>
            <div class="col-md-2">
              <select id="bookStatusFilter" class="form-select">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="damaged">Damaged</option>
              </select>
            </div>
            <div class="col-md-2">
              <button class="btn btn-outline-primary w-100" onclick="libraryController.loadBooks()">Search</button>
            </div>
          </div>
          <div id="booksTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- CURRENT LOANS TAB -->
    <div class="tab-pane fade" id="tabIssues">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div id="issuesTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- OVERDUE TAB -->
    <div class="tab-pane fade" id="tabOverdue">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div id="overdueTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- FINES TAB -->
    <div class="tab-pane fade" id="tabFines">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="mb-3">
            <select id="fineStatusFilter" class="form-select w-auto" onchange="libraryController.loadFines()">
              <option value="">All Fines</option>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="waived">Waived</option>
            </select>
          </div>
          <div id="finesTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- CATEGORIES TAB -->
    <div class="tab-pane fade" id="tabCategories">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-outline-primary btn-sm" onclick="libraryController.showAddCategoryModal()">
              <i class="bi bi-plus me-1"></i> Add Category
            </button>
          </div>
          <div id="categoriesTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<!-- ADD BOOK MODAL -->
<div class="modal fade" id="addBookModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addBookModalTitle">Add Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="bookFormId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" id="bookTitle" class="form-control" placeholder="Book title">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">ISBN</label>
            <input type="text" id="bookIsbn" class="form-control" placeholder="978-…">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Author <span class="text-danger">*</span></label>
            <input type="text" id="bookAuthor" class="form-control" placeholder="Author name(s)">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Publisher</label>
            <input type="text" id="bookPublisher" class="form-control" placeholder="Publisher">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Edition</label>
            <input type="text" id="bookEdition" class="form-control" placeholder="e.g. 3rd">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Year</label>
            <input type="number" id="bookYear" class="form-control" placeholder="2024" min="1900" max="2099">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Category</label>
            <select id="bookCategory" class="form-select">
              <option value="">— Select —</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Copies</label>
            <input type="number" id="bookCopies" class="form-control" value="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Shelf / Location</label>
            <input type="text" id="bookShelf" class="form-control" placeholder="e.g. A3-L2">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Status</label>
            <select id="bookStatus" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="damaged">Damaged</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea id="bookDesc" class="form-control" rows="2" placeholder="Short description…"></textarea>
          </div>
        </div>
        <div id="bookFormError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveBookBtn" onclick="libraryController.saveBook()">Save Book</button>
      </div>
    </div>
  </div>
</div>

<!-- ISSUE BOOK MODAL -->
<div class="modal fade" id="issueBookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Issue Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Book <span class="text-danger">*</span></label>
          <select id="issueBookId" class="form-select">
            <option value="">— Select available book —</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Borrower Type</label>
          <select id="issueBorrowerType" class="form-select" onchange="libraryController.loadBorrowerList()">
            <option value="student">Student</option>
            <option value="staff">Staff</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Borrower <span class="text-danger">*</span></label>
          <select id="issueBorrowerId" class="form-select">
            <option value="">— Loading… —</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Due Date</label>
          <input type="date" id="issueDueDate" class="form-control">
        </div>
        <div id="issueError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="libraryController.submitIssue()">Issue Book</button>
      </div>
    </div>
  </div>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
          <input type="text" id="catName" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea id="catDesc" class="form-control" rows="2"></textarea>
        </div>
        <div id="catError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="libraryController.saveCategory()">Save</button>
      </div>
    </div>
  </div>
</div>
