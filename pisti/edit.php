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
$conn->set_charset("utf8mb4");

/* ===============================
   LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   REQUIRE book_id
================================ */
if (!isset($_GET['book_id'])) {
    header("Location: index.php");
    exit();
}

$book_id = (int)$_GET['book_id'];

/* ===============================
   GET BOOK INFO
================================ */
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ? LIMIT 1");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();
$stmt->close();

if (!$book) {
    die("Book not found.");
}

/* ===============================
   UPDATE BOOK
================================ */
if (isset($_POST['updateItem'])) {

    $title      = trim($_POST['itemName']);
    $category   = trim($_POST['itemCategory']);
    $author     = trim($_POST['itemAuthor']);
    $publisher  = trim($_POST['itemPublisher']);
    $shelf      = trim($_POST['itemShelf']);
    $status     = trim($_POST['itemStatus']);
    $volume     = (int)$_POST['itemQuantity'];
    $accession  = trim($_POST['itemAccession']);
    $book_year  = (int)$_POST['itemYear'];

    if ($volume < 0) {
        $volume = 0;
    }

    if ($volume == 0) {
        $status = "Out of Stock";
    }

    $update = $conn->prepare("
        UPDATE books
        SET Title = ?,
            category = ?,
            Author = ?,
            Publisher = ?,
            Shelf_Location = ?,
            status = ?,
            volume = ?,
            Accession_Number = ?,
            Book_Year = ?
        WHERE book_id = ?
    ");

    $update->bind_param(
        "ssssssiiis",
        $title,
        $category,
        $author,
        $publisher,
        $shelf,
        $status,
        $volume,
        $accession,
        $book_year,
        $book_id
    );

    $update->execute();
    $update->close();

    header("Location: index.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Book</title>
<link rel="stylesheet" href="reserve.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="borrow.php">Borrow / Return</a></li>
            <li><a href="transaction.php">Transaction History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <h1>Edit Book</h1>

        <form method="POST">

            <label>Title</label>
            <input type="text" name="itemName"
                   value="<?= htmlspecialchars($book['Title']) ?>" required>

            <label>Category</label>
            <input type="text" name="itemCategory"
                   value="<?= htmlspecialchars($book['category']) ?>" required>

            <label>Author</label>
            <input type="text" name="itemAuthor"
                   value="<?= htmlspecialchars($book['Author']) ?>" required>

            <label>Publisher</label>
            <input type="text" name="itemPublisher"
                   value="<?= htmlspecialchars($book['Publisher']) ?>" required>

            <label>Shelf Location</label>
            <input type="text" name="itemShelf"
                   value="<?= htmlspecialchars($book['Shelf_Location']) ?>" required>

            <label>Status</label>
            <select name="itemStatus">
                <option value="Available" <?= $book['status']=="Available" ? "selected" : "" ?>>Available</option>
                <option value="Borrowed" <?= $book['status']=="Borrowed" ? "selected" : "" ?>>Borrowed</option>
                <option value="Defective" <?= $book['status']=="Defective" ? "selected" : "" ?>>Defective</option>
                <option value="Out of Stock" <?= $book['status']=="Out of Stock" ? "selected" : "" ?>>Out of Stock</option>
            </select>

            <label>Quantity</label>
            <input type="number" name="itemQuantity" min="0"
                   value="<?= (int)$book['volume'] ?>" required>

            <label>Accession Number</label>
            <input type="text" name="itemAccession"
                   value="<?= htmlspecialchars($book['Accession_Number']) ?>">

            <label>Year of Publication</label>
            <input type="number" name="itemYear"
                   value="<?= (int)$book['Book_Year'] ?>">

            <button type="submit" name="updateItem">Save Changes</button>
            <a href="index.php">Cancel</a>

        </form>
    </main>

</div>

</body>
</html>
