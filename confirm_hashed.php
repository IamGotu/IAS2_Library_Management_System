<?php
$plain = 'admin123';
$hash = '$2y$10$xAe.E9rbbJFq..f9FfTbH.TLMNg8zLoVbKQwLUiiXSg7gQ6ZVih0O';
var_dump(password_verify($plain, $hash));
?>
