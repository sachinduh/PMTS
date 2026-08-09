PMTS SMTP / PHPMailer Setup
===========================

This ZIP includes SMTP email sending for BEC and Specification Committee appointment letters.
Email status is saved in the SQL table committee_letters, and every attempt is also logged in email_send_logs.

1) Open this file:
   backend/config/smtp_config.php

2) Add real SMTP details. Gmail example:

   define('PMTS_SMTP_ENABLED', true);
   define('PMTS_SMTP_HOST', 'smtp.gmail.com');
   define('PMTS_SMTP_PORT', 587);
   define('PMTS_SMTP_SECURE', 'tls');
   define('PMTS_SMTP_AUTH', true);
   define('PMTS_SMTP_USERNAME', 'your-email@gmail.com');
   define('PMTS_SMTP_PASSWORD', 'your Gmail App Password');
   define('PMTS_MAIL_FROM_EMAIL', 'your-email@gmail.com');
   define('PMTS_MAIL_FROM_NAME', 'PMTS - Badulla Hospital');

Important: For Gmail, use an App Password. Do not use your normal Gmail password.

3) Enable PHP OpenSSL in XAMPP:
   - Open xampp/php/php.ini
   - Make sure this line is enabled:
     extension=openssl
   - Restart Apache after changing php.ini

4) Run this SQL migration in phpMyAdmin:
   database/smtp_ready_email_logs_and_status.sql

5) Test SMTP by sending a POST request to:
   backend/config/test_smtp_email.php

   JSON body:
   { "recipient_email": "your-test-email@gmail.com" }

6) To check SMTP status, open:
   backend/config/get_smtp_status.php

7) To check email results in SQL:

   SELECT member_name, member_email, email_status, sent_at, email_error
   FROM committee_letters
   ORDER BY id DESC;

   SELECT recipient_email, subject, email_status, sent_at, error_message, attempted_at
   FROM email_send_logs
   ORDER BY id DESC;

Notes
-----
- The project cannot send real email until your SMTP username/password are added.
- Do not share the project ZIP after adding a real SMTP password.
