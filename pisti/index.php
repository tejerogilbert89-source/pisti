<?php
session_start();
include "db.php";

/* ===============================
   ADMIN LOGIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* ===============================
   ADD NEW BOOK
================================ */
if (isset($_POST['addItem'])) {
    $available = (int)$_POST['itemQuantity'];
    $status = $available > 0 ? 'Available' : 'Out of Stock';

    $stmt = $conn->prepare("
        INSERT INTO books 
        (Call_Number, Title, Author, category, Accession_Number, Publisher, Shelf_Location, Book_Year, status, volume, available)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "sssssssisii",
        $_POST['Callnumber'],
        $_POST['itemTitle'],
        $_POST['itemAuthor'],
        $_POST['itemCategory'],
        $_POST['Accession_Number'],
        $_POST['itemPublisher'],
        $_POST['itemShelf'],
        $_POST['Book_Year'],
        $status,
        $available,
        $available
    );

    $stmt->execute();
    $_SESSION['message'] = "Book added successfully!";
    header("Location: index.php");
    exit();
}

/* ===============================
   DELETE RESERVATION
================================ */
if (isset($_POST['delete_reserve'])) {
    $book_id = (int)$_POST['book_id'];
    $conn->query("DELETE FROM reservations WHERE book_id = $book_id");
    $_SESSION['message'] = "Reservation deleted.";
    header("Location: index.php");
    exit();
}

/* ===============================
   ADJUST QUANTITY + AUTO STATUS
================================ */
if (isset($_POST['adjust_qty'])) {
    $book_id = (int)$_POST['book_id'];
    $adjustment = (int)$_POST['adjustment'];

    // Get current volume and available
    $book = $conn->query("SELECT volume, available FROM books WHERE book_id = $book_id")->fetch_assoc();
    $new_volume = max(0, $book['volume'] + $adjustment);
    $new_available = max(0, $book['available'] + $adjustment);

    // Determine status
    $status = $new_volume >= 1 ? 'Available' : 'Out of Stock';

    // Update book
    $conn->query("
        UPDATE books SET
            volume = $new_volume,
            available = $new_available,
            status = '$status'
        WHERE book_id = $book_id
    ");

    $_SESSION['message'] = "Book quantity adjusted.";
    header("Location: index.php");
    exit();
}

/* ===============================
   FETCH BOOKS + BORROW + RESERVE
================================ */
$sql = "
SELECT 
    b.*,
    br.borrower_name AS borrower_name,
    br.borrower_id AS borrower_id,
    r.borrower_name  AS reserver_name,
    r.borrower_id    AS reserver_id,
    r.reserve_date
FROM books b
LEFT JOIN transactions t 
    ON b.book_id = t.book_id AND t.date_returned IS NULL
LEFT JOIN borrower br 
    ON t.borrower_id = br.borrower_id
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
<title>Manage Books</title>
<link rel="stylesheet" href="wars.css">
</head>
<body>

<div class="container">

<header class="sidebar">
    <nav>
        <h2>ADMIN</h2>
        <ul>
            <li><a class="active" href="index.php">Books</a></li>
            <li><a href="borrow.php">Borrow / Return</a></li>
            <li><a href="transaction.php">Transaction History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<main class="main">

<h1>Manage Books</h1>

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<h2>Add New Book</h2>
<form method="POST">
    <input name="Callnumber" placeholder="Call Number" required>
    <input name="itemTitle" placeholder="Title" required>
    <input name="itemAuthor" placeholder="Author" required>
    <input name="itemCategory" placeholder="Category" required>
    <input name="Accession_Number" placeholder="Accession Number" required>
    <input name="itemPublisher" placeholder="Publisher" required>
    <input name="itemShelf" placeholder="Shelf Location" required>
    <input type="number" name="Book_Year" placeholder="Year of Publication" required>

    <select name="itemStatus">
        <option value="Available">Available</option>
        <option value="Defective">Defective</option>
        <option value="Out of Stock">Out of Stock</option>
    </select>

    <input type="number" name="itemQuantity" min="1" value="1">
    <button name="addItem">Add Book</button>
</form>

<h2>Book List</h2>
<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table id="bookTable">
<tr>
    <th>ID</th>
    <th>Call No.</th>
    <th>Title</th>
    <th>Author</th>
    <th>Category</th>
    <th>Accession</th>
    <th>Publisher</th>
    <th>Shelf</th>
    <th>Year</th>
    <th>Status</th>
    <th>Qty</th>
    <th>Borrowed</th>
    <th>Reserver</th>
    <th>Reserve Date</th>
    <th>Actions</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['Call_Number']) ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= htmlspecialchars($row['Accession_Number']) ?></td>
    <td><?= htmlspecialchars($row['Publisher']) ?></td>
    <td><?= htmlspecialchars($row['Shelf_Location']) ?></td>
    <td><?= htmlspecialchars($row['Book_Year']) ?></td>
    <td><?= htmlspecialchars($row['status']) ?></td>
    <td><?= (int)$row['volume'] ?></td>

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
    <td><?= $row['reserve_date'] ? date("M d, Y h:i A", strtotime($row['reserve_date'])) : "—" ?></td>

    <!-- Actions -->
    <td>
        <!-- Adjust Quantity -->
        <form method="POST" style="display:inline">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="adjustment" value="1">
            <button name="adjust_qty">+</button>
        </form>
        <form method="POST" style="display:inline">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="adjustment" value="-1">
            <button name="adjust_qty">−</button>
        </form>
        <!-- Edit / Delete -->
        <a href="edit.php?book_id=<?= $row['book_id'] ?>">Edit</a>
        <a href="delete.php?book_id=<?= $row['book_id'] ?>" onclick="return confirm('Delete this book?')">Delete</a>
        <br>
        <!-- Reserve Details / Delete Reservation -->
        <?php if ($row['reserver_id']): ?>
            <form method="GET" action="reserve_details.php" style="display:inline">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button type="submit">RESERVE DETAILS</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this reservation?')">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button name="delete_reserve" style="background:red;color:white">DELETE</button>
            </form>
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
