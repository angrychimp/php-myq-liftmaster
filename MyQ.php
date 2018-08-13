<?php
/**
 * class MyQ
 *
 * Offers authentication to MyQ API, and access to garage door open/close/status functions
 *
 */
const MYQ_DOOR_ACTION_CLOSE  = 0;
const MYQ_DOOR_ACTION_OPEN   = 1;

const MYQ_DOOR_STATE_UKNOWN  =-1;
const MYQ_DOOR_STATE_OPEN    = 1;
const MYQ_DOOR_STATE_CLOSED  = 2;
const MYQ_DOOR_STATE_OPENING = 4;
const MYQ_DOOR_STATE_CLOSING = 5;


const MYQ_LAMP_ACTION_CLOSE  = 0;
const MYQ_LAMP_ACTION_OPEN   = 1;

const MYQ_LAMP_STATE_UKNOWN  =-1;
const MYQ_LAMP_STATE_OFF     = 0;
const MYQ_LAMP_STATE_ON      = 1;


class MyQException extends Exception {}

class MyQState {

    protected $_state = MYQ_DOOR_STATE_UNKNOWN;

    protected $_stateTime = 0;

    protected $_door_stateDescription = array (
        MYQ_DOOR_STATE_UKNOWN => 'unknown',
        MYQ_DOOR_STATE_OPEN => 'open',
        MYQ_DOOR_STATE_CLOSED => 'closed',
        MYQ_DOOR_STATE_OPENING => 'opening',
        MYQ_DOOR_STATE_CLOSING => 'closing',
    );

    protected $_lamp_stateDescription = array (
		MYQ_LAMP_STATE_UKNOWN => 'unknown',
		MYQ_LAMP_STATE_OFF => 'off',
		MYQ_LAMP_STATE_ON => 'on',
    );

    public function __construct ($state=false, $timestamp=false) {
        if ($state) {
            $this->_state = $state;
        }
        $this->_stateTime = time();
        if ($timestamp) {
            $this->_stateTime = (int)$timestamp / 1000;
        }
        return $this;
    }

    public function __get ($attr) {
        switch ($attr) {
            case 'desc':
                return $this->_door_stateDescription[$this->_state];
            case 'time':
            case 'updated':
            case 'date':
                return $this->_stateTime;
            case 'delta':
                return $this->_getTimeDelta('str');
            case 'seconds':
                return $this->_getTimeDelta();
        }
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    public function __toString () {
        return $this->_door_stateDescription[$this->_state];
    }

    private function _getTimeDelta($opt=null) {
        $currentTz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $delta = time() - $this->_stateTime;

        if ($opt != 'str') {
            return $delta;
        }

        // Convert delta in human-readable format
        // via https://stackoverflow.com/a/43956977/98030
        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;

        // Extract days
        $days = floor($delta / $secondsInADay);

        // Extract hours
        $hourSeconds = $delta % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour);

        // Extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // Extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        // Format and return
        $timeParts = [];
        $sections = [
            'day' => (int)$days,
            'hour' => (int)$hours,
            'minute' => (int)$minutes,
            'second' => (int)$seconds,
        ];

        foreach ($sections as $name => $value){
            if ($value > 0){
                $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
            }
        }

        return sizeof($timeParts) ? implode(', ', $timeParts) : '0 seconds';
    }

}

class MyQ {

	static $door_stateDescription = array (
        MYQ_DOOR_STATE_UKNOWN => 'unknown',
        MYQ_DOOR_STATE_OPEN => 'open',
        MYQ_DOOR_STATE_CLOSED => 'closed',
        MYQ_DOOR_STATE_OPENING => 'opening',
        MYQ_DOOR_STATE_CLOSING => 'closing',
    );

    static $lamp_stateDescription = array (
		MYQ_LAMP_STATE_UKNOWN => 'unknown',
		MYQ_LAMP_STATE_OFF => 'off',
		MYQ_LAMP_STATE_ON => 'on',
    );

    /** @var string|null $username contains the username used to authenticate with the MyQ API */
    protected $username = null;

    /** @var string|null $password contains the password used to authenticate with the MyQ API */
    protected $password = null;

    /** @var string|null $appId is the application ID used to register with the MyQ API */
    protected $appId = 'Vj8pQggXLhLy0WHahglCD4N1nAkkXQtGYpq2HrHD7H1nvmbT55KqtN6RSF4ILB/i';

    /** @var string|null $securityToken is the auth token returned after a successful login */
    protected $securityToken = null;

    /** @var string|null $userAgent is the User-Agent header value sent with each API request */
    protected $userAgent = 'Chamberlain/3.4.1';

    /** @var string|null $culture is the API culture code for the API */
    protected $culture = 'en';

