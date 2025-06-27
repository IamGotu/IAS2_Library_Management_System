<?php
$plain = 'itpersonnel123';
$hash = '$2y$10$2olH3zhdByowU4Ij46DEpObqNVQ9/BcBvOvOx6qMLtshm7zlXt0sW';
var_dump(password_verify($plain, $hash));
?>
