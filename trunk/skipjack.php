<?php
/**
 * 2008-24-01 11:28:17est
 * 
 * Skipjack
 * 
 * <usage>
 * 	$sj = new Skipjack();
 * 	$sj->setDeveloper(true); // use the development server address
 * 
 * 	$sj->addFields(array(
 * 		'OrderNumber' => '5',
 * 		'ItemNumber' => 'i5',
 * 		'ItemDescription' => 'Test Item',
 * 		'ItemCost' => '5.50',
 * 		'Quantity' => '1',
 * 		'Taxable' => '0',
 * 		'AccountNumber' => '4445999922225',
 * 		'Month' => '12',
 * 		'Year' => '2010',
 * 		'TransactionAmount' => '200.00'
 * 	));
 *  
 * 	if($sj->process() && $sj->isApproved()) {
 * 		echo "Transaction approved!";
 * 	} else {
 * 		echo "Transaction declined!\n";
 * 		echo $sj->getErrors();
 * 	}
 * </usage>
 * 
 * Used to connect to the Skipjack API and submit credit card orders that need
 * to be authorized.
 * 
 * @author	Steven Vondruska, Bret Kuhns
 * @link	http://imgserver.skipjack.com/imgServer/5293710/skipjack_integration_guide.pdf
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @access	public
 */
class Skipjack {
	var $fields = array();		// Fields to be posted to the API
	var $response = array();	// Reponse array returned after query
	var $errors = array();		// Any errors that might have populated
	
	// REPLACE THESE VALUES WITH YOUR OWN GIVEN BY SKIPJACK
	var $serialNumber    = '123456789012';
	var $devSerialNumber = '123456789012';
	
	var $DEVELOPER = false;
	
	// Required fields from pages 49-50 in API manual
	var $requiredFields = array(
			'SerialNumber', 'DeveloperSerialNumber', 'OrderNumber', 'ItemNumber',
			'ItemDescription', 'ItemCost', 'Quantity', 'Taxable', 'SJName',
			'Email', 'StreetAddress', 'City', 'State', 'ZipCode', 
			'ShipToPhone', 'OrderString', 'AccountNumber', 'Month', 'Year',
			'TransactionAmount'
		);
	// recommended dummy values from page 50 in API manual
	var $dummyVals = array(
			'SJName' => 'NA',
			'Email'  => 'None',
			'StreetAddress' => 'None',
			'City' => 'None',
			'State' => 'XX',
			'ZipCode' => '00000',
			'ShipToPhone' => '0000000000',
			'OrderString' => '1~None~0.00~0~N~||'
		);
	var $authCodes = array(
			0 => 'Source Unknown',
			1 => 'STIP, Timeout Response',
			2 => 'LCS Response',
			3 => 'STIP, Issuer in Suppression',
			4 => 'STIP Reponse, Issuer Unavailable',
			5 => 'Issuer Aproval',
			7 => 'Aquirer Approval, Base 1 Down',
			8 => 'Aquirer Approval of Referral'
		);
	var $errorCodes = array(
			"1"	  => "Success (Valid Data)",
			"-35" => "Invalid credit card number",
			"-37" => "Error failed communication",
			"-39" => "Error length serial number",
			"-51" => "Invalid Billing Zip Code",
			"-52" => "Invalid Shipto zip code",
			"-53" => "Invalid expiration date",
			"-54" => "Error length account number date",
			"-55" => "Invalid Billing Street Address",
			"-56" => "Invalid Shipto Street Address",
			"-57" => "Error length transaction amount",
			"-58" => "Invalid Name",
			"-59" => "Error length location",
			"-60" => "Invalid Billing State",
			"-61" => "Invalid Shipto State",
			"-62" => "Error length order string",
			"-64" => "Invalid Phone Number",
			"-65" => "Empty name",
			"-66" => "Empty email",
			"-67" => "Empty street address",
			"-68" => "Empty city",
			"-69" => "Empty state",
			"-79" => "Error length customer name",
			"-80" => "Error length shipto customer name",
			"-81" => "Error length customer location",
			"-82" => "Error length customer state",
			"-83" => "Invalid Phone Number",
			"-84" => "Pos error duplicate ordernumber",
			"-91" => "Pos_error_CVV2",
			"-92" => "Pos_error_Error_Approval_Code",
			"-93" => "Pos_error_Blind_Credits_Not_Allowed",
			"-94" => "Pos_error_Blind_Credits_Failed",
			"-95" => "Pos_error_Voice_Authorizations_Not_Allowed"
		);
	
	
	/**
	 * Constructor
	 *
	 * @param	String	$serial
	 * @param	String	$developer
	 * @return	Skipjack
	 */
	function Skipjack($serial = null, $developer = null) {
		if($serial != null) {
			$this->addField('SerialNumber', $serial);
		} else {
			$this->addField('SerialNumber', $this->serialNumber);
		}
		
		if($developer != null) {
			$this->addField('DeveloperSerialNumber', $developer);
		} else {
			$this->addfield('DeveloperSerialNumber', $this->devSerialNumber);
		}
	}
	
	
	/**
	 * Add field to request, required field are:
	 *   SJName (Billing Name), Email, StreetAddress, City, State, ZipCode, 
	 *   ShipToPhone, AccountNumber (CC#), Month, Year, TransactionAmount, 
	 *   OrderNumber, OrderString
	 *
	 * @param	String	$key
	 * @param	String	$value
	 * @return	void
	 */
	function addField($key, $value) {
		if($value !== "" && $value !== "Submit") {
			$this->fields[$key] = $value;
		}
	}
	
	
	/**
	 * Allow array to be sent to object at once
	 *
	 * @param	Array(String => String)	$array
	 * @return	void
	 */
	function addFields($array) {
		foreach($array as $key => $value) {
			$this->addField($key, $value);
		}
	}
	
	
	/**
	 * Determines if all required fields are in the fields array before
	 * attempting to post to Skipjack. If a dummy value is found for a field,
	 * then it used as a default and no error is thrown for that field. Returns
	 * false if any errors are encountered.
	 *
	 * @access	private
	 * @return	boolean
	 */
	function __canPost() {
		$return = true;
		
		foreach($this->requiredFields as $field) {
			if(!isset($this->fields[$field])) {
				if(array_key_exists($field, $this->dummyVals)) {
					$this->addField($field, $this->dummyVals[$field]);
				} else {
					$return = false;
					$this->errors[] = 'Required field not found: '.$field;
				}
			}
		}
		
		return $return;
	}
	
	
	/**
	 * Process the order using information in Skipjack::fields. Returns false
	 * when an error is encountered.
	 *
	 * @return	boolean
	 */
	function process() {
		$post = '';
		$return = true;
		
		if($this->__canPost()) {
			foreach($this->fields as $key=>$value) {
				$post .= "$key=" . urlencode($value) . "&";
			}
			
			if($this->DEVELOPER) {
				$url = "https://developer.skipjackic.com/scripts/evolvcc.dll?AuthorizeAPI";
			} else {
				$url = "https://www.skipjackic.com/scripts/evolvcc.dll?AuthorizeAPI";
			}
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim($post, "&"));
			$response = curl_exec($ch);
			if(curl_errno($ch) > 0) {
				$this->errors[] = "Encountered Curl error number: ".curl_errno($ch);
				$return = false;
			}
			curl_close($ch);
			
			$response = explode("\r", $response);
			$header = explode('","', $response[0]);
			$data = explode('","', $response[1]);
			
			foreach($header as $i => $array) {
				$this->response[str_replace(array("\r",'"'), "", $array)] = str_replace(array("\r",'"'), "", $data[$i]);
			}
		} else {
			$return = false;
		}
		
