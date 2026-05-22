# 🖨️ PrintShop Manager — Setup Guide for XAMPP

## Prerequisites
- XAMPP installed (PHP 8.0+ recommended)
- MySQL / MariaDB running via XAMPP

---

## Step-by-Step Setup

### Step 1 — Copy Project Files
Place the `printing_shop_system` folder inside XAMPP's `htdocs`:
```
C:\xampp\htdocs\printing_shop_system\
```

### Step 2 — Create the Database
1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **Import** in the top tab
3. Click **Choose File** → select `database/printing_shop_system.sql`
4. Click **Go** — the database and tables will be created automatically

### Step 3 — Configure Database Connection
Open `includes/config/database.php` and update if needed:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'printing_shop_system');
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password (blank by default in XAMPP)
```

### Step 4 — Create Uploads Folder
Make sure this folder exists and is writable:
```
printing_shop_system/assets/uploads/
```
It is included in the zip. If missing, create it manually.

### Step 5 — Access the System
Open your browser and go to:
```
http://localhost/printing_shop_system/
```

### Step 6 — Login
| Field    | Value         |
|----------|---------------|
| Username | `admin`       |
| Password | `admin123`    |

> ⚠️ Change the default password after first login (via phpMyAdmin → users table).

---

## Folder Structure

```
printing_shop_system/
├── index.php                  ← Root redirect
├── login.php                  ← Login page
├── logout.php                 ← Logout handler
│
├── admin/
│   ├── dashboard.php          ← Main dashboard with charts
│   ├── orders.php             ← Orders (list, create, view, edit, delete)
│   ├── customers.php          ← Customer management
│   ├── payments.php           ← Payment tracking & updates
│   └── reports.php            ← Daily / weekly / monthly reports
│
├── includes/
│   ├── config/
│   │   ├── app.php            ← App constants & helpers
│   │   └── database.php       ← PDO connection
│   ├── header.php             ← HTML head + top navbar
│   ├── sidebar.php            ← Sidebar navigation
│   ├── footer.php             ← Closing tags + JS includes
│   └── customer_form_fields.php ← Reusable form partial
│
├── assets/
│   ├── css/
│   │   └── style.css          ← Custom styles
│   ├── js/
│   │   └── app.js             ← Custom JavaScript
│   ├── images/                ← Static images
│   └── uploads/               ← Uploaded order files (auto-created)
│
└── database/
    └── printing_shop_system.sql ← Full DB schema + seed data
```

---

## Features Summary

| Feature                  | Details                                               |
|--------------------------|-------------------------------------------------------|
| Authentication           | Login/logout, bcrypt passwords, remember-me cookie    |
| Dashboard                | Stats cards, bar chart, order status overview         |
| Orders                   | Full CRUD, file upload, receipt auto-generation       |
| Print Types              | B&W, Colored, Photo, Tarpaulin, ID Picture            |
| Order Status             | Pending → Processing → Completed → Claimed            |
| Payment Tracking         | Paid / Unpaid / Partial, multiple payment methods     |
| Customer Management      | Add, edit, delete, search customers                   |
| Reports                  | Daily, weekly, monthly — printable with chart         |
| Receipt Printing         | Auto-generated receipt number, browser print dialog   |
| File Uploads             | PDF, images, Word docs (max 10MB)                    |
| Security                 | PDO prepared statements, session management           |
| Responsive UI            | Bootstrap 5, mobile-friendly sidebar                  |

---

## Default Credentials
- **Username:** admin
- **Password:** admin123
- **Email:** admin@printshop.com

---

## Troubleshooting

**Cannot connect to database?**
→ Check `includes/config/database.php` credentials
→ Ensure MySQL is running in XAMPP Control Panel

**File upload not working?**
→ Ensure `assets/uploads/` folder exists and is writable
→ Check `php.ini` upload_max_filesize (set to at least 10M)

**Blank page / errors?**
→ Enable PHP error display in `php.ini`: `display_errors = On`
→ Check XAMPP PHP version (needs 7.4+)

**Wrong BASE_URL?**
→ Edit `includes/config/app.php`: `define('BASE_URL', '/printing_shop_system');`
→ If your folder name is different, update this constant

---

## Tech Stack
- **PHP 8.x** (PDO, sessions, password_hash)
- **MySQL / MariaDB**
- **Bootstrap 5.3**
- **Bootstrap Icons**
- **Chart.js 4**
- **Vanilla JavaScript (ES6)**
- **Google Fonts** (Plus Jakarta Sans, JetBrains Mono)

---

*Built for capstone / thesis projects. Clean MVC-inspired structure, beginner-friendly code.*
