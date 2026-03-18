<?php

require_once 'includes/config.php';

http_response_code(410);
header('Content-Type: application/json');
echo json_encode(array(
    'status' => 'error',
    'message' => 'Manual approval is no longer used. Guest accounts are created automatically from the request form.'
));
exit;

?>
