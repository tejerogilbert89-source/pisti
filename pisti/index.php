<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$servername = "localhost";
$username   = "root";      // Change if your DB username is different
$password   = "";          // Change if your DB password is set
$dbname     = "school_inventory";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
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

        $update = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
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
    $name       = trim($_POST['itemName']);
    $author     = trim($_POST['itemAuthor']);
    $isbn       = trim($_POST['itemISBN']);
    $category   = trim($_POST['itemCategory']);
    $status     = trim($_POST['itemStatus']);
    $qty        = (int)$_POST['itemQuantity'];

    $accession  = $_POST['Accession_Number'];
    $copy       = $_POST['Copy'];
    $masterlist = $_POST['Masterlist'];
    $year       = $_POST['Book_Year'];

    $insert = $conn->prepare(
        "INSERT INTO books 
        (book_name, category, status, volume, ISBN, Author, Accession_Number, Copy, Masterlist, Book_Year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $insert->bind_param(
        "sssississi",
        $name,
        $category,
        $status,
        $qty,
        $isbn,
        $author,
        $accession,
        $copy,
        $masterlist,
        $year
    );

    $insert->execute();
    $insert->close();

    $_SESSION['message'] = "Book added successfully.";
    header("Location: index.php");
    exit();
}

/* ===============================
   FETCH BOOKS + BORROWER + RESERVER
================================ */
$sql = "
SELECT 
    b.*,
    s.student_name AS borrower_name,
    s.student_id   AS borrower_id,
    t.transaction_id,
    t.date_borrowed,
    r.student_name AS reserver_name,
    r.student_id   AS reserver_id
FROM books b
LEFT JOIN transactions t 
    ON b.book_id = t.book_id AND t.date_returned IS NULL
LEFT JOIN students s 
    ON t.student_id = s.student_id
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
<link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Admin</h1>

<?php if (isset($_SESSION['message'])): ?>
    <p style="color:green;"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<!-- NAVIGATION -->
<ul>
    <li><a href="index.php">Books</a></li>
    <li><a href="borrow.php">Borrow / Return</a></li>
    <li><a href="transaction.php" class="active">Transaction History</a></li>
    <li><a href="logout.php">Logout</a></li>
</ul>

<!-- ADD BOOK FORM -->
<h2>Add New Book</h2>
<form method="POST">
    <input name="itemName" placeholder="Book Name" required><br>
    <input name="itemAuthor" placeholder="Author" required><br>
    <input name="itemISBN" placeholder="ISBN" required><br>
    <input name="itemCategory" placeholder="Category" required><br>
    <input name="Accession_Number" placeholder="Accession Number" required><br>
    <input name="Copy" placeholder="Copy" required><br>
    <input name="Masterlist" placeholder="Masterlist" required><br>
    <input name="Book_Year" placeholder="Book Year" required><br>
    <select name="itemStatus">
        <option value="Available">Available</option>
        <option value="Defective">Defective</option>
        <option value="Out of Stock">Out of Stock</option>
    </select><br>
    <input type="number" name="itemQuantity" min="1" value="1" required><br><br>
    <button name="addItem">Add Book</button>
</form>

<!-- SEARCH BOX BELOW ADD BOOK -->
<h3>Search Books</h3>
<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<hr>

<!-- BOOK TABLE -->
<table border="1" id="bookTable">
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
    <th>Actions</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['book_name']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= $row['ISBN'] ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['volume'] ?></td>
    <td><?= $row['borrower_name'] ? htmlspecialchars($row['borrower_name'])." ({$row['borrower_id']})" : "—" ?></td>
    <td><?= $row['date_borrowed'] ?? "—" ?></td>
    <td><?= $row['reserver_name'] ? htmlspecialchars($row['reserver_name'])." ({$row['reserver_id']})" : "—" ?></td>

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

        <a href="edit.php?book_id=<?= $row['book_id'] ?>">Edit</a>

        <!-- Borrow/Return -->
        <?php if ($row['status'] === 'Available' && $row['volume'] > 0): ?>
            <a href="borrow.php?book_id=<?= $row['book_id'] ?>">Borrow</a>
        <?php elseif ($row['borrower_name']): ?>
            <a href="return.php?transaction_id=<?= $row['transaction_id'] ?>">Return</a>
        <?php endif; ?>

        <a href="delete.php?book_id=<?= $row['book_id'] ?>" onclick="return confirm('Delete this book?');">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>

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
