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
   ADMIN / LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   MANUAL BORROW (BUTTON)
================================ */
if (isset($_POST['borrow'])) {

    $borrower_id   = $_POST['borrower_id'];
    $borrower_name = $_POST['borrower_name'];
    $course        = $_POST['course'];
    $year          = $_POST['year'];
    $type          = $_POST['borrower_type'];
    $book_id       = $_POST['book_id'];
    $reservation_id = $_POST['reservation_id'];

    // Check borrower exists
    $stmt = $conn->prepare("SELECT borrower_id FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("i", $borrower_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows;
    $stmt->close();

    if (!$exists) {
        $stmt = $conn->prepare("
            INSERT INTO borrower (borrower_id, borrower_name, course, year, borrower_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issis",
            $borrower_id,
            $borrower_name,
            $course,
            $year,
            $type
        );
        $stmt->execute();
        $stmt->close();
    }

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $book_id, $borrower_id);
    $stmt->execute();
    $stmt->close();

    // Reduce book volume
    $stmt = $conn->prepare("
        UPDATE books SET volume = volume - 1 WHERE book_id=?
    ");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();

    // Delete reservation after borrowing
    $stmt = $conn->prepare("
        DELETE FROM reservations WHERE reservation_id=?
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book borrowed successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   AUTO BORROW EXPIRED RESERVATIONS
================================ */
$expired = $conn->query("
    SELECT r.*, b.volume
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.reserve_date < (NOW() - INTERVAL 1 DAY)
      AND b.volume > 0
");

while ($row = $expired->fetch_assoc()) {

    $borrower_id   = $row['borrower_id'];
    $borrower_name = $row['borrower_name'];
    $course        = $row['course'];
    $year          = $row['year'];
    $type          = $row['borrower_type'];
    $book_id       = $row['book_id'];

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $book_id, $borrower_id);
    $stmt->execute();
    $stmt->close();

    // Reduce volume
    $stmt = $conn->prepare("
        UPDATE books SET volume = volume - 1 WHERE book_id=?
    ");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();

    // Remove reservation
    $stmt = $conn->prepare("
        DELETE FROM reservations WHERE reservation_id=?
    ");
    $stmt->bind_param("i", $row['reservation_id']);
    $stmt->execute();
    $stmt->close();
}

/* ===============================
   FETCH RESERVATIONS
================================ */
$reservations = $conn->query("
    SELECT * FROM reservations ORDER BY reserve_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Books</title>
<link rel="stylesheet" href="borrow.css">
</head>
<body>

<aside class="sidebar">
    <h2>BORROWER</h2>
   <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="main-content">
<h1>Borrow / Return Books</h1>

<?php
if (isset($_SESSION['message'])) {
    echo "<p style='color:green'>{$_SESSION['message']}</p>";
    unset($_SESSION['message']);
}
?>

<h2>Reservations</h2>

<table border="1" cellpadding="6">
<tr>
    <th>Borrower Name</th>
    <th>Borrower ID</th>
    <th>Course</th>
    <th>Year</th>
    <th>Book ID</th>
    <th>Reserve Date</th>
    <th>Type</th>
    <th>Action</th>
</tr>

<?php while ($row = $reservations->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= $row['borrower_id'] ?></td>
    <td><?= htmlspecialchars($row['course']) ?></td>
    <td><?= $row['year'] ?></td>
    <td><?= $row['book_id'] ?></td>
    <td><?= $row['reserve_date'] ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td>
        <form method="post">
            <input type="hidden" name="reservation_id" value="<?= $row['reservation_id'] ?>">
            <input type="hidden" name="borrower_name" value="<?= htmlspecialchars($row['borrower_name']) ?>">
            <input type="hidden" name="borrower_id" value="<?= $row['borrower_id'] ?>">
            <input type="hidden" name="course" value="<?= htmlspecialchars($row['course']) ?>">
            <input type="hidden" name="year" value="<?= $row['year'] ?>">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="borrower_type" value="<?= htmlspecialchars($row['borrower_type']) ?>">
            <button type="submit" name="borrow">Borrow</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>

</table>
</div>

</body>
</html>
