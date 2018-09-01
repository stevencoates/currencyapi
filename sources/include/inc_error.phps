<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
class apierror {
	private $code = false;
	
	/**
	 * This function checks whether or not an error has been set.
	 * @return boolean Whether or not an error has been set.
	 */
	public function __invoke() {
		return isset($this->code);
	}
	
	/**
	 * This function sets an error code if one has not yet been set.
	 * @param int $code The code to be assigned to the object.
	 */
	public function set($code) {
		if(!$this->code) {
			$this->code = $code;
		}
	}
	
	/**
	 * This function retrieves the error code used.
	 * @return int The error code assigned.
	 */
	public function code() {
		return $this->code;
	}
	
	/**
	 * This function retrieves the error message associated with the code.
	 * @return string The associated error message.
	 */
	public function msg() {
		switch($this->code) {
			//Error messages for GET requests
			case 1000 : $msg = "Required parameter is missing"; break;
			case 1100 : $msg = "Parameter not recognized"; break;
			case 1200 : $msg = "Currency type not recognized"; break;
			case 1300 : $msg = "Currency amount must be a decimal number"; break;
			case 1400 : $msg = "Format must be xml or json"; break;
			case 1500 : $msg = "Error in service"; break;
			
			//Error messages for POST, PUT and DELETE requests
			case 2000 : $msg = "Method not recognized or is missing"; break;
			case 2100 : $msg = "Rate in wrong format or is missing"; break;
			case 2200 : $msg = "Currency code in wrong format or is missing"; break;
			case 2300 : $msg = "Country name in wrong format or is missing"; break;
			case 2400 : $msg = "Currency code not found for update"; break;
			case 2500 : $msg = "Error in service"; break;
			
			default : $msg = "Unknown error"; break;
		}
		
		return $msg;
	}
}
