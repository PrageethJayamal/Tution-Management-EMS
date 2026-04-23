# Core Security and Data Integrity Guidelines

A Multi-Tenant application relies entirely on impenetrable security logic. If an endpoint is left unsecured, horizontal data-bleeding can destroy the operational integrity of the software.

## 1. Password Subsystem
Every single user relies entirely on native PHP `password_hash()` engines. 

**Implementation Details**: We utilize the `PASSWORD_DEFAULT` algorithm structure, which natively triggers the BCRYPT encryption suite. This means physical passwords are never stored in the database. Instead, highly randomized mathematical strings are saved. When logging in (`login.php`), the `password_verify()` boolean function cross-references the hashing string implicitly, bypassing the need for raw textual parsing.

## 2. Strict Gateway Validation
Because the architecture relies entirely on the isolated `$role` variable, users cannot just type URLs to bypass endpoints.

**Implementation Details**:
Inside `login.php`, the Database fetches the user array parameters. Even if their `$username` and `$password` are entirely true, a final check acts as a rigid firewall:
`if ($user['role'] !== $requested_role) { session_destroy(); }`
This implicitly makes it impossible for an Administrative `$role` to log into the Public Parent Gateway visually, retaining complete system segregation.

Furthermore, every single page opens with `if ($_SESSION['role'] !== 'expected') die();`, hard-blocking forced URL navigations.

## 3. SQL Injection Mitigation 
This entire SaaS platform executes 100% of its database operations utilizing PHP Data Objects (**PDO**) Prepared Statements.

**Implementation Details**:
Instead of writing native string implementations:
*(Vulnerable)*: `SELECT * FROM users WHERE email = '$email'`

We completely decoupled user input from execution engines:
*(Protected)*: `$pdo->prepare("SELECT * FROM users WHERE email = ?")->execute([$email]);`

This implicitly prevents any system user from passing active `DROP TABLE` or `UNION SELECT` command injections into our database, rendering traditional SQL injection structures null unconditionally.
