# Multi-Center SaaS Architecture Origins

## 1. Project Genesis
The project began as a standard **School Management System (SMS)** but was immediately upgraded into an advanced **Multi-Tenant SaaS (Software as a Service) Platform**. Instead of deploying a separate code application for every single tuition center, the explicit goal was to build a single, centralized codebase that could host infinitely many completely independent tuition centers silently.

## 2. Multi-Tenant Shared Database
To achieve infinite scalability without creating thousands of databases, we used a **Shared-Schema Multi-Tenant Architecture**. 

Every single table in the SQL logic (Students, Classes, Faculty) has a strict mathematical relationship bound back to a single column: `center_id`.
Whenever a user logs in, the PHP Backend universally stamps their `$_SESSION['center_id']`. 

**How it was implemented:**
Every single SQL query executed on the entire website has a mandatory `WHERE center_id = ?` filter. This physically prevents any Center Administrator from querying, deleting, or even seeing data from a rival center, executing perfect data isolation natively within the MySQL engine.

## 3. Top-Level Hierarchy (Superadmin)
To govern this massive architecture, we implemented a single omnipotent `superadmin` master account. 
When a tuition center signs up on the public landing page (`center_register.php`), their status is thrown into a localized validation queue.

**Implementation:**
The `superadmin/centers.php` fetches all dormant centers. The Superadmin reviews the real-world credentials of the tuition center, and executes a Database Insert to formally generate their Master Administrator account, giving the new Center control of their localized slice of the app.
