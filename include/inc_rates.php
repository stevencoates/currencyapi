<?php

/**
 * Description of inc_rates
 *
 * @author steve
 */
class rates {
	private $data;
	
	function __construct() {
		$this->data = new DOMDocument();
		if(file_exists(RATES_FILE)) {
			$this->populate_rates();
		}
		else {
			$this->initialise_rates();
		}
	}
    
	private function initialise_rates() {
		$rawCurrencies = new DOMDocument();
		$rawCurrencies->load(CURRENCIES_FILE);
		
		$currencies = array();
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
		echo "<pre>";
		print_r($currencies);
		echo "</pre>";
	}
	
	private function populate_rates() {
		
	}
	
	private function save_rates() {
		
	}
}
