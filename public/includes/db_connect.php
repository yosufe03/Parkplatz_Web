<?php

$servername = "db";
$username = "db";
$password = "db";
$dbname = "db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


