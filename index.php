<?php
require_once("include/inc_all.php");

//Initiate our rates object
$api = new currencyapi();

//Filter all of the get inputs into a new array
$get = filter_input_array(INPUT_GET);

$api->get($get);

////Check the required parameters are all supplied and valid
//$api->check_parameters(array(), $get);
//
//$conversion = $api->convert($get['from'], $get['to'], $get['amnt']);
//
//$response = new DOMDocument();
//$root = $response->createElement("conv");
//$response->appendChild($root);
//
//if($api->error()) {
//	$error = $response->createElement("error");
//	$root->appendChild($error);
//	
//	$code = $response->createElement("code", $api->error());
//	$error->appendChild($code);
//	
//	$message = $response->createElement("msg", $api->error_message());
//	$error->appendChild($message);
//}
//else {
//	
//}
//
////Output the response as XML
//header("Content-type: text/xml");
//echo $response->saveXML();