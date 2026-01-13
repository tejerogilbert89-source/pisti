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
if (!isset($_SESSION['username']) || $_SESSION['username'] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ===============================
   ADJUST BOOK QUANTITY
================================ */
if (isset($_POST['adjust_qty'])) {

    $book_id    = (int)$_POST['book_id'];
    $adjustment = (int)$_POST['adjustment'];

    $stmt = $conn->prepare("SELECT book_name, volume FROM books WHERE book_id=?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($book) {
        $new_qty = max(0, $book['volume'] + $adjustment);
        $status  = ($new_qty == 0) ? 'Out of Stock' : 'Available';

        $update = $conn->prepare(
            "UPDATE books SET volume=?, status=? WHERE book_id=?"
        );
        $update->bind_param("isi", $new_qty, $status, $book_id);
        $update->execute();
        $update->close();

        $_SESSION['message'] = "Updated '{$book['book_name']}' quantity to $new_qty";
    }

    header("Location: index.php");
    exit();
}

/* ===============================
   ADD BOOK
================================ */
if (isset($_POST['addItem'])) {

    $insert = $conn->prepare("
        INSERT INTO books
        (book_name, category, status, volume, ISBN, Author,
         Accession_Number, Copy, Masterlist, Book_Year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insert->bind_param(
        "sssississi",
        $_POST['itemName'],
        $_POST['itemCategory'],
        $_POST['itemStatus'],
        $_POST['itemQuantity'],
        $_POST['itemISBN'],
        $_POST['itemAuthor'],
        $_POST['Accession_Number'],
        $_POST['Copy'],
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
   FETCH BOOKS + BORROW + RESERVE
================================ */
$sql = "
SELECT 
    b.*,

    -- Borrow
    s.student_name AS borrower_name,
    s.student_id   AS borrower_id,
    t.transaction_id,
    t.date_borrowed,

    -- Reservation
    r.student_name AS reserver_name,
    r.student_id   AS reserver_id,
    r.reserve_date

FROM books b

LEFT JOIN transactions t
    ON b.book_id = t.book_id
   AND t.date_returned IS NULL

LEFT JOIN students s
    ON t.student_id = s.student_id

LEFT JOIN reservations r
    ON b.book_id = r.book_id

ORDER BY b.book_id DESC
";

$books = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin | Manage Books</title>
<link rel="stylesheet" href="transaction_copy.css">
</head>
<body>

<header>ADMIN PANEL</header>

<div class="container">

<nav>
<ul>
    <li><a class="active" href="index.php">Books</a></li>
    <li><a href="borrow.php">Borrow / Return</a></li>
    <li><a href="transaction.php">Transaction History</a></li>
    <li><a href="logout.php">Logout</a></li>
</ul>
</nav>

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green">
<?= $_SESSION['message']; unset($_SESSION['message']); ?>
</p>
<?php endif; ?>

<h2>Add New Book</h2>

<form method="POST">
    <input name="itemName" placeholder="Book Name" required>
    <input name="itemAuthor" placeholder="Author" required>
    <input name="itemISBN" placeholder="ISBN" required>
    <input name="itemCategory" placeholder="Category" required>
    <input name="Accession_Number" placeholder="Accession Number" required>
    <input name="Copy" placeholder="Copy" required>
    <input name="Masterlist" placeholder="Masterlist" required>
    <input name="Book_Year" placeholder="Book Year" required>

    <select name="itemStatus">
        <option value="Available">Available</option>
        <option value="Defective">Defective</option>
        <option value="Out of Stock">Out of Stock</option>
    </select>

    <input type="number" name="itemQuantity" min="1" value="1">
    <button name="addItem">Add Book</button>
</form>

<hr>

<input type="text" id="search" placeholder="Search..." onkeyup="searchTable()">

<table id="bookTable" border="1">
<tr>
    <th>ID</th>
    <th>Book</th>
    <th>Author</th>
    <th>ISBN</th>
    <th>Category</th>
    <th>Status</th>
    <th>Qty</th>
    <th>Borrower</th>
    <th>Date Borrowed</th>
    <th>Reserver</th>
    <th>Reserve Date</th>
    <th>Actions</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
<td><?= $row['book_id'] ?></td>
<td><?= htmlspecialchars($row['book_name']) ?></td>
<td><?= htmlspecialchars($row['Author']) ?></td>
<td><?= $row['ISBN'] ?></td>
<td><?= $row['category'] ?></td>
<td><?= $row['status'] ?></td>
<td><?= $row['volume'] ?></td>

<td>
<?= $row['borrower_name']
    ? $row['borrower_name']." ({$row['borrower_id']})"
    : "—"; ?>
</td>

<td><?= $row['date_borrowed'] ?? "—" ?></td>

<td>
<?= $row['reserver_name']
    ? $row['reserver_name']." ({$row['reserver_id']})"
    : "—"; ?>
</td>

<td>
<?= $row['reserve_date']
    ? date("M d, Y", strtotime($row['reserve_date']))
    : "—"; ?>
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

<?php if ($row['borrower_name']): ?>
    <a href="return.php?transaction_id=<?= $row['transaction_id'] ?>">Return</a>
<?php elseif ($row['status'] === 'Available' && $row['volume'] > 0): ?>
    <a href="borrow.php?book_id=<?= $row['book_id'] ?>">Borrow</a>
<?php endif; ?>

<a href="delete.php?book_id=<?= $row['book_id'] ?>"
   onclick="return confirm('Delete this book?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>

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
