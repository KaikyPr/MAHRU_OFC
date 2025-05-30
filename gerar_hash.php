<?php
$senha_para_hash = "12345"; // Ou a senha que desejar
$hash = password_hash($senha_para_hash, PASSWORD_DEFAULT);
echo "Hash: " . $hash;
?>
