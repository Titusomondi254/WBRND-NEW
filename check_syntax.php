<?php
$file = 'c:\xampp\htdocs\WBRND\WBRND\property_listings.php';
$output = [];
$return = 0;
exec("\"C:\\xampp\\php\\php.exe\" -l \"" . $file . "\"", $output, $return);
echo implode("\n", $output);
?>