<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] != "admin") {
    header("Location: login.php");
    exit();
}

/* ===============================
   BORROW BOOK
================================ */
if (isset($_POST['borrow'])) {

    $borrower_id   = trim($_POST['borrower_id']);
    $borrower_name = trim($_POST['borrower_name']);
    $course        = isset($_POST['course']) ? trim($_POST['course']) : '';
    $year          = intval($_POST['year']);
    $book_id       = intval($_POST['book_id']);
    $type          = $_POST['borrower_type'];

    if ($type == "Student" && strlen($borrower_id) != 7) {
        $_SESSION['error'] = "Student ID must be 7 digits.";
        header("Location: borrow.php");
        exit();
    }
    if ($type == "Teacher" && strlen($borrower_id) != 5) {
        $_SESSION['error'] = "Teacher ID must be 5 digits.";
        header("Location: borrow.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT Title, volume, status FROM books WHERE book_id=?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book) {
        $_SESSION['error'] = "Book not found.";
        header("Location: borrow.php");
        exit();
    }

    if ($book['volume'] <= 0) {
        $_SESSION['error'] = "Book '{$book['Title']}' is out of stock.";
        header("Location: borrow.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
    $stmt->bind_param("i", $borrower_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows;
    $stmt->close();

    if (!$exists) {
        $stmt = $conn->prepare("INSERT INTO students(student_id, student_name, course, year, borrower_type, phone_number) VALUES (?, ?, ?, ?, ?, '')");
        $stmt->bind_param("issis", $borrower_id, $borrower_name, $course, $year, $type);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO transactions(book_id, student_id, date_borrowed) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $book_id, $borrower_id);
    $stmt->execute();
    $stmt->close();

    $new_qty = $book['volume'] - 1;
    $new_status = $new_qty > 0 ? "Available" : "Out of Stock";
    $stmt = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
    $stmt->bind_param("isi", $new_qty, $new_status, $book_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book '{$book['Title']}' borrowed successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   RETURN BOOK
================================ */
if (isset($_POST['return'])) {

    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("
        SELECT t.book_id, b.Title, b.volume
        FROM transactions t
        JOIN books b ON t.book_id=b.book_id
        WHERE t.transaction_id=? AND t.date_returned IS NULL
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        $_SESSION['error'] = "Transaction already returned or not found.";
        header("Location: borrow.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE transactions SET date_returned=NOW() WHERE transaction_id=?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $new_qty = $data['volume'] + 1;
    $stmt = $conn->prepare("UPDATE books SET volume=?, status='Available' WHERE book_id=?");
    $stmt->bind_param("ii", $new_qty, $data['book_id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book '{$data['Title']}' returned successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   DELETE TRANSACTION
================================ */
if (isset($_POST['delete'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("DELETE FROM transactions WHERE transaction_id=?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Transaction deleted.";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   FETCH DATA
================================ */
$books = $conn->query("SELECT * FROM books ORDER BY Title ASC");
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed, s.student_name, s.borrower_type, s.course, s.year, b.*
    FROM transactions t
    LEFT JOIN students s ON t.student_id=s.student_id
    LEFT JOIN books b ON t.book_id=b.book_id
    WHERE t.date_returned IS NULL
    ORDER BY t.date_borrowed ASC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Books</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<!-- Main Content -->
<div class="main-content">
<h1>Borrow / Return Books</h1>

<?php 
if(isset($_SESSION['message'])){
    echo "<p style='color:green'>{$_SESSION['message']}</p>";
    unset($_SESSION['message']);
} 
if(isset($_SESSION['error'])){
    echo "<p style='color:red'>{$_SESSION['error']}</p>";
    unset($_SESSION['error']);
} 
?>

<form method="POST" id="borrowForm">

    <label>Borrower Type:</label>
    <select name="borrower_type" id="borrower_type" required onchange="toggleFields()">
        <option value="Student">Student</option>
        <option value="Teacher">Teacher</option>
    </select>

    <label>Borrower Name:</label>
    <input type="text" name="borrower_name" placeholder="Full Name" required>

    <label id="idLabel">Borrower ID:</label>
    <input type="text" name="borrower_id" id="borrower_id" placeholder="7-digit Student ID" required>

    <div id="courseYear">
        <label>Course:</label>
        <select name="course">
            <option value="">Select Course</option>
            <option value="BSIT">BSIT</option>
            <option value="BSHT">BSHT</option>
            <option value="BSBA">BSBA</option>
            <option value="BSA">BSA</option>
        </select>

        <label>Year:</label>
        <input type="text" name="year" placeholder="Year">
    </div>

    <label>Book ID:</label>
    <input type="number" name="book_id" required>

    <button type="submit" name="borrow">Borrow</button>
</form>

<h2>Borrowed Books</h2>
<table border="1" cellpadding="5">
<tr>
<th>Title</th>
<th>Borrower</th>
<th>Type</th>
<th>Course/Year</th>
<th>Date Borrowed</th>
<th>Action</th>
</tr>

<?php while($row=$borrowed->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['Title']) ?></td>
<td><?= htmlspecialchars($row['student_name']) ?></td>
<td><?= htmlspecialchars($row['borrower_type']) ?></td>
<td><?= !empty($row['course']) ? htmlspecialchars($row['course']) : 'N/A' ?> / <?= !empty($row['year']) ? htmlspecialchars($row['year']) : 'N/A' ?></td>
<td><?= htmlspecialchars($row['date_borrowed']) ?></td>
<td>
<form method="POST" style="display:inline">
<input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
<button name="return">Return</button>
</form>
<form method="POST" style="display:inline">
<input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
<button name="delete">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>

<script>
function toggleFields() {
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

window.onload = toggleFields;
</script>

</div>
</body>
</html>
