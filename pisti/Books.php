<?php
session_start();
include "db.php";

/* ===============================
   LOGIN CHECK (BORROWER)
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$borrower_id = (int)$_SESSION['borrower_id'];

/* ===============================
   FETCH BOOKS + BORROW + RESERVE
================================ */
$sql = "
SELECT 
    b.book_id,
    b.Title,
    b.Author,
    b.Publisher,
    b.category,
    b.status,
    b.volume,
    b.available,
    b.Call_Number,
    b.Shelf_Location,

    -- Borrow info
    s1.borrower_name AS borrower_name,
    s1.borrower_id   AS borrower_id,
    t.date_borrowed,

    -- Reservation info
    r.borrower_name  AS reserver_name,
    r.borrower_id    AS reserver_id,
    r.reserve_date

FROM books b

LEFT JOIN transactions t 
    ON b.book_id = t.book_id
   AND t.date_returned IS NULL

LEFT JOIN borrower s1 
    ON t.borrower_id = s1.borrower_id

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
<title>Library Books</title>
<link rel="stylesheet" href="books.css">
</head>
<body>

<div class="container">

<aside class="sidebar">
    <h2>BORROWER</h2>
    <ul>
        <li><a class="active" href="#">Books</a></li>
        <li><a href="student_transaction.php">My Transactions</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main">

<h1>Library Books</h1>

<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table id="bookTable" cellpadding="6">
<tr>
    <th>ID</th>
    <th>Call No.</th>
    <th>Title</th>
    <th>Author</th>
    <th>Publisher</th>
    <th>Category</th>
    <th>Status</th>
    <th>Total Copies</th>
    <th>Available</th>
    <th>Shelf</th>
    <th>Borrowed By</th>
    <th>Reserved By</th>
    <th>Reserve Date</th>
    <th>Action</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>

<td><?= $row['book_id'] ?></td>
<td><?= htmlspecialchars($row['Call_Number']) ?></td>
<td><?= htmlspecialchars($row['Title']) ?></td>
<td><?= htmlspecialchars($row['Author']) ?></td>
<td><?= htmlspecialchars($row['Publisher']) ?></td>
<td><?= htmlspecialchars($row['category']) ?></td>
<td><?= htmlspecialchars($row['status']) ?></td>
<td><?= (int)$row['volume'] ?></td>
<td><?= (int)$row['available'] ?></td>
<td><?= htmlspecialchars($row['Shelf_Location']) ?></td>

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

<!-- Reserve Date -->
<td>
<?= $row['reserve_date']
    ? date("M d, Y", strtotime($row['reserve_date']))
    : "—"; ?>
</td>

<!-- Action -->
<td style="text-align:center;">
<?php if ($row['available'] > 0 && !$row['reserver_id']): ?>
    <form method="GET" action="reserve.php">
        <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
        <button type="submit">RESERVE</button>
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
