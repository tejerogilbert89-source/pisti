<?php
session_start();
date_default_timezone_set('Asia/Manila');

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
   AUTO SYNC COPIES
================================ */
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
        b.status    = IF(GREATEST(b.Copies - IFNULL(t.borrowed_count, 0), 0) > 0, 'Available', 'Out of Stock')
");

/* ===============================
   ADD BOOK
================================ */
if (isset($_POST['addItem'])) {
    $title = trim($_POST['itemTitle']);

    // Enforce 3–50 character limit
    if (strlen($title) < 3 || strlen($title) > 50) {
        echo "<script>alert('Title must be between 3 and 50 characters.'); window.history.back();</script>";
        exit();
    }

    $copies = max(1, (int)$_POST['itemCopies']);
    $borrowed = 0;
    $available = $copies;
    $status = 'Available';

    $stmt = $conn->prepare("
        INSERT INTO books
        (Call_Number, Title, Author, Edition, Accession_Number, Imprint,
         Publisher, Shelf_Location, Copies, volume, available, borrowed, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssssssssiiiii",
        $_POST['Callnumber'],
        $title,
        $_POST['itemAuthor'],
        $_POST['itemEdition'],
        $_POST['Accession_Number'],
        $_POST['IMPRINT'],
        $_POST['itemPublisher'],
        $_POST['itemShelf'],
        $copies,
        $copies,
        $available,
        $borrowed,
        $status
    );

    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit();
}

/* ===============================
   DELETE RESERVATION
================================ */
if (isset($_POST['delete_reserve'])) {
    $book_id = (int)$_POST['book_id'];

    $conn->query("
        DELETE r
        FROM reservations r
        JOIN transactions t
        ON r.book_id = t.book_id AND r.borrower_id = t.borrower_id
        WHERE r.book_id = $book_id AND t.date_returned IS NULL
    ");

    header("Location: index.php");
    exit();
}

/* ===============================
   ADJUST COPIES
================================ */
if (isset($_POST['adjust_qty'])) {
    $book_id    = (int)$_POST['book_id'];
    $adjustment = (int)$_POST['adjustment'];

    $conn->query("
        UPDATE books
        SET Copies = GREATEST(Copies + $adjustment, 0)
        WHERE book_id = $book_id
    ");

    header("Location: index.php");
    exit();
}

/* ===============================
   FETCH BOOKS
================================ */
$sql = "
SELECT 
    b.*,
    GROUP_CONCAT(DISTINCT br.borrower_name SEPARATOR '||') AS borrower_names,
    GROUP_CONCAT(DISTINCT r.borrower_name SEPARATOR '||') AS reserver_names
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
<html>
<head>
<meta charset="UTF-8">
<title>Manage Books</title>
<link rel="stylesheet" href="wars.css">
<style>
/* Simple styling for shelf filter buttons */
.shelf-filter button {
    margin: 3px;
    padding: 5px 10px;
    cursor: pointer;
}
.add-book-btn {
    margin-top: 5px;
    padding: 5px 10px;
}
</style>
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

<h2>Add New Book</h2>
<form method="POST" class="book-form">
    <input name="Callnumber" placeholder="CALL NO" required>
    <input name="itemTitle" placeholder="TITLE" required minlength="3" maxlength="50">
    <input name="itemAuthor" placeholder="AUTHOR" required>
    <select name="itemEdition">
        <option value="">EDITION</option>
        <option>N/A</option>
        <option>FIRST</option>
        <option>SECOND</option>
        <option>THIRD</option>
    </select>
    <input name="IMPRINT" placeholder="IMPRINT">
    <input name="itemPublisher" placeholder="PUBLISHER">
    <input name="Accession_Number" placeholder="ACCESSION NUMBER" required>
    <input name="itemCopies" type="number" min="1" value="1" required>
    <input name="itemShelf" placeholder="SHELF LOCATION">
    <button type="submit" name="addItem" class="add-book-btn">ADD BOOK</button>
</form>

<h2>Book Masterlist</h2>

<!-- Shelf Filter Buttons -->
<div class="shelf-filter">
<button onclick="filterShelf('all')">All Books</button>
<?php
$shelves = $conn->query("SELECT DISTINCT Shelf_Location FROM books");
while ($shelf = $shelves->fetch_assoc()) {
    $shelf_name = $shelf['Shelf_Location'];
    echo '<button onclick="filterShelf(\''.addslashes($shelf_name).'\')">'.htmlspecialchars($shelf_name).'</button>';
}
?>
</div>

<!-- Search Box -->
<input type="text" id="search" placeholder="Search by Call No, Title, Author, Accession">

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
    <th>BORROWERS</th>
    <th>RESERVERS</th>
    <th>ACTIONS</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr data-shelf="<?= htmlspecialchars($row['Shelf_Location']) ?>">
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['Call_Number']) ?></td>
    <td title="<?= htmlspecialchars($row['Title']) ?>">
        <?= htmlspecialchars(mb_strimwidth($row['Title'], 0, 50, '...')) ?>
    </td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['Edition'] ?: 'N/A') ?></td>
    <td><?= htmlspecialchars($row['Imprint']) ?></td>
    <td><?= htmlspecialchars($row['Publisher']) ?></td>
    <td><?= htmlspecialchars($row['Accession_Number']) ?></td>
    <td><?= $row['Copies'] ?></td>
    <td><?= htmlspecialchars($row['Shelf_Location']) ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['available'] ?></td>

    <!-- BORROWERS COLUMN -->
    <td>
    <?php
    $borrowers = $row['borrower_names'] ? explode('||', $row['borrower_names']) : [];
    if (count($borrowers) === 0) {
        echo "—";
    } elseif (count($borrowers) === 1) {
        echo htmlspecialchars($borrowers[0]);
    } else {
        echo '<select class="borrower-select">';
        foreach ($borrowers as $b_name) {
            echo '<option>' . htmlspecialchars($b_name) . '</option>';
        }
        echo '</select>';
    }
    ?>
    </td>

    <!-- RESERVERS COLUMN -->
    <td>
    <?php
    $reservers = $row['reserver_names'] ? explode('||', $row['reserver_names']) : [];
    if (count($reservers) === 0) {
        echo "—";
    } elseif (count($reservers) === 1) {
        echo htmlspecialchars($reservers[0]);
    } else {
        echo '<select class="borrower-select">';
        foreach ($reservers as $r) {
            echo '<option>' . htmlspecialchars($r) . '</option>';
        }
        echo '</select>';
    }
    ?>
    </td>

    <td>
        <!-- Adjust + -->
        <form method="POST" style="display:inline">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="adjustment" value="1">
            <button name="adjust_qty">+</button>
        </form>

        <!-- Adjust - -->
        <form method="POST" style="display:inline">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="adjustment" value="-1">
            <button name="adjust_qty">−</button>
        </form>

        <a href="edit.php?book_id=<?= $row['book_id'] ?>">Edit</a>
        <a href="delete.php?book_id=<?= $row['book_id'] ?>" onclick="return confirm('Delete this book?')">Delete</a>

        <?php if ($row['reserver_names']): ?>
            <form method="GET" action="reserve_details.php" style="display:inline">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button type="submit">RESERVE DETAILS</button>
            </form>

            <form method="POST" style="display:inline" 
                  onsubmit="return confirm('Delete only reservations of borrowers who already borrowed?')">
                <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                <button name="delete_reserve" style="background:red;color:white">DELETE RESERVE</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>

</table>
</main>
</div>

<!-- ===============================
   FAST SEARCH SCRIPT
================================ -->
<script>
const searchInput = document.getElementById("search");
const rows = Array.from(document.querySelectorAll("#bookTable tr[data-shelf]"));

const rowData = rows.map(row => {
    let text = '';
    for (let i = 0; i <= 11; i++) {
        text += row.cells[i].innerText.toLowerCase() + ' ';
    }
    return text.trim();
});

searchInput.addEventListener("input", function () {
    const filter = this.value.trim().toLowerCase();
    for (let i = 0; i < rows.length; i++) {
        rows[i].style.display = rowData[i].includes(filter) ? "" : "none";
    }
});

function filterShelf(location) {
    rows.forEach(row => {
        const shelf = row.getAttribute("data-shelf");
        if (location === "all") {
            row.style.display = "";
        } else {
            row.style.display = (shelf === location) ? "" : "none";
        }
    });
}
</script>

</body>
</html>