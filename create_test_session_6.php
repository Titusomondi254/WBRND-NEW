<?php
$id = 'test456';
$data = 'user_id|i:6;';
file_put_contents('C:\xampp\tmp\sess_' . $id, $data);
echo 'session ok';
