<?php
session_start();
date_default_timezone_set('Asia/Manila');

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   GET BOOK ID
================================ */
$selected_book = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$reserve_date  = date("Y-m-d\TH:i"); // default current date & time

/* ===============================
   RESERVE BOOK
================================ */
if (isset($_POST['reserve'])) {

    $borrower_type = $_POST['borrower_type'];
    $first_name  = strtoupper(trim(preg_replace("/[^A-Za-z ]/", "", $_POST['first_name'])));
    $middle_name = strtoupper(trim(preg_replace("/[^A-Za-z ]/", "", $_POST['middle_name'])));
    $last_name   = strtoupper(trim(preg_replace("/[^A-Za-z ]/", "", $_POST['last_name'])));
    $borrower_id  = preg_replace("/[^0-9]/", "", $_POST['borrower_id']);
    $course       = strtoupper(trim($_POST['course'] ?? ''));
    $year         = intval($_POST['year'] ?? 0);
    $book_id      = intval($_POST['book_id']);
    $reserve_date_input = $_POST['reserve_date'];
    $borrow_period = trim($_POST['borrow_period']);

    $borrower_name = trim("$first_name $middle_name $last_name");

    // Validation
    if (!preg_match("/^[A-Z ]+$/", $first_name)) {
        $_SESSION['error'] = "First name must contain letters only.";
    } elseif (!empty($middle_name) && !preg_match("/^[A-Z ]+$/", $middle_name)) {
        $_SESSION['error'] = "Middle name must contain letters only.";
    } elseif (!preg_match("/^[A-Z ]+$/", $last_name)) {
        $_SESSION['error'] = "Last name must contain letters only.";
    } elseif (!preg_match("/^[0-9]+$/", $borrower_id)) {
        $_SESSION['error'] = "Borrower ID must contain numbers only.";
    } elseif ($borrower_type === "Student" && strlen($borrower_id) != 7) {
        $_SESSION['error'] = "Student Borrower ID must be 7 digits.";
    } elseif ($borrower_type === "Teacher" && strlen($borrower_id) != 5) {
        $_SESSION['error'] = "Teacher Borrower ID must be 5 digits.";
    }

    // Validate course/year for students
    $valid_courses = ["BSBA", "BSIT", "BSHM", "BSA", "TEED"];
    $valid_years   = [1,2,3,4];
    if ($borrower_type === "Student") {
        if (!in_array($course, $valid_courses)) {
            $_SESSION['error'] = "Please select a valid course.";
        }
        if (!in_array($year, $valid_years)) {
            $_SESSION['error'] = "Please select a valid year.";
        }
    } else {
        $course = "";
        $year = 0;
    }

    // Validate borrow period
    $valid_periods = ["1 day", "3 days", "7 days", "1 month"];
    if (!in_array($borrow_period, $valid_periods)) {
        $_SESSION['error'] = "Invalid borrow period selected.";
    }

    if (isset($_SESSION['error'])) {
        header("Location: reserve.php?book_id=$book_id");
        exit();
    }

    // Convert datetime-local to MySQL DATETIME
    $reserve_date = date('Y-m-d H:i:s', strtotime($reserve_date_input));

    // Calculate due date
    switch ($borrow_period) {
        case '1 day':   $due_date = date('Y-m-d H:i:s', strtotime($reserve_date.' +1 day')); break;
        case '3 days':  $due_date = date('Y-m-d H:i:s', strtotime($reserve_date.' +3 days')); break;
        case '7 days':  $due_date = date('Y-m-d H:i:s', strtotime($reserve_date.' +7 days')); break;
        case '1 month': $due_date = date('Y-m-d H:i:s', strtotime($reserve_date.' +30 days')); break;
        default:        $due_date = date('Y-m-d H:i:s', strtotime($reserve_date.' +7 days')); break;
    }

    // Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO reservations
        (first_name, middle_name, last_name, borrower_name, borrower_id,
         course, year, book_id, reserve_date, borrower_type, borrow_period, due_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssisiissss",
        $first_name, $middle_name, $last_name, $borrower_name,
        $borrower_id, $course, $year, $book_id, $reserve_date,
        $borrower_type, $borrow_period, $due_date
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Book reserved successfully!";
    } else {
        $_SESSION['error'] = "Reservation failed: " . $stmt->error;
    }

    $stmt->close();
    header("Location: reserve.php?book_id=$book_id");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Reserve Book</title>
<link rel="stylesheet" href="reserve.css">
<style>
input[name="first_name"], input[name="middle_name"], input[name="last_name"] {
    text-transform: uppercase;
}
#courseYear {
    margin-top:10px;
}
</style>
</head>
<body>
<h1>Reserve Book</h1>

<nav>
<a href="Books.php">BOOKS</a> |
<a href="student_transaction.php">Borrower Transaction</a> |
<a href="logout.php">Logout</a>
</nav>

<?php if(isset($_SESSION['message'])): ?>
<p style="color:green"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<p style="color:red"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>

<form method="POST">

<label>Borrower Type</label>
<select name="borrower_type" id="borrower_type" onchange="toggleCourseYear()" required>
    <option value="Student">Student</option>
    <option value="Teacher">Teacher</option>
</select>

<label>First Name</label>
<input type="text" name="first_name" required pattern="[A-Za-z ]+" title="Letters only">

<label>Middle Name</label>
<input type="text" name="middle_name" pattern="[A-Za-z ]*" title="Letters only">

<label>Last Name</label>
<input type="text" name="last_name" required pattern="[A-Za-z ]+" title="Letters only">

<label>Borrower ID</label>
<input type="text" name="borrower_id" id="borrower_id" required pattern="[0-9]+" title="Numbers only">

<div id="courseYear">
<label>Course</label>
<select name="course" required>
    <option value="">--Select Course--</option>
    <option value="BSBA">BSBA</option>
    <option value="BSIT">BSIT</option>
    <option value="BSHM">BSHM</option>
    <option value="BSA">BSA</option>
    <option value="TEED">TEED</option>
</select>

<label>Year</label>
<select name="year" required>
    <option value="">--Select Year--</option>
    <option value="1">1</option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
</select>
</div>

<label>Book ID</label>
<input type="number" name="book_id" value="<?= htmlspecialchars($selected_book) ?>" readonly required>

<label>Reserve Date & Time</label>
<input type="datetime-local" name="reserve_date" 
       value="<?= date('Y-m-d\TH:i', strtotime($reserve_date)) ?>" required>

<label>Borrow Period</label>
<select name="borrow_period" required>
    <option value="1 day">1 Day</option>
    <option value="3 days">3 Days</option>
    <option value="7 days" selected>7 Days</option>
    <option value="1 month">1 Month</option>
</select>

<button type="submit" name="reserve">Reserve</button>
</form>

<script>
function toggleCourseYear() {
    const type = document.getElementById('borrower_type').value;
    const courseYear = document.getElementById('courseYear');
    if (type === 'Teacher') {
        courseYear.style.display = 'none';
    } else {
        courseYear.style.display = 'block';
    }
}
// Initialize on page load
window.onload = toggleCourseYear;
</script>

</body>
</html>
