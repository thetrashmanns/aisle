<?php
// If the request is made from our space preview functionality then turn on PHP error reporting
if (isset($_SERVER['HTTP_X_FORWARDED_URL']) && strpos($_SERVER['HTTP_X_FORWARDED_URL'], '.w3spaces-preview.com/') !== false) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>

<html lang="en">
	<head>
		<title>PHP blank template</title>
		<link rel="stylesheet" href="styles.css">
	</head>
	<body>
		<h1>Aisle Location Label Creator</h1><br>
		<h2>Created to prevent repetitive strain injuries</h2><br><br>
		<form>
			<label for="aisle-letter"></label>
			<input type="text" id="aisle-letter" name="aisle-letter"><br>
		</form>
	</body>
</html>
