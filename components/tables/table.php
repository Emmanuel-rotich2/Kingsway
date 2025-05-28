<?php
function renderTable($title, $headers, $rows, $withActions = false) {
  ?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><?php echo $title; ?></h5>
      <input type="text" class="form-control w-25" id="tableSearchInput" placeholder="Search..." onkeyup="filterTable(this)">
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0" id="dataTable">
        <thead class="table-light">
          <tr>
            <?php foreach ($headers as $header) echo "<th>$header</th>"; ?>
            <?php if ($withActions) echo "<th>Actions</th>"; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($row as $col) echo "<td>$col</td>"; ?>
              <?php if ($withActions): ?>
                <td>
                  <button class="btn btn-sm btn-primary">Edit</button>
                  <button class="btn btn-sm btn-danger">Delete</button>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <nav>
      <ul class="pagination justify-content-center mt-2" id="pagination"></ul>
    </nav>
  </div>

  <script>
    function filterTable(input) {
      const filter = input.value.toLowerCase();
      const rows = document.querySelectorAll("#dataTable tbody tr");
      rows.forEach(row => {
        const match = [...row.cells].some(td => td.innerText.toLowerCase().includes(filter));
        row.style.display = match ? '' : 'none';
      });
    }

    // Basic Pagination
    document.addEventListener("DOMContentLoaded", () => {
      const rows = document.querySelectorAll("#dataTable tbody tr");
      const rowsPerPage = 5;
      let currentPage = 1;

      function paginate() {
        const totalPages = Math.ceil(rows.length / rowsPerPage);
        rows.forEach((row, i) => {
          row.style.display = (i >= (currentPage - 1) * rowsPerPage && i < currentPage * rowsPerPage) ? '' : 'none';
        });

        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
          const li = document.createElement('li');
          li.classList.add('page-item');
          if (i === currentPage) li.classList.add('active');
          li.innerHTML = `<a class='page-link' href='#'>${i}</a>`;
          li.addEventListener('click', e => {
            e.preventDefault();
            currentPage = i;
            paginate();
          });
          pagination.appendChild(li);
        }
      }

      paginate();
    });
  </script>
  <?php
}
?>
