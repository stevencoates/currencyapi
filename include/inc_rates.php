<?php

/**
 * Description of inc_rates
 */
class rates {
	private $currencies;
	private $rates;
	
	function __construct() {
		$this->currencies = new DOMDocument();
		$this->rates = new DOMDocument();
		
		//If the currencies file does not exist, populate it before loading
		if(file_exists(CURRENCIES_FILE)) {
			$this->initialise_currencies();
		}
		$this->currencies->load(CURRENCIES_FILE);
		
		//If the rates file does not exist, populate it befor eloading
		if(file_exists(RATES_FILE)) {
			$this->initialise_rates();
		}
		$this->rates->load(RATES_FILE);
	}
    
	private function initialise_currencies() {
		$rawCurrencies = new DOMDocument();
		$rawCurrencies->load(CURRENCIES_SOURCE);
		
		$currencies = array();
		//First process the raw data so that it is grouped by currency, not country
		foreach($rawCurrencies->getElementsByTagName("CcyNtry") AS $currency) {
			//Check if a currency has yet been processed with this code
			if(!isset($currencies[$currency->getElementsByTagName("Ccy")->item(0)->nodeValue])) {
				$currencies[$currency->getElementsByTagName("Ccy")->item(0)->nodeValue] =
					array(
						'code' => $currency->getElementsByTagName("Ccy")->item(0)->nodeValue,
						'curr' => $currency->getElementsByTagName("CcyNm")->item(0)->nodeValue,
						'loc' => array(),
						'amnt' => 0
					);
			}
			//Add the location in to the currency's object
			$currencies[$currency->getElementsByTagName("Ccy")->item(0)->nodeValue]['loc'][] =
				$currency->getElementsByTagName("CtryNm")->item(0)->nodeValue;
		}
		
		//Piece together the XML file to be saved with all currencies
		$xml =
		"<?xml version='1.0' encoding='UTF-8'?>".
		"<currencies>";
			foreach($currencies AS $currency) {
				$xml .=
				"<currency>".
					"<code>{$currency['code']}</code>".
					"<name>{$currency['curr']}</name>".
					//Locaitons need joining, since stored as an array
					"<location>".join(", ", $currency['loc'])."</location>".
				"</currency>";
			}
			$xml .=
		"</currencies>";
			
		file_put_contents(CURRENCIES_FILE, $xml);
	}
	
	private function initialise_rates() {
		$yql = "";
		foreach(CURRENCIES AS $currency) {
			
		}
	}
}
