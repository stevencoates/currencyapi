<?php

/**
 * This function fetches the up to date conversion rates of all specified
 * currencies, to be updated in the XML file.
 * @return array An array of the results indexed by currency code (GBP, USD)
 */
function fetch_rates() {
	$currencies = array("CAD", "CHF", "CNY", "DKK", "EUR", "GBP", "HKD", "HUF", 
		"INR", "JPY", "MXN", "MYR", "NOK", "NZD", "PHP", "RUB", "SEK", "SGD",
		"THB", "TRY", "USD", "ZAR");
	$pairedCurrencies = array();

	//Loop through each currency we are concerning ourselves with
	foreach($currencies AS $currency) {
		//Omit the base currency, as that value should always remain at 1.0
		if($currency !== BASE_CURRENCY) {
			//Write the pair of currencies in quotes, for use in YQL
			$pairedCurrencies[] = "'".BASE_CURRENCY.$currency."'";
		}
	}
	
	$url = str_replace(" ", "%20", 
			"http://query.yahooapis.com/v1/public/yql?q=".
			"select * from yahoo.finance.xchange where pair in (".join(",", $pairedCurrencies).")".
			"&format=xml&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys");
	
	$xml = simplexml_load_file($url);
	$rates = array();
	
	foreach($xml->results->rate AS $rate) {
		$code = (string)explode("/", $rate->Name)[1];
		$rates[$code] = (float)$rate->Rate;
	}

	return $rates;
}