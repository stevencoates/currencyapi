<?php

//$configuration = simplexml_load_file("configuration.xml");
$configuration = new DOMDocument();
$configuration->load("configuration.xml");

define("BASE_CURRENCY", $configuration->getElementsByTagName("base")->item(0)->nodeValue);

define("DEFAULT_FORMAT", $configuration->getElementsByTagName("default_format")->item(0)->nodeValue);

define("CURRENCIES_FILE", $configuration->getElementsByTagName("currencies_data")->item(0)->nodeValue);
define("CURRENCIES_SOURCE", $configuration->getElementsByTagName("currencies_source")->item(0)->nodeValue);

define("RATES_FILE", $configuration->getElementsByTagName("rates_data")->item(0)->nodeValue);
define("RATES_SOURCE", $configuration->getElementsByTagName("rates_source")->item(0)->nodeValue);
define("RATES_KEY", $configuration->getElementsByTagName("rates_key")->item(0)->nodeValue);


$defaultCurrencies = array();
foreach($configuration->getElementsByTagName("currency") AS $currency) {
	$defaultCurrencies[] = $currency->textContent;
}
define("DEFAULT_CURRENCIES", join(",",$defaultCurrencies));