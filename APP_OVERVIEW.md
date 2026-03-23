# RCS HRMS Pro - Application Overview

## Project Overview

**RCS HRMS Pro** is a comprehensive Human Resource Management System designed specifically for **Labour Contractors** in India. It manages the complete lifecycle of contract workers deployed at client locations, including payroll processing, statutory compliance (PF/ESI/PT/LWF), and employee self-service portal.

### Key Features
- **Employee Management**: Complete employee lifecycle from registration to F&F settlement
- **Multi-Client/Multi-Unit Support**: Manage workers across multiple clients and locations
- **Payroll Processing**: Monthly payroll with attendance integration
- **Statutory Compliance**: PF ECR, ESI returns, PT, LWF, Minimum Wages validation
- **Document Generation**: Appointment letters, Form V, XVI, XVII, F2, Nomination forms
- **Employee Self-Service Portal**: Payslips, attendance, profile management
- **Role-Based Access Control**: Granular permissions at menu and action level

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 8.x (No framework) |
| **Database** | MySQL/MariaDB 10.3+ |
| **Frontend** | Bootstrap 5, jQuery |
| **Data Tables** | DataTables with Export (CSV, Excel, PDF) |
| **Forms** | Select2, Flatpickr |
| **Architecture** | Modular MVC-like structure |

---

## Folder Structure

```
/home/z/my-project/
├── index.php                    # Main entry point / Router
├── config/
│   ├── config.php              # Main configuration (session, autoloader, helpers)
│   └── config.local.example.php # Local config template
├── includes/
│   ├── class.database.php      # PDO wrapper (Singleton pattern)
│   ├── class.auth.php          # Authentication & permissions
│   ├── class.employee.php      # Employee management
│   ├── class.payroll.php       # Payroll processing
│   ├── class.attendance.php    # Attendance tracking
│   ├── class.compliance.php    # Statutory compliance
│   ├── class.client.php        # Client management
│   ├── class.unit.php          # Unit/Location management
│   ├── class.notification.php  # Notification system
│   ├── class.excel.php         # Excel import/export
│   ├── constants.php           # Application constants
│   ├── database.php            # DB connection initializer
│   └── SimpleXLSX.php          # Excel parsing library
├── modules/
│   ├── dashboard/              # Dashboard module
│   ├── auth/                   # Login/Logout
│   ├── employee/               # Employee management
│   ├── attendance/             # Attendance tracking
│   ├── payroll/                # Payroll processing
│   ├── compliance/             # PF/ESI/PT compliance
│   ├── client/                 # Client management
│   ├── unit/                   # Unit management
│   ├── contract/               # Contract management
│   ├── forms/                  # HR form generation
│   ├── report/                 # Reports
│   ├── settings/               # System settings
│   ├── notifications/          # Notifications
│   ├── portal/                 # Employee self-service portal
│   ├── recruitment/            # Recruitment
│   ├── billing/                # Billing/Invoicing
│   ├── deployment/             # Employee deployment
│   ├── requisition/            # Manpower requisition
│   ├── advance/                # Salary advances
│   ├── timesheet/              # Timesheet management
│   ├── leave/                  # Leave management
│   ├── settlement/             # F&F Settlement
│   ├── assets/                 # Asset management
│   ├── helpdesk/               # Helpdesk tickets
│   ├── feedback/               # Client feedback
│   ├── announcement/           # Announcements
│   ├── audit/                  # Audit logs
│   ├── ratecard/               # Rate cards
│   └── api/                    # REST API endpoints
├── templates/
│   ├── header.php              # Common header (sidebar, navbar)
│   └── footer.php              # Common footer (JS, modals)
├── assets/
│   ├── css/style.css           # Main stylesheet
│   ├── js/app.js               # Main JavaScript
│   └── images/                 # Logo, favicon
├── uploads/                    # User uploaded files
└── upload/                     # SQL migration files
```

---

## Database Tables (35+ Tables)

### Core Tables
| Table | Description |
|-------|-------------|
| `employees` | Employee master data |
| `employee_salary_structures` | Salary components & statutory flags |
| `employee_documents` | Uploaded documents |
| `employee_advances` | Salary advances/deductions |
| `clients` | Client companies |
| `units` | Work locations/sites |
| `contracts` | Client contracts |
| `designations` | Job designations |

### Payroll Tables
| Table | Description |
|-------|-------------|
| `payroll_periods` | Monthly payroll periods |
| `payroll` | Individual payroll records |
| `payroll_records` | Historical payroll records |
| `payroll_history` | Payroll change history |
| `payroll_exceptions` | Processing exceptions |
| `payslip_templates` | Customizable payslip templates |

### Compliance Tables
| Table | Description |
|-------|-------------|
| `compliance_calendar` | Compliance due dates |
| `compliance_filings` | Filed compliance records |
| `pf_rates` | PF contribution rates |
| `esi_rates` | ESI contribution rates |
| `professional_tax_rates` | State-wise PT slabs |
| `lwf_rates` | Labour Welfare Fund rates |
| `minimum_wages` | State-wise minimum wages |
| `pfdatabase` | PF member database |
| `epfo_members` | EPFO member data |

### System Tables
| Table | Description |
|-------|-------------|
| `users` | System users |
| `roles` | User roles |
| `role_menu_permissions` | Menu access permissions |
| `menu_definitions` | Menu/submenu definitions |
| `notifications` | In-app notifications |
| `audit_log` | System audit trail |
| `settings` | Application settings |
| `companies` | Company information |

---

## Key Relationships

```
clients (1) ──────< units (N)
    │                   │
    │                   └───< employees (via unit_id)
    │
    └───────────────< employees (via client_id)
    
employees (1) ──────< employee_salary_structures (N)
employees (1) ──────< employee_documents (N)
employees (1) ──────< attendance (N)
employees (1) ──────< payroll (N)

payroll_periods (1) ──────< payroll (N)

roles (1) ──────< users (N)
roles (1) ──────< role_menu_permissions (N)
```

---

## Important Workflows

### 1. Login → Dashboard
```
User visits index.php
    ↓
Not logged in? → Redirect to auth/login
    ↓
Login form submission
    ↓
Validate credentials (bcrypt)
    ↓
Set session variables (user_id, role_id, role_code)
    ↓
Regenerate session (prevent fixation)
    ↓
Redirect to dashboard
```

### 2. Employee Registration → Approval
```
Add Employee (employee/add)
    ↓
Insert into employees table (status = 'pending')
    ↓
Create notification for admin/hr
    ↓
Admin views pending employee
    ↓
Approve/Reject (status = 'active' or 'rejected')
    ↓
If approved: Generate employee code
    ↓
Create salary structure record
```

### 3. Payroll Processing Flow
```
Select Payroll Period (payroll/process)
    ↓
Select Client/Unit/Employees
    ↓
Calculate:
  - Basic + Allowances
  - PF Contribution (Employee + Employer)
  - ESI Contribution
  - Professional Tax
  - LWF
  - Deductions (advances, etc.)
    ↓
Generate payroll records (status = 'draft')
    ↓
Review & Approve (status = 'approved')
    ↓
Mark as Paid (status = 'paid')
    ↓
Generate payslips
```

### 4. Report Generation
```
Select Report Type
    ↓
Apply Filters (date range, client, unit, etc.)
    ↓
Query database
    ↓
Display in DataTable
    ↓
Export options: CSV, Excel, PDF
```

---

## Routing Mechanism

### URL Pattern
```
index.php?page=module/action&id=123

Examples:
- index.php?page=dashboard           → modules/dashboard/index.php
- index.php?page=employee/list       → modules/employee/list.php
- index.php?page=employee/view&id=5  → modules/employee/view.php (with $_GET['id'])
```

### Routing Flow
1. `sanitizePageParam()` validates URL parameter (alphanumeric + slash/hyphen/underscore)
2. `getSafeModulePath()` checks whitelist of allowed modules
3. Real path resolution prevents directory traversal
4. Role-based access check via `$moduleAccess` array
5. Include `templates/header.php` → module file → `templates/footer.php`

### Special Request Types
| Type | Pattern | Handler |
|------|---------|---------|
| AJAX | `?ajax=1` or `X-Requested-With` header | Returns JSON |
| API | `?page=api/employees` | JSON API endpoints |
| Export | `?export=1` | CSV/Excel download (no template) |
| Delete | `?page=*/delete` | Direct delete handler |

---

## Authentication & Authorization

### Role Hierarchy
| Role | Level | Access Scope |
|------|-------|--------------|
| admin | 100 | All modules |
| hr_executive | 80 | Most modules except some settings |
| manager | 60 | Dashboard, employees, attendance, payroll, reports |
| supervisor | 40 | Dashboard, employees, attendance |
| worker | 20 | Dashboard, profile, payslips (portal only) |

### Session Variables
```php
$_SESSION['user_id']
$_SESSION['username']
$_SESSION['role_id']
$_SESSION['role_code']
$_SESSION['role_name']
$_SESSION['first_name']
$_SESSION['last_name']
$_SESSION['csrf_token']
```

