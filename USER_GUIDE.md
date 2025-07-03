# Rukindo Kweyamba Savings System - User Guide

## 1. Introduction

Welcome to the Rukindo Kweyamba Savings System! This comprehensive guide will help you understand how to use the platform effectively, whether you are a member or an administrator.

The system is designed to help manage savings, process loans, maintain member records, and provide detailed reporting for transparent financial management.

### 1.1 System Features Overview

- **Member Management**: Registration, profile management, and member tracking
- **Savings Management**: Deposit requests, savings tracking, and performance analytics
- **Loan Management**: Loan applications, approvals, repayment tracking
- **Financial Reports**: Comprehensive reporting with export capabilities
- **Dashboard Analytics**: Real-time statistics and visual charts
- **Security**: Role-based access control and secure authentication
- **Notifications**: Email notifications for important events
- **Calendar**: Event and reminder management

## 2. Getting Started

### 2.1. System Requirements

**Server Requirements:**
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PDO PHP extension enabled

**Client Requirements:**
- Modern web browser (Chrome, Firefox, Safari, Edge, brave etc)
- Apache server and MySQL running (XAMPP)
-Internet connection(In the next future when the system goes live)
- JavaScript enabled

### 2.2. Accessing the System

The system runs on a web server with the following access points:

**Local Development:**
- URL: `http://localhost/savingssystem/`
- Landing page: `landing.php`

**Production Environment:**
- URL: `domain name not secured yet`
- 

### 2.3. Account Registration (For New Members)

**Registration Process:**
1. Registration is currently done by only admnistrators
Navigate to the registration page (`members/register.php`)
2. Fill in required details:
   - Full Name
   - Email Address (will be your username)
   - Phone Number
   - Password (minimum 8 characters)
   - Confirm Password
   - National ID Number (NIN)
   - Address Information
3. Submit the registration form
4. Registered member should check email email for activation instructions
5. Click the activation link to activate your account

**Administrator Registration:**
- If self-registration is disabled, contact an administrator
- Admin will create your account and provide login credentials

### 2.4. Logging In

1. Go to the login page (`auth/login.php`)
2. Enter your **Email Address** and **Password**
3. Click "Login"
4. For forgotten passwords, use "Forgot Password?" link
5. Follow email instructions to reset your password

**First-Time Login:**
- Change your default password immediately
- Update your profile information
- Review system notifications

## 3. For Members

### 3.1. Member Dashboard Overview

**Dashboard Features:**
- **Savings Summary**: Current balance, recent transactions
- **Loan Overview**: Active loans, repayment schedules
- **Quick Actions**: Request deposit, make repayment, view statements
- **Notifications**: Important system messages
- **Performance Charts**: Savings growth visualization

**Navigation Menu:**
- My Savings
- My Loans
- Profile
- Calendar
- Reports

### 3.2. Profile Management (`profile.php`)

**Profile Features:**
- **Personal Information**: Name, email, phone, address
- **Profile Photo**: Upload and manage profile picture
- **Password Change**: Secure password updates
- **Contact Preferences**: Notification settings
- **Security Settings**: Two-factor authentication (if enabled)

**Updating Profile:**
1. Navigate to Profile section
2. Click "Edit Profile"
3. Update required fields
4. Save changes
5. Confirm updates via email if required

### 3.3. Savings Management

#### 3.3.1 Making Deposits (`members/request_deposit.php`)

**Deposit Process:**
1. Navigate to "Request Deposit"
2. Enter deposit amount
3. Select payment method
4. Upload proof of payment (receipt/screenshot)
5. Add transaction notes (optional)
6. Submit request for admin approval


#### 3.3.2 Viewing Savings (`members/my_savings.php`)

**Savings Dashboard:**
- **Current Balance**: Real-time savings balance
- **Transaction History**: Detailed transaction log
- **Monthly Performance**: Savings growth charts

**Transaction Details:**
- Date and time
- Amount
- Transaction type
- Status (pending, approved, rejected)
- Reference number
- Supporting documents

#### 3.3.3 Savings Performance (`members/savings_performance.php`)

**Performance Metrics:**
- Monthly savings trends
- Year-over-year growth
- Average monthly deposits

**Visual Analytics:**
- Line charts for trends
- Bar charts for comparisons
- Pie charts for distribution
- Progress bars for goals

### 3.4. Loan Management

#### 3.4.1 Applying for Loans (`loans/apply_loan.php`)

**Application Process:**
1. Navigate to "Apply for Loan"
2. Fill out application form:
   - Loan amount requested
   - Loan purpose
   - Proposed repayment period
   - Guarantor information
   - Supporting documents
3. Review terms and conditions
4. Submit application
5. Track application status

**Required Documents:**
- Loan application form
- Proof of income
- Guarantor details
- Collateral information (if applicable)

