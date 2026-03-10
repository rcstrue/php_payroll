# RCS HRMS Pro - Installation Guide

## System Requirements

- **PHP**: 7.4 or higher (8.x recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache with mod_rewrite enabled
- **Extensions**: PDO, PDO_MySQL, JSON, ZipArchive, cURL

## Installation Steps

### Step 1: Upload Files

1. Upload all files from `rcs-hrms` folder to your web server
   - Either in root directory or a subdirectory (e.g., `hrms/`)

2. Ensure the following directories are writable:
   ```
   uploads/
   uploads/documents/
   uploads/attendance/
   uploads/exports/
   ```

### Step 2: Create Database

1. Create a new database in phpMyAdmin or MySQL:
   ```sql
   CREATE DATABASE rcs_hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the database schema:
   - Open phpMyAdmin
   - Select the `rcs_hrms` database
   - Go to "Import" tab
   - Select file: `install/database.sql`
   - Click "Go"

### Step 3: Configure Database Connection

Edit `config/config.php` and update database credentials:

```php
define('DB_HOST', 'localhost');      // Database host
define('DB_NAME', 'rcs_hrms');        // Database name
define('DB_USER', 'your_username');   // Database username
define('DB_PASS', 'your_password');   // Database password
```

### Step 4: Configure Application URL

Edit `config/config.php`:

```php
define('APP_URL', 'https://yourdomain.com');  // Your domain URL
```

### Step 5: Set Up Apache (if needed)

If using Apache, create `.htaccess` file in the root directory:

```apache
RewriteEngine On
RewriteBase /

# Redirect to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Protect sensitive directories
RedirectMatch 403 ^/(config|includes|classes|install)/.*$
RedirectMatch 403 ^/(uploads)/.*\.php$

# Set default character set
AddDefaultCharset UTF-8
```

### Step 6: Default Login

After installation, login with default credentials:

- **URL**: `https://yourdomain.com/index.php?page=auth/login`
- **Username**: `admin`
- **Password**: `password`

**IMPORTANT**: Change the default password immediately after first login!

### Step 7: Configure Company Settings

1. Go to **Settings → Company**
2. Update company information
3. Set PF and ESI establishment IDs
4. Configure statutory rates

## Post-Installation Setup

### 1. Create Clients and Units

1. Go to **Clients & Units → Clients**
2. Add your client companies
3. Go to **Clients & Units → Units**
4. Add work locations/units under each client

### 2. Configure Minimum Wages

1. Go to **Compliance → Minimum Wages**
2. Add minimum wage rates for each state and worker category
3. Update when government notifications are released

### 3. Set Up Users

1. Go to **Settings → Users**
2. Create user accounts for HR, Managers, Supervisors
3. Assign appropriate roles

### 4. Employee Onboarding

Option A: Manual Entry
- Go to **Employees → Add Employee**
- Fill in employee details

Option B: Bulk Import
- Go to **Employees → Import**
- Upload Excel or EPFO CSV

Option C: API Sync
- Configure external API
- Use sync button to pull employee data

### 5. Attendance Processing

1. Go to **Attendance → Upload Attendance**
2. Select unit and month
3. Upload Excel/CSV file with attendance data

### 6. Payroll Processing

1. Go to **Payroll → Process Payroll**
2. Create payroll period
3. Process payroll
4. Review and approve

### 7. Generate Statutory Forms

- Form V: Register of Workmen
- Form XVI: Muster Roll
- Form XVII: Wage Register
- Form F2: Return of Employees

## File Structure

```
rcs-hrms/
├── assets/
│   ├── css/
│   │   └── style.css           # Main stylesheet
│   ├── js/
│   │   └── app.js              # Main JavaScript
│   ├── images/
│   └── fonts/
├── classes/
│   ├── Auth.php                # Authentication class
│   ├── Employee.php            # Employee management
│   ├── Attendance.php          # Attendance processing
│   ├── Payroll.php             # Payroll calculation
│   └── Compliance.php          # Compliance management
├── config/
│   └── config.php              # Configuration file
├── includes/
│   ├── database.php            # Database connection
│   └── SimpleXLSX.php          # Excel parser
├── install/
│   └── database.sql            # Database schema
├── modules/
│   ├── auth/                   # Authentication pages
│   ├── dashboard/              # Dashboard
│   ├── employee/               # Employee management
│   ├── attendance/             # Attendance pages
│   ├── payroll/                # Payroll pages
│   ├── compliance/             # Compliance pages
│   ├── forms/                  # Statutory forms
│   ├── report/                 # Reports
│   ├── settings/               # Settings pages
│   └── api/                    # API endpoints
├── templates/
│   ├── header.php              # Page header
│   └── footer.php              # Page footer
├── uploads/                    # Upload directory
├── index.php                   # Main entry point
└── .htaccess                   # Apache configuration
```

## Troubleshooting

### Database Connection Error
- Check database credentials in `config/config.php`
- Ensure MySQL/MariaDB is running
- Verify database user has proper permissions

### Blank Page / 500 Error
- Check PHP error logs
- Ensure all required PHP extensions are installed
- Verify file permissions

### Session Issues
- Ensure sessions are working on server
- Check `session.save_path` in php.ini

### File Upload Issues
- Check `upload_max_filesize` and `post_max_size` in php.ini
- Ensure uploads directory is writable

## Support

For support, contact:
- Email: support@rcsfacility.com
- Documentation: [Link to documentation]

---

**RCS HRMS Pro**  
Version 1.0.0  
© 2024 RCS TRUE FACILITIES PVT LTD
