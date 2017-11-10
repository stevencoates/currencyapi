<?php
require_once("include/inc_all.php");

//Initiate our rates object
$api = new currencyapi();

//Filter all of the get inputs into a new array
$get = filter_input_array(INPUT_GET);

$api->get($get);