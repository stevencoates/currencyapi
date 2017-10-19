<?php

/**
 * Description of inc_rates
 */
class rates {
	private $currencies;
	private $rates;
	private $error;
	
	function __construct() {
		$this->currencies = new DOMDocument();
		$this->rates = new DOMDocument();
		
		//If the currencies file does not exist, populate it before loading
		if(!file_exists(CURRENCIES_FILE)) {
			$this->initialise_currencies();
		}
		$this->currencies->load(CURRENCIES_FILE);
		
		//If the rates file does not exist, populate it befor eloading
		if(!file_exists(RATES_FILE)) {
			$this->initialise_rates();
		}
		$this->rates->load(RATES_FILE);
		
		//TODO check through all rates here and then update if needed
		if(false) {
			$this->update_rates();
		}
	}
    
	private function initialise_currencies() {
		$countries = new DOMDocument();
		$countries->load(CURRENCIES_SOURCE);
		
		$currencies = array();
		//First process the raw data so that it is grouped by currency, not country
		foreach($countries->getElementsByTagName("CcyNtry") AS $currency) {
			//Check that the currency has all valid data
			if($currency->getElementsByTagName("Ccy")->length) {
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
			
		//Write the XML out to a file
		file_put_contents(CURRENCIES_FILE, $xml);
	}
	
	private function initialise_rates() {		
		$rates = $this->fetch_rates(explode(",", DEFAULT_CURRENCIES));
		
		$xml =
		"<?xml version='1.0' encoding='UTF-8'?>".
		"<rates>";
			foreach($rates->getElementsByTagName("rate") AS $rate) {
				//Pick out the name and rate from the response
				$code = substr($rate->getElementsByTagName("Name")->item(0)->nodeValue, -3);
				$value = $rate->getElementsByTagName("Rate")->item(0)->nodeValue;
				//Set up a timestamp with the response's date and time
				$timestamp = new DateTime();
				$timestamp->modify($rate->getElementsByTagName("Date")->item(0)->nodeValue);
				$timestamp->modify($rate->getElementsByTagName("Time")->item(0)->nodeValue);
				$xml .= "<rate code='{$code}' value='{$value}' ts='{$timestamp->format("U")}'/>";
			}
			$xml .=
		"</rates>";
			
		//Write the XML out to a file
		file_put_contents(RATES_FILE, $xml);
	}
	
	private function update_rates() {
		$query = new DOMXpath($this->rates);
		
		$currencies = array();
		$codes = $query->query("//rate/@code");
		foreach($codes AS $code) {
			$currencies[] = $code->nodeValue;
		}
		
		$this->fetch_rates($codes);
		
		//TODO write to file... see initialise rates and consider moving second half into fetch or new function
	}
	
	private function fetch_rates($currencies) {
		$pairs = array();
		//Pair each of the currencies with the base currency
		foreach($currencies AS $currency) {
			//Write the pair of currencies in quotes for our query
			$pairs[] = "'".BASE_CURRENCY.$currency."'";
		}
		
		//Parse the query so that we can fetch it
		$query = urlencode("select * from yahoo.finance.xchange where pair in (".join(",", $pairs).")");
		
		$rates = new DOMDocument();
		$rates->load(RATES_SOURCE.$query);
		
		return $rates;
	}
	
	private function check_rate($currency) {
		$query = new DOMXpath($this->rates);
		$result = $query->evaluate("number(//rate[@code='{$currency}']/@value)");
		
		//If no valid result is found, set an error
		if(!$result) {
			$this->error = 1200;
		}
		
		return $result;
	}
	
	public function convert($from, $to, $amount = 1) {
		//Check that a valid amount is being converted
		if(!is_numeric($amount)) {
			$this->error = 1300;
		}
		
		$fromRate = $this->check_rate($from);
		$toRate = $this->check_rate($to);
		
		//Check no error has been encountered
		if(!isset($this->error)) {
			$result = ($amount * $toRate)/$fromRate;
		}
		else {
			$result = false;
		}
		
		return $result;
	}
}