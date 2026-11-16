
<?php
session_start();

/* =====================================
   DATABASE CONNECTION
===================================== */

$host = "localhost";
$dbname = "campus_portfolio";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed. Check XAMPP and database settings.");
}

/* =====================================
   CREATE TABLES AUTOMATICALLY
===================================== */

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    achievement_date DATE,
    certificate VARCHAR(255),
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
)");

$categories = [
    "Academic", "Sports", "Cultural",
    "Technical", "Research", "Other"
];

foreach ($categories as $category) {
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO categories (category_name) VALUES (?)"
    );
    $stmt->execute([$category]);
}

/* Create or initialize administrator account */

$adminEmail = "admin@gmail.com";
$adminPassword = password_hash("Admin@123", PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$adminEmail]);
$admin = $stmt->fetch();

if (!$admin) {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name,email,password,role)
         VALUES (?,?,?,'admin')"
    );
    $stmt->execute([
        "Administrator",
        $adminEmail,
        $adminPassword
    ]);
} else {
    // Set the initial administrator password correctly.
    $stmt = $pdo->prepare(
        "UPDATE users SET password=?, role='admin' WHERE email=?"
    );
    $stmt->execute([$adminPassword, $adminEmail]);
}

/* =====================================
   HELPER FUNCTIONS
===================================== */

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($page) {
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

function loggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) &&
           $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!loggedIn()) {
        redirect('login');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('dashboard');
    }
}

$message = "";
$error = "";

/* =====================================
   LOGOUT
===================================== */

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

/* =====================================
   REGISTRATION
===================================== */

if (isset($_POST['register'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid name and email.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must contain at least 8 characters.";
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (name,email,password,role)
                 VALUES (?,?,?,'student')"
            );

            $stmt->execute([$name, $email, $hash]);

            $message = "Registration successful! Please login.";

        } catch (PDOException $e) {
            $error = "This email is already registered.";
        }
    }
}

/* =====================================
   LOGIN
===================================== */

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email=?"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user['password'])) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            redirect('admin');
        } else {
            redirect('dashboard');
        }

    } else {
        $error = "Invalid email or password.";
    }
}

/* =====================================
   ADD ACHIEVEMENT
===================================== */

if (isset($_POST['add_achievement'])) {

    requireLogin();

    if (isAdmin()) {
        redirect('admin');
    }

    $title = trim($_POST['title']);
    $category = (int)$_POST['category'];
    $description = trim($_POST['description']);
    $date = $_POST['achievement_date'];

    if ($title === "" || !$date || strtotime($date) === false) {
        $error = "Please enter a valid title and date.";
    } else {

        $stmt = $pdo->prepare(
            "SELECT id FROM categories WHERE id=?"
        );
        $stmt->execute([$category]);

        if (!$stmt->fetch()) {
            $error = "Please select a valid category.";
        } else {

            $certificate = "";

            if (!empty($_FILES['certificate']['name'])) {

                $allowed = [
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];

                $file = $_FILES['certificate'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = "Certificate upload failed.";
                } elseif ($file['size'] > 3 * 1024 * 1024) {
                    $error = "Certificate must be smaller than 3 MB.";
                } else {

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);

                    if (!isset($allowed[$mime])) {
                        $error = "Only PDF, JPG and PNG files are allowed.";
                    } else {

                        $uploadDir = __DIR__ . "/uploads";

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $filename = bin2hex(random_bytes(16))
                                  . "." . $allowed[$mime];

                        if (move_uploaded_file(
                            $file['tmp_name'],
                            $uploadDir . "/" . $filename
                        )) {
                            $certificate = "uploads/" . $filename;
                        } else {
                            $error = "Unable to save certificate.";
                        }
                    }
                }
            }

            if ($error === "") {

                $stmt = $pdo->prepare(
                    "INSERT INTO achievements
                    (user_id,category_id,title,description,
                     achievement_date,certificate)
                    VALUES (?,?,?,?,?,?)"
                );

                $stmt->execute([
                    $_SESSION['user_id'],
                    $category,
                    $title,
                    $description,
                    $date,
                    $certificate
                ]);

                redirect('my_achievements');
            }
        }
    }
}

/* =====================================
   ADMIN APPROVAL / REJECTION
===================================== */

if (isset($_POST['update_status'])) {

    requireAdmin();

    $id = (int)$_POST['achievement_id'];
    $status = $_POST['status'];

    if (in_array($status, ['Approved', 'Rejected'], true)) {

        $stmt = $pdo->prepare(
            "UPDATE achievements SET status=? WHERE id=?"
        );

        $stmt->execute([$status, $id]);
    }

    redirect('admin');
}

/* =====================================
   PAGE ROUTING
===================================== */

$page = $_GET['page'] ?? 'home';

$protectedPages = [
    'dashboard', 'add_achievement',
    'my_achievements', 'admin'
];

