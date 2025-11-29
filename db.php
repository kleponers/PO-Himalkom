<?php
$host = 'localhost';
$dbname = 'postgres';
$user = 'postgres';
$password = 'Bogor@789'; 

$conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");
if (!$conn) {
  die("Koneksi gagal: " . pg_last_error());
}

pg_query($conn, "SET search_path TO himalkomerz");
?>
