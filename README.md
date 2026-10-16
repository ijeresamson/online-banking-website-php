# Online Banking System

A web-based Online Banking System developed using PHP, MySQL, HTML, CSS, and JavaScript. 
This project demonstrates how an online banking platform can manage customer accounts 
and provide basic banking operations through a web interface.

## Features
1. Customer Account Management
- Customer registration and account creation
- Customer login and logout
- Customer dashboard
- Personal information and profile management
- View account details and account number
- View account balance
- View account status
- Update email address, phone number, and residential address
- Change password
- Forgot password and password reset
- Email verification
- Account activation and deactivation
- Profile picture upload
- View account activity
- 
2. Money Transfer and Payments
- Transfer money between accounts within the system
- Send money to another customer
- Transfer funds to external bank accounts, if supported
- Internal and external transfer forms
- Beneficiary management
- Add, edit, and delete beneficiaries
- Save frequently used beneficiaries
- Transfer confirmation page
- Transaction PIN setup
- Transaction PIN verification
- Change or reset transaction PIN
- Validate available balance before transfers
- Transfer limits
- Transfer fees and charges
- Transfer processing status
- Transfer success and failure messages
- Transaction reference number
- Transfer receipts
- Transfer history
  
3. Transactions and Account Statements
- View transaction history
- View deposits and withdrawals
- View incoming and outgoing transfers
- View transaction dates and amounts
- View transaction status
- Search transactions by reference number
- Filter transactions by date, type, or status
- View transaction details
- Generate account statements
- Download or print statements
- Download or print transaction receipts
- Track pending, successful, and failed transactions
- Maintain transaction records
  
4. Deposits and Withdrawals
- Deposit funds into an account
- Withdrawal requests
- Cash withdrawal tracking
- Deposit history
- Withdrawal history
- Deposit confirmation
- Withdrawal processing status
- Admin approval of deposits or withdrawals, where applicable
  
Note: Real deposits and withdrawals require a properly integrated payment provider, banking partner, or verified funding process. A form that simply increases the database balance is only a simulation.

5. Cards and Account Services
- Debit card request
- Card details and status display
- Card activation and deactivation
- Freeze or block a card
- Card replacement request
- Card transaction history
- Card spending limits
- Request a new bank account
- Manage multiple accounts
- Savings account management
- Account upgrade requests
- Cheque book request, if supported
  
6. Beneficiaries and Saved Accounts
- Add a beneficiary
- Save beneficiary bank details
- Edit beneficiary information
- Delete saved beneficiaries
- Select saved beneficiaries during transfers
- Validate beneficiary account details
- View beneficiary transaction history

6. Notifications and Communication
- Account registration notifications
- Email verification messages
- Password reset emails
- Successful transfer notifications
- Deposit and withdrawal notifications
- Login alerts
- Low-balance alerts
- Transaction status notifications
- In-app notifications
- Notification history
- Mark notifications as read
- Contact form
- Customer support requests
- Frequently asked questions (FAQ)
- 
10. Security Features
- Secure password hashing
- Session-based authentication
- Role-based access control
- Customer and administrator access separation
- Transaction PIN verification
- Password change and reset
- Account lockout after repeated failed login attempts
- Login activity tracking
- Session timeout
- Input validation and output escaping
- Protection against SQL injection using PDO prepared statements
- Cross-site request forgery (CSRF) protection
- Secure file upload validation
- HTTPS support
- Sensitive information protection
- Audit logs for important account activities

## Technologies Used
- **Frontend:** HTML, CSS, JavaScript, Bootstrap
- **Backend:** PHP
- **Database:** MySQL
- **Development Environment:** XAMPP and phpMyAdmin

## Installation
1. Clone or download this repository.
2. Move the project folder into your XAMPP `htdocs` directory.
3. Create a MySQL database using phpMyAdmin.
4. Import the project's SQL database file.
5. Update the database connection settings in the configuration file.
6. Start Apache and MySQL in XAMPP.
7. Open the project in your browser using `http://localhost/your-project-folder/`.

## Disclaimer
This project is intended for learning and portfolio demonstration. It is not a production-ready banking application and should not be used to manage real money or sensitive financial information without proper security reviews and testing.

💻 **Need a Professional Website or Web Application?**

Are you looking to build a banking website, business website, e-commerce store, or any other type of web project? I can help turn your ideas into a functional website tailored to your needs.

📩 **Contact me today:**

📱 Phone/WhatsApp: +234 903 396 3157  
📧 Email: ijereifeanyi5@gmail.com

Let’s bring your ideas to life! 🚀
