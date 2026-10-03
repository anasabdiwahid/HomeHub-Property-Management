# HomeHub Property Management System (Mogadishu, Somalia)

A modern, comprehensive Property Management and Real Estate Rental platform built using **PHP 8+**, **MySQL**, **Bootstrap 5**, **HTML5**, **CSS3**, and **JavaScript**. Specifically tailored for property operations across the districts of **Mogadishu** (Hodan, Wadajir, Waaberi, Cabdicasiis, Darusalaam, etc.) with support for mobile payments (EVC Plus, Zaad, Sahal, Bank Transfer).

---

## 🌟 Key Features

### 🔐 Authentication & Role-Based Access Control
* **Single Login Page (`login.php`)**: Auto-detects user role and redirects accordingly:
  * **Admin** &rarr; Admin Dashboard (`admin/index.php`)
  * **Manager** &rarr; Manager Dashboard (`manager/index.php`)
  * **User (Tenant)** &rarr; User Dashboard (`user/index.php`)
* **Tenant Registration (`register.php`)**: Prospective tenants can self-register.
* **Security**: Bcrypt password hashing, PDO prepared statements (SQL injection protection), CSRF token validation, XSS prevention.

### 👑 Admin Panel (`admin/`)
* **Dashboard with 6 Key Statistics Cards**:
  1. Total Houses
  2. Total Occupied Apartments
  3. Total Vacant Apartments
  4. Total Managers
  5. Total Users
  6. Total Monthly Revenue
* **Interactive Charts**: Last 6 months collection trend & occupancy status doughnut chart.
* **Sidebar Navigation**:
  * Dashboard
  * Houses (List, Add, Edit, Delete, Photos)
  * Categories (Apartments, Villas, Offices, Studios)
  * Managers (Create, oversee, toggle active status, assign houses)
  * Users / Tenants (Manage tenants, toggle status)
  * Rental Requests (Approve/Reject tenant leasing applications)
  * Revenue & Rent (Record and track collections with receipt references)
  * Reports (Revenue, Occupancy, Collections, Vacancy with PDF & Excel export)
  * Settings (Branding, currency symbol, support contact details)
  * Logout

### 👔 Manager Panel (`manager/`)
* **Dashboard**:
  * My Houses (Assigned properties)
  * Occupied Apartments
  * Vacant Apartments
  * Monthly Revenue Collections
* **Sidebar Navigation**:
  * Dashboard
  * My Houses (Inspect assigned houses, update unit occupancy status, rates, and amenities)
  * Rental Requests (Review and approve/reject applications for assigned houses)
  * Rent Tracking (Record tenant rent collections via EVC Plus, Zaad, Sahal, Bank, Cash)
  * Reports (Assigned Houses, Occupancy Report, Revenue Report with PDF & Excel export)
  * Profile (Account settings & password update)
  * Logout

### 🏠 User / Tenant Panel (`user/`)
* **Top Navigation Only (No Sidebar as specified)**:
  * Available Houses (Search by keyword, filter by City, Category, Max Price, Vacancy)
  * View Details (Comprehensive photos, amenities, manager contact)
  * Rent House Request (Modal/Form with preferred move-in date and notes)
  * My Rental Requests (Track request status: Pending, Approved, Rejected)
  * Approved Rentals (Active leases, manager contact, rent payment receipts)
  * Profile (Update personal details, phone, password)

### 🌓 Modern UI & Design
* **Light & Dark Mode**: Persistent theme toggle powered by Bootstrap 5 and custom CSS variables.
* **Responsive**: Seamless experience on Mobile, Tablet, and Desktop.
* **Exporting**: Print / PDF views and one-click Excel (.CSV) exports.

---

## 🔑 Default Credentials

| Role | Email | Password | Access Dashboard |
|---|---|---|---|
| **Admin** | `admin@homehub.so` | `admin123` | `http://localhost/Home hub/admin/` |
| **Manager** | `manager@homehub.so` | `manager123` | `http://localhost/Home hub/manager/` |
| **Manager 2** | `farah@homehub.so` | `manager123` | `http://localhost/Home hub/manager/` |
| **Tenant** | `user@homehub.so` | `user123` | `http://localhost/Home hub/user/` |
| **Tenant 2** | `hodan@homehub.so` | `user123` | `http://localhost/Home hub/user/` |

*(Quick demo buttons are also embedded on the login page for 1-click test credential filling!)*

---

## 📂 Project Architecture

```
Home hub/
├── admin/                     # Administrator controllers & views
│   ├── categories.php         # Categories management
│   ├── export.php             # Server-side CSV exports
│   ├── houses.php             # Houses listing & filters
│   ├── house_add.php          # Add house form
│   ├── house_delete.php       # House deletion handler
│   ├── house_edit.php         # Edit house form
│   ├── index.php              # Admin Dashboard (6 Stats Cards + Charts)
│   ├── managers.php           # Property managers management
│   ├── payments.php           # Rent payments & revenue
│   ├── rental_requests.php    # Review leasing applications
│   ├── reports.php            # 4 Admin Reports (Revenue, Occupancy, Collections, Vacant)
│   └── settings.php           # System configuration
├── manager/                   # Property Manager controllers & views
│   ├── export.php             # Manager CSV exports
│   ├── houses.php             # Assigned houses
│   ├── house_edit.php         # Update house info & apartment status
│   ├── index.php              # Manager Dashboard (4 Stats Cards)
│   ├── payments.php           # Rent tracking & collections
│   ├── profile.php            # Manager profile & credentials
│   ├── rental_requests.php    # Applications review for assigned properties
│   └── reports.php            # 3 Manager Reports (Assigned, Occupancy, Revenue)
├── user/                      # Tenant controllers & views (Top Nav Only)
│   ├── approved_rentals.php   # Approved leases & rent receipts
│   ├── house_details.php      # Full property view & inquiry form
│   ├── index.php              # User Dashboard & Available houses search
│   ├── my_requests.php        # Application status & tracking
│   ├── profile.php            # Tenant profile
│   └── rent_request.php       # Application submission handler
├── assets/
│   ├── css/
│   │   ├── style.css          # Custom styling & elevations
│   │   └── dark-mode.css      # Dark mode color definitions
│   ├── js/
│   │   └── main.js            # Theme controller, CSV exporter, alerts
│   └── images/
├── config/
│   ├── config.php             # App constants, BASE_URL & CSRF
│   └── database.php           # PDO MySQL connection
├── database/
│   └── schema.sql             # MySQL schema & initial seed data
├── includes/
│   ├── admin_sidebar.php      # Admin sidebar navigation
│   ├── auth.php               # RBAC & authentication helpers
│   ├── footer.php             # Global scripts & footer
│   ├── functions.php          # Helpers, sanitization & flash messages
│   ├── header.php             # Global HTML head & stylesheets
│   ├── manager_sidebar.php    # Manager sidebar navigation
│   ├── topbar.php             # Top navigation bar for admin & manager
│   └── user_navbar.php        # User top navigation (No Sidebar)
├── uploads/
│   └── houses/                # Uploaded property images
├── .htaccess                  # Apache mod_rewrite & upload security
├── index.php                  # Public landing page with featured listings
├── login.php                  # Single unified login page
├── logout.php                 # Safe session destruction
└── register.php               # Tenant registration page
```

---

## 🛠️ Database Setup

The database schema and seed data are located in `database/schema.sql`.

If you need to re-import it on another machine or production cPanel server:
```bash
mysql -u root -p homehub_db < database/schema.sql
```
Or import `database/schema.sql` via **phpMyAdmin**.
