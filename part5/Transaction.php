<?php
session_start();

/* ===============================
   ERROR REPORTING
================================ */
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
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
   FETCH TRANSACTIONS
================================ */
$sql = "
    SELECT 
        t.transaction_id,
        t.date_borrowed,
        t.due_date,
        t.date_returned,
        t.late_fee,
        b.borrower_name,
        b.borrower_type,
        b.borrower_id,
        b.course,
        b.year,
        bo.Title
    FROM transactions t
    LEFT JOIN borrower b ON t.borrower_id = b.borrower_id
    LEFT JOIN books bo ON t.book_id = bo.book_id
    ORDER BY t.transaction_id DESC
";

$transactions = $conn->query($sql);
if (!$transactions) {
    die("Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transaction History</title>
<link rel="stylesheet" href="style.css">

<style>
.overdue-row {
    background-color: #ffe5e5;
}
</style>

</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="Static.php">Statistics</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="main">
<h1>Transaction History</h1>

<!-- FILTER BUTTONS -->
<div style="margin-bottom:15px;">
    <button onclick="showAll()">Show All</button>
    <button onclick="showOverdue()">Show Overdue Only</button>
</div>

<input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">

<table id="transactionTable">
<thead>
<tr>
    <th>ID</th>
    <th>BOOK</th>
    <th>FIRST NAME/MIDDLE NAME/LAST NAME</th>
    <th>BORROWER ID</th>
    <th>TYPE</th>
    <th>COURSE</th>
    <th>YEAR</th>
    <th>DATE BORROWED</th>
    <th>DUE DATE</th>
    <th>DATE RETURNED</th>
    <th>LATE FEE (₱)</th>
</tr>
</thead>
<tbody>

<?php if ($transactions->num_rows > 0): ?>
<?php while ($row = $transactions->fetch_assoc()): ?>

<?php
$lateFee = 0;
$isOverdue = false;

if (!empty($row['due_date'])) {

    $today = new DateTime();
    $dueDate = new DateTime($row['due_date']);

    if (empty($row['date_returned'])) {
        // Not yet returned → auto calculate
        if ($today > $dueDate) {
            $daysLate = $dueDate->diff($today)->days;
            $lateFee = $daysLate * 5;
            $isOverdue = true;
        }
    } else {
        // Already returned → use saved late_fee
        $lateFee = floatval($row['late_fee']);
    }
}
?>

<tr class="<?= $isOverdue ? 'overdue-row' : '' ?>">
    <td><?= htmlspecialchars($row['transaction_id']) ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= htmlspecialchars($row['borrower_id']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td><?= htmlspecialchars($row['course'] ?: '-') ?></td>
    <td><?= htmlspecialchars($row['year'] ?: '-') ?></td>
    <td>
        <?= !empty($row['date_borrowed']) ? date('F d, Y h:i A', strtotime($row['date_borrowed'])) : '-' ?>
    </td>

    <td style="color:<?= $lateFee > 0 ? 'red' : 'inherit' ?>">
        <?= !empty($row['due_date']) ? date('F d, Y h:i A', strtotime($row['due_date'])) : '-' ?>
        <?= ($isOverdue) ? '<br><strong>OVERDUE</strong>' : '' ?>
    </td>

    <td>
        <?= !empty($row['date_returned']) 
            ? date('F d, Y h:i A', strtotime($row['date_returned'])) 
            : '<span style="color:red;">Not Returned</span>' ?>
    </td>

    <td style="color:<?= $lateFee > 0 ? 'red' : 'green' ?>; font-weight:bold;">
        <?= number_format($lateFee, 2) ?>
    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="11" style="text-align:center;">No transactions found</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<script>
function searchTable() {
    const input = document.getElementById("searchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#transactionTable tbody tr");
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}

function showOverdue() {
    const rows = document.querySelectorAll("#transactionTable tbody tr");
    rows.forEach(row => {
        row.style.display = row.classList.contains("overdue-row") ? "" : "none";
    });
}

function showAll() {
    const rows = document.querySelectorAll("#transactionTable tbody tr");
    rows.forEach(row => {
        row.style.display = "";
    });
}
</script>

</body>
</html>