<?php
session_start();

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
$reserve_date  = date("Y-m-d");

/* ===============================
   RESERVE BOOK
================================ */
if (isset($_POST['reserve'])) {

    $borrower_type = $_POST['borrower_type'];

    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);

    $borrower_id  = trim($_POST['borrower_id']);
    $course       = trim($_POST['course'] ?? '');
    $year         = intval($_POST['year'] ?? 0);
    $book_id      = intval($_POST['book_id']);
    $reserve_date = $_POST['reserve_date'];

    // Remove unwanted characters
    $first_name  = preg_replace("/[^A-Za-z ]/", "", $first_name);
    $middle_name = preg_replace("/[^A-Za-z ]/", "", $middle_name);
    $last_name   = preg_replace("/[^A-Za-z ]/", "", $last_name);
    $borrower_id = preg_replace("/[^0-9]/", "", $borrower_id);
    $course      = preg_replace("/[^A-Za-z ]/", "", $course);

    $borrower_name = trim("$first_name $middle_name $last_name");

    // Server-side validation
    if (!preg_match("/^[A-Za-z ]+$/", $first_name)) {
        $_SESSION['error'] = "First name must contain letters only.";
    } elseif (!empty($middle_name) && !preg_match("/^[A-Za-z ]+$/", $middle_name)) {
        $_SESSION['error'] = "Middle name must contain letters only.";
    } elseif (!preg_match("/^[A-Za-z ]+$/", $last_name)) {
        $_SESSION['error'] = "Last name must contain letters only.";
    } elseif (!preg_match("/^[0-9]+$/", $borrower_id)) {
        $_SESSION['error'] = "Borrower ID must contain numbers only.";
    } elseif ($borrower_type === "Student" && strlen($borrower_id) != 7) {
        $_SESSION['error'] = "Student Borrower ID must be 7 digits.";
    } elseif ($borrower_type === "Teacher" && strlen($borrower_id) != 5) {
        $_SESSION['error'] = "Teacher Borrower ID must be 5 digits.";
    }

    if (isset($_SESSION['error'])) {
        header("Location: reserve.php?book_id=$book_id");
        exit();
    }

    // Insert reservation
    $stmt = $conn->prepare("
        INSERT INTO reservations
        (first_name, middle_name, last_name, borrower_name, borrower_id,
         course, year, book_id, reserve_date, borrower_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssisiiss",
        $first_name,
        $middle_name,
        $last_name,
        $borrower_name,
        $borrower_id,
        $course,
        $year,
        $book_id,
        $reserve_date,
        $borrower_type
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Book reserved successfully!";
    } else {
        $_SESSION['error'] = "Reservation failed.";
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
/* Force uppercase visually */
input[name="first_name"],
input[name="middle_name"],
input[name="last_name"] {
    text-transform: uppercase;
}
#courseYear {
    margin-top: 10px;
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

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
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
<input type="text" name="borrower_id" id="borrower_id"
       required pattern="[0-9]+" title="Numbers only">

<div id="courseYear">
    <label>Course</label>
    <input type="text" name="course" pattern="[A-Za-z ]*" title="Letters only">

    <label>Year</label>
    <input type="number" name="year">
</div>

<label>Book ID</label>
<input type="number" name="book_id" value="<?= htmlspecialchars($selected_book) ?>" readonly required>

<label>Reserve Date</label>
<input type="date" name="reserve_date" value="<?= $reserve_date ?>" required>

<button type="submit" name="reserve">Reserve</button>
</form>

<script>
function toggleCourseYear() {
    const type = document.getElementById('borrower_type').value;
    const courseYear = document.getElementById('courseYear');
    const borrowerId = document.getElementById('borrower_id');

    if (type === 'Teacher') {
        courseYear.style.display = 'none';
        borrowerId.placeholder = '5-digit Borrower ID';
    } else {
        courseYear.style.display = 'block';
        borrowerId.placeholder = '7-digit Borrower ID';
    }
}
window.onload = toggleCourseYear;
</script>

</body>
</html>