if (in_array($page, $protectedPages, true)) {
    requireLogin();
}

if ($page === 'admin') {
    requireAdmin();
}

if (in_array($page, ['dashboard', 'add_achievement', 'my_achievements'], true)
    && isAdmin()) {
    redirect('admin');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Campus Achievement Portfolio</title>

<style>

/* =====================================
   GENERAL DESIGN
===================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    color: #263248;
}

header {
    background: linear-gradient(120deg,#243b80,#6941c6);
    color: white;
    padding: 20px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

header h2 {
    font-size: 23px;
}

nav {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
}

nav a {
    color: white;
    text-decoration: none;
    font-size: 14px;
}

nav a:hover {
    color: #ffdc83;
}

.container {
    width: 90%;
    max-width: 1150px;
    margin: 35px auto;
}

.hero {
    background: linear-gradient(120deg,#293d89,#7545cb);
    color: white;
    padding: 75px 35px;
    border-radius: 18px;
    text-align: center;
}

.hero h1 {
    font-size: 38px;
    margin-bottom: 18px;
}

.hero p {
    line-height: 1.8;
    max-width: 700px;
    margin: auto;
}

.btn {
    display: inline-block;
    padding: 12px 23px;
    background: #6246c7;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 14px;
    margin-top: 15px;
}

.btn:hover {
    background: #422e9b;
}

.btn-light {
    background: white;
    color: #48349c;
}

.btn-light:hover {
    background: #e9e5ff;
}

.card {
    background: white;
    padding: 27px;
    border-radius: 13px;
    box-shadow: 0 4px 18px rgba(30,40,80,.07);
    margin-bottom: 22px;
}

.card h3 {
    margin-bottom: 15px;
    color: #263d80;
}

.features {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap: 20px;
    margin-top: 30px;
}

.feature {
    background: white;
    padding: 27px;
    border-radius: 12px;
    box-shadow: 0 3px 14px rgba(0,0,0,.05);
}

.feature h3 {
    color: #5841b5;
    margin-bottom: 12px;
}

.feature p {
    line-height: 1.6;
    color: #68738a;
}

.form-box {
    background: white;
    max-width: 480px;
    margin: 40px auto;
    padding: 35px;
    border-radius: 13px;
    box-shadow: 0 5px 20px rgba(0,0,0,.07);
}

.form-box h2 {
    text-align: center;
    color: #453b9b;
    margin-bottom: 25px;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    margin: 8px 0 17px;
    border: 1px solid #d7dbea;
    border-radius: 7px;
    font-size: 14px;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

label {
    font-weight: bold;
    font-size: 14px;
}

.form-box button {
    width: 100%;
}

.alert {
    padding: 14px;
    margin: 15px 0;
    border-radius: 7px;
    background: #e2f8eb;
    color: #176b3b;
}

.error {
    background: #ffe7e7;
    color: #a22;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(180px,1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat {
    padding: 25px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.stat h2 {
    font-size: 30px;
    color: #6246c7;
    margin-top: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    margin-top: 15px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e5e8f0;
    text-align: left;
    font-size: 13px;
}

th {
    background: #edf0ff;
    color: #34468b;
}

.status {
    font-weight: bold;
}

.Pending {
    color: #d18a00;
}

.Approved {
    color: #16834c;
}

.Rejected {
    color: #d13b3b;
}

.table-wrap {
    overflow-x: auto;
}

footer {
    margin-top: 55px;
    background: #202d55;
    color: white;
    text-align: center;
    padding: 23px;
}

@media(max-width:650px) {
    header {
        flex-direction: column;
        text-align: center;
    }

    nav {
        justify-content: center;
    }

    .hero h1 {
        font-size: 27px;
    }

    .form-box {
        width: 92%;
        padding: 23px;
    }
}

</style>

</head>

<body>

<header>

<h2>Campus Portfolio</h2>

<nav>

<a href="index.php">Home</a>

<?php if (!loggedIn()): ?>

<a href="index.php?page=login">Login</a>
<a href="index.php?page=register">Register</a>

<?php elseif (isAdmin()): ?>

<a href="index.php?page=admin">Admin Dashboard</a>
<a href="index.php?logout=1">Logout</a>

<?php else: ?>

<a href="index.php?page=dashboard">Dashboard</a>
<a href="index.php?page=add_achievement">Add Achievement</a>
<a href="index.php?page=my_achievements">My Achievements</a>
<a href="index.php?logout=1">Logout</a>

<?php endif; ?>

</nav>

</header>

<div class="container">

<?php if ($message): ?>
<div class="alert"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert error"><?= e($error) ?></div>
<?php endif; ?>


<!-- =====================================
     HOME PAGE
===================================== -->

<?php if ($page === 'home'): ?>

<div class="hero">

<h1>Campus Achievement Portfolio</h1>

<p>
A smart digital platform to record, manage and showcase
students' academic, sports, cultural and technical achievements.
Build your profile and preserve your success in one place.
</p>

<a class="btn btn-light" href="index.php?page=register">
Get Started
</a>

<a class="btn btn-light" href="index.php?page=login">
Login
</a>

</div>

<div class="features">

<div class="feature">
<h3>Academic Achievements</h3>
<p>Record academic awards, certificates and educational accomplishments.</p>
</div>

<div class="feature">
<h3>Sports Achievements</h3>
<p>Maintain sports competition results and participation records.</p>
</div>

<div class="feature">
<h3>Cultural Activities</h3>
<p>Record cultural events, competitions and creative performances.</p>
</div>

<div class="feature">
<h3>Digital Certificates</h3>
<p>Upload and maintain digital copies of achievement certificates.</p>
</div>

</div>


<!-- =====================================
     REGISTRATION PAGE
===================================== -->

<?php elseif ($page === 'register'): ?>

<div class="form-box">

<h2>Student Registration</h2>

<form method="POST">

<label>Full Name</label>
<input type="text" name="name" placeholder="Enter your name" required maxlength="100">

<label>Email Address</label>
<input type="email" name="email" placeholder="Enter your email" required maxlength="100">

<label>Password</label>
<input type="password" name="password" placeholder="Create password (minimum 8 characters)" minlength="8" required>

<button class="btn" type="submit" name="register">
Register
</button>

</form>

<p style="text-align:center;margin-top:18px">
Already registered?
<a href="index.php?page=login">Login here</a>
</p>

</div>


<!-- =====================================
     LOGIN PAGE
===================================== -->

<?php elseif ($page === 'login'): ?>

<div class="form-box">

<h2>Login</h2>

<form method="POST">

<label>Email Address</label>
<input type="email" name="email" placeholder="Enter email" required>

<label>Password</label>
<input type="password" name="password" placeholder="Enter password" required>

<button class="btn" type="submit" name="login">
Login
</button>

</form>

<p style="text-align:center;margin-top:18px">
New student?
<a href="index.php?page=register">Register here</a>
</p>

</div>


<!-- =====================================
     STUDENT DASHBOARD
===================================== -->

<?php elseif ($page === 'dashboard'): ?>

<?php

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM achievements WHERE user_id=?"
);
$stmt->execute([$_SESSION['user_id']]);
$total = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM achievements
     WHERE user_id=? AND status='Approved'"
);
$stmt->execute([$_SESSION['user_id']]);
$approved = $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM achievements
     WHERE user_id=? AND status='Pending'"
);
$stmt->execute([$_SESSION['user_id']]);
$pending = $stmt->fetchColumn();

?>

<h2>Welcome, <?= e($_SESSION['name']) ?>!</h2>

<p style="margin:12px 0 25px">
Welcome to your personal achievement dashboard.
</p>

<div class="stats">

<div class="stat">
<p>Total Achievements</p>
<h2><?= $total ?></h2>
</div>

<div class="stat">
<p>Approved</p>
<h2><?= $approved ?></h2>
</div>

<div class="stat">
<p>Pending</p>
<h2><?= $pending ?></h2>
</div>

</div>

<div class="card">

<h3>Manage Your Portfolio</h3>

<p>Add new achievements and maintain your digital portfolio.</p>

<a href="index.php?page=add_achievement" class="btn">
Add Achievement
</a>

<a href="index.php?page=my_achievements" class="btn">
View Achievements
</a>

</div>


<!-- =====================================
     ADD ACHIEVEMENT PAGE
===================================== -->

<?php elseif ($page === 'add_achievement'): ?>

<div class="form-box">

<h2>Add Achievement</h2>

<form method="POST" enctype="multipart/form-data">

<label>Achievement Title</label>
<input type="text" name="title" placeholder="Enter achievement title" maxlength="200" required>

<label>Achievement Category</label>

<select name="category" required>

<option value="">Select Category</option>

<?php

$cats = $pdo->query(
    "SELECT * FROM categories ORDER BY category_name"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($cats as $cat):
?>

<option value="<?= $cat['id'] ?>">
<?= e($cat['category_name']) ?>
</option>

<?php endforeach; ?>

</select>

<label>Description</label>
<textarea name="description" placeholder="Describe your achievement"></textarea>

<label>Achievement Date</label>
<input type="date" name="achievement_date" max="<?= date('Y-m-d') ?>" required>

<label>Upload Certificate (PDF/JPG/PNG, maximum 3 MB)</label>
<input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png">

<button class="btn" type="submit" name="add_achievement">
Submit Achievement
</button>

</form>

</div>


<!-- =====================================
     STUDENT ACHIEVEMENTS
===================================== -->

<?php elseif ($page === 'my_achievements'): ?>

<h2>My Achievements</h2>

<div class="card">

<div class="table-wrap">

<table>

<tr>
<th>Title</th>
<th>Category</th>
<th>Date</th>
<th>Certificate</th>
<th>Status</th>
</tr>

<?php

$stmt = $pdo->prepare(
    "SELECT a.*,c.category_name
     FROM achievements a
     JOIN categories c ON a.category_id=c.id
     WHERE a.user_id=?
     ORDER BY a.id DESC"
);

$stmt->execute([$_SESSION['user_id']]);

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($records as $record):

?>

<tr>

<td><?= e($record['title']) ?></td>

<td><?= e($record['category_name']) ?></td>

<td><?= e($record['achievement_date']) ?></td>

<td>

<?php if ($record['certificate']): ?>

<a href="<?= e($record['certificate']) ?>" target="_blank" rel="noopener">
View Certificate
</a>

<?php else: ?>

Not uploaded

<?php endif; ?>

</td>

<td class="status <?= e($record['status']) ?>">
<?= e($record['status']) ?>
</td>

</tr>

<?php endforeach; ?>

<?php if (!$records): ?>

<tr>
<td colspan="5">No achievements added yet.</td>
</tr>

<?php endif; ?>

</table>

</div>

</div>


<!-- =====================================
     ADMIN DASHBOARD
===================================== -->

<?php elseif ($page === 'admin'): ?>

<?php

$totalStudents = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role='student'"
)->fetchColumn();

$totalAchievements = $pdo->query(
    "SELECT COUNT(*) FROM achievements"
)->fetchColumn();

$totalPending = $pdo->query(
    "SELECT COUNT(*) FROM achievements WHERE status='Pending'"
)->fetchColumn();

$totalApproved = $pdo->query(
    "SELECT COUNT(*) FROM achievements WHERE status='Approved'"
)->fetchColumn();

$records = $pdo->query(
    "SELECT a.*,u.name,u.email,c.category_name
     FROM achievements a
     JOIN users u ON a.user_id=u.id
     JOIN categories c ON a.category_id=c.id
     ORDER BY a.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>Administrator Dashboard</h2>

<p style="margin:12px 0 25px">
Welcome, Administrator!
</p>

<div class="stats">

<div class="stat">
<p>Total Students</p>
<h2><?= $totalStudents ?></h2>
</div>

<div class="stat">
<p>Total Achievements</p>
<h2><?= $totalAchievements ?></h2>
</div>

<div class="stat">
<p>Pending</p>
<h2><?= $totalPending ?></h2>
</div>

<div class="stat">
<p>Approved</p>
<h2><?= $totalApproved ?></h2>
</div>

</div>

<div class="card">

<h3>Achievement Management</h3>

<div class="table-wrap">

<table>

<tr>
<th>Student</th>
<th>Email</th>
<th>Achievement</th>
<th>Category</th>
<th>Date</th>
<th>Certificate</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach ($records as $record): ?>

<tr>

<td><?= e($record['name']) ?></td>

<td><?= e($record['email']) ?></td>

<td><?= e($record['title']) ?></td>

<td><?= e($record['category_name']) ?></td>

<td><?= e($record['achievement_date']) ?></td>

<td>

<?php if ($record['certificate']): ?>

<a href="<?= e($record['certificate']) ?>" target="_blank" rel="noopener">
View
</a>

<?php else: ?>

None

<?php endif; ?>

</td>

<td class="status <?= e($record['status']) ?>">
<?= e($record['status']) ?>
</td>

<td>

<?php if ($record['status'] === 'Pending'): ?>

<form method="POST">

<input type="hidden" name="achievement_id"
value="<?= $record['id'] ?>">

<button class="btn" name="update_status"
value="1" onclick="return setStatus(this,'Approved')">
Approve
</button>

<button class="btn" name="update_status"
value="1" onclick="return setStatus(this,'Rejected')"
style="background:#d33">
Reject
</button>

<input type="hidden" name="status" value="">

</form>

<?php else: ?>

Completed

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php if (!$records): ?>

<tr>
<td colspan="8">No achievements submitted yet.</td>
</tr>

<?php endif; ?>

</table>

</div>

</div>

<?php else: ?>

<div class="card">
<h2>Page not found</h2>
<a class="btn" href="index.php">Go Home</a>
</div>

<?php endif; ?>

</div>

<footer>

<p>© 2026 Campus Achievement Portfolio System</p>
<p>Designed for Student Achievement Management</p>

</footer>

<script>

/* =====================================
   JAVASCRIPT
===================================== */

function setStatus(button, status) {

    const form = button.closest('form');

    form.querySelector('input[name="status"]').value = status;

    return confirm("Are you sure you want to " + status + " this achievement?");
}

</script>

</body>
</html>
