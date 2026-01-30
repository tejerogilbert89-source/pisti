<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN LOGIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* ===============================
   AUTO SYNC COPIES BASED ON BORROW
================================ */
/*
   borrowed  = active transactions
   available = Copies - borrowed
*/
$conn->query("
    UPDATE books b
    LEFT JOIN (
        SELECT book_id, COUNT(*) AS borrowed_count
        FROM transactions
        WHERE date_returned IS NULL
        GROUP BY book_id
    ) t ON b.book_id = t.book_id
    SET
        b.borrowed  = IFNULL(t.borrowed_count, 0),
        b.available = GREATEST(b.Copies - IFNULL(t.borrowed_count, 0), 0),
        b.status    = IF(GREATEST(b.Copies - IFNULL(t.borrowed_count, 0), 0) > 0,
                         'Available', 'Out of Stock')
");

/* ===============================
   ADD NEW BOOK
================================ */
if (isset($_POST['addItem'])) {

    $copies = max(1, (int)$_POST['itemCopies']);

    $stmt = $conn->prepare("
        INSERT INTO books
        (Call_Number, Title, Author, Edition, Accession_Number, Imprint,
         Publisher, Shelf_Location, Copies, volume, available, borrowed, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssssssssiiiis",
        $_POST['Callnumber'],
        $_POST['itemTitle'],
        $_POST['itemAuthor'],
        $_POST['itemEdition'],
        $_POST['Accession_Number'],
        $_POST['IMPRINT'],
        $_POST['itemPublisher'],
        $_POST['itemShelf'],
        $copies,
        $copies,
        $copies,
        $zero = 0,
        $status = 'Available'
    );

    $stmt->execute();
    $stmt->close();

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
   ADJUST COPIES (PHYSICAL STOCK)
================================ */
if (isset($_POST['adjust_qty'])) {
    $book_id    = (int)$_POST['book_id'];
    $adjustment = (int)$_POST['adjustment'];

    $conn->query("
        UPDATE books SET
            Copies = GREATEST(Copies + $adjustment, 0)
        WHERE book_id = $book_id
    ");

    $_SESSION['message'] = "Total copies adjusted.";
    header("Location: index.php");
    exit();
}

/* ===============================
   FETCH BOOKS (MULTI-BORROWER)
================================ */
$sql = "
SELECT 
    b.*,
    GROUP_CONCAT(br.borrower_name SEPARATOR '||') AS borrower_names,
    r.borrower_name AS reserver_name,
    r.borrower_id AS reserver_id,
    r.reserve_date
FROM books b
LEFT JOIN transactions t
    ON b.book_id = t.book_id AND t.date_returned IS NULL
LEFT JOIN borrower br
    ON t.borrower_id = br.borrower_id
LEFT JOIN reservations r
    ON b.book_id = r.book_id
GROUP BY b.book_id
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
    <input name="Callnumber" type="text" placeholder="CALL NO" required>
    <input name="itemTitle" type="text" placeholder="TITLE" required>
    <input name="itemAuthor" type="text" placeholder="AUTHOR" required>

    <select name="itemEdition" required>
        <option value="">Select Edition</option>
        <option value="N/A">N/A</option>
        <option value="REVISED">REVISED</option>
        <option value="FIRST">FIRST</option>
        <option value="SECOND">SECOND</option>
        <option value="THIRD">THIRD</option>
    </select>

    <input name="IMPRINT" type="text" placeholder="IMPRINT" required>
    <input name="itemPublisher" type="text" placeholder="PUBLISHER" required>
    <input name="Accession_Number" type="text" placeholder="ACCESSION NUMBER" required>
    <input name="itemCopies" type="number" placeholder="COPIES" required>
    <input name="itemShelf" type="text" placeholder="SHELF LOCATION" required>
    <button name="addItem">Add Book</button>
</form>

<h2>Book List</h2>
<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table id="bookTable">
<tr>
    <th>ID</th>
    <th>CALL NO</th>
    <th>TITLE</th>
    <th>AUTHOR</th>
    <th>EDITION</th>
    <th>IMPRINT</th>
    <th>PUBLISHER</th>
    <th>ACCESSION</th>
    <th>COPIES</th>
    <th>SHELF</th>
    <th>STATUS</th>
    <th>AVAILABLE</th>
    <th>BORROWER(S)</th>
    <th>RESERVER</th>
    <th>RESERVE DATE</th>
    <th>ACTIONS</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['Call_Number']) ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['Edition'] ?: 'N/A') ?></td>
    <td><?= htmlspecialchars($row['Imprint']) ?></td>
    <td><?= htmlspecialchars($row['Publisher']) ?></td>
    <td><?= htmlspecialchars($row['Accession_Number']) ?></td>
    <td><?= $row['Copies'] ?></td>
    <td><?= htmlspecialchars($row['Shelf_Location']) ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['available'] ?></td>

    <td>
    <?php
    if (!empty($row['borrower_names'])) {
        $borrowers = explode('||', $row['borrower_names']);
        if (count($borrowers) > 1) {
            echo '<select>';
            foreach ($borrowers as $b) {
                echo '<option>' . htmlspecialchars($b) . '</option>';
            }
            echo '</select>';
        } else {
            echo htmlspecialchars($borrowers[0]);
        }
    } else {
        echo "—";
    }
    ?>
    </td>

    <td><?= $row['reserver_name'] ?? "—" ?></td>
    <td><?= $row['reserve_date'] ? date("M d, Y h:i A", strtotime($row['reserve_date'])) : "—" ?></td>

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

        <?php if ($row['reserver_id']): ?>
            <form method="GET" action="reserve_details.php" style="display:inline">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button type="submit">RESERVE DETAILS</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this reservation?')">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button name="delete_reserve" style="background:red;color:white">
                    DELETE RESERVE
                </button>
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
