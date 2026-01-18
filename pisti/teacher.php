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
    $borrower_id = trim($_POST['borrower_id']);  
    $borrower_name = trim($_POST['borrower_name']);
    $course = trim($_POST['course']);
    $year = trim($_POST['year']);
    $book_id = intval($_POST['book_id']);

    // Determine borrower type
    if (strlen($borrower_id) == 7) {
        $type = 'Student';
    } elseif (strlen($borrower_id) == 5) {
        $type = 'Teacher';
    } else {
        $_SESSION['error'] = "Invalid ID format. Students 7-digit, Teachers 5-digit.";
        header("Location: borrow.php");
        exit();
    }

    // Check book availability
    $stmt = $conn->prepare("SELECT Title, volume, status FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $bookResult = $stmt->get_result();
    if ($bookResult->num_rows == 0) {
        $_SESSION['error'] = "Book not found.";
        header("Location: borrow.php");
        exit();
    }
    $book = $bookResult->fetch_assoc();
    $stmt->close();

    if ($book['volume'] <= 0 || $book['status'] == 'Out of Stock') {
        $_SESSION['error'] = "Book '{$book['Title']}' is not available.";
        header("Location: borrow.php");
        exit();
    }

    // Check if borrower exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $borrower_id);
    $stmt->execute();
    $borrowerResult = $stmt->get_result();
    if ($borrowerResult->num_rows == 0) {
        // Insert new borrower
        $stmt_insert = $conn->prepare("INSERT INTO students (student_id, student_name, course, phone_number, year, borrower_type) VALUES (?, ?, ?, '', ?, ?)");
        $stmt_insert->bind_param("issis", $borrower_id, $borrower_name, $course, $year, $type);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();

    // Borrow book
    $stmt = $conn->prepare("INSERT INTO transactions (book_id, student_id, date_borrowed) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $book_id, $borrower_id);
    if ($stmt->execute()) {
        $new_qty = $book['volume'] - 1;
        $new_status = $new_qty > 0 ? 'Available' : 'Out of Stock';
        $updateStmt = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
        $updateStmt->bind_param("isi", $new_qty, $new_status, $book_id);
        $updateStmt->execute();
        $updateStmt->close();

        $_SESSION['message'] = "Book '{$book['Title']}' borrowed by {$borrower_name} successfully!";
        header("Location: borrow.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to borrow book: " . $stmt->error;
    }
    $stmt->close();
}

/* ===============================
   RETURN BOOK
================================ */
if (isset($_POST['return'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("
        SELECT t.book_id, b.Title, b.volume
        FROM transactions t
        JOIN books b ON t.book_id = b.book_id
        WHERE t.transaction_id = ? AND t.date_returned IS NULL
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "Transaction not found or already returned.";
        header("Location: borrow.php");
        exit();
    }
    $trans = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE transactions SET date_returned = NOW() WHERE transaction_id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $new_qty = $trans['volume'] + 1;
    $new_status = 'Available';
    $stmt = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
    $stmt->bind_param("isi", $new_qty, $new_status, $trans['book_id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book '{$trans['Title']}' returned successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   DELETE TRANSACTION
================================ */
if (isset($_POST['delete'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("SELECT transaction_id FROM transactions WHERE transaction_id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "Transaction not found.";
    } else {
        $stmt_del = $conn->prepare("DELETE FROM transactions WHERE transaction_id = ?");
        $stmt_del->bind_param("i", $transaction_id);
        if ($stmt_del->execute()) {
            $_SESSION['message'] = "Transaction deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete transaction: " . $stmt_del->error;
        }
        $stmt_del->close();
    }
    $stmt->close();
    header("Location: borrow.php");
    exit();
}

/* ===============================
   FETCH BOOKS & BORROWED BOOKS
================================ */
$books = $conn->query("SELECT * FROM books ORDER BY Title ASC");
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed, s.student_name, s.borrower_type, s.course, s.year, b.*
    FROM transactions t
    LEFT JOIN students s ON t.student_id = s.student_id
    LEFT JOIN books b ON t.book_id = b.book_id
    WHERE t.date_returned IS NULL
    ORDER BY t.date_borrowed ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Borrow / Return Books</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a class="active" href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main">
<h1>Borrow / Return Books</h1>

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green;"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<p style="color:red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>
<button name="borrow_student">Borrow as Student</button>
<button name="teacher">Borrow as Teacher</button>

<h2>Manual Borrow</h2>
<form method="POST">
<button name="borrow_student">Borrow as Student</button>
<button name="borrow_teacher">Borrow as Teacher</button>
    <label>Borrower Name:
        <input type="text" name="borrower_name" placeholder="Full Name" required>
    </label><br>
    <label>Borrower ID:
        <input type="text" name="borrower_id" placeholder="Students 7-digit / Teachers 5-digit" required>
    </label><br>
    <label>Book ID: <input type="number" name="book_id" required></label><br>
    <button type="submit" name="borrow">Borrow Book</button>

</form>

<h2>Borrowed Books</h2>
<input type="text" id="returnSearch" placeholder="Search borrowed books..." onkeyup="searchTable('returnSearch', 'returnTable')">

<table id="returnTable" border="1" cellpadding="5" cellspacing="0">
<thead>
<tr>
    <th>Title</th>
    <th>Author</th>
    <th>Category</th>
    <th>Accession Number</th>
    <th>Book Year</th>
    <th>Borrower Name</th>
    <th>Type</th>
    <th>Course / Year</th>
    <th>Borrowed Since</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($row = $borrowed->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['Author'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= htmlspecialchars($row['Accession_Number']) ?></td>
    <td><?= htmlspecialchars($row['Book_Year']) ?></td>
    <td><?= htmlspecialchars($row['student_name'] ?: 'Teacher ID: '.$row['student_id']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type'] ?: 'Teacher') ?></td>
    <td><?= htmlspecialchars($row['course'] ?? '—') ?> / <?= $row['year'] ?? '—' ?></td>
    <td><?= $row['date_borrowed'] ?></td>
    <td>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this book as returned?');">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button type="submit" name="return">Return</button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this transaction?');">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button type="submit" name="delete" style="background:red;color:white;">Delete</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</main>

<script>
function searchTable(inputId, tableId) {
    let input = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll("#" + tableId + " tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
