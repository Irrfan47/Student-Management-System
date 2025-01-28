<?php
require_once 'config.php';

// Redirect if not authenticated or not a teacher
if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Get teacher's courses
try {
    $stmt = $conn->prepare("
        SELECT id, course_name, course_code 
        FROM courses 
        WHERE teacher_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();

    // Get selected course
    $selected_course = $_GET['course_id'] ?? ($courses[0]['id'] ?? null);

    if ($selected_course) {
        // Get enrolled students and their grades
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name,
                   ap.assessment_type,
                   ap.score,
                   ap.max_score,
                   ap.date
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            LEFT JOIN academic_performance ap ON u.id = ap.student_id 
                AND ap.course_id = ?
            WHERE e.course_id = ?
            AND e.status = 'approved'
            ORDER BY u.full_name, ap.date DESC
        ");
        $stmt->execute([$selected_course, $selected_course]);
        $results = $stmt->fetchAll();

        // Organize results by student
        $students = [];
        foreach ($results as $row) {
            if (!isset($students[$row['id']])) {
                $students[$row['id']] = [
                    'id' => $row['id'],
                    'full_name' => $row['full_name'],
                    'grades' => []
                ];
            }
            if ($row['assessment_type']) {
                $students[$row['id']]['grades'][] = [
                    'type' => $row['assessment_type'],
                    'score' => $row['score'],
                    'max_score' => $row['max_score'],
                    'date' => $row['date']
                ];
            }
        }
    }

    // Handle grade submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = $_POST['student_id'];
        $assessment_type = $_POST['assessment_type'];
        $score = $_POST['score'];
        $max_score = $_POST['max_score'];

        $stmt = $conn->prepare("
            INSERT INTO academic_performance 
            (student_id, course_id, assessment_type, score, max_score, date) 
            VALUES (?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$student_id, $selected_course, $assessment_type, $score, $max_score]);

        $_SESSION['success'] = "Grade has been recorded successfully.";
        header("Location: grades.php?course_id=" . $selected_course);
        exit();
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
    <title>KD Academy - Grades</title>
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
                        <a class="nav-link active" href="grades.php">Grades</a>
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
        <h1>Grade Management</h1>

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
            <div class="row mb-4">
                <div class="col">
                    <form method="GET" class="d-flex gap-2">
                        <select name="course_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                    <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if (!empty($students)): ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Student Grades</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Assessment Type</th>
                                                <th>Score</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <?php if (empty($student['grades'])): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                        <td colspan="3">No grades recorded</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($student['grades'] as $index => $grade): ?>
                                                        <tr>
                                                            <?php if ($index === 0): ?>
                                                                <td rowspan="<?php echo count($student['grades']); ?>">
                                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td><?php echo htmlspecialchars($grade['type']); ?></td>
                                                            <td><?php echo $grade['score'] . '/' . $grade['max_score']; ?></td>
                                                            <td><?php echo date('Y-m-d', strtotime($grade['date'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Add New Grade</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="student" class="form-label">Student</label>
                                        <select name="student_id" id="student" class="form-select" required>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="assessment_type" class="form-label">Assessment Type</label>
                                        <input type="text" class="form-control" id="assessment_type" 
                                               name="assessment_type" required
                                               placeholder="e.g., Quiz 1, Midterm, Final">
                                    </div>
                                    <div class="mb-3">
                                        <label for="score" class="form-label">Score</label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="score" name="score" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="max_score" class="form-label">Maximum Score</label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="max_score" name="max_score" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Add Grade</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No students enrolled in this course.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>