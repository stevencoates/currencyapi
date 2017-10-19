<?php
require_once("include/inc_all.php");

//Initiate our rates object
$rates = new rates();

//Filter all of the get inputs into a new array
$get = filter_input_array(INPUT_GET);

echo $rates->convert($get['from'], $get['to'], $get['amnt']);