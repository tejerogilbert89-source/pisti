<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("127.0.0.1", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ===============================
   DELETE RESERVATION
================================ */
if (isset($_POST['delete_reserve'])) {
    $book_id = (int)$_POST['book_id'];

    $stmt = $conn->prepare("DELETE FROM reservations WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Reservation deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete reservation.";
    }

    $stmt->close();
    header("Location: index.php");
    exit();
}

/* ===============================
   ADJUST BOOK QUANTITY
================================ */
if (isset($_POST['adjust_qty'])) {
    $book_id    = (int)$_POST['book_id'];
    $adjustment = (int)$_POST['adjustment'];

    $stmt = $conn->prepare("SELECT Title, volume FROM books WHERE book_id=?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($book) {
        $new_qty = max(0, $book['volume'] + $adjustment);
        $status  = ($new_qty == 0) ? 'Out of Stock' : 'Available';

        $update = $conn->prepare("
            UPDATE books 
            SET volume=?, available=?, status=? 
            WHERE book_id=?
        ");
        $update->bind_param("iisi", $new_qty, $new_qty, $status, $book_id);
        $update->execute();
        $update->close();

        $_SESSION['message'] = "Updated '{$book['Title']}' quantity to $new_qty";
    }

    header("Location: index.php");
    exit();
}

/* ===============================
   ADD BOOK
================================ */
if (isset($_POST['addItem'])) {
    $qty = (int)$_POST['itemQuantity'];

    $insert = $conn->prepare("
        INSERT INTO books
        (Title, category, status, volume, available, borrowed, Author, Accession_Number, Masterlist, Book_Year)
        VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
    ");

    $insert->bind_param(
        "sssiiisis",
        $_POST['itemTitle'],
        $_POST['itemCategory'],
        $_POST['itemStatus'],
        $qty,
        $qty,
        $_POST['itemAuthor'],
        $_POST['Accession_Number'],
        $_POST['Masterlist'],
        $_POST['Book_Year']
    );

    $insert->execute();
    $insert->close();

    $_SESSION['message'] = "Book added successfully.";
    header("Location: index.php");
    exit();
}

/* ===============================
   FETCH BOOKS + RESERVATIONS
================================ */
$sql = "
SELECT 
    b.*,
    r.student_name AS reserver_name,
    r.student_id   AS reserver_id,
    r.reserve_date
FROM books b
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
<title>Admin | Manage Books</title>
<link rel="stylesheet" href="wars.css">
</head>
<body>

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

<?php if (isset($_SESSION['error'])): ?>
<p style="color:red"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>

<h2>Add New Book</h2>
<form method="POST">
    <input name="itemTitle" placeholder="Title" required>
    <input name="itemAuthor" placeholder="Author" required>
    <input name="itemCategory" placeholder="Category" required>
    <input name="Accession_Number" placeholder="Accession Number" required>
    <input name="Masterlist" placeholder="Masterlist" required>
    <input name="Book_Year" type="number" placeholder="Book Year" required>
    <select name="itemStatus">
        <option value="Available">Available</option>
        <option value="Defective">Defective</option>
        <option value="Out of Stock">Out of Stock</option>
    </select>
    <input type="number" name="itemQuantity" min="1" value="1">
    <button class="btn-primary" name="addItem">Add Book</button>
</form>

<h2>Book List</h2>
<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table id="bookTable">
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Author</th>
    <th>Category</th>
    <th>Accession</th>
    <th>Year</th>
    <th>Status</th>
    <th>Qty</th>
    <th>Borrowed</th>
    <th>Reserver</th>
    <th>Reserve Date</th>
    <th>Reserve</th>
    <th>Actions</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
<td><?= $row['book_id'] ?></td>
<td><?= htmlspecialchars($row['Title']) ?></td>
<td><?= htmlspecialchars($row['Author']) ?></td>
<td><?= $row['category'] ?></td>
<td><?= $row['Accession_Number'] ?></td>
<td><?= $row['Book_Year'] ?></td>
<td><?= $row['status'] ?></td>
<td><?= $row['volume'] ?></td>
<td><?= $row['borrowed'] ?></td>

<td>
<?= $row['reserver_name']
    ? htmlspecialchars($row['reserver_name']) . " ({$row['reserver_id']})"
    : "—"; ?>
</td>

<td>
<?= $row['reserve_date']
    ? date("M d, Y h:i A", strtotime($row['reserve_date']))
    : "—"; ?>
</td>

<td style="text-align:center;">
<?php if ($row['reserver_id']): ?>
    <form method="POST" onsubmit="return confirm('Delete this reservation?')">
        <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
        <button name="delete_reserve" style="background:red;color:white">DELETE</button>

<?php endif; ?>
</td>

<td>
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

<a href="edit.php?book_id=<?= $row['book_id'] ?>">Edit</a>
<a href="delete.php?book_id=<?= $row['book_id'] ?>" onclick="return confirm('Delete this book?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>

</main>

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
