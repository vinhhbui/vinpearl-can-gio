# Vinpearl Can Gio - Hotel Management & Booking System

A comprehensive hotel management and online booking system, built primarily with **PHP** and **MySQL**, designed to simulate and optimize operational workflows for the Vinpearl Can Gio resort.

---

## Overall

**Vinpearl Can Gio** is a web-based software solution developed to digitize and automate core processes in hotel and resort business management. This project not only provides a seamless, user-friendly interface for customers to book rooms but also delivers a powerful admin dashboard for staff and management to oversee daily operations.

**Core Objectives and System Values:**
1. **Reservation Management:** Delivers a smooth booking flow—from checking room availability and selecting check-in/check-out dates to confirming customer details. The system automatically updates room statuses in real-time to prevent overbooking.
2. **Centralized Database:** All information regarding room types, amenities, transaction history, and customer data is structurally stored via `hotel_db.sql`. This ensures easy data retrieval, streamlined statistical reporting, and robust data integrity.
3. **Authentication & Authorization:** Strictly separates access levels between regular visitors (Guests) and the management team (Admin/Staff). This guarantees that sensitive operations, such as modifying room rates or accessing revenue reports, are restricted to authorized personnel only.
4. **Performance & Scalability:** Developed using PHP, the project's architecture is designed for easy maintenance and future scalability. Additional modules (e.g., online payment gateways, HR management) can be integrated seamlessly. Detailed business logic and system architecture specifications are thoroughly documented in the `System Design Document.docx` file.

---

## Repository Structure

The project is organized with the following main directories and files:

```text
vinpearl-can-gio/
├── source/         # Contains all PHP, HTML, CSS, JS source code and web assets
├── .gitignore      # Specifies intentionally untracked files to ignore (e.g., vendor, config files with passwords)
├── README.md       # Project documentation (this file)
├── doc.docx        # System design analysis and Software Requirements Specification (SRS)
└── hotel_db.sql    # MySQL database dump file (contains table structures and sample data)

```

---

## Tech Stack

* **Back-end:** PHP
* **Database:** MySQL / MariaDB
* **Front-end:** HTML5, CSS3, JavaScript (customized within the `source` directory)
* **Recommended Environment:** LAMP Stack (Linux/Ubuntu, Apache, MySQL, PHP) or XAMPP/MAMP.

---

## Installation & Setup

To run this project on your local environment (e.g., an Ubuntu 24.04 LTS machine or any OS supporting a Web Server), follow these steps:

### Step 1: Clone the Repository

Open your terminal and clone the source code:

```bash
git clone [https://github.com/vinhhbui/vinpearl-can-gio.git](https://github.com/vinhhbui/vinpearl-can-gio.git)
cd vinpearl-can-gio

```

### Step 2: Database Setup

1. Log in to your MySQL server:
```bash
mysql -u root -p

```


2. Create a new database for the project:
```sql
CREATE DATABASE vinpearl_hotel;
USE vinpearl_hotel;

```


3. Import the existing data from the SQL file:
```bash
mysql -u root -p vinpearl_hotel < hotel_db.sql

```



### Step 3: Web Server Configuration

* **If using XAMPP:** Copy the `source` directory into your `htdocs` folder.
* **If using Apache on Linux/Ubuntu:** Copy the `source` directory to `/var/www/html/` and rename it to `vinpearl` for easier access:
```bash
sudo cp -r source/ /var/www/html/vinpearl

```



### Step 4: Configure Database Connection

Open the database connection configuration file inside the `source/` directory (typically named `config.php`, `db.php`, or `connect.php`) and update the credentials to match your local environment:

```php
<?php
$host = "localhost";
$username = "root";
$password = "your_password"; // Leave blank if using default XAMPP settings
$database = "vinpearl_hotel";
// ...
?>

```

### Step 5: Run the Application

Open your web browser and navigate to:

* **Guest Portal:** `http://localhost/vinpearl`
* **Admin Dashboard:** `http://localhost/vinpearl/admin` *(The exact path may vary depending on your routing structure)*.

---

## Author

* **Vic** - [@vinhhbui](https://www.google.com/search?q=https://github.com/vinhhbui)

---

*If you encounter any issues during the setup process, please feel free to open an **Issue** on this repository.*