#### 3.4.2 Loan Tracking (`members/my_loans.php`)

**Loan Dashboard:**
- **Active Loans**: Current loan details
- **Loan History**: Past loan records
- **Repayment Schedule**: Upcoming payments
- **Outstanding Balance**: Current amounts due
- **Interest Calculations**: Interest accrued

**Loan Status Types:**
- Pending: Under review
- Approved: Loan approved, funds disbursed
- Active: Loan being repaid
- Completed: Fully paid
- Defaulted: Overdue payments
- Rejected: Loan not approved

#### 3.4.3 Loan Repayments (`members/add_loan_repayment.php`)

**Repayment Process:**
1. Select loan to repay
2. Enter repayment amount
3. Choose payment method
4. Upload proof of payment
5. Submit for verification
6. Track repayment status

**Repayment Options:**
- Full payment
- Partial payment
- Minimum payment
- Extra payment

### 3.5. Reports and Statements

**Available Reports:**
- **Savings Report**: Detailed savings transaction history
- **Loan Report**: Loan details and repayment history
- **Savings Summary**: Monthly/yearly savings reports
- **Members report**: Information about registered members
**Export Options:**
- PDF format
- Excel format
- CSV format
- Print-friendly version

### 3.6. Calendar (`usercalendar.php`)

**Calendar Features:**
- **Payment Reminders**: Loan repayment due dates
- **Group Events**: Meeting schedules
- **System Maintenance**: Planned downtime
- **Important Dates**: Deadlines and milestones

## 4. For Administrators

### 4.1. Admin Dashboard Overview (`index.php`)

**Dashboard Statistics:**
- Total members count
- Total savings amount
- Active loans count
- Pending applications
- System health metrics

**Quick Actions:**
- Register new member
- Process loan applications
- Approve deposit requests
- Generate reports
- System settings

**Visual Analytics:**
- Monthly savings trends
- Top saving members
- Loan portfolio distribution
- Member activity charts

### 4.2. Member Management

#### 4.2.1 Member Registration (`members/register.php`)

**Admin Registration:**
1. Navigate to "Add Member"
2. Fill member details:
   - Personal information
   - Contact details
   - Initial savings amount
   - Member category
3. Generate member ID
4. Send welcome email
5. Print member card

#### 4.2.2 Member Management (`members/memberslist.php`)

**Member Operations:**
- **View Member**: Detailed member profile
- **Edit Member**: Update member information
- **Activate/Deactivate**: Account status management
- **Convert User**: Convert user to member
- **Delete Member**: Remove member (with restrictions)

**Member Search and Filter:**
- Search by name, email, or member ID
- Filter by status, date joined, or group
- Sort by various criteria
- Export member list

### 4.3. Savings Management (Admin)

#### 4.3.1 Deposit Approval (`savings/savingslist.php`)

**Approval Process:**
1. Review deposit requests
2. Verify supporting documents
3. Check payment proof
4. Approve or reject request
5. Send notification to member
6. Update member balance

**Batch Processing:**
- Approve multiple deposits
- Bulk import from CSV

#### 4.3.2 Savings Transactions (`savings/transactions.php`)

**Transaction Management:**
- View all transactions
- Filter by date, member, or type
- Edit transaction details
- Reverse transactions (with authorization)
- Generate transaction reports

### 4.4. Loan Management (Admin)

#### 4.4.1 Loan Applications (`admin/loans/loan_applications_list.php`)

**Application Review:**
1. Review application details
2. Verify member eligibility
3. Check guarantor information
4. Calculate loan terms
5. Approve, reject, or request more info
6. Set repayment schedule

**Loan Evaluation Criteria:**
- Member savings history
- Previous loan performance
- Guarantor verification
- Loan-to-savings ratio
- Risk assessment

#### 4.4.2 Loan Portfolio (`loans/loanslist.php`)

**Portfolio Management:**
- View all loans by status
- Track repayment performance
- Identify overdue loans
- Generate portfolio reports
- Risk analysis and monitoring

**Loan Operations:**
- Restructure loans
- Waive penalties (with authorization)
- Write-off bad loans
- Transfer loans between members

### 4.5. Financial Reports (`reports.php`)

**Report Types:**
- **Member Statements**: Individual member reports
- **Loan Reports**: Loan portfolio analysis
- **Savings Reports**: Savings performance summaries
- **Financial Statements**: Balance sheets, P&L
- **Audit Reports**: Transaction audit trails

**Report Features:**
- Date range selection
- Member filtering
- Status filtering
- Export to PDF/Excel
- Automated report scheduling
- Email distribution

### 4.6. System Settings

#### 4.6.1 General Settings (`admin/system_settings/`)

**System Configuration:**
- Application name and logo
- Currency settings
- Interest rate settings
- Loan parameters
- Email configuration
- Backup settings

