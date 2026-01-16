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
   ADMIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== "admin") {
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
   FETCH BOOK
================================ */
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
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

    $title      = trim($_POST['itemTitle']);
    $callnum    = trim($_POST['itemCall']);
    $author     = trim($_POST['itemAuthor']);
    $category   = trim($_POST['itemCategory']);
    $publisher  = trim($_POST['itemPublisher']);
    $shelf      = trim($_POST['itemShelf']);
    $year       = (int)$_POST['itemYear'];
    $accession  = trim($_POST['itemAccession']);
    $volume     = max(0, (int)$_POST['itemQuantity']);
    $borrowed   = (int)$book['borrowed'];

    /* Prevent invalid borrowed > volume */
    if ($borrowed > $volume) {
        $borrowed = $volume;
    }

    $available = $volume - $borrowed;

    if ($volume == 0) {
        $status = "Out of Stock";
    } elseif ($available == 0) {
        $status = "Borrowed";
    } else {
        $status = $_POST['itemStatus'];
    }

    $update = $conn->prepare("
        UPDATE books SET
            Title = ?,
            Call_Number = ?,
            Author = ?,
            category = ?,
            Publisher = ?,
            Shelf_Location = ?,
            Book_Year = ?,
            Accession_Number = ?,
            volume = ?,
            available = ?,
            borrowed = ?,
            status = ?
        WHERE book_id = ?
    ");

    $update->bind_param(
        "ssssssisiissi",
        $title,
        $callnum,
        $author,
        $category,
        $publisher,
        $shelf,
        $year,
        $accession,
        $volume,
        $available,
        $borrowed,
        $status,
        $book_id
    );

    $update->execute();
    $update->close();

    $_SESSION['message'] = "Book updated successfully.";
    header("Location: index.php");
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
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main">
<h1>Edit Book</h1>

<form method="POST">

<label>Title</label>
<input type="text" name="itemTitle"
       value="<?= htmlspecialchars($book['Title']) ?>" required>

<label>Call Number</label>
<input type="text" name="itemCall"
       value="<?= htmlspecialchars($book['Call_Number']) ?>" required>

<label>Author</label>
<input type="text" name="itemAuthor"
       value="<?= htmlspecialchars($book['Author']) ?>" required>

<label>Category</label>
<input type="text" name="itemCategory"
       value="<?= htmlspecialchars($book['category']) ?>" required>

<label>Publisher</label>
<input type="text" name="itemPublisher"
       value="<?= htmlspecialchars($book['Publisher']) ?>" required>

<label>Shelf Location</label>
<input type="text" name="itemShelf"
       value="<?= htmlspecialchars($book['Shelf_Location']) ?>" required>

<label>Year of Publication</label>
<input type="number" name="itemYear"
       value="<?= (int)$book['Book_Year'] ?>">

<label>Accession Number</label>
<input type="text" name="itemAccession"
       value="<?= htmlspecialchars($book['Accession_Number']) ?>">

<label>Status</label>
<select name="itemStatus">
    <option value="Available" <?= $book['status']=="Available"?"selected":"" ?>>Available</option>
    <option value="Defective" <?= $book['status']=="Defective"?"selected":"" ?>>Defective</option>
</select>

<label>Total Copies</label>
<input type="number" name="itemQuantity" min="0"
       value="<?= (int)$book['volume'] ?>" required>

<p><strong>Borrowed:</strong> <?= (int)$book['borrowed'] ?></p>
<p><strong>Available:</strong> <?= (int)$book['available'] ?></p>

<button type="submit" name="updateItem">Save Changes</button>
<a href="index.php">Cancel</a>

</form>
</main>

</div>
</body>
</html>
