PMTS backend query folder
=========================

SQL used by the auth/user security flow has been moved here so endpoint PHP
files do not contain large inline SELECT / INSERT / UPDATE statements.

Main file:
- user_queries.php

Used by:
- backend/auth/login.php
- backend/auth/register.php
- backend/auth/create_first_admin.php
- backend/config/auth.php

This keeps the endpoint files cleaner and makes security-related database logic
easier to review and update.
