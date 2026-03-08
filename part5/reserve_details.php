<?php
session_start();
date_default_timezone_set('Asia/Manila');

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
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
   AUTO DELETE EXPIRED RESERVATIONS
================================ */
$seven_days_ago = date("Y-m-d H:i:s", strtotime("-7 days"));

$stmt = $conn->prepare("
    DELETE FROM reservations
    WHERE reserve_date < ?
");
$stmt->bind_param("s", $seven_days_ago);
$stmt->execute();
$stmt->close();

/* ===============================
   MANUAL DELETE RESERVATION
================================ */
if (isset($_POST['delete_reservation'])) {

    $reservation_id = (int) $_POST['reservation_id'];

    $stmt = $conn->prepare("
        DELETE FROM reservations
        WHERE reservation_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "🗑️ Reservation deleted successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   BORROW PROCESS
================================ */
if (isset($_POST['borrow'])) {

    $borrower_id    = (int) $_POST['borrower_id'];
    $borrower_name  = strtoupper(trim($_POST['borrower_name']));
    $course         = strtoupper(trim($_POST['course']));
    $year           = (int) $_POST['year'];
    $type           = trim($_POST['borrower_type']);
    $book_id        = (int) $_POST['book_id'];
    $reservation_id = (int) $_POST['reservation_id'];

    $borrow_period = 7;
    $due_date = date("Y-m-d H:i:s", strtotime("+$borrow_period days"));

    /* CHECK BOOK AVAILABILITY */
    $stmt = $conn->prepare("SELECT available FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book || $book['available'] <= 0) {
        $_SESSION['message'] = "❌ Book is out of stock.";
        header("Location: borrow.php");
        exit();
    }

    /* CHECK IF BORROWER EXISTS */
    $stmt = $conn->prepare("SELECT borrower_id FROM borrower WHERE borrower_id = ?");
    $stmt->bind_param("i", $borrower_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows;
    $stmt->close();

    /* INSERT BORROWER IF NOT EXISTS */
    if ($exists == 0) {
        $stmt = $conn->prepare("
            INSERT INTO borrower 
            (borrower_id, borrower_name, course, year, borrower_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issis", $borrower_id, $borrower_name, $course, $year, $type);
        $stmt->execute();
        $stmt->close();
    }

    /* INSERT TRANSACTION */
    $stmt = $conn->prepare("
        INSERT INTO transactions 
        (book_id, borrower_id, date_borrowed, due_date)
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->bind_param("iis", $book_id, $borrower_id, $due_date);
    $stmt->execute();
    $stmt->close();

    /* UPDATE BOOK COUNTS */
    $stmt = $conn->prepare("
        UPDATE books
        SET borrowed = borrowed + 1,
            available = available - 1,
            status = CASE
                WHEN available - 1 > 0 THEN 'Available'
                ELSE 'Out of Stock'
            END
        WHERE book_id = ?
    ");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();

    /* DELETE ONLY SELECTED RESERVATION */
    $stmt = $conn->prepare("
        DELETE FROM reservations 
        WHERE reservation_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();

    $due_date_formatted = date("F d, Y h:i A", strtotime($due_date));
    $_SESSION['message'] = "✅ Book borrowed successfully! Due: $due_date_formatted";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   FETCH RESERVATIONS
================================ */
$reservations = $conn->query("
    SELECT r.*, b.available
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    ORDER BY r.reserve_date ASC
");

/* ===============================
   HELPER: FORMAT DATETIME
================================ */
function formatPHDateTime($datetime) {
    if (!$datetime) return '';
    $dt = new DateTime($datetime);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('F d, Y h:i A'); // Example: March 04, 2026 02:30 PM
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Books</title>
<link rel="stylesheet" href="borrow.css">
<style>

</style>
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="content">
<h2>Borrow / Return</h2>

<?php if (isset($_SESSION['message'])): ?>
    <p style="color:green;">
        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
    </p>
<?php endif; ?>

<table>
<tr>
    <th>Name</th>
    <th>ID</th>
    <th>Course</th>
    <th>Year</th>
    <th>Book ID</th>
    <th>Reserve Date</th>
    <th>Type</th>
    <th>Action</th>
</tr>

<?php while ($row = $reservations->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars(strtoupper($row['borrower_name'])) ?></td>
    <td><?= $row['borrower_id'] ?></td>
    <td><?= htmlspecialchars(strtoupper($row['course'])) ?></td>
    <td><?= $row['year'] ?></td>
    <td><?= $row['book_id'] ?></td>
    <td><?= formatPHDateTime($row['reserve_date']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td>

        <!-- BORROW BUTTON -->
        <?php if ($row['available'] > 0): ?>
        <form method="post" style="display:inline;">
            <input type="hidden" name="reservation_id" value="<?= $row['reservation_id'] ?>">
            <input type="hidden" name="borrower_id" value="<?= $row['borrower_id'] ?>">
            <input type="hidden" name="borrower_name" value="<?= $row['borrower_name'] ?>">
            <input type="hidden" name="course" value="<?= $row['course'] ?>">
            <input type="hidden" name="year" value="<?= $row['year'] ?>">
            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
            <input type="hidden" name="borrower_type" value="<?= $row['borrower_type'] ?>">
            <button type="submit" name="borrow" class="borrow-btn">Borrow</button>
        </form>
        <?php else: ?>
            <span style="color:red;">Out of Stock</span>
        <?php endif; ?>

        <!-- DELETE BUTTON -->
        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this reservation?');">
            <input type="hidden" name="reservation_id" value="<?= $row['reservation_id'] ?>">
            <button type="submit" name="delete_reservation" class="delete-btn">Delete</button>
        </form>

    </td>
</tr>
<?php endwhile; ?>

</table>
</div>

</body>
</html>