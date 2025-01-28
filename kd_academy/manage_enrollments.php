<?php
require_once 'config.php';

// Redirect if not authenticated or not a teacher
if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Handle enrollment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enrollment_id = $_POST['enrollment_id'];
    $action = $_POST['action'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET status = ? 
            WHERE id = ? AND course_id IN (
                SELECT id FROM courses WHERE teacher_id = ?
            )
        ");
        $stmt->execute([$action, $enrollment_id, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Enrollment has been " . $action . " successfully.";
        header('Location: manage_enrollments.php');
        exit();
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get teacher's courses with enrollments
try {
    $stmt = $conn->prepare("
        SELECT 
            c.id as course_id,
            c.course_name,
            c.course_code,
            c.max_students,
            e.id as enrollment_id,
            e.enrollment_date,
            e.status,
            u.full_name as student_name,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'approved') as enrolled_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN users u ON e.student_id = u.id
        WHERE c.teacher_id = ?
        ORDER BY c.course_code, e.enrollment_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $results = $stmt->fetchAll();

    // Organize results by course
    $courses = [];
    foreach ($results as $row) {
        if (!isset($courses[$row['course_id']])) {
            $courses[$row['course_id']] = [
                'course_name' => $row['course_name'],
                'course_code' => $row['course_code'],
                'max_students' => $row['max_students'],
                'enrolled_count' => $row['enrolled_count'],
                'enrollments' => []
            ];
        }
        if ($row['enrollment_id']) {
            $courses[$row['course_id']]['enrollments'][] = [
                'id' => $row['enrollment_id'],
                'student_name' => $row['student_name'],
                'enrollment_date' => $row['enrollment_date'],
                'status' => $row['status']
            ];
        }
    }
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KD Academy - Manage Enrollments</title>
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
                        <a class="nav-link active" href="manage_enrollments.php">Enrollments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
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
        <h1>Manage Course Enrollments</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="alert alert-info">You are not assigned to any courses.</div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            <span class="badge bg-info float-end">
                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> Students
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($course['enrollments'])): ?>
                            <p class="text-muted">No enrollment requests for this course.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Enrollment Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($course['enrollments'] as $enrollment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($enrollment['enrollment_date'])); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo match($enrollment['status']) {
                                                            'approved' => 'bg-success',
                                                            'rejected' => 'bg-danger',
                                                            default => 'bg-warning'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($enrollment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($enrollment['status'] === 'pending'): ?>
                                                        <div class="btn-group" role="group">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                                <input type="hidden" name="action" value="approved">
                                                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                            </form>
                                                            <form method="POST" class="d-inline ms-1">
                                                                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                                <input type="hidden" name="action" value="rejected">
                                                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>