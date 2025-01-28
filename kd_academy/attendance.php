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
        // Get all attendance records for the selected course
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name, 
                   a.status as attendance_status,
                   a.date as attendance_date
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            LEFT JOIN attendance a ON u.id = a.student_id 
                AND a.course_id = ?
            WHERE e.course_id = ?
            AND e.status = 'approved'
            ORDER BY u.full_name ASC, a.date ASC
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
                    'attendance' => []
                ];
            }
            if ($row['attendance_date']) {
                $students[$row['id']]['attendance'][] = [
                    'date' => $row['attendance_date'],
                    'status' => $row['attendance_status']
                ];
            }
        }

        // Get students for the attendance form
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.course_id = ?
            AND e.status = 'approved'
            ORDER BY u.full_name
        ");
        $stmt->execute([$selected_course]);
        $student_list = $stmt->fetchAll();
    }

    // Handle attendance submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = $_POST['student_id'];
        $status = $_POST['status'];
        $date = $_POST['date'];
        
        // Check if attendance record already exists for this date
        $stmt = $conn->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND course_id = ? AND date = ?
        ");
        $stmt->execute([$student_id, $selected_course, $date]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = ? 
                WHERE student_id = ? AND course_id = ? AND date = ?
            ");
            $stmt->execute([$status, $student_id, $selected_course, $date]);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO attendance (student_id, course_id, date, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $selected_course, $date, $status]);
        }

        $_SESSION['success'] = "Attendance has been recorded successfully.";
        header("Location: attendance.php?course_id=" . $selected_course);
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
    <title>KD Academy - Attendance</title>
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
                        <a class="nav-link active" href="attendance.php">Attendance</a>
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
        <h1>Attendance Management</h1>

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
                                <h5 class="card-title">Attendance Records</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <?php if (empty($student['attendance'])): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                        <td colspan="2">No attendance records</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($student['attendance'] as $index => $record): ?>
                                                        <tr>
                                                            <?php if ($index === 0): ?>
                                                                <td rowspan="<?php echo count($student['attendance']); ?>">
                                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php 
                                                                    echo $record['status'] === 'present' ? 'success' : 
                                                                        ($record['status'] === 'late' ? 'warning' : 'danger'); 
                                                                ?>">
                                                                    <?php echo ucfirst($record['status']); ?>
                                                                </span>
                                                            </td>
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
                                <h5 class="card-title">Mark Attendance</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="student" class="form-label">Student</label>
                                        <select name="student_id" id="student" class="form-select" required>
                                            <?php foreach ($student_list as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select" required>
                                            <option value="present">Present</option>
                                            <option value="absent">Absent</option>
                                            <option value="late">Late</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Mark Attendance</button>
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