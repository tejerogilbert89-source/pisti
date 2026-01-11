<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   FETCH BOOKS + BORROWER + RESERVER
================================ */
$sql = "
SELECT 
    b.*,

    -- Borrow info
    sb.student_name AS borrower_name,
    sb.student_id   AS borrower_id,
    t.date_borrowed,

    -- Reserve info
    r.student_name AS reserver_name,
    r.student_id   AS reserver_id

FROM books b

-- Borrow joins
LEFT JOIN transactions t 
    ON b.book_id = t.book_id 
   AND t.date_returned IS NULL
LEFT JOIN students sb 
    ON t.student_id = sb.student_id

-- Reservation joins
LEFT JOIN reservations r 
    ON b.book_id = r.book_id

ORDER BY b.book_id DESC
";

$books = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Books</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

<aside class="sidebar">
    <h2>STUDENT</h2>
    <ul>
        <li><a class="active" href="#">Books</a></li>
        <li><a href="Student_transaction.php">Student Transaction</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main">

<h1>Books</h1>

<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table id="bookTable" border="1" cellpadding="5" cellspacing="0">
<tr>
    <th>ID</th>
    <th>Book</th>
    <th>Author</th>
    <th>ISBN</th>
    <th>Category</th>
    <th>Status</th>
    <th>Qty</th>
    <th>Borrowed By</th>
    <th>Reserved By</th>
    <th>Reserve</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['book_name']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['ISBN']) ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= htmlspecialchars($row['status']) ?></td>
    <td><?= $row['volume'] ?></td>

    <!-- Borrower -->
    <td>
        <?= $row['borrower_name'] 
            ? htmlspecialchars($row['borrower_name']) . " ({$row['borrower_id']})"
            : "—"; ?>
    </td>

    <!-- Reserver -->
    <td>
        <?= $row['reserver_name'] 
            ? htmlspecialchars($row['reserver_name']) . " ({$row['reserver_id']})"
            : "—"; ?>
    </td>

    <!-- Reserve icon -->
    <td style="text-align:center;">
        <?php if ($row['status'] === 'Available' && !$row['reserver_id']): ?>
            <form method="GET" action="reserve.php" title="Reserve Book">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button type="submit" style="border:none;background:none;font-size:18px;">RESERVE</button>
            </form>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

</main>
</div>

<script>
function searchTable() {
    let input = document.getElementById("search").value.toLowerCase();
    document.querySelectorAll("#bookTable tr").forEach((row, i) => {
        if (i === 0) return;
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
