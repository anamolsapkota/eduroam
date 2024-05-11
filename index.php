<?php
// Perform a temporary redirect to the root directory ("/")
header('Location: /', true, 302);
exit; // Ensure that no further code is executed after the redirect
?>