### Menu Permission System
- Database table: `role_menu_permissions`
- Granular permissions: `can_view`, `can_add`, `can_edit`, `can_delete`, `can_export`, `can_import`, `can_print`
- Admin sees all; other roles need explicit permissions

---

## Setup Instructions

### Local Development

1. **Requirements**
   - PHP 8.0+
   - MySQL/MariaDB 10.3+
   - Apache/Nginx web server

2. **Installation**
   ```bash
   # Clone repository
   git clone https://github.com/rcstrue/php_payroll.git
   
   # Navigate to project
   cd php_payroll
   
   # Create local config
   cp config/config.local.example.php config/config.local.php
   ```

3. **Configure Database**
   Edit `config/config.local.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'hrms_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_CHARSET', 'utf8mb4');
   define('BASE_URL', 'http://localhost/hrms');
   ```

4. **Import Database**
   ```bash
   mysql -u root -p hrms_db < upload/rcsfaxhz_bolt.sql
   ```

5. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chown -R www-data:www-data uploads/
   ```

6. **Access Application**
   - URL: `http://localhost/hrms/`
   - Default admin credentials (check database)

### Production Server

1. **Environment Setup**
   - Ensure PHP 8.0+ with PDO MySQL extension
   - Configure HTTPS (recommended)
   - Set appropriate file permissions

2. **Configuration**
   - Update `config/config.local.php` with production DB credentials
   - Set `BASE_URL` to production URL
   - Disable error display in production:
     ```php
     ini_set('display_errors', 0);
     error_reporting(E_ALL & ~E_NOTICE);
     ```

3. **Security Checklist**
   - Change default admin password
   - Enable HTTPS
   - Configure CSRF protection
   - Set secure session cookies
   - Restrict `uploads/` directory access

---

## Environment/Config Details

### config/config.php
```php
// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 7200);  // 2 hours

// Application Constants
define('APP_NAME', 'RCS HRMS Pro');
define('APP_VERSION', '2.3.0');

// CSRF Protection
function generateCSRFToken()
function verifyCSRFToken($token)

// Input Sanitization
function sanitize($input)
function sanitizePageParam($page)

// Flash Messages
function setFlash($type, $message)
function getFlash()

// Activity Logging
function logActivity($action, $details = '')
```

### Upload Paths
- **Profile Photos**: `/uploads/profile/`
- **Aadhaar Cards**: `/uploads/aadhaar/`
- **Documents**: `/uploads/documents/`

---

## Security Features

1. **CSRF Protection** - Token-based validation on all forms
2. **Path Traversal Prevention** - Whitelisted modules, realpath validation
3. **Input Sanitization** - `sanitize()` function with htmlspecialchars
4. **Prepared Statements** - PDO with emulated prepares disabled
5. **Role-Based Access Control** - Module and action-level permissions
6. **Session Security** - Custom session name, regeneration, 2-hour timeout
7. **Open Redirect Prevention** - URL validation in `redirect()` function
8. **Password Hashing** - bcrypt with cost 12

---

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/employees` | GET | List employees (JSON) |
| `api/units` | GET | List units |
| `api/zones` | GET | List zones by state |
| `api/next-unit-code` | GET | Generate next unit code |
| `api/menu-permissions` | GET/POST | Menu permissions management |

---

## Form Generation Module

Available HR forms:
- **Appointment Letter** - `forms/appointment`
- **Form V** - Contractor license form
- **Form XVI** - Muster roll
- **Form XVII** - Register of workmen
- **Form F2** - Nomination form
- **Service Certificate** - Employment certificate
- **Relieving Letter** - Exit documentation
- **Experience Certificate** - Work experience proof
- **Nomination Forms** - PF, ESI, Gratuity nominations

---

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check `config/config.local.php` credentials
   - Ensure MySQL service is running
   - Verify database exists

2. **Page Not Found (404)**
   - Check if module is in allowed list in `index.php`
   - Verify file exists in `modules/` directory

3. **Permission Denied**
   - Check user role has access to module
   - Verify `role_menu_permissions` table

4. **Upload Failures**
   - Check `uploads/` directory permissions
   - Verify file size limits in PHP config

### Debug Mode
Enable in `config/config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.3.0 | Current | Menu Permissions Enhanced |
| 2.2.0 | - | Notification System |
| 2.1.0 | - | Payroll Cycle Support |
| 2.0.0 | - | Initial Release |

---

## Support & Contact

**Developer**: RCS TRUE FACILITIES PVT LTD  
**Website**: https://join.rcsfacility.com/hrms/  
**Repository**: https://github.com/rcstrue/php_payroll

---

*This documentation was generated as part of a comprehensive application audit.*
