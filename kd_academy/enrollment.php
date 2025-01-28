<?php
require_once 'config.php';

// Redirect to login if not authenticated
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// Fetch student's enrollments
try {
    $stmt = $conn->prepare("
        SELECT e.*, c.course_name, c.course_code, c.credits, c.semester, u.full_name as teacher_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE e.student_id = ?
        ORDER BY e.enrollment_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $enrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KD Academy - My Enrollments</title>
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
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="enrollment.php">My Enrollments</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="change_password.php" class="btn btn-outline-light me-2">Change Password</a>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>My Enrollments</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row mt-4">
            <?php foreach ($enrollments as $enrollment): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($enrollment['course_name']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Code: <?php echo htmlspecialchars($enrollment['course_code']); ?></h6>
                            
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item">Credits: <?php echo $enrollment['credits']; ?></li>
                                <li class="list-group-item">Semester: <?php echo htmlspecialchars($enrollment['semester']); ?></li>
                                <li class="list-group-item">Teacher: <?php echo htmlspecialchars($enrollment['teacher_name']); ?></li>
                                <li class="list-group-item">
                                    Status: 
                                    <span class="badge <?php 
                                        echo match($enrollment['status']) {
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            default => 'bg-warning'
                                        };
                                    ?>">
                                        <?php echo ucfirst($enrollment['status']); ?>
                                    </span>
                                </li>
                                <li class="list-group-item">Enrolled: <?php echo date('F j, Y', strtotime($enrollment['enrollment_date'])); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>