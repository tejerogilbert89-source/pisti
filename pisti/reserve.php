<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "school_inventory";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   GET BOOK ID (if any)
================================ */
$selected_book = $_GET['book_id'] ?? '';
$reserve_date  = date("Y-m-d"); // today's date

/* ===============================
   RESERVE BOOK LOGIC
================================ */
if (isset($_POST['reserve'])) {

    $borrower_type = $_POST['borrower_type'];
    $book_id       = (int)$_POST['book_id'];
    $student_id    = (int)$_POST['student_id'];
    $student_name  = htmlspecialchars(trim($_POST['student_name']));
    $course        = htmlspecialchars(trim($_POST['course']));
    $year          = trim($_POST['year']);
    $reserve_date  = $_POST['reserve_date'];

    // Validate ID length
    if ($borrower_type === "Student" && strlen((string)$student_id) != 7) {
        $_SESSION['error'] = "Student ID must be 7 digits.";
        header("Location: reserve.php");
        exit();
    }
    if ($borrower_type === "Teacher" && strlen((string)$student_id) != 5) {
        $_SESSION['error'] = "Teacher ID must be 5 digits.";
        header("Location: reserve.php");
        exit();
    }

    // Check if student/teacher exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Insert new student/teacher
        $stmt_insert = $conn->prepare("
            INSERT INTO students (student_id, student_name, course, year, borrower_type, phone_number)
            VALUES (?, ?, ?, ?, ?, '')
        ");
        $year_int = ($borrower_type === "Student" && is_numeric($year)) ? (int)$year : 0;
        $stmt_insert->bind_param("issis", $student_id, $student_name, $course, $year_int, $borrower_type);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();

    // Insert reservation
    $stmt = $conn->prepare("
        INSERT INTO reservations 
        (student_name, student_id, course, year, book_id, reserve_date, borrower_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $year_int = ($borrower_type === "Student" && is_numeric($year)) ? (int)$year : 0;
    $stmt->bind_param(
        "sisisss",
        $student_name,
        $student_id,
        $course,
        $year_int,
        $book_id,
        $reserve_date,
        $borrower_type
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = ucfirst($borrower_type) . " reserved book successfully!";
    header("Location: reserve.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reserve Book</title>
</head>
<body>

<h1>Reserve Book</h1>

<nav>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php">Student Transaction</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<?php if (isset($_SESSION['message'])): ?>
    <p style="color:green;"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <p style="color:red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Borrower Type:
        <select name="borrower_type" id="borrower_type" onchange="toggleCourseYear()">
            <option value="Student">Student</option>
            <option value="Teacher">Teacher</option>
        </select>
    </label><br><br>

    <label>Name:
        <input type="text" name="student_name" required>
    </label><br><br>

    <label>ID:
        <input type="number" name="student_id" id="borrower_id" required>
    </label><br><br>

    <div id="courseYear">
        <label>Course:
            <input type="text" name="course">
        </label><br><br>

        <label>Year:
            <input type="number" name="year">
        </label><br><br>
    </div>

    <label>Book ID:
        <input type="number" name="book_id" value="<?= htmlspecialchars($selected_book) ?>" readonly required>
    </label><br><br>

    <label>Reserve Date:
        <input type="date" name="reserve_date" value="<?= $reserve_date ?>" required>
    </label><br><br>

    <button type="submit" name="reserve">Reserve</button>
</form>

<script>
function toggleCourseYear() {
    let type = document.getElementById('borrower_type').value;
    let courseYear = document.getElementById('courseYear');
    let borrowerId = document.getElementById('borrower_id');

    if(type === "Teacher") {
        courseYear.style.display = 'none';
        borrowerId.placeholder = "5-digit Teacher ID";
    } else {
        courseYear.style.display = 'block';
        borrowerId.placeholder = "7-digit Student ID";
    }
}

// Initialize on page load
window.onload = toggleCourseYear;
</script>

</body>
</html>
