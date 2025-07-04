# Rukindo Kweyamba Savings System

A comprehensive PHP-based web application designed to manage savings, loans, and member activities for a savings group.

## Features

*   **User Management:**
    *   Member registration and login.
    *   Admin and Member roles with distinct permissions.
    *   User profile management.
*   **Savings Management:**
    *   Recording member savings deposits.
    *   Viewing individual savings balances and transaction history.
    *   Admin overview of total group savings.
*   **Loan Management:**
    *   Members can apply for loans.
    *   Admins can review, approve, or reject loan applications.
    *   Tracking loan repayment schedules and statuses.
    *   Viewing loan history.
*   **Admin Panel:**
    *   Dashboard with key statistics (total members, total savings, active loans).
    *   Comprehensive user management interface.
    *   Group management capabilities.
    *   System settings configuration (e.g., managing savings year).
*   **Reporting:**
    *   Generation of PDF reports for savings, loans, etc. (using mpdf/dompdf).
*   **Email Notifications:**
    *   Automated email notifications for important events (e.g., account activation, loan status updates - using PHPMailer).
*   **Security:**
    *   Password hashing.
    *   CSRF protection.
    *   Secure session management.

## Technologies Used

*   **Backend:** PHP
*   **Database:** MySQL
*   **Frontend:** HTML, CSS, JavaScript, Bootstrap
*   **PHP Libraries:**
    *   [mpdf/mpdf](https://github.com/mpdf/mpdf): For PDF generation.
    *   [PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer): For sending emails.
    *   [dompdf/dompdf](https://github.com/dompdf/dompdf): Alternative for PDF generation.
    *   Composer for dependency management.

## Setup Instructions

### Prerequisites

*   Web Server (Apache, Nginx, or similar) with PHP support (PHP 7.4+ recommended).
*   MySQL Database server (5.7+ or MariaDB 10.2+).
*   Composer (for managing PHP dependencies).
*   Git (for cloning the repository).

### Installation Steps

1.  **Clone the Repository:**
    ```bash
    git clone <repository-url>
    cd savingssystem # Or your project directory name
    ```

2.  **Install PHP Dependencies:**
    ```bash
    composer install
    ```

3.  **Database Setup:**
    *   Create a MySQL database (e.g., `savings_mgt_systemdb` as per `config.php`).
    *   Import the initial database schema. This might involve executing SQL files like `migrations/0_setup_new_tables.php` or following a specific migration procedure if the project uses a migration tool (though this project appears to use individual scripts). If `0_setup_new_tables.php` is the primary schema file, execute its SQL content in your database.
    *   Configure the database connection details in `config.php` if they differ from the defaults:
        ```php
        define('DB_HOST', '127.0.0.1');
        define('DB_NAME', 'savings_mgt_systemdb');
        define('DB_USER', 'root');
        define('DB_PASS', ''); // Default is empty password for root
        define('DB_PORT', '3306');
        ```

4.  **Create Admin User:**
    *   The recommended way to create an initial admin user is by using the script in the `scripts/` folder:
        ```bash
        php scripts/create_core_admin.php YourAdminUsername YourAdminEmail YourAdminPassword
        ```
    *   Alternatively, `admin_setup.sql` provides a template if you prefer manual SQL execution, but you'll need to generate a password hash first. Use PHP's `password_hash()` function:
        ```php
        // Example: php -a
        // echo password_hash('yourChosenSecurePassword', PASSWORD_DEFAULT);
        ```
        Then, insert into the appropriate users table (likely `users`, not `admins` as `admin_setup.sql` suggests, check `create_core_admin.php` for the correct table and columns).

5.  **Configure Application URL:**
    *   Ensure `BASE_URL` in `config.php` is correctly set for your environment:
        ```php
        define('BASE_URL', 'http://localhost/savingssystem/'); // Adjust if your project is in a subfolder
        ```

6.  **Web Server Configuration (Basic):**
    *   Set your web server's document root to the project's root directory (where `index.php` and `landing.php` are located).
    *   For Apache, ensure `mod_rewrite` is enabled.

7.  **Directory Permissions:**
    *   The application needs write access to certain directories.
    *   Ensure the web server user (e.g., `www-data`, `apache`, `nginx`) has write permissions for:
        *   `assets/uploads/` (and its subdirectories like `savings_proofs/`)
        *   `tmp/sessions/` (as indicated by `make_sessions_folder.php`)
    ```bash
    # Example commands (run from project root):
    sudo chmod -R 775 assets/uploads/ tmp/
    sudo chown -R www-data:www-data assets/uploads/ tmp/ # Replace www-data:www-data with your web server's user:group
    ```
    *   You might need to run `php make_sessions_folder.php` if the `tmp/sessions` directory doesn't exist.

8.  **Access the Application:**
    *   Open your web browser and navigate to the `BASE_URL` (e.g., `http://localhost/savingssystem/landing.php` for the public page or `http://localhost/savingssystem/` for the admin/member login).

## Directory Structure Overview

```
.
├── admin/            # Admin panel specific PHP files and functionalities
├── admin_setup.sql   # SQL script for setting up an initial admin (use scripts/create_core_admin.php instead)
├── assets/           # Static assets (CSS, JS, images, uploaded files)
│   ├── css/
│   ├── js/
│   └── uploads/      # Directory for user-uploaded files
├── auth/             # Authentication scripts (login, logout, registration, password reset)
├── composer.json     # PHP project dependencies for Composer
├── composer.lock     # Records exact versions of installed dependencies
├── config.php        # Core application configuration (DB credentials, paths, constants)
├── create-admin.php  # (Likely deprecated by scripts/create_core_admin.php)
├── emails/           # Email templates
├── helpers/          # Helper functions (e.g., for authentication, loans)
├── includes/         # Shared PHP files (e.g., database connection setup, common functions, RBAC logic)
├── index.php         # Main entry point for logged-in admin/privileged users (dashboard)
├── landing.php       # Public landing page for users not logged in
├── loans/            # Modules related to loan management
├── make_sessions_folder.php # Script to create the session storage directory
├── members/          # Modules for member-specific functionalities
├── migrations/       # Database migration scripts
├── partials/         # Reusable HTML/PHP snippets (navbar, sidebar, footer)
├── paths.php         # (Potentially defines more path constants)
├── profile.php       # User profile page
├── reports.php       # Script for generating reports
├── savings/          # Modules related to savings management
├── scripts/          # Utility scripts (e.g., creating core admin user)
├── settings/         # User and system settings management
├── tmp/              # Temporary files
│   └── sessions/     # Session storage
├── vendor/           # Directory where Composer installs dependencies
└── ... (other specific files and directories)
```

## Contribution Guidelines

*   (To be defined by project maintainers - e.g., coding standards, branch strategy, pull request process).

## License

*   (No `LICENSE` file found. It's recommended to add one, e.g., MIT, GPL, Apache 2.0).
