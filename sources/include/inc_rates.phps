<?php

require_once("inc_error.php");

/**
 * This class is put together to contain all valid currencies stored by the
 * system, as well as the most up to date exchange rates.
 */
class currencyapi {
	//An XML object containing names, codes and locations for all valid currencies
	private $currencies;
	//An XML object containing codes and exchange rates for all selected currencies
	private $rates;

	//Any error object that has been set, defaults to false on construction
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
		$this->error = new apierror();
		
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

		//Set up timestamps to check for the 12 hour cutoff
		$timestamp = new DateTime();
		$cutoff = new DateTime();
		$cutoff->modify("-12 hours");
		//Loop through to check each rate's timestamp
		foreach($this->rates->getElementsByTagName("rate") AS $rate) {
			$timestamp->setTimestamp($rate->getAttribute("timestamp"));
			//If the timestamp is before the 12 hour cutoff, update all rates
			if($timestamp < $cutoff) {
				$this->update_rates();
				break;
			}
		}
	}
    
	/**
	 * This function retrieves information relating to all valid ISO currencies
	 * and stores them in an XML file to be referenced along with exchange rates.
	 */
	private function initialise_currencies() {
		//Check that the source file can be located
		$countries = new DOMDocument();
		if(@$countries->load(CURRENCIES_SOURCE)) {
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
			$this->error->set(1500);
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
        $data = @file_get_contents(RATES_SOURCE.RATES_KEY);
        if($data) {
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
                        $node = $this->rates->createElement("rate");
                        $root->appendChild($node);

                        //Write all of the data in as attributes
                        $codeAttribute = $this->rates->createAttribute("code");
                        $codeAttribute->value = $currency;
                        $node->appendChild($codeAttribute);

                        $valueAttribute = $this->rates->createAttribute("value");
						//The values all need adjusting by the base currency
                        $valueAttribute->value = $rates['rates'][$currency]/$rates['rates'][BASE_CURRENCY];
                        $node->appendChild($valueAttribute);

                        $timeAttribute = $this->rates->createAttribute("timestamp");
                        $timeAttribute->value = $rates['timestamp'];
                        $node->appendChild($timeAttribute);
                    }
                    //Otherwise set an error for the current request
                    else {
                        $this->error->set(2200);
                    }
                }

                //Save the XML regardless of an error encountered, removing any invalid currencies
                $this->rates->save(RATES_FILE);
            }
            //If an error is in the file, set an error in service
            else {
                $this->error->set(1500);
            }
        }
        else {
            $this->error->set(1500);
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
			$this->error->set(1200);
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
			$this->error->set(2200);
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
		$result = $query->evaluate("string(//currency[code='{$currency}']/name)");
		
		//If no valid result is found, set an error
		if(!is_string($result)) {
			$this->error->set(1200);
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
			$this->error->set(1200);
			$result = false;
		}
		
		return $result;
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
		if(!preg_match('/^\d+\.\d{2}$/', (string)$amount)) {
			$this->error->set(1300);
		}
		
		//Check no error has been encountered
		if(!$this->error->code()) {
			$result = round($rate * $amount, 2);
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
        
        if($toRate && $fromRate) {
            $result = $toRate / $fromRate;
        }
        else {
            $result = false;
            $this->error->set(1500);
        }
	
		return $result;
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

		if($rates->length) {
			//Loop through to remove all instances in case of duplication
			foreach($rates AS $rate) {
				$rate->parentNode->removeChild($rate);
			}
		}
		else {
			$this->error->set(2200);
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
			$this->error->set(2100);
		}
		
		if(!$this->error->code()) {
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
	private function check_parameters($requiredParameters, &$givenParameters) {
		$valid = true;
		//Take a copy of the given parameters to destructively check
		$checkParameters = $givenParameters;
		//First check we have an array at all
		if(is_array($givenParameters)) {
			//Loop through each required parameter to compare
			foreach($requiredParameters AS $required) {
				//Unset the proper parameters, so we can check for any unrecognized
				if(isset($checkParameters[$required]) && $checkParameters[$required] != "") {
					unset($checkParameters[$required]);
				}
				//If a required parameter is not found set an error
				else {
					$this->error->set(1000);
					$valid = false;
				}
			}

			//If there are any additional parameters given (unrecognized) set an error
			if(count($checkParameters)) {
				$this->error->set(1100);
				$valid = false;
			}

			//Run all of the given parameters through strtoupper
			$givenParameters = array_map("strtoupper", $givenParameters);
		}
		//If not, no parameters were set
		else {
			$this->error->set(1000);
			$valid = false;
		}
		
		return $valid;
	}
	
	/**
	 * This function creates the root node of the response object, based on the
	 * method provided.
	 * @param string $method The method type used. If not provided, root will be conv.
	 */
	private function write_root($method = null) {
		//If a method is provided (PUT, POST, DELETE requests, we want a method root node
		if(isset($method)) {
			$this->root = $this->response->createElement("method");

			//Add in the type attribute on the root node
			$methodAttribute = $this->response->createAttribute("type");
			$methodAttribute->value = $method;
			$this->root->appendChild($methodAttribute);	
		}
		//Otherwise provide a conv root node
		else {
			$this->root = $this->response->createElement("conv");
		}
		
		$timestamp = new DateTime();
		$timeNode = $this->response->createElement("at", $timestamp->format("d M Y g:i"));
		$this->root->appendChild($timeNode);
		
		$this->response->appendChild($this->root);
	}
	
	/**
	 * This function writes a set of currency details into a DOM node.
	 * @param DOMNode $parent The node to write the details into.
	 * @param string $currency The three letter ISO currency code to check.
	 * @param float $amount The amount of the currency to include (if any).
	 */
	private function currency_details($parent, $currency, $amount = null) {
		$codeNode = $this->response->createElement("code", $currency);
		$parent->appendChild($codeNode);
		
		//Set the label name based on if an amount is given (i.e. it is a GET request)
		$label = isset($amount) ? "curr" : "name";
		$nameNode = $this->response->createElement($label, $this->currency_name($currency));
		$parent->appendChild($nameNode);
		
		$locNode = $this->response->createElement("loc", $this->currency_location($currency));
		$parent->appendChild($locNode);
		
		//Add in the amount node, if a value is provided
		if(isset($amount)) {
			$amountNode = $this->response->createElement("amnt", $amount);
			$parent->appendChild($amountNode);
		}
	}
	
	/**
	 * This function gives the response from our API call as set up so far.
	 * @return string This returns the raw XML for our response.
	 */
	private function response() {
		if($this->error->code()) {
			//Empty the current response of all child nodes
			while($this->root->hasChildNodes()) {
				$this->root->removeChild($this->root->firstChild);
			}
			
			$error = $this->response->createElement("error");
			$this->root->appendChild($error);

			$code = $this->response->createElement("code", $this->error->code());
			$error->appendChild($code);

			$message = $this->response->createElement("msg", $this->error->msg());
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
			
			//Load the DOMDocument to simplexml instead, for conversion to JSON
			$simple = simplexml_load_string($this->response());
			//Provide the root node as an array key to encode
			echo json_encode(array($simple->getName() => $simple), JSON_PRETTY_PRINT);
		}
		//Otherwise default to XML headers
		else {
			header("Content-type: text/xml");
			//If XML was defaulted to, not specified, set an error
			if(strtolower($format) !== "xml") {
				$this->error->set(1400);
			}
			
			//Send the response as XML
			echo $this->response();
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
		
		$this->write_root();
		
		//Check that all parameters given are correct
		if($this->check_parameters($requiredParameters, $parameters)) {
			$rate = $this->conversion_rate($parameters['from'], $parameters['to']);
			$rateNode =	$this->response->createElement("rate", $rate);
			$this->root->appendChild($rateNode);
			
			$fromNode = $this->response->createElement("from");
			$this->currency_details($fromNode, $parameters['from'], $parameters['amnt']);
			$this->root->appendChild($fromNode);
			
			$conversion = $this->convert($parameters['from'], $parameters['to'], $parameters['amnt']);
			$toNode = $this->response->createElement("to");
			$this->currency_details($toNode, $parameters['to'], $conversion);
			$this->root->appendChild($toNode);
		}
		
		$this->send_response(isset($parameters['format']) ? $parameters['format'] : null);
	}

	/**
	 * This function completes a post request (updating a currency's exchange rate).
	 * @param array $parameters The parameters sent by the request, expecting 'code'
	 * and 'rate'.
	 */
	public function post($parameters) {
		$requiredParameters = array(
			'code',
			'rate'
		);
		
		$this->write_root("post");
		
		if($this->check_parameters($requiredParameters, $parameters)) {
			$currNode = $this->response->createElement("curr");
			$this->currency_details($currNode, $parameters['code']);
			
			$previousNode = $this->response->createElement("previous");
			
			$previousRateNode = $this->response->createElement("rate", $this->check_rate($parameters['code']));
			$previousNode->appendChild($previousRateNode);
			$previousNode->appendChild(clone $currNode);
			$this->root->appendChild($previousNode);
			
			$this->edit_rate($parameters['code'], $parameters['rate']);
			
			$newNode = $this->response->createElement("new");
			
			$newRateNode = $this->response->createElement("rate", $this->check_rate($parameters['code']));
			$newNode->appendChild($newRateNode);
			$newNode->appendChild(clone $currNode);
			$this->root->appendChild($newNode);
		}
		
		$this->send_response();
	}

	/**
	 * This function completes a put request (adding a new currency in).
	 * @param array $parameters The parameters sent by the request, expecting 'code'.
	 */
	public function put($parameters) {
		$requiredParameters = array(
			'code'
		);

		$this->write_root("put");
		
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->insert_rate(strtoupper($parameters['code']));
			
			$rate = $this->check_rate($parameters['code']);
			$this->root->appendChild($this->response->createElement("rate", $rate));
			
			$currNode = $this->response->createElement("curr");
			$this->currency_details($currNode, $parameters['code']);
			$this->root->appendChild($currNode);
		}
		
		$this->send_response();
	}

	/**
	 * This function completes a delete request (removing a currency).
	 * @param array $parameters The parameters sent by the request, expecting 'code'.
	 */
	public function delete($parameters) {
		$requiredParameters = array(
			'code'
		);

		$this->write_root("delete");
		
		if($this->check_parameters($requiredParameters, $parameters)) {
			$this->remove_rate($parameters['code']);

			$this->root->appendChild($this->response->createElement("code", $parameters['code']));
		}
		
		$this->send_response();		
	}
}