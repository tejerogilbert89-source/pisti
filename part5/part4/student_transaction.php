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
   LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
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
        t.date_returned,
        t.due_date,
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
<html>
<head>
<meta charset="UTF-8">
<title>Transaction History</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php" class="active">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="main">
<h1>Transaction History</h1>

<input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">

<table id="transactionTable">
<thead>
<tr>
    <th>ID</th>
    <th>BOOK</th>
    <th>FISRT NAME/MIDDLE NAME/LAST NAME</th>
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

if (!empty($row['due_date'])) {

    $today = new DateTime();
    $dueDate = new DateTime($row['due_date']);

    // If not yet returned → auto compute
    if (empty($row['date_returned'])) {

        if ($today > $dueDate) {
            $daysLate = $dueDate->diff($today)->days;
            $lateFee = $daysLate * 5;
        }

    } else {
        // If returned → use saved late_fee
        $lateFee = $row['late_fee'];
    }
}
?>

<tr>
    <td><?= htmlspecialchars($row['transaction_id']) ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= htmlspecialchars($row['borrower_id']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td><?= htmlspecialchars($row['course'] ?: '-') ?></td>
    <td><?= htmlspecialchars($row['year'] ?: '-') ?></td>
    <td><?= $row['date_borrowed'] ?></td>
    
    <td style="color:<?= $lateFee > 0 ? 'red' : 'inherit' ?>">
        <?= $row['due_date'] ?>
        <?= ($lateFee > 0 && empty($row['date_returned'])) ? '<br><strong>OVERDUE</strong>' : '' ?>
    </td>

    <td>
        <?= !empty($row['date_returned']) 
            ? $row['date_returned'] 
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
</script>

</body>
</html>
