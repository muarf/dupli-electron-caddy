<?php
header('Content-Type: text/plain');
echo "--- POST DATA ---\n";
print_r($_POST);
echo "\n--- FILES DATA ---\n";
print_r($_FILES);
echo "\n--- PHP INPUT ---\n";
echo file_get_contents('php://input');
