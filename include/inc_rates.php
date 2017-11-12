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
		$this->response = new DOMDocument('1.0', 'UTF-8');
		$this->response->preserveWhiteSpace = false;
		$this->response->formatOutput = true;
		//Set the error to false, any number later assigned will pass as true
		$this->set_error(false);
		
		//Temporary success body made here, need to format successful response
		$this->body = $this->response->createElement("successful");
		
		//If the currencies file does not exist populate it, otherwise load it
		if(!file_exists(CURRENCIES_FILE)) {
			$this->initialise_currencies();
		}
		else {
			$this->currencies->load(CURRENCIES_FILE);
		}
		
		//If the rates file does not exist populate it, otherwise load it
		if(!file_exists(RATES_FILE)) {
			$this->initialise_rates();
		}
		else {
			$this->rates->load(RATES_FILE);
		}
		
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
		//Check that the source file can be located
		if(file_exists(CURRENCIES_SOURCE)) {
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

			//Piece together the new XML document with all 
			$root = $this->currencies->createElement("currencies");
			$this->currencies->appendChild($root);
			foreach($currencies AS $currency) {
				$item = $this->currencies->createElement("currency");
				$root->appendChild($item);
				$item->appendChild($this->currencies->createElement("code", $currency['code']));
				$item->appendChild($this->currencies->createElement("name", $currency['curr']));
				$item->appendChild($this->currencies->createElement("location", join(", ", $currency['loc'])));
			}

			//Write the XML out to a file
			$this->currencies->save(CURRENCIES_FILE);
		}
		//If the file cannot be found, set an error in service
		else {
			$this->set_error(1500);
		}
	}
	
	/**
	 * This function retrieves information of exchange rates specified in our
	 * configuration and stores them in an XML file.
	 */
	private function initialise_rates() {		
		$this->fetch_rates(explode(",", DEFAULT_CURRENCIES));
	}
	
	/**
	 * This function retrieves all currencies currently in our exchange rates
	 * XML file, and updates their exchange rates to the most recent values to
	 * be stored in the file.
	 * @param string $additional An additional three letter ISO code to be added.
	 */
	private function update_rates($additional = null) {
		$query = new DOMXpath($this->rates);
		
		$currencies = array();
		$codes = $query->query("//rate/@code");
		foreach($codes AS $code) {
			$currencies[] = $code->nodeValue;
		}
		
		//If an additional currency is provided, and is not already used, add it
		if(isset($additional) && !in_array(strtoupper($additional), $currencies)) {
			$currencies[] = strtoupper($additional);
		}
		
		//Set up rates as a clear DOMDocument, to avoid duplication
		$this->rates = new DOMDocument();
		$this->fetch_rates($currencies);
	}
	
	/**
	 * This function fetches the most recent exchange rates for a set of
	 * currencies provided, and writes them out to our XML file.
	 * @param array $currencies An array containing three letter ISO currencies.
	 */
	private function fetch_rates($currencies) {
		//Check that the source file can be located
		if(file_exists(RATES_SOURCE.RATES_KEY)) {
			$data = file_get_contents(RATES_SOURCE.RATES_KEY);
			$rates = json_decode($data, true);
			
			//Check that the file has no error (e.g. invalid key, limit reached)
			if(!isset($rates['error'])) {
				//Initialize (or re-initialize to be clear) the DOMDocument
				$this->rates = new DOMDocument();

				$root = $this->rates->createElement("rates");
				$this->rates->appendChild($root);
				//Loop through each of the desired currencies
				foreach($currencies AS $currency) {
					//Ensure that the rate exists in data
					if(isset($rates['rates'][$currency])) {
						$element = $this->rates->createElement("rate");
						$root->appendChild($element);

						//Write all of the data in as attributes
						$codeAttribute = $this->rates->createAttribute("code");
						$codeAttribute->value = $currency;
						$element->appendChild($codeAttribute);

						$valueAttribute = $this->rates->createAttribute("value");
						$valueAttribute->value = $rates['rates'][$currency];
						$element->appendChild($valueAttribute);

						$timeAttribute = $this->rates->createAttribute("timestamp");
						$timeAttribute->value = $rates['timestamp'];
						$element->appendChild($timeAttribute);
					}
					//Otherwise set an error for the current request
					else {
						$this->set_error(1200);
					}
				}

				//Save the XML regardless of an error encountered, removing any invalid currencies
				$this->rates->save(RATES_FILE);
			}
			//If an error is in the file, set an error in service
			else {
				$this->set_error(1500);
			}
		}
		//If the file cannot be found, set an error in service
		else {
			$this->set_error(1500);
		}
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
			$this->set_error(1200);
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function checks whether a currency is valid in our set of ISO currencies.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return boolean Whether or not the currency is valid.
	 */
	private function validate_currency($currency) {
		$query = new DOMXpath($this->currencies);
		$result = $query->evaluate("//currency[code='{$currency}']");
		
		if(!$result) {
			$this->set_error(2200);
		}
		
		return (bool)$result;
	}
	
	/**
	 * This function checks the name associated with a single currency.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return string The name of the given currency, false if no valid result.
	 */
	private function currency_name($currency) {
		$query = new DOMXpath($this->currencies);
		$result = $query->evaluate("string(//currency[code='{$currency}']/name");
		
		//If no valid result is found, set an error
		if(!is_string($result)) {
			$this->set_error(1200);
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function checks the locations associated with a single currency.
	 * @param string $currency The three letter ISO currency code to be checked.
	 * @return string The locations of the given currency, false if no valid result.
	 */
	private function currency_location($currency) {
		$query = new DOMXpath($this->currencies);
		$result = $query->evaluate("string(//currency[code='{$currency}']/location)");
		
		//If no valid result is found, set an error
		if(!is_string($result)) {
			$this->set_error(1200);
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
	private function convert($from, $to, $amount) {
		$rate = $this->conversion_rate($from, $to);
		
		//Check that a valid amount is being converted
		if(!preg_match('/^\d+\.\d+$/', (string)$amount)) {
			$this->set_error(1300);
		}
		
		//Check no error has been encountered
		if(!$this->error) {
			$result = $rate * $amount;
		}
		else {
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function checks the rate of conversion between two ISO currencies.
	 * @param string $from The three letter ISO currency code to convert from.
	 * @param string $to The three letter ISO currency code to convert to.
	 * @return float The resulting conversion rate between the two currencies.
	 */
	private function conversion_rate($from, $to) {
		$fromRate = $this->check_rate($from);
		$toRate = $this->check_rate($to);
		
		return $toRate / $fromRate;
	}
	
	/**
	 * This function adds a new exchange rate into our set of rates.
	 * @param string $currency The three letter ISO currency code to add.
	 * @return boolean Whether the value was successfully inserted or not.
	 */
	private function insert_rate($currency) {
		if($this->validate_currency($currency)) {
			//Update the rates with the new currency added in
			$this->update_rates($currency);
			
			$result = true;
		}
		else {
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * This function removes a specified exchange rate from our set of rates.
	 * @param string $currency The three letter ISO currency code to remove.
	 */
	private function remove_rate($currency) {
		$query = new DOMXpath($this->rates);
		$rates = $query->query("//rate[@code='{$currency}']");

		//Loop through to remove all instances in case of duplication
		foreach($rates AS $rate) {
			$rate->parentNode->removeChild($rate);
		}
		
		//Write over the file with the rate removed
		$this->rates->save(RATES_FILE);
	}
	
	/**
	 * This function edits the exchange rate of an existing currency in our set of rates.
	 * @param string $currency The three letter ISO currency code to edit.
	 * @param float $value The exchange rate to apply to our currency.
	 * @return boolean Whether the rate was successfully edited or not.
	 */
	private function edit_rate($currency, $value) {
		//Check that a valid rate is being given
		if(!preg_match('/^\d+\.\d+$/', (string)$value)) {
			$this->set_error(2100);
		}
		
		if(!$this->error) {
			$query = new DOMXpath($this->rates);
			$rates = $query->query("//rate[@code='{$currency}']");

			//Loop through to edit all instances in case of duplication
			foreach($rates AS $rate) {
				$rate->setAttribute("value", $value);
				$rate->setAttribute("timestamp", time());
			}

			//Write over the file with the rate removed
			$this->rates->save(RATES_FILE);
			
			$result = true;
		}
		else {
			$result = false;
		}
		
		return $result;
	}
	
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
			if(isset($givenParameters[$required]) && $givenParameters[$required] != "") {
				unset($givenParameters[$required]);
			}
			//If a required parameter is not found set an error
			else {
				$this->set_error(1000);
				$valid = false;
			}
		}
		
		//If there are any additional parameters given (unrecognized) set an error
		if(count($givenParameters)) {
			$this->set_error(1100);
			$valid = false;
		}
		
		return $valid;
	}
	
	/**
	 * This function gives the response from our API call as set up so far.
	 * @return string This returns the raw XML for our response.
	 */
	private function response() {
		if($this->error) {
			$error = $this->response->createElement("error");
			$this->root->appendChild($error);

			$code = $this->response->createElement("code", $this->error);
			$error->appendChild($code);

			$message = $this->response->createElement("msg", $this->error_message());
			$error->appendChild($message);
		}

		return $this->response->saveXML();
	}
	
	/**
	 * This function outputs the API's response in a given format.
	 * @param string $format The format to output our response, expecting "xml" or "json".
	 */
	private function send_response($format = DEFAULT_FORMAT) {
		//Set JSON headers if specified
		if(strtolower($format) === "json") {
			header("Content-type: application/json");
			
			//TODO format as JSON instead of XML, json_encode not sufficient
			echo json_encode(simplexml_load_string($this->response()), JSON_PRETTY_PRINT);
		}
		//Otherwise default to XML headers
		else {
			header("Content-type: text/xml");
			//If XML was defaulted to, not specified, set an error
			if(strtolower($format) !== "xml") {
				$this->set_error(1400);
			}
			
			//Send the response as XML
			echo $this->response();
		}
	}
	
	/**
	 * This function sets an error to our request, if one has not been encountered yet.
	 * @param int $error The error code encountered.
	 */
	private function set_error($error) {
		if(!$this->error) {
			$this->error = $error;
		}
	}
	
	/**
	 * This function completes a get request (converting between currencies).
	 * @param array $parameters The parameters sent by the request, expecting 'from',
	 * 'to', 'amnt' and 'format'.
	 */
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
		
		$this->send_response(isset($parameters['format']) ? $parameters['format'] : null);
	}

	/**
	 * This function completes a post request (updating a currency's exchange rate).
	 * @param array $parameters The parameters sent by the request, expecting 'code'
	 * and 'rate'.
	 */
	public function post($parameters) {
		$requiredParameters = array(
//			'method',
			'code',
			'rate'
		);
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->edit_rate($parameters['code'], $parameters['rate']);
		}
		
		$this->root = $this->response->createElement("conv");
		$this->response->appendChild($this->root);
		
		$this->send_response();
	}

	/**
	 * This function completes a put request (adding a new currency in).
	 * @param array $parameters The parameters sent by the request, expecting 'code'.
	 */
	public function put($parameters) {
		$requiredParameters = array(
//			'method',
			'code'
		);
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->insert_rate($parameters['code']);
		}

		$this->root = $this->response->createElement("conv");
		$this->response->appendChild($this->root);
		
		$this->send_response();
	}

	/**
	 * This function completes a delete request (removing a currency).
	 * @param array $parameters The parameters sent by the request, expecting 'code'.
	 */
	public function delete($parameters) {
		$requiredParameters = array(
//			'method',
			'code'
		);
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->remove_rate($parameters['code']);
		}

		$this->root = $this->response->createElement("conv");
		$this->response->appendChild($this->root);
		
		$this->send_response();		
	}
}