<?php
session_start();
include "db.php";

// Only allow admin
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// ===============================
// BORROW BOOK
// ===============================
$message = "";

if (isset($_POST['borrow'])) {
    $student_id   = intval($_POST['student_id']);
    $student_name = trim($_POST['student_name']);
    $course       = trim($_POST['course']);
    $year         = intval($_POST['year']);
    $book_id      = intval($_POST['book_id']);

    // Check if book exists
    $book_check = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $book_check->bind_param("i", $book_id);
    $book_check->execute();
    $result = $book_check->get_result();

    if ($result->num_rows > 0) {
        // Insert into borrow_book
        $stmt = $conn->prepare("INSERT INTO borrow_book (Student_ID, Student_NAME, course, year) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $student_id, $student_name, $course, $year);
        $stmt->execute();
        $stmt->close();

        // Update book status to Borrowed
        $conn->query("UPDATE books SET status='Borrowed' WHERE book_id=$book_id");

        $message = "Book borrowed successfully!";
    } else {
        $message = "Book ID not found!";
    }
}

// LOAD BORROWED BOOKS
$borrowed_books = $conn->query("
    SELECT b.Student_ID, b.Student_NAME, b.course, b.year, bo.book_name
    FROM borrow_book b
    LEFT JOIN books bo ON bo.book_id = b.Student_ID
    ORDER BY b.Student_ID DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="stylesheet" content="style.css">
<title>Borrow Books</title>
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="borrow.php">Borrow Books</a></li>
            <li><a href="manage_items.php">Manage Items</a></li>
            <li><a href="TransactionHistory.php">Transaction History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <h1>Borrow Book</h1>

        <?php if (!empty($message)) echo "<p>{$message}</p>"; ?>

        <!-- BORROW BOOK FORM -->
        <form method="POST">
            <input type="number" name="student_id" placeholder="Student ID" required>
            <input type="text" name="student_name" placeholder="Student Name" required>
            <input type="text" name="course" placeholder="Course" required>
            <input type="number" name="year" placeholder="Year" required>
            <input type="number" name="book_id" placeholder="Book ID to Borrow" required>
            <button type="submit" name="borrow">Borrow Book</button>
        </form>

        <!-- BORROWED BOOKS TABLE -->
        <h2>Borrowed Books</h2>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Book Name</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $borrowed_books->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Student_ID']) ?></td>
                        <td><?= htmlspecialchars($row['Student_NAME']) ?></td>
                        <td><?= htmlspecialchars($row['course']) ?></td>
                        <td><?= htmlspecialchars($row['year']) ?></td>
                        <td><?= htmlspecialchars($row['book_name'] ?? 'N/A') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </main>
</div>

</body>
</html>
