# Advanced Feature Implementations (Evolution)

As the project expanded, we drastically upgraded the Center functionality beyond simple array saving, executing highly complex mathematical and logical algorithms.

## 1. Timetable Clash Detection Algorithm
A massive challenge was preventing Administrators from assigning the same Teacher to teach multiple classes simultaneously.

**Implementation**: 
Inside `admin/classes.php`, we built a temporal mathematical formula traversing the SQL database. Before inserting a class, the system pulls every class attached to the proposed `faculty_id`. It natively checks if the requested `day_of_week` matches an existing entry, and then executes a PHP temporal evaluation to see if the new `start_time` and `end_time` mathematically overlap the pre-existing block. If an overlap is triggered, the operation is structurally blocked, returning an error to the Admin.

## 2. Dynamic Excel / CSV Exporting Nodes
Administrators required external data pipelines for standard reporting.

**Implementation**:
Inside the `student, classes, & faculty` modules, we bound an HTTP `?export=csv` GET listener. Upon triggering, we hijack the PHP rendering engine wrapper and shift the output `Content-Type` strictly to `text/csv`. We then boot up a native `fputcsv()` logical loop that iterates across the MySQL multidimensional array, parsing the raw DB data into comma-separated text files forcing an immediate `.CSV` file payload download onto the user's hard drive.

## 3. The Remarks Extension
Center Admins wanted active qualitative behavioral tracking over quantitative grades.

**Implementation**:
We generated a brand new SQL Table named `remarks`, featuring a `severity` ENUM payload (`positive`, `negative`, `neutral`). Faculty generate these notes, which are then dynamically pulled onto `parent/index.php`. The PHP HTML renderer mathematically intercepts the `severity` string and rewrites the CSS colors of the div element in real-time (Green for positive, Red for negative).

## 4. Personal Configurations (Avatars & Contacts)
We extended a localized `profile.php` endpoint to Students and Guardians.

**Implementation**:
Users can physically upload image payloads via a localized `<form enctype="multipart/form-data">`. 
We execute strict security measures locally:
1. `pathinfo()` extracts the exact file extension type.
2. We evaluate it against a strict whitelist array `['jpg', 'png']`.
3. We rename the file physically using their `user_id` and the `time()` epoch sequence (e.g., `stu_12_170305822.jpg`). Because it is named programmatically, it inherently prevents hackers from executing localized code injections disguised as `.php` images.
