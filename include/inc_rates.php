<?php

/**
 * This class is put together to contain all valid currencies stored by the
 * system, as well as the most up to date exchange rates.
 */
class rates {
	private $currencies;
	private $rates;
	private $error;
	
	/**
	 * This function initiates the rates object, either retrieving information
	 * of all currencies and exchange rates, or initialising the files containing
	 * them if they do not yet exist.
	 */
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
    
	/**
	 * This function retrieves information relating to all valid ISO currencies
	 * and stores them in an XML file to be referenced along with exchange rates.
	 */
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
		
		//Piece together the new XML document with all currencies
		$xml = new DOMDocument();
		$root = $xml->createElement("currencies");
		$xml->appendChild($root);
		foreach($currencies AS $currency) {
			$item = $xml->createElement("currency");
			$root->appendChild($item);
			$item->appendChild($xml->createElement("code", $currency['code']));
			$item->appendChild($xml->createElement("name", $currency['curr']));
			$item->appendChild($xml->createElement("location", join(", ", $currency['loc'])));
		}
		
		//Write the XML out to a file
		$xml->save(CURRENCIES_FILE);
	}
	
	/**
	 * This function retrieves information of exchange rates specified in our
	 * configuration and stores them in an XML file.
	 */
	private function initialise_rates() {		
		$rates = $this->fetch_rates(explode(",", DEFAULT_CURRENCIES));
		$this->write_rates($rates);
	}
	
	/**
	 * This function retrieves all currencies currently in our exchange rates
	 * XML file, and updates their exchange rates to the most recent values to
	 * be stored in the file.
	 */
	private function update_rates() {
		$query = new DOMXpath($this->rates);
		
		$currencies = array();
		$codes = $query->query("//rate/@code");
		foreach($codes AS $code) {
			$currencies[] = $code->nodeValue;
		}
		
		$rates = $this->fetch_rates($codes);
		$this->write_rates($rates);
	}
	
	/**
	 * This function writes a set of exchange rates to an XML file.
	 * @param DOMNodeList $rates The set of exchange rates to be stored.
	 */
	private function write_rates($rates) {
		//Set a timestamp for the time we're updating the rates
		//This is opting to use system time, rather than the respone timestamp to avoid
		//any confusion of timezones.
		$timestamp = new DateTime();
		
		//Piece together the new XML document with all given rates
		$xml = new DOMDocument();
		$root = $xml->createElement("rates");
		$xml->appendChild($root);
		foreach($rates->getElementsByTagName("rate") AS $rate) {
			$item = $xml->createElement("rate");
			$root->appendChild($item);
			
			$codeAttribute = $xml->createAttribute("code");
			$codeAttribute->value = substr($rate->getElementsByTagName("Name")->item(0)->nodeValue, -3);
			$item->appendChild($codeAttribute);
			
			$valueAttribute = $xml->createAttribute("value");
			$valueAttribute->value = $rate->getElementsByTagName("Rate")->item(0)->nodeValue;
			$item->appendChild($valueAttribute);
			
			$timeAttribute = $xml->createAttribute("timestamp");
			$timeAttribute->value = $timestamp->format("U");
			$item->appendChild($timeAttribute);
		}
			
		//Write the XML out to a file
		$xml->save(RATES_FILE);
	}
	
	/**
	 * This function fetches the most recent exchange rates for a set of
	 * currencies provided.
	 * @param array $currencies The exchange rates to be retrieved.
	 * @return DOMDocument An XML document containing the rates retrieved.
	 */
	private function fetch_rates($currencies) {
		$pairs = array();
		//Pair each of the currencies with the base currency
		foreach($currencies AS $currency) {
			//Write the pair of currencies in quotes for our query
			$pairs[] = "'".BASE_CURRENCY.$currency."'";
		}
		
		//Parse the query so that we can fetch it
		$query = urlencode("select * from yahoo.finance.xchange where pair in ".
							"(".join(",", $pairs).")");
		
		$rates = new DOMDocument();
		try {
			$rates->load(RATES_SOURCE.$query);
		}
		catch(Exception $e) {
			echo "error";
		}
		
		return $rates;
	}
	
	/**
	 * This function checks the most recent exchange rate for a single currency.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return float The exchange rate currently stored.
	 */
	private function check_rate($currency) {
		$query = new DOMXpath($this->rates);
		$result = $query->evaluate("number(//rate[@code='{$currency}']/@value)");
		
		//If no valid result is found, set an error
		if(!$result) {
			$this->error = 1200;
		}
		
		return $result;
	}
	
	private function error_message() {
		switch($this->error) {
			//Error messages for GET
			case 1000 : $msg = "Required parameter is missing"; break;
			case 1100 : $msg = "Parameter not recognized"; break;
			case 1200 : $msg = "Currency type not recognized"; break;
			case 1300 : $msg = "Currency amount must be a decimal number"; break;
			case 1400 : $msg = "Format must be xml or json"; break;
			case 1500 : $msg = "Error in service"; break;
			
			//Error messages for POST, PUT and DELETE
			case 2000 : $msg = "Method not recognized or is missing"; break;
			case 2100 : $msg = "Rate in wrong format or is missing"; break;
			case 2200 : $msg = "Currency code in wrong format or is missing"; break;
			case 2300 : $msg = "Country name in wrong format or is missing"; break;
			case 2400 : $msg = "Currency code not found for update"; break;
			case 2500 : $msg = "Error in service"; break;
			
			default : $msg = "An unknown error has occurred"; break;	
		}
		
		return $msg;
	}
	
	/**
	 * This function converts a specified amount between two ISO currencies.
	 * @param string $from The three letter ISO currency code to convert from.
	 * @param string $to The three letter ISO currency code to convert to.
	 * @param float $amount The amount of the from currency to be converted.
	 * @return float The amount resulting from the conversion, or false if an
	 * error was encountered.
	 */
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
	
	public function response() {
		$xml = new DOMDocument();
		$root = $xml->createElement("conv");
		$xml->appendChild($root);
		if(isset($this->error)) {
			$error = $xml->createElement("error");
			$root->appendChild($error);
		}
		else {
			
		}
		
		return $xml->saveXML();
	}
}