# RCS HRMS Pro - Developer Notes

## ⚠️ IMPORTANT: READ BEFORE MAKING CHANGES

This document contains critical information to prevent common mistakes. **Always refer to this file before modifying payroll, employee, or related modules.**

---

## Database Schema

### Column Names

| Table | Correct Column | Common Mistake |
|-------|---------------|----------------|
| `employees` | `client_id` (FK) | `client_name` ❌ |
| `employees` | `unit_id` (FK) | `unit_name` ❌ |
| `clients` | `name` | `client_name` ❌ |
| `units` | `name` | `unit_name` ❌ |

### Getting Client/Unit Names

**ALWAYS use JOINs to get names:**

```php
// CORRECT
$sql = "SELECT e.*, c.name as client_name, u.name as unit_name
        FROM employees e
        LEFT JOIN clients c ON e.client_id = c.id
        LEFT JOIN units u ON e.unit_id = u.id";

// WRONG - These columns don't exist in employees table
$sql = "SELECT client_name, unit_name FROM employees";
```

---

## Employee Module

### Aadhaar Number Display

**🚨 AADHAAR NUMBER SHOULD NEVER BE HIDDEN IN INTERNAL VIEWS**

The `maskAadhaar()` function should **ONLY** be used for:
- External reports shared with third parties
- Printed payslips given to employees
- Export files sent outside the organization

**DO NOT use `maskAadhaar()` in:**
- Employee list page
- Employee view page
- Employee edit page
- Admin dashboards
- Any internal HR views

```php
// CORRECT for internal views
echo $employee['aadhaar_number'];

// CORRECT only for external/payslip exports
echo maskAadhaar($employee['aadhaar_number']);
```

### Statutory Applicability Checkboxes

When working with statutory applicability checkboxes (PF, ESI, PT, LWF, Bonus, Gratuity, Overtime):

1. The database columns are in `employee_salary_structures` table
2. They are boolean (`tinyint(1)`) - 0 or 1
3. Use proper checkbox syntax in forms:

```php
// CORRECT - Checkbox properly bound to value
<input type="checkbox" name="pf_applicable" value="1" 
       <?php echo $salary['pf_applicable'] ? 'checked' : ''; ?>>

// WRONG - Missing value attribute
<input type="checkbox" name="pf_applicable" 
       <?php echo $salary['pf_applicable'] ? 'checked' : ''; ?>>
```

---

## Payroll Module

### Status Flow

```
Draft → Processed → Approved → Paid
                    ↓
                  Frozen (no edits allowed)
                    ↓
            Hold (individual employee level)
```

### Frozen Status

- Once a payroll period is marked as **Frozen**, no modifications are allowed
- This includes: recalculations, hold/release, deletions
- Unfreeze is available but should be used with caution
- Frozen status is for compliance audit trail

### Salary Hold

- Individual employees can have their salary held
- Held salaries are excluded from bank transfers
- Hold status shows as warning badge in payroll list
- Reason must be provided when holding salary

### Payroll Dirty Flag

- When attendance, salary, or advances change after payroll processing
- The `payroll_dirty` flag is set to 1
- Use "Recalculate" to update only dirty records
- This ensures payroll stays synchronized with source data

---

## API Endpoints

### AJAX Calls

When making AJAX calls for dropdowns, always check:

```php
// The API should return proper JSON
header('Content-Type: application/json');
echo json_encode($data);
```

### Column Detection

For tables where column names might vary:

```php
// Check column existence
$colCheck = $db->query("SHOW COLUMNS FROM clients LIKE 'name'");
$nameCol = ($colCheck && $colCheck->rowCount() > 0) ? 'name' : 'client_name';

// Use the detected column with alias
$clients = $db->query("SELECT id, {$nameCol} as name FROM clients")->fetchAll();
```

---

## JavaScript/jQuery

### Document Ready

All inline JavaScript should be assigned to `$inlineJS` variable:

```php
$inlineJS = "
$(document).ready(function() {
    // Your code here
});
";
```

This is automatically wrapped in `$(document).ready()` by the footer template.

### External JS Libraries

Load external libraries using `$extraJS`:

```php
$extraJS = '<script src="https://cdn.example.com/library.js"></script>';
```

---

## Common Errors & Solutions

### Headers Already Sent

**Error:** `Cannot modify header information - headers already sent`

**Solution:** Use the `redirect()` helper function instead of `header()`:

```php
// CORRECT
redirect('index.php?page=employee/list');

// WRONG - Will fail if any output was sent
header('Location: index.php?page=employee/list');
exit;
```

### Undefined Array Key 'name'

**Error:** `Undefined array key 'name'`

**Cause:** Trying to access `$row['name']` when the column is named differently

**Solution:** Always use column aliasing:

```php
$sql = "SELECT id, client_name as name FROM clients";
// Now $row['name'] will work
```

### Employee List Edit Button Not Working

**Common Causes:**
1. Missing or incorrect `data-id` attribute
2. JavaScript not loading due to `$` not defined
3. Modal not properly initialized

**Solution:**
```php
// Ensure jQuery is loaded before inline scripts
// Use $inlineJS for document.ready code
// Verify data attributes match expected format
```

---

## File Upload Paths

All uploads should go to:

```
/home/z/my-project/uploads/
├── profiles/         # Employee profile pictures
├── documents/        # Employee documents
├── temp/            # Temporary files
└── exports/         # Generated exports
```

---

## Git Deployment

The project uses GitHub Actions for auto-deployment:

1. **Local changes** → Push to GitHub
2. **GitHub Actions** → Automatically deploys to production
3. **Production** → Should match local after deployment

**Before pushing:**
- Test changes locally
- Run `git status` to see modified files
- Commit with descriptive messages
- Push to main branch

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.2.0 | 2024 | Payroll enhancements: filters, bulk actions, freeze, hold, charts |
| 2.1.0 | 2024 | Bug fixes, security improvements |
| 2.0.0 | 2024 | Database schema update, new modules |

---

## Quick Reference

```php
// Format currency
echo formatCurrency($amount);

// Format date
echo formatDate($date);

// Sanitize output
echo sanitize($input);

// Redirect safely
redirect('index.php?page=module/action');

// Set flash message
setFlash('success', 'Record saved!');
setFlash('error', 'Something went wrong');

// Get flash message (auto-clears)
$flash = getFlash();
```

---

**Last Updated:** 2024
**Maintainer:** RCS TRUE FACILITIES PVT LTD
