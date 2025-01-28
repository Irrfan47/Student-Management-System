<?php
require_once 'config.php';

// Redirect to login if not authenticated
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $student_id = $_SESSION['user_id'];

    try {
        // Check if already enrolled (including pending and rejected statuses)
        $stmt = $conn->prepare("SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$student_id, $course_id]);
        $existing_enrollment = $stmt->fetch();
        
        if ($existing_enrollment) {
            // Set appropriate message based on enrollment status
            switch($existing_enrollment['status']) {
                case 'approved':
                    $_SESSION['error'] = "You are already enrolled in this course.";
                    break;
                case 'pending':
                    $_SESSION['error'] = "Your enrollment request for this course is pending approval.";
                    break;
                case 'rejected':
                    $_SESSION['error'] = "Your previous enrollment request was rejected. Please contact your administrator.";
                    break;
            }
            header('Location: courses.php');
            exit();
        }

        // Check available slots
        $stmt = $conn->prepare("
            SELECT c.max_students, COUNT(e.id) as enrolled_count 
            FROM courses c 
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'approved'
            WHERE c.id = ?
            GROUP BY c.id, c.max_students
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();

        if ($course && $course['enrolled_count'] < $course['max_students']) {
            // Create enrollment
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$student_id, $course_id]);
            $_SESSION['success'] = "Enrollment request submitted successfully! Please wait for teacher approval.";
        } else {
            $_SESSION['error'] = "Sorry, this course is full.";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header('Location: enrollment.php');
    exit();
}
?>