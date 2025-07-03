# Rukindo Kweyamba Savings System - User Guide

## 1. Introduction

Welcome to the Rukindo Kweyamba Savings System! This guide will help you understand how to use the platform, whether you are a member or an administrator.

The system is designed to help manage savings, process loans, and maintain member records efficiently and transparently.

## 2. Getting Started

### 2.1. Accessing the System

You can access the system by navigating to the following URL in your web browser:
`http://your-domain.com/savingssystem/` (Replace `http://your-domain.com/savingssystem/` with the actual URL provided by your administrator, e.g., `http://localhost/savingssystem/`).

You will typically land on the `landing.php` page.

### 2.2. Account Registration (For New Members)

If you are a new member and self-registration is enabled:

1.  Navigate to the registration page. This is often linked from the landing page or accessible via a URL like `auth/register.html` or `members/register.php`.
2.  Fill in the required details:
    *   Full Name
    *   Email Address (this will be your username)
    *   Password (choose a strong one)
    *   Confirm Password
    *   Other details as requested (e.g., phone number, address).
3.  Submit the registration form.
4.  You may need to activate your account via an email sent to your registered email address. Check your inbox (and spam folder) for an activation link.

*If self-registration is not enabled, an administrator will create an account for you.*

### 2.3. Logging In

1.  Go to the login page, usually accessible from the landing page or directly via `auth/login.php`.
2.  Enter your **Email Address** and **Password**.
3.  Click the "Login" button.
4.  If you've forgotten your password, use the "Forgot Password?" link on the login page to initiate a password reset process (you'll receive an email with instructions).

Upon successful login, you will be redirected to your respective dashboard.

## 3. For Members

### 3.1. Member Dashboard Overview

After logging in, you'll be taken to your member dashboard (e.g., `members/my_savings.php` or a similar page). This page typically shows:

*   A summary of your savings.
*   Information about your active loans.
*   Navigation links to other member sections.

### 3.2. Managing Your Profile (`profile.php`)

*   You can view and update your personal information.
*   To change your password, there might be a separate option or it might be part of the profile edit page.
*   Keep your contact information up-to-date.

### 3.3. Savings Management

*   **Making a Deposit / Requesting Deposit (`members/request_deposit.php` or `savings/deposit.php`):**
    *   Navigate to the "Make Deposit" or "Request Deposit" section.
    *   Enter the amount you wish to deposit.
    *   You might need to upload proof of payment (e.g., a receipt screenshot).
    *   Submit the request. An administrator will then verify and approve the deposit.
*   **Viewing Savings Balance and History (`members/my_savings.php`, `savings/transactions.php`):**
    *   Your current savings balance is usually displayed on your dashboard.
    *   You can view a detailed list of all your savings transactions, including dates, amounts, and types (deposit, withdrawal if applicable).
*   **Savings Performance (`members/savings_performance.php`):**
    *   This section might show charts or summaries of your savings activity over time.

### 3.4. Loan Management

*   **Applying for a Loan (`loans/apply_loan.php` or `members/my_loans.php` then "Apply"):**
    *   Go to the "Apply for Loan" section.
    *   Fill out the loan application form, which may include:
        *   Loan amount requested.
        *   Reason for the loan.
        *   Proposed repayment period.
        *   Guarantor information (if required).
    *   Submit the application. It will be sent to administrators for review.
*   **Viewing Loan Status and Repayment Schedule (`members/my_loans.php`, `loans/viewloan.php`):**
    *   You can track the status of your loan applications (pending, approved, rejected).
    *   For approved loans, you can view the details, including the principal amount, interest, total payable, and the repayment schedule.
*   **Making Loan Repayments (`members/add_loan_repayment.php`):**
    *   Navigate to the loan repayment section.
    *   Select the loan you want to make a payment for.
    *   Enter the repayment amount.
    *   Provide proof of payment if required.
    *   Submit the repayment. Administrators will verify and update your loan balance.

### 3.5. Calendar (`usercalendar.php`)
*   This section may display important dates, payment reminders, or group events.

## 4. For Administrators

Upon logging in with an administrator account, you'll be directed to the Admin Dashboard (`index.php`).

### 4.1. Admin Dashboard Overview (`index.php`)

The admin dashboard provides:

