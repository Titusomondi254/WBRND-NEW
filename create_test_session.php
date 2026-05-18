<?php
$id = 'test123';
$data = 'user_id|i:1;';
file_put_contents('C:\xampp\tmp\sess_' . $id, $data);
echo 'session ok';
