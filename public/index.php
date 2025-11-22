<?php
include("includes/db_connect.php");
?>

<!DOCTYPE html>
<html lang="de">
<?php
    include("includes/header.php");
?>

<body>
<div class="container mt-5">
    <h1 class="text-center">Willkommen bei ParkShare</h1>
    <form method="GET" action="search.php" class="row g-3 justify-content-center mt-4">
        <div class="col-md-4">
            <input type="text" class="form-control" name="location" placeholder="Ort eingeben">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">Suchen</button>
        </div>
    </form>
</div>
</body>
</html>
