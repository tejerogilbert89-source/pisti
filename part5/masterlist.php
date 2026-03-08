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
   MONTH LIST
================================ */
$months = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];

/* ===============================
   SELECTED MONTH
================================ */
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date("n");

/* ===============================
   FETCH BORROWED BOOKS
================================ */
$sql = "
    SELECT bo.Title, COUNT(t.transaction_id) AS total_borrows
    FROM books bo
    INNER JOIN transactions t ON bo.book_id = t.book_id
    WHERE MONTH(t.date_borrowed) = $selectedMonth
    AND YEAR(t.date_borrowed) = YEAR(CURRENT_DATE())
    GROUP BY bo.book_id
    ORDER BY total_borrows DESC
";
$transactions = $conn->query($sql);

/* ===============================
   TOTAL BORROWS THIS MONTH
================================ */
$monthQuery = "
    SELECT COUNT(transaction_id) AS month_total
    FROM transactions
    WHERE MONTH(date_borrowed) = $selectedMonth
    AND YEAR(date_borrowed) = YEAR(CURRENT_DATE())
";
$monthResult = $conn->query($monthQuery); 
$monthData = $monthResult->fetch_assoc(); 
$monthlyTotal = $monthData['month_total'];

$currentMonth = $months[$selectedMonth] . " " . date("Y"); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Borrow Statistics</title>
<link rel="stylesheet" href="static.css">
<style>

/* ========================
   GENERAL STYLING
======================== */

/* CENTER CONTROLS (PRINT ONLY) */
.controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin: 20px 0;
}

.controls button#printBtn {
    padding: 10px 28px;
    border-radius: 999px;
    border: none;
    background: #0b7a4f;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    transition: 0.25s;
}

.controls button#printBtn:hover {
    background: #075f3c;
}

/* TABLE WRAPPER */
.table-wrapper { overflow-x:auto; margin-bottom:40px; }
table {
    width: 100%;
    border-collapse: collapse;
}
table th, table td {
    padding: 12px 15px;
    border: 1px solid #ddd;
    text-align: left;
}
table th { background: #2c7be5; color: #fff; cursor: pointer; }

/* PRINT STYLES */
@media print {
    .sidebar, #printBtn, form[method="GET"], .controls { display:none !important; }
    .main { margin:0; padding:0; }
    .table-wrapper table { width:100%; }
    body { margin:0; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="Static.php">Books History</a></li>
        <li><a href="masterlist.php">Books Masterlist</a></li>
    </ul>
</aside>

<div class="main">
    <h1 style="text-align:center;">Book Borrow Statistics</h1>

    <!-- CENTERED MONTH & TOTAL BORROWS -->
    <div style="text-align:center; margin: 20px 0;">
        <h3>Month: <?php echo $currentMonth; ?></h3>
        <h3>Total Books Borrowed This Month: <span id="monthlyTotal"><?php echo $monthlyTotal; ?></span></h3>
    </div>

    <!-- MONTH FILTER -->
    <form method="GET" id="monthForm" style="text-align:center; margin-bottom:20px;">
        Month: 
        <select name="month" onchange="this.form.submit()">
            <?php foreach ($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= ($selectedMonth==$num)?'selected':'' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- PRINT BUTTON ONLY -->
    <div class="controls">
        <button id="printBtn" onclick="window.print()">Print Report</button>
    </div>

    <div class="table-wrapper">
        <table id="transactionTable">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">Total Borrows &#x25B2;&#x25BC;</th>
                    <th onclick="sortTable(1)">Book &#x25B2;&#x25BC;</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions->num_rows > 0): ?>
                    <?php while ($row = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['total_borrows']) ?></td>
                            <td><?= htmlspecialchars($row['Title']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="text-align:center;"> No borrowed books this month </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ========================
// SORT TABLE FUNCTION
// ========================
function sortTable(colIndex) {
    const table = document.getElementById("transactionTable");
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const asc = table.getAttribute("data-sort-dir") !== "asc";
    rows.sort((a, b) => {
        let aText = a.cells[colIndex].innerText;
        let bText = b.cells[colIndex].innerText;
        if(!isNaN(aText) && !isNaN(bText)){
            return asc ? aText - bText : bText - aText;
        }
        return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    rows.forEach(row => tbody.appendChild(row));
    table.setAttribute("data-sort-dir", asc ? "asc" : "desc");
}
</script>

</body>
</html>