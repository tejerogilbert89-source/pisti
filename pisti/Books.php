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
    r.student_name  AS reserver_name,
    r.student_id    AS reserver_id,
    r.reserve_date

FROM books b

-- Borrow joins (only active borrow)
LEFT JOIN transactions t 
    ON b.book_id = t.book_id 
   AND t.date_returned IS NULL
LEFT JOIN students sb 
    ON t.student_id = sb.student_id

-- Reservation join (latest reservation only)
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
<link rel="stylesheet" href="book.css">
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
    <th>Title</th>
    <th>Author</th>
    <th>Publisher</th>
    <th>Category</th>
    <th>Status</th>
    <th>Qty</th>
    <th>Shelf Location</th>
    <th>Borrowed By</th>
    <th>Reserved By</th>
    <th>Reserve Date</th>
    <th>Reserve</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['Publisher']) ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= htmlspecialchars($row['status']) ?></td>
    <td><?= $row['volume'] ?></td>
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

    <!-- Reserve Date (editable) -->
    <td>
        <?php if ($row['reserve_date']): ?>
            <form method="POST" action="">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <input type="date" name="reserve_date" value="<?= date('Y-m-d', strtotime($row['reserve_date'])) ?>">
                <button type="submit" name="update_date">Update</button>
            </form>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

    <!-- Reserve Button -->
    <td style="text-align:center;">
        <?php if ($row['status'] === 'Available' && !$row['reserver_id']): ?>
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
        if (i === 0) return; // skip header
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>

<?php
/* ===============================
   HANDLE RESERVE DATE UPDATE
================================ */
if (isset($_POST['update_date'], $_POST['book_id'], $_POST['reserve_date'])) {
    $book_id = (int)$_POST['book_id'];
    $reserve_date = $_POST['reserve_date'];

    $stmt = $conn->prepare("UPDATE reservations SET reserve_date = ? WHERE book_id = ?");
    $stmt->bind_param("si", $reserve_date, $book_id);

    if ($stmt->execute()) {
        // Reload page to reflect the updated date
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<p style='color:red;'>Error updating date: " . $conn->error . "</p>";
    }
}
?>