		return $return;
	}
	
	
	/**
	 * Check the response for errors, returns false if errors found.
	 *
	 * @return	boolean
	 */
	function checkForErrors() {
		$return = true;
		
		if(!$this->isApproved()) {
			if($this->isCardDeclined()) {
				$this->errors[] = $this->response['szAuthorizationDeclinedMessage'];
				$return = false;
			} else {
				// this will run if there is an error with the information that you have provided to skipjack
				$this->errors[] = $errorCodes[$this->response['szReturnCode']];
				$return = false;
			}
		}
		
		return $return;
	}
	
	
	/**
	 * @return	boolean
	 */
	function isApproved() {
		return ($this->response['szIsApproved'] == 1);
	}
	
	
	/**
	 * @return	boolean
	 */
	function isCardDeclined() {
		return !empty($this->response['szAuthorizationDeclinedMessage']);
	}
	
	
	/**
	 * Returns the response auth code and associated string
	 *
	 * @return	Array(int => String)
	 */
	function getAuthCode() {
		return array((int)$this->response['AUTHCODE'] => $this->authCodes[(int)$this->response['AUTHCODE']]);
	}
	
	
	/**
	 * Set the developer variable. If set to true, development server is used.
	 *
	 * @param	boolean	$val
	 * @return	void
	 */
	function setDeveloper($value) {
		$this->DEVELOPER = (bool)$value;
	}
	
	
	/**
	 * @return	boolean
	 */
	function errorsExist() {
		return (count($this->errors) > 0);
	}
	
	
	/**
	 * @return	Array(String)
	 */
	function getErrors() {
		return $this->errors;
	}
	
	
	/**
	 * Reset the object's properties so multiple instantiations aren't required
	 * for batch processing.
	 *
	 * @return	void
	 */
	function reset() {
		$this->fields = array();
		$this->response = array();
		$this->errors = array();
	}
	
	
}
?>