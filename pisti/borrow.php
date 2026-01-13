<?php
session_start();
include "db.php";

// Only allow admin
if (!isset($_SESSION['username']) || $_SESSION['username'] != "admin") {
    header("Location: login.php");
    exit();
}

// ------------------------
// BORROW BOOK
// ------------------------
if (isset($_POST['borrow'])) {
    $book_id = intval($_POST['book_id']);
    $student_id = intval($_POST['student_id']);
    $student_name = trim($_POST['student_name']);
    $course = trim($_POST['course']);
    $year = trim($_POST['year']);

    // Check book
    $stmt = $conn->prepare("SELECT book_name, volume, status FROM books WHERE book_id = ?");
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
        $_SESSION['error'] = "Book '{$book['book_name']}' is not available.";
        header("Location: borrow.php");
        exit();
    }

    // Check if student exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    if ($studentResult->num_rows == 0) {
        $stmt_insert = $conn->prepare("INSERT INTO students (student_id, student_name, course, year) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("isss", $student_id, $student_name, $course, $year);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();

    // Insert transaction
    $stmt = $conn->prepare("INSERT INTO transactions (book_id, student_id, date_borrowed) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $book_id, $student_id);
    if ($stmt->execute()) {
        $new_qty = $book['volume'] - 1;
        $new_status = $new_qty > 0 ? 'Available' : 'Out of Stock';
        $updateStmt = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
        $updateStmt->bind_param("isi", $new_qty, $new_status, $book_id);
        $updateStmt->execute();
        $updateStmt->close();

        $_SESSION['message'] = "Book '{$book['book_name']}' borrowed by {$student_name} successfully!";
        header("Location: borrow.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to borrow book: " . $stmt->error;
    }
    $stmt->close();
}

// ------------------------
// RETURN BOOK
// ------------------------
if (isset($_POST['return'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("
        SELECT t.book_id, b.book_name, b.volume
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

    $_SESSION['message'] = "Book '{$trans['book_name']}' returned successfully!";
    header("Location: borrow.php");
    exit();
}

// ------------------------
// DELETE TRANSACTION
// ------------------------
if (isset($_POST['delete'])) {
    $transaction_id = intval($_POST['transaction_id']);

    // Check if transaction exists
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

// Fetch all books
$books = $conn->query("SELECT book_id, book_name, author, isbn, category, volume, status FROM books ORDER BY book_name ASC");

// Fetch borrowed books
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed, b.book_name, b.author, b.isbn, b.category,
           s.student_name, s.course, s.year
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    JOIN students s ON t.student_id = s.student_id
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

<!-- Sidebar -->
<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php"> Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php"> Transaction History</a></li>
        <li><a href="logout.php"> Logout</a></li>
    </ul>
</aside>

<!-- Main content -->
<main class="main">

<h1>Borrow / Return Books</h1>

<?php if (isset($_SESSION['message'])): ?>
    <p style="color:green;"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <p style="color:red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>

<!-- Manual Borrow Form -->
<h2>Manual Borrow</h2>
<form method="POST">
    <label>Student Name: <input type="text" name="student_name" required></label><br>
    <label>Student ID: <input type="number" name="student_id" required></label><br>
    <label>Course: <input type="text" name="course" required></label><br>
    <label>Year: <input type="text" name="year" required></label><br>
    <label>Book ID: <input type="number" name="book_id" required></label><br>
    <button type="submit" name="borrow">Borrow Book</button>
</form>

<!-- Borrowed Books Table -->
<h2>Return / Delete Borrowed Books</h2>
<input type="text" id="returnSearch" placeholder="Search borrowed books..." onkeyup="searchTable('returnSearch', 'returnTable')">
<table id="returnTable" border="1" cellpadding="5" cellspacing="0">
<thead>
<tr>
    <th>Book Name</th>
    <th>Author</th>
    <th>ISBN</th>
    <th>Category</th>
    <th>Student</th>
    <th>Course / Year</th>
    <th>Borrowed Since</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($row = $borrowed->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['book_name']) ?></td>
    <td><?= htmlspecialchars($row['author'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['isbn'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= htmlspecialchars($row['student_name']) ?></td>
    <td><?= htmlspecialchars($row['course']) ?> / <?= $row['year'] ?></td>
    <td><?= $row['date_borrowed'] ?></td>
    <td>
        <!-- Return Button -->
        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this book as returned?');">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button type="submit" name="return">Return</button>
        </form>
        <!-- Delete Button -->
        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this transaction?');">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button type="submit" name="delete" style="background:red; color:white;">Delete</button>
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