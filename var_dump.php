<?php
$json = '{"a":1,"b":2,"c":3,"d":4,"e":5}';

$x = json_decode($json, true);
//var_dump(json_decode($json, true));
echo $x;

?>