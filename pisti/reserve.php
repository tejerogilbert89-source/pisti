<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$servername = "localhost";
$username   = "root";      // change if your DB username is different
$password   = "";          // change if your DB password is set
$dbname     = "school_inventory";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
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

$selected_book = $_GET['book_id'] ?? '';

/* ===============================
   RESERVE BOOK
================================ */
if (isset($_POST['reserve'])) {
    $book_id      = (int)$_POST['book_id'];
    $student_id   = (int)$_POST['student_id'];
    $student_name = trim($_POST['student_name']);
    $course       = trim($_POST['course']);
    $year         = trim($_POST['year']);

    // Optional: sanitize input further if needed
    $student_name = htmlspecialchars($student_name);
    $course       = htmlspecialchars($course);
    $year         = htmlspecialchars($year);

    // Check if student exists in a students table (if you have one)
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Insert new student into students table
        $add = $conn->prepare("INSERT INTO students (student_id, student_name, course, year) VALUES (?, ?, ?, ?)");
        $add->bind_param("isss", $student_id, $student_name, $course, $year);
        $add->execute();
        $add->close();
    }
    $stmt->close();

    // Insert reservation
    $stmt = $conn->prepare("INSERT INTO reservations (student_name, student_id, course, year, book_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisii", $student_name, $student_id, $course, $year, $book_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book reserved successfully!";
    header("Location: reserve.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reserve Book</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Reserve Book</h1>

<nav>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php" class="active">Student Transaction</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<?php if (isset($_SESSION['message'])): ?>
    <p style="color:green"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Student Name: <input type="text" name="student_name" required></label><br>
    <label>Student ID: <input type="number" name="student_id" required></label><br>
    <label>Course: <input type="text" name="course" required></label><br>
    <label>Year: <input type="number" name="year" required></label><br>

    <label>Book ID:
        <input type="number" name="book_id" value="<?= htmlspecialchars($selected_book) ?>" required readonly>
    </label><br><br>

    <button type="submit" name="reserve">Reserve</button>
</form>

</body>
</html>
