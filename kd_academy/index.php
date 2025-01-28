<?php
require_once 'config.php';

// Redirect to login if not authenticated
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KD Academy - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="images/logo.png" alt="KD Academy Logo">
                KD Academy
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <?php if ($_SESSION['role'] === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enrollment.php">My Enrollments</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_enrollments.php">Enrollments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <a href="change_password.php" class="btn btn-outline-light me-2">Change Password</a>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <ul class="list-group">
                            <?php if ($_SESSION['role'] === 'student'): ?>
                            <li class="list-group-item">
                                <a href="courses.php">View Available Courses</a>
                            </li>
                            <li class="list-group-item">
                                <a href="enrollment.php">Manage Enrollments</a>
                            </li>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] === 'teacher'): ?>
                            <li class="list-group-item">
                                <a href="manage_enrollments.php">Manage Course Enrollments</a>
                            </li>
                            <li class="list-group-item">
                                <a href="attendance.php">Take Attendance</a>
                            </li>
                            <li class="list-group-item">
                                <a href="grades.php">Manage Grades</a>
                            </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <a href="change_password.php">Change Password</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>