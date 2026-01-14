<?php
session_start();
include "db.php";

/* ===============================
   STUDENT CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   FETCH TRANSACTIONS JOIN BOOKS + STUDENTS
================================ */
$sql = "
    SELECT t.transaction_id,
           t.date_borrowed,
           t.date_returned,
           s.student_name,
           s.student_id,
           s.course,
           s.year,
           b.Title
    FROM transactions t
    LEFT JOIN students s ON t.student_id = s.student_id
    LEFT JOIN books b ON t.book_id = b.book_id
    ORDER BY t.transaction_id DESC
";

$transactions = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Transaction</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar">
    <h2>STUDENT</h2>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php" class="active">Student Transaction</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="main">
    <h1>Student Transaction</h1>

    <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">

    <table id="transactionTable" border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date Borrowed</th>
                <th>Date Returned</th>
                <th>Student</th>
                <th>Student ID</th>
                <th>Course</th>
                <th>Year</th>
                <th>Book</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                    <td><?= $row['date_borrowed'] ? date("M d, Y", strtotime($row['date_borrowed'])) : '-' ?></td>
                    <td><?= $row['date_returned'] ? date("M d, Y", strtotime($row['date_returned'])) : '-' ?></td>
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                    <td><?= htmlspecialchars($row['course']) ?></td>
                    <td><?= htmlspecialchars($row['year']) ?></td>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td>1</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    document.querySelectorAll("#transactionTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
