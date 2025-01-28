<?php
require_once 'config.php';

// Redirect to login if not authenticated
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Fetch all available courses
try {
    $stmt = $conn->prepare("
        SELECT c.*, u.full_name as teacher_name, 
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'approved') as enrolled_students 
        FROM courses c 
        LEFT JOIN users u ON c.teacher_id = u.id
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KD Academy - Courses</title>
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
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="images/logo.png" alt="KD Academy Logo">
                KD Academy
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="courses.php">Courses</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="enrollment.php">My Enrollments</a>
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
        <h1>Available Courses</h1>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row mt-4">
            <?php foreach ($courses as $course): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Code: <?php echo htmlspecialchars($course['course_code']); ?></h6>
                            <p class="card-text"><?php echo htmlspecialchars($course['description']); ?></p>
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item">Credits: <?php echo $course['credits']; ?></li>
                                <li class="list-group-item">Semester: <?php echo htmlspecialchars($course['semester']); ?></li>
                                <li class="list-group-item">Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></li>
                                <li class="list-group-item">Available Slots: <?php echo $course['max_students'] - $course['enrolled_students']; ?></li>
                            </ul>
                            <?php if ($_SESSION['role'] === 'student'): ?>
                                <form action="enroll.php" method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-primary" 
                                        <?php echo ($course['max_students'] <= $course['enrolled_students']) ? 'disabled' : ''; ?>>
                                        Enroll Now
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>