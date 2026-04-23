# Core Functionality & Implementation Matrix

The core of the application relies on an intertwined User Hierarchy, managed centrally through Administrative Control nodes.

## 1. Centralized Administrative Tracking (`admin/`)
The foundational core of the software is the local Center Administrator. We engineered their dashboard to serve as the master switchboard for all CRUD (Create, Read, Update, Delete) data operations.

**Implementation Details:**
- **Students Portal (`admin/students.php`)**: An HTML `<form>` binds a student to a `user_id` inside the auth portal. A secondary DB Insert binds them into a localized `class_id` structure.
- **Faculty Portal (`admin/faculty.php`)**: Dynamically binds teachers to the Auth system whilst isolating them to standard Faculty dashboard routing.
- **Classes Portal (`admin/classes.php`)**: We implemented the original timetable array here, forcing a strict Foreign Key `ON DELETE SET NULL` rule. If the Admin fires a Teacher, the system does not crash or delete the Class—it merely orphans the class so the Admin can assign a new Teacher.

## 2. Dynamic Cross-Referencing Dashboards
Instead of just displaying static names, we heavily implemented `LEFT JOIN` algorithms to cross-reference data beautifully.

**Implementation Example:**
On `parent/index.php`, a parent does not just see their child's name. The SQL statement joins the `students` table against the `classes` table, using the `class_id` foreign key. This allows the Parent portal to intelligently display exactly what Time slot and Day their particular child is assigned to, natively pulling from the Admin's timetable array.

## 3. Disciplinary Tracking & Attendance
The Faculty portals were specifically designed to log empirical metrics.

**Implementation details:**
- **Attendance (`faculty/attendance.php`)**: Uses a complex multidimensional POST array (`$_POST['attendance'][$student_id]`) combined with an `INSERT IGNORE` MySQL parameter to rapidly digest bulk class attendance without allowing duplicate records on the same calendar day.
- **Grades (`faculty/grades.php`)**: Relies on a numeric precision map taking raw decimal structures attached to generalized "Subject String" parameters.