*   **Key Statistics:** Total members, total savings, number of active loans, etc.
*   **Quick Actions:** Links to common administrative tasks like registering a new member, recording savings, or processing a loan.
*   **Recent Activity:** Possibly a list of recent transactions or loan applications.
*   **Navigation Menu (Sidebar):** Access to all administrative modules.

### 4.2. User Management

*   **Viewing and Managing Members (`admin/user_management/index.php`, `members/memberslist.php`):**
    *   List all registered users/members.
    *   View individual member details (`members/view.php`).
    *   Edit member information (`members/edit.php`).
    *   Activate/deactivate accounts.
    *   Convert a general user to a member (`admin/user_management/convert_member.php`).
*   **Managing User Details (`admin/user_management/manage_user_details.php`):**
    *   More specific user detail management.
*   **Roles and Permissions (Conceptual - `includes/rbac.php` suggests this exists):**
    *   The system uses roles (e.g., 'Core Admin', 'Administrator', 'Member'). While direct UI for managing roles/permissions isn't explicitly listed in filenames for admins, the `settings/` area might contain this.

### 4.3. Group Management (`admin/group_management/`)

*   **Creating and Managing Groups (`admin/group_management/index.php`):**
    *   If the system supports sub-groups within the savings scheme.
    *   Assign users to groups (`assign_users.php`).
    *   Edit (`edit_group.php`) or delete (`delete_group.php`) groups.

### 4.4. Loan Management (Admin Perspective)

*   **Loan Applications List (`admin/loans/loan_applications_list.php`):**
    *   View all pending loan applications.
*   **Viewing and Processing Loan Applications (`admin/loans/view_loan_application.php`):**
    *   Review application details.
    *   Approve, reject, or request more information for loan applications.
    *   Set loan terms if approved (e.g., interest rate, repayment schedule if not auto-calculated).
*   **Managing Existing Loans (`loans/loanslist.php` for all, then `loans/editloan.php` or `loans/viewloan.php`):**
    *   View all active and past loans.
    *   Record repayments made by members.
    *   Adjust loan details if necessary (with caution and proper authorization).
    *   Mark loans as fully paid.

### 4.5. Savings Management (Admin Perspective)

*   **Viewing All Savings (`savings/savingslist.php`):**
    *   Get an overview of all member savings.
    *   Verify and approve deposit requests made by members.
*   **Recording Savings (`savings/savings.php` or `savings/deposit.php`):**
    *   Manually record savings on behalf of members if needed.
*   **Viewing Transactions (`savings/transactions.php`):**
    *   See a global list of all savings transactions.
*   **Generating Receipts (`savings/generate_request_receipt.php`, `savings/printreceipt.php`):**
    *   Create and print receipts for savings transactions.

### 4.6. System Settings (`admin/system_settings/` and `settings/`)

*   **General Settings (`admin/system_settings/index.php`, `admin/system_settings/update_settings.php`):**
    *   Configure application name, currency, and other general parameters.
    *   Manage savings year (e.g., `start_savings_year.php`, `close_savings_year.php`).
*   The `settings/` directory (e.g. `settings/index.php`, `settings/general.php`) appears to be a newer or alternative settings area. Explore this for more configuration options related to:
    *   Dashboard settings (`settings/dashboard.php`)
    *   Group settings (`settings/groups.php`)
    *   User role and permission settings (`settings/permissions.php`, `settings/roles.php`, `settings/users.php`)

### 4.7. Reports (`reports.php`)

*   Generate various reports, such as:
    *   Member statements.
    *   Loan portfolio reports.
    *   Savings summaries.
    *   Transaction logs.
*   Reports are often downloadable as PDFs.

### 4.8. Calendar (`calendar.php`)
*   View and manage a system-wide calendar for events, deadlines, or important notices.

## 5. Troubleshooting/FAQ

*   **Q: I can't log in.**
    *   A: Double-check your email and password. Use the "Forgot Password?" link if needed. Ensure your account is activated. Contact an administrator if problems persist.
*   **Q: My deposit/repayment isn't reflecting.**
    *   A: Deposits and repayments often require administrator approval. Allow some time for verification. If it takes too long, contact an administrator with your transaction details.
*   **Q: How do I change my email address?**
    *   A: Check your profile page. If the option isn't available, you may need to request an administrator to update it for you, as it's often tied to your login.

## 6. Contact/Support

If you encounter issues or have questions not covered in this guide, please contact your system administrator or the designated support person for the Rukindo Kweyamba Savings System.
Provide as much detail as possible about your issue, including screenshots if helpful.
