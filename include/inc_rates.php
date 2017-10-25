<?php

/**
 * This class is put together to contain all valid currencies stored by the
 * system, as well as the most up to date exchange rates.
 */
class currencyapi {
	//An XML object containing names, codes and locations for all valid currencies
	private $currencies;
	//An XML object containing codes and exchange rates for all selected currencies
	private $rates;

	//Any error code that has been set, defaults to false on construction
	private $error;
	
	//The XML object to send as a response
	private $response;
	//The root node of the response object
	private $root;
	//The main body to be added to the response
	private $body;
	
	/**
	 * This function initiates the rates object, either retrieving information
	 * of all currencies and exchange rates, or initialising the files containing
	 * them if they do not yet exist.
	 */
	function __construct() {
		$this->currencies = new DOMDocument();
		$this->rates = new DOMDocument();
		$this->response = new DOMDocument();
		//Set the error to false, any number assigned will pass as true
		$this->error = false;
		
		//Temporary success body made here, need to format successful response
		$this->body = $this->response->createElement("successful");
		
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
	 * @return float The exchange rate currently stored, false if no valid result.
	 */
	private function check_rate($currency) {
		$query = new DOMXpath($this->rates);
		$result = $query->evaluate("number(//rate[@code='{$currency}']/@value)");
		
		//If no valid result is found, set an error
		if(is_nan($result)) {
			$this->error = 1200;
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function checks the name associated with a single currency.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return string The name of the given currency, false if no valid result.
	 */
	private function check_name($currency) {
		$query = new DOMXpath($this->currencies);
		$result = $query->evaluate("string(//currency[code='{$currency}']/name");
		
		//If no valid result is found, set an error
		if(!is_string($result)) {
			$this->error = 1200;
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function checks the locations associated with a single currency.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return string The locations of the given currency, false if no valid result.
	 */
	private function check_location($currency) {
		$query = new DOMXpath($this->currencies);
		$result = $query->evaluate("string(//currency[code='{$currency}']/location)");
		
		//If no valid result is found, set an error
		if(!is_string($result)) {
			$this->error = 1200;
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function returns any given error code in the API's runtime
	 */
	public function error() {
		return $this->error;
	}
	
	/**
	 * This function looks up the relevant error message for the most recent error
	 * encountered in the API.
	 * @return string The error message encountered, or false if no error occurred
	 */
	public function error_message() {
		switch($this->error) {
			//Error messages for GET requests
			case 1000 : $error = "Required parameter is missing"; break;
			case 1100 : $error = "Parameter not recognized"; break;
			case 1200 : $error = "Currency type not recognized"; break;
			case 1300 : $error = "Currency amount must be a decimal number"; break;
			case 1400 : $error = "Format must be xml or json"; break;
			case 1500 : $error = "Error in service"; break;
			
			//Error messages for POST, PUT and DELETE requests
			case 2000 : $error = "Method not recognized or is missing"; break;
			case 2100 : $error = "Rate in wrong format or is missing"; break;
			case 2200 : $error = "Currency code in wrong format or is missing"; break;
			case 2300 : $error = "Country name in wrong format or is missing"; break;
			case 2400 : $error = "Currency code not found for update"; break;
			case 2500 : $error = "Error in service"; break;
			
			default : $error = false; break;	
		}
		
		return $error;
	}
	
	/**
	 * This function converts a specified amount between two ISO currencies.
	 * @param string $from The three letter ISO currency code to convert from.
	 * @param string $to The three letter ISO currency code to convert to.
	 * @param float $amount The amount of the from currency to be converted.
	 * @return float The amount resulting from the conversion, or false if an
	 * error was encountered.
	 */
	private function convert($from, $to, $amount = 1) {
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
	
//	public function check_parameters($requiredParameters, $givenParameters) {
//		$valid = true;
//		foreach($requiredParameters AS $required) {
//			if(!isset($givenParameters[strtolower($required)])) {
//				switch(strtolower($required)) {
//					case "method" : $this->error = 2000; break;
//					default : $this->error = 1000; break;
//				}
//				$valid = false;
//				break;
//			}
//			else {
//				unset($givenParameters[strtolower($required)]);
//			}
//		}
//		
//		//If any parameters remain, they are unrecognized
//		if(count($givenParameters)) {
//			$this->error = 1100;
//		}
//		return $valid;
//		
//		return true;
//	}
	
//	public function response() {
//		$xml = new DOMDocument();
//		$root = $xml->createElement("conv");
//		$xml->appendChild($root);
//		if($this->error) {
//			$error = $xml->createElement("error");
//			$root->appendChild($error);
//		}
//		else {
//			
//		}
//		
//		return $xml->saveXML();
//	}
	
	/**
	 * This function checks whether all required parameters are present, and
	 * that no unrecognized parameters are.
	 * @param array $requiredParameters An array of required parameter names.
	 * @param array $givenParameters An array of parameters, indexed by name.
	 * @return boolean Whether or not all (and only) required parameters are set.
	 */
	private function check_parameters($requiredParameters, $givenParameters) {
		$valid = true;
		//Loop through each required parameter to compare
		foreach($requiredParameters AS $required) {
			//Unset the proper parameters, so we can check for any unrecognized
			if(isset($givenParameters[$required])) {
				unset($givenParameters[$required]);
			}
			//If a required parameter is not found set an error
			else {
				$this->error = 1000;
				$valid = false;
			}
		}
		
		//If there are any additional parameters given (unrecognized) set an error
		if(count($givenParameters)) {
			$this->error = 1100;
			$valid = false;
		}
		
		return $valid;
	}
	
	private function response() {
		if($this->error) {
			$error = $this->response->createElement("error");
			$this->root->appendChild($error);

			$code = $this->response->createElement("code", $this->error);
			$error->appendChild($code);

			$message = $this->response->createElement("msg", $this->error_message());
			$error->appendChild($message);
		}
		else {
			$this->root->appendChild($this->body);
		}
		
		return $this->response->saveXML();
	}
	
	private function send_response($format = "xml") {
		//Set JSON headers if specified
		if(strtolower($format) === "json") {
			header("Content-type: application/json");
			
			//TODO format as JSON instead of XML, json_encode not sufficient
			echo $this->response();
		}
		//Otherwise default to XML headers
		else {
			header("Content-type: text/xml");
			//If XML was defaulted to, not specified, set an error
			if(strtolower($format) !== "xml") {
				$this->error = 1400;
			}
			
			//Send the response as XML
			echo $this->response();
		}
	}
	
	public function get($parameters) {
		$requiredParameters = array(
			'from',
			'to',
			'amnt',
			'format'
		);
		//Check that all parameters given are correct
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->convert($parameters['from'], $parameters['to'], $parameters['amnt']);
		}
		
		$this->root = $this->response->createElement("conv");
		$this->response->appendChild($this->root);
		$this->send_response($parameters['format']);
	}
	
	public function post($parameters) {
		$requiredParameters = array(
			'method',
			'code',
			'rate'
		);
		$this->check_parameters($requiredParameters, $parameters);
		
	}
	
	public function put($parameters) {
		$requiredParameters = array(
			'method',
			'code'
		);
		$this->check_parameters($requiredParameters, $parameters);
		
	}
	
	public function delete($parameters) {
		$requiredParameters = array(
			'method',
			'code'
		);
		$this->check_parameters($requiredParameters, $parameters);
		
	}
}