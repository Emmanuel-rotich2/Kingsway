<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Kingsway Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    .school-header {
      background-color: #006400;
      padding: 1rem;
      color: white;
    }

    .school-header img {
      height: 60px;
      margin-right: 15px;
    }

    .sidebar {
      width: 250px;
      background-color: #800000;
      color: white;
      position: fixed;
      height: 100%;
      transition: transform 0.3s ease, width 0.3s ease;
      overflow: hidden;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      color: white;
      padding: 0.75rem 1rem;
      text-decoration: none;
    }

    .sidebar a:hover {
      background-color: #a00000;
    }

    .sidebar a i {
      margin-right: 10px;
    }

    .sidebar h4 {
      padding: 1rem;
      background-color: #5a0000;
      margin: 0;
      display: flex;
      align-items: center;
      white-space: nowrap;
    }

    .sidebar h4 i {
      margin-right: 10px;
    }

    .sidebar-collapsed {
      width: 80px;
    }

    .sidebar-collapsed a .sidebar-text,
    .sidebar-collapsed h4 .sidebar-text {
      display: none;
    }

    .sidebar-collapsed a {
        justify-content: center;
        padding: 0.75rem 0;
    }

    .sidebar-collapsed h4 {
        justify-content: center;
        padding: 1rem 0;
    }

    .main-content {
      margin-left: 250px;
      padding: 1.5rem;
      transition: margin-left 0.3s ease;
    }

    .collapsed-main {
      margin-left: 80px;
    }

    .topbar {
      background-color: #28a745;
      color: white;
      padding: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card {
      border-radius: 1rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      height: 100%;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
      }

      .sidebar {
        z-index: 1000;
        transform: translateX(-100%);
        width: 250px;
      }

      .sidebar-visible-mobile {
          transform: translateX(0%);
      }

      .collapsed-main {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

  <div class="school-header d-flex align-items-center">
    <img src="images/download (16).jpg" alt="Kingsway Logo">
    <div>
      <h5 class="mb-0">KINGSWAY PREPARATORY SCHOOL</h5>
      <small>
        P.O BOX 203-20203, LONDIANI<br>
        PHONE: 0720113030 / 0720113031<br>
        Motto: <em>“In God We Soar”</em>
      </small>
    </div>
  </div>
  <div id="sidebar" class="sidebar">
    <h4> <span class="sidebar-text">Kingsway Admin</span></h4>
    <a href="#"><i class="fas fa-tachometer-alt"></i> <span class="sidebar-text">Dashboard</span></a>
    <a href="manage.php"><i class="fas fa-user-graduate"></i> <span class="sidebar-text">Manage Students</span></a>
    <a href="teacher.php"><i class="fas fa-chalkboard-teacher"></i> <span class="sidebar-text">Manage Teachers</span></a>
    <a href="#"><i class="fas fa-clipboard-list"></i> <span class="sidebar-text">Manage Classes</span></a>
    <a href="#"><i class="fas fa-money-bill-wave"></i> <span class="sidebar-text">Fee Management</span></a>
    <a href="#"><i class="fas fa-poll"></i> <span class="sidebar-text">Exam Results</span></a>
    <a href="#"><i class="fas fa-user-times"></i> <span class="sidebar-text">Absenteeism</span></a>
    <a href="#"><i class="fas fa-clipboard"></i> <span class="sidebar-text">Notice Board</span></a>
    <a href="#"><i class="fas fa-calendar-alt"></i> <span class="sidebar-text">Events Calendar</span></a>
    <a href="#"><i class="fas fa-money-check-alt"></i> <span class="sidebar-text">Payroll Management</span></a>
    <a href="#"><i class="fas fa-chart-line"></i> <span class="sidebar-text">Reports</span></a>
    <a href="#"><i class="fas fa-sign-out-alt"></i> <span class="sidebar-text">Logout</span></a>
  </div>


  <div id="main" class="main-content">
    <div class="topbar">
      <button class="btn btn-light" onclick="toggleSidebar()">☰</button>
      <div>Welcome Admin</div>
    </div>


  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const main = document.getElementById('main');
      sidebar.classList.toggle('sidebar-collapsed');
      main.classList.toggle('collapsed-main');
      if (window.innerWidth <= 768) {
        sidebar.classList.toggle('sidebar-visible-mobile');
      }
    }
  </script>

</body>
</html>
