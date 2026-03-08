<?php
session_start();
include 'db_connect.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrow Book</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .container {
            width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }

        label {
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Borrow Book</h2>

    <label>Borrower ID</label>
    <input type="number" id="borrower_id" name="borrower_id">

    <label>First Name</label>
    <input type="text" id="first_name" readonly>

    <label>Middle Name</label>
    <input type="text" id="middle_name" readonly>

    <label>Last Name</label>
    <input type="text" id="last_name" readonly>

    <label>Borrower Name</label>
    <input type="text" id="borrower_name" readonly>

    <label>Course</label>
    <input type="text" id="course" readonly>

    <label>Year</label>
    <input type="text" id="year" readonly>

    <label>Borrower Type</label>
    <input type="text" id="borrower_type" readonly>

    <label>Borrow Period</label>
    <input type="text" id="borrow_period" readonly>
</div>

<script>
$(document).ready(function(){

    $("#borrower_id").on("keyup change", function(){

        var borrower_id = $(this).val();

        if (borrower_id !== "") {

            $.ajax({
                url: "fetch_borrower.php",
                type: "POST",
                data: { borrower_id: borrower_id },
                success: function(data){

                    if (data !== null) {
                        $("#first_name").val(data.first_name);
                        $("#middle_name").val(data.middle_name);
                        $("#last_name").val(data.last_name);
                        $("#borrower_name").val(data.borrower_name);
                        $("#course").val(data.course);
                        $("#year").val(data.year);
                        $("#borrower_type").val(data.borrower_type);
                        $("#borrow_period").val(data.borrow_period);
                    } else {
                        alert("Borrower not found!");
                        $("input[type=text]").val("");
                    }
                }
            });

        } else {
            $("input[type=text]").val("");
        }

    });

});
</script>

</body>
</html>
