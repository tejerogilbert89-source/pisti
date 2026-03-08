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

    $callnum    = trim($_POST['itemCall']);
    $title      = trim($_POST['itemTitle']);
    $author     = trim($_POST['itemAuthor']);
    $edition    = trim($_POST['itemEdition']);
    $imprint    = trim($_POST['itemImprint']);
    $publisher  = trim($_POST['itemPublisher']);
    $accession  = trim($_POST['itemAccession']);
    $shelf      = trim($_POST['itemShelf']);
    $copies     = max(0, (int)$_POST['itemCopies']);

    $borrowed = (int)$book['borrowed'];

    // Prevent borrowed > total copies
    if ($borrowed > $copies) {
        $borrowed = $copies;
    }

    $available = $copies - $borrowed;

    // Determine status automatically if needed
    if ($copies == 0) {
        $status = "Out of Stock";
    } elseif ($available == 0) {
        $status = "Borrowed";
    } else {
        $status = $_POST['itemStatus'];
    }

    $update = $conn->prepare("
        UPDATE books SET
            Call_Number = ?,
            Title = ?,
            Author = ?,
            Edition = ?,
            Imprint = ?,
            Publisher = ?,
            Accession_Number = ?,
            Shelf_Location = ?,
            Copies = ?,
            available = ?,
            borrowed = ?,
            status = ?
        WHERE book_id = ?
    ");

    $update->bind_param(
        "ssssssssiiiii",
        $callnum,
        $title,
        $author,
        $edition,
        $imprint,
        $publisher,
        $accession,
        $shelf,
        $copies,
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
<link rel="stylesheet" href="edit.css">
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

<?php if(isset($_SESSION['message'])): ?>
    <p style="color:green"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>

<form method="POST">

    <label>Call Number</label>
    <input type="text" name="itemCall" value="<?= htmlspecialchars($book['Call_Number']) ?>" required>

    <label>Title</label>
    <input type="text" name="itemTitle" value="<?= htmlspecialchars($book['Title']) ?>" required>

    <label>Author</label>
    <input type="text" name="itemAuthor" value="<?= htmlspecialchars($book['Author']) ?>" required>

    <label>Edition</label>
    <input type="text" name="itemEdition" value="<?= htmlspecialchars($book['Edition'] ?: 'N/A') ?>">

    <label>Imprint</label>
    <input type="text" name="itemImprint" value="<?= htmlspecialchars($book['Imprint']) ?>">

    <label>Publisher</label>
    <input type="text" name="itemPublisher" value="<?= htmlspecialchars($book['Publisher']) ?>" required>

    <label>Accession Number</label>
    <input type="text" name="itemAccession" value="<?= htmlspecialchars($book['Accession_Number']) ?>">

    <label>Shelf Location</label>
    <input type="text" name="itemShelf" value="<?= htmlspecialchars($book['Shelf_Location']) ?>" required>

    <label>Status</label>
    <select name="itemStatus">
        <option value="Available" <?= $book['status']=="Available"?"selected":"" ?>>Available</option>
        <option value="Borrowed" <?= $book['status']=="Borrowed"?"selected":"" ?>>Borrowed</option>
        <option value="Out of Stock" <?= $book['status']=="Out of Stock"?"selected":"" ?>>Out of Stock</option>
        <option value="Defective" <?= $book['status']=="Defective"?"selected":"" ?>>Defective</option>
    </select>

    <label>Total Copies</label>
    <input type="number" name="itemCopies" min="0" value="<?= (int)$book['Copies'] ?>" required>

    <p><strong>Borrowed:</strong> <?= (int)$book['borrowed'] ?></p>
    <p><strong>Available:</strong> <?= (int)$book['available'] ?></p>

    <button type="submit" name="updateItem">Save Changes</button>
    <a href="index.php">Cancel</a>

</form>
</main>

</div>
</body>
</html>