    /** @var string|null $contentType is the content type used for all cURL requests */
    protected $contentType = 'application/json';

    /** @var array $headers contain HTTP headers for cURL requests */
    protected $_headers = array();

    protected $_deviceId = null;

    protected $_locationName = null;

    protected $_doorName = null;

    protected $_doorState = null;

	protected $_myDevices = null;

    protected $_baseUrl = 'https://myqexternal.myqdevice.com/api/v4';

    protected $_loginUri = '/User/Validate';

    protected $_getDeviceDetailUri = '/UserDeviceDetails/Get';

    protected $_putDeviceStateUri = '/DeviceAttribute/PutDeviceAttribute';

	protected $_getDeviceStateUri = '/deviceattribute/getdeviceattribute';

    /** @var resource|null $_conn is the web connection to the MyQ API */
    protected $_conn = null;

    /**
     * Initializes class. Optionally allows user to override variables
     *
     * @param array $params A associative array for overwriting class variables
     *
     * @return MyQ
     */
    public function __construct ($params = array()) {
        // Overwrite class variables
        foreach ($params as $k => $v) {
            $this->$k = $v;
        }

        // Initialize cURL request headers
        if (sizeof($this->_headers) == 0) {
            $this->_headers = array (
                'MyQApplicationId' => $this->appId,
                'Culture' => $this->culture,
                'Content-Type' => $this->contentType,
                'User-Agent' => $this->userAgent,
            );
        }

        // Initialize cURL connection
        $this->_init();

        return $this;
    }

