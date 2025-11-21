# Kingsway School Management System

A comprehensive, modern school management system built with PHP and JavaScript, providing a robust REST API and a fully dynamic, real-time, API-driven frontend.

## Features

- **User Management**: Role-based access control with customizable permissions
- **Academic Management**: Subjects, classes, assessments, and performance tracking
- **Student Management**: Enrollment, attendance, performance, and fee management
- **Staff Management**: Teachers, administrative staff, attendance, and payroll
- **Financial Management**: Fees, payments, invoices, and financial reporting
- **Inventory Management**: Stock tracking, valuations, and low stock alerts
- **Transport Management**: Routes, vehicles, drivers, and maintenance tracking
- **Activities Management**: Extra-curricular activities and event management
- **Communication**: Announcements, notifications, SMS, and email integration
- **Reporting**: Comprehensive reporting system for all modules
- **Scheduling**: Class timetables, exam schedules, and event planning
- **Modern Frontend**: All data flows through a centralized `api.js` file, with real-time auto-reload, unified notification modal, and dummy data fallback for seamless UX

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer for dependency management
- XAMPP/LAMPP (recommended for local development)

## Installation

1. Clone the repository:

```bash
git clone https://github.com/yourusername/kingsway.git
```

1. Navigate to the project directory:

```bash
cd kingsway
```

1. Install dependencies:

```bash
composer install
```

1. Create a database and import the schema:

```bash
mysql -u root -p
CREATE DATABASE kingsway;
exit;
mysql -u root -p kingsway < database/KingsWayAcademy.sql
```

1. Configure your environment:
   - Copy `.env.example` to `.env`
   - Update database credentials and other settings in `.env`

1. Set up your web server:
   - For Apache, ensure mod_rewrite is enabled
   - Point your web root to the project's public directory
   - Ensure proper permissions on storage directories

## API Documentation

The system provides a RESTful API with the following main endpoints (see `docs/api_guide.md` for full details):

- `/api/users.php` - User management
- `/api/students.php` - Student management
- `/api/staff.php` - Staff management
- `/api/academic.php` - Academic management
- `/api/finance.php` - Financial management
- `/api/inventory.php` - Inventory management
- `/api/transport.php` - Transport management
- `/api/activities.php` - Activities management
- `/api/communications.php` - Communication management
- `/api/reports.php` - Reporting system
- `/api/schedules.php` - Scheduling management

For detailed API documentation, refer to `docs/api_guide.md`

## Modern Frontend Architecture

- **Centralized API Calls**: All frontend pages interact with the backend via `js/api.js`.
- **Unified Notification Modal**: All notifications and popups use a single Bootstrap modal, color-coded for success, error, warning, and info.
- **Real-Time Data**: All tables, dashboards, and data-driven components auto-reload every 30 seconds for real-time updates.
- **Dummy Data Fallback**: If the backend returns no data, the frontend uses dummy data to keep the UI populated.
- **No Legacy AJAX/PHP Rendering**: All legacy direct AJAX, fetch, or PHP data rendering has been removed in favor of the new architecture.

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. To access protected endpoints:

1. Obtain a token through `/api/auth.php?action=login`
2. Include the token in the Authorization header:

```http
Authorization: Bearer <your_token>
```

## Directory Structure

```plaintext
Kingsway/
├── api/                 # API endpoints and modules
│   ├── modules/        # Core business logic
│   ├── includes/       # Shared components
│   └── index.php       # API entry point
├── config/             # Configuration files
├── database/           # Database migrations and seeds
├── docs/              # Documentation
├── vendor/            # Composer dependencies
└── public/            # Public assets
```

## Security Features

- JWT-based authentication
- Role-based access control
- Input validation and sanitization
- Prepared statements for database queries
- Password hashing with secure algorithms
- CORS protection
- Rate limiting
- Request validation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and queries, please create an issue in the repository or contact the development team.