#### 4.6.2 User Management (`settings/users.php`)

**User Administration:**
- Create administrator accounts
- Manage user roles and permissions
- Reset user passwords
- Lock/unlock accounts
- View user activity logs

#### 4.6.3 Group Management (`admin/group_management/`)

**Group Features:**
- Create member groups
- Assign users to groups
- Set group-specific settings
- Group performance tracking
- Group reporting

### 4.7. System Maintenance

**Maintenance Tasks:**
- **Database Backup**: Regular  backups
- **System Updates**: Software updates and patches
- **Performance Monitoring**: System health checks
- **Security Audits**: Regular security assessments
- **Data Cleanup**: Archive old data

**Monitoring Features:**
- System performance metrics
- Error logging and monitoring
- User activity tracking
- Security event logging

## 5. Security Features

### 5.1. Authentication

**Security Measures:**
- Strong password requirements
- Account lockout after failed attempts
- Session timeout management
- Two-factor authentication (optional)
- Password reset security

### 5.2. Data Protection

**Privacy Features:**
- Data encryption in transit and at rest
- Regular security backups
- Access logging and monitoring
- GDPR compliance features
- Data retention policies

### 5.3. Role-Based Access Control

**User Roles:**
- **Core Admin**: Full system access
- **Administrator**: Administrative functions
- **Member**: Limited member functions
- **Viewer**: Read-only access of the landing page

**Permission System:**
- Granular permission control
- Role-based feature access

## 6. Mobile and Browser Support

### 6.1. Browser Compatibility

**Supported Browsers:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Opera 76+
- etc

### 6.2. Mobile Responsiveness

**Mobile Features:**
- Responsive design for all screen sizes
- Touch-friendly interface
- Mobile-optimized forms
- Fast loading on mobile networks
- Offline capability (limited)

## 7. Troubleshooting

### 7.1. Common Issues

**Login Problems:**
- Check email and password
- Clear browser cache
- Verify account activation
- Check internet connection
- Contact administrator if locked out

**Transaction Issues:**
- Allow time for admin approval
- Check supporting documents
- Verify payment proof
- Contact support with transaction details

**System Performance:**
- Clear browser cache
- Check internet speed
- Try different browser
- Report persistent issues

### 7.2. Error Messages

**Common Error Messages:**
- "Invalid username or password." : Using incorrect user name or password
- "Session expired": Log in again
- "Access denied": Check user permissions
- "Database error": Contact administrator
- "File upload error": Check file size and format
- "Invalid data": Review form inputs

### 7.3. Getting Help

**Support Channels:**
- System administrator contact
- User manual and documentation
- FAQ section
- Email support

**When Contacting Support:**
- Provide detailed error description
- Include screenshots if helpful
- Mention browser and device used
- Provide transaction reference numbers
- Describe steps taken before error

## 8. Best Practices

### 8.1. For Members

**Security Best Practices:**
- Use strong, unique passwords
- Log out after each session
- Don't share login credentials
- Report suspicious activity
- Keep contact information updated

**Financial Best Practices:**
- Make regular savings deposits
- Keep payment receipts
- Monitor account statements
- Pay loans on time

### 8.2. For Administrators

**Management Best Practices:**
- Regular system backups
- Monitor system performance
- Review user access regularly
- Keep software updated
- Maintain audit trails

**Financial Best Practices:**
- Regular financial reconciliation
- Monitor loan portfolio health
- Review interest rates regularly
- Maintain adequate reserves
- Generate regular reports

## 9. System Limitations

### 9.1. Current Limitations

**Technical Limitations:**
- Local server deployment only
- Manual backup process
- Limited mobile app features
- Single currency support
- Basic reporting templates

**Functional Limitations:**
- No automated loan scoring
- Limited integration options
- Basic notification system
- Manual approval processes
- Limited customization options

### 9.2. Future Enhancements

**Planned Features:**
- Mobile application
- Advanced reporting
- Automated notifications
- Integration with banks and mobile money
- Multi-currency support
- Advanced analytics

## 10. Appendices

### Appendix A: System Requirements
- Detailed technical specifications
- Installation requirements
- Configuration guidelines

### Appendix B: API Documentation
- Available API endpoints
- Authentication methods
- Data formats

### Appendix C: Database Schema
- Table structures
- Relationships
- Data types

### Appendix D: Change Log
- Version history
- Feature updates
- Bug fixes

---

**Document Version**: 2.0  
**Last Updated**: August 03, 2025  
**Contact**: System Administrator  
**Support Email**: isaacvital44@gmail.com
contact us incase of any issue not working as expected or notes in this user manual guide
thank you!
we are the nerds!
## copyrights holders
1. Isaac Mukonyezi
2. Bakaruba Anold
3. Kyogabire Lucky
4. Muwanika Eric
5. Masinde Doreen
6. UICT Mgt 