    public function __get ($attr) {
        if ($attr == 'state') {
            return $this->_doorState;
        }
        if (isset($this->$attr)) {
            return $this->$attr;
        }
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    public function __set ($attr, $value) {

        switch ($attr) {
            case 'open':
                return ($value) ? $this->open() : $this->close();
                break;
            case 'close':
                return ($value) ? $this->close() : $this->open();
                break;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /**
     * Perform a login request
     *
     * @param string|null $username Username to use when logging in
     * @param string|null $password Password to use for logging in
     *
     * @return MyQ
     */
    public function login ($username = null, $password = null) {
        // Set username/password if not null
        if (!is_null($username)) {
            $this->username = $username;
        }
        if (!is_null($password)) {
            $this->password = $password;
        }

        // confirm that we have a valid username/password
        $error = array();
        if (is_null($this->username)) {
            $error[] = 'username';
        }
        if (is_null($this->password)) {
            $error[] = 'password';
        }
        if (sizeof($error) > 0) {
            throw new MyQException('Missing required auth credential: ' . implode(',', $error));
        }

        return $this->_login();
    }

    public function refresh () {
        $this->_getDetails();
        #$MyQ->refresh()
        return $this;
    }

    public function open($MyQDevice) {
        return $this->_requestState($MyQDevice,MYQ_DOOR_ACTION_OPEN);
    }

    public function close($MyQDevice) {
        return $this->_requestState($MyQDevice,MYQ_DOOR_ACTION_CLOSE);
    }

    public function on($MyQDevice) {
        return $this->_requestState($MyQDevice,MYQ_LAMP_ACTION_OPEN);
    }

    public function off($MyQDevice) {
        return $this->_requestState($MyQDevice,MYQ_LAMP_ACTION_CLOSE);
    }

    private function _requestState ($MyQDeviceName, $action) {
        // Fetch current device information
        $this->_getDetails();

		// get $MyQDeviceId  associated with MyQDeviceName argument.
			$MyQDeviceId =  false;
			foreach($this->_myDevices as $deviceType => $devices) {

				foreach($devices as $deviceID => $thisone) {
					if( ($thisone['desc'] == $MyQDeviceName) || ($thisone['MyQDeviceId'] == $MyQDeviceName) ) {
						$MyQDeviceId = $thisone['MyQDeviceId'];
						if($deviceType == 'LampModule')
							$AttributeName = 'desiredlightstate';
						else
							$AttributeName = 'desireddoorstate';
						break;
					}
				}
			}

		if(!$MyQDeviceId) {
			throw new MyQException("MyQDeviceName: {$MyQDeviceName} not found.");
		}

		//'MyQDeviceId' => $this->_deviceId,
		//AttributeName' => 'desireddoorstate',
        curl_setopt($this->_conn, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->_conn, CURLOPT_URL, $this->_baseUrl . $this->_putDeviceStateUri);
        $payload = array (
            'AttributeName' => $AttributeName,
            'AttributeValue' => $action,
            'MyQDeviceId' => $MyQDeviceId,
        );
        curl_setopt($this->_conn, CURLOPT_POSTFIELDS, json_encode($payload));
        $output = curl_exec($this->_conn);
        $err = curl_error($this->_conn);

		#$info = curl_getinfo($this->_conn);
        #print_r($info);
        #print "\n";

        if ($err) {
           throw new MyQException("cURL Error #:" . $err);
        }

        $data = json_decode($output);

        if ($data == false) {
            throw new MyQException("Error updating device state: $output");
        }

        if (strlen($data->ErrorMessage) > 0 || $data->ReturnCode != 0) {
            throw new MyQException("Error returned from API: " . var_export($data));
        }

        // Update was successful, fetch the new status and report
        return $this->refresh();

    }

    private function _init () {
        if (!isset($this->_conn)) {
            $this->_conn = curl_init();
            curl_setopt_array($this->_conn, array (
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_FAILONERROR => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_USERAGENT => $this->userAgent,
            ));
        }
        $this->_setHeaders();
    }

    private function _setHeaders () {
        $headers = array();
        foreach ($this->_headers as $k => $v) {
            $headers[] = "$k: $v";
        }
        curl_setopt($this->_conn, CURLOPT_HTTPHEADER, $headers);
    }

    private function _login () {
        $this->_init();

        curl_setopt($this->_conn, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->_conn, CURLOPT_URL, $this->_baseUrl . $this->_loginUri);

        $post = json_encode(array('username' => $this->username, 'password' => $this->password));
        curl_setopt($this->_conn, CURLOPT_POSTFIELDS, $post);
        $output = curl_exec($this->_conn);
        $data = json_decode($output);
        if ($data == false || !isset($data->SecurityToken)) {
            throw new MyQException("Error processing login request: $output");
        }
        $this->_headers['SecurityToken'] = $data->SecurityToken;
        return $this;
    }

    private function _getDetails ($getState = false, $forceUpdate = false) {
        $this->_init();

		$myDevices = array();

        // always fetch state info from API. Location data is not expected to have changed
        $cachedLocation = true;
        if ($getState === false && $forceUpdate !== true) {
            // get the location information
            // check to see if we have this information cached already
            if ( $forceUpdate === false && ! (is_null($this->_doorName) || is_null($this->_locationName) ) ) {
                return;
            }
            $cachedLocation = false;
        }

        curl_setopt($this->_conn, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->_conn, CURLOPT_URL, $this->_baseUrl . $this->_getDeviceDetailUri);

        $output = curl_exec($this->_conn);
        $data = json_decode($output);

        if ($data == false || !isset($data->Devices)) {
            throw new MyQException("Error fetching device details: $output");
        }

        // Find our door device ID
        // (we only look at the first listed device - later we can look for a device by name)
        foreach ($data->Devices as $device) {

			$myDevice = array();
			$myDevice['DeviceType'] = $device->MyQDeviceTypeName;

			#print_r($device);print "\n";
			//"AttributeDisplayName|MyQDeviceTypeAttributeId"

            if ( $cachedLocation === false && stripos($device->MyQDeviceTypeName, "xGateway") !== false ) {
                // Find location name
                foreach ($device->Attributes as $attr) {
                    if ($attr->AttributeDisplayName == 'desc') {
                        $this->_locationName = $attr->Value;
                    }
                }
                continue; // we don't want device info on the location
            }

            // we should be looking at just our WGDO unit
            $this->_deviceId = $device->MyQDeviceId;

			#print_r($device->Attributes);print "\n";

            foreach ($device->Attributes as $attr) {
                switch ($attr->AttributeDisplayName) {
                    case 'desc':
                        $myDevice[$attr->AttributeDisplayName] = $attr->Value;
                        break;
                    case 'doorstate':
						$myDevice['deviceState'] = array('state' => self::$door_stateDescription[$attr->Value], 'timestamp' => $attr->UpdatedTime);
						break;
					case 'lightstate':
						$myDevice['deviceState'] = array('state' => self::$lamp_stateDescription[$attr->Value], 'timestamp' => $attr->UpdatedTime);
						break;
					case 'learnmodestate':
						$myDevice['deviceState'] = array('state' => 'onLine', 'timestamp' => $attr->UpdatedTime);
						break;
                    default:
                        continue;
                }
            }
			$myDevice['MyQDeviceId'] = $device->MyQDeviceId;
			$myDevice['rawDevice'] = $device;
			$myDevices[$device->MyQDeviceTypeName][$device->MyQDeviceId] = $myDevice;

        }

		# order $myDevices.. gateway, garage, ligt
			$tmpDevices['Gateway'] = $myDevices['Gateway'];
			$tmpDevices['GarageDoorOpener'] = $myDevices['GarageDoorOpener'];
			$tmpDevices['LampModule'] = $myDevices['LampModule'];

		$this->_myDevices = $tmpDevices;
    }

}
