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

/* ===============================
   GET BOOK ID (if any)
================================ */
$selected_book = $_GET['book_id'] ?? '';
$reserve_date  = date("Y-m-d"); // today's date

/* ===============================
   RESERVE BOOK LOGIC
================================ */
if (isset($_POST['reserve'])) {

    $book_id      = (int)$_POST['book_id'];
    $student_id   = (int)$_POST['student_id'];
    $student_name = trim($_POST['student_name']);
    $course       = trim($_POST['course']);
    $year         = trim($_POST['year']);
    $reserve_date = $_POST['reserve_date'];

    // Prevent XSS
    $student_name = htmlspecialchars($student_name);
    $course       = htmlspecialchars($course);
    $year         = htmlspecialchars($year);

    // Check if student exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Insert new student
        $add = $conn->prepare("
            INSERT INTO students (student_id, student_name, course, year)
            VALUES (?, ?, ?, ?)
        ");
        $add->bind_param("isss", $student_id, $student_name, $course, $year);
        $add->execute();
        $add->close();
    }
    $stmt->close();

    // Insert reservation
    $stmt = $conn->prepare("
        INSERT INTO reservations 
        (student_name, student_id, course, year, book_id, reserve_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sisiss",
        $student_name,
        $student_id,
        $course,
        $year,
        $book_id,
        $reserve_date
    );
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
    <meta charset="UTF-8">
    <title>Reserve Book</title>
    <link rel="stylesheet" href="reserve.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f7f6;
        }
        h1 {
            color: #0a7a50;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 350px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        button {
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #0a7a50;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 15px;
            padding: 0;
        }
        nav a {
            text-decoration: none;
            color: #0a7a50;
        }
        nav a.active {
            font-weight: bold;
        }
        p.message {
            color: green;
        }
    </style>
</head>
<body>

<h1>Reserve Book</h1>

<nav>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php" >Student Transaction</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<?php if (isset($_SESSION['message'])): ?>
    <p class="message"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Student Name:
        <input type="text" name="student_name" required>
    </label>

    <label>Student ID:
        <input type="number" name="student_id" required>
    </label>

    <label>Course:
        <input type="text" name="course" required>
    </label>

    <label>Year:
        <input type="number" name="year" required>
    </label>

    <label>Book ID:
        <input type="number" name="book_id" value="<?= htmlspecialchars($selected_book) ?>" readonly required>
    </label>

    <label>Reserve Date:
        <input type="date" name="reserve_date" value="<?= $reserve_date ?>" required>
    </label>

    <button type="submit" name="reserve">Reserve</button>
</form>

</body>
</html>
