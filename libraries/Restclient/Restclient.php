<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Librairie REST Full Client
 * @author Yoann VANITOU
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @link https://github.com/maltyxx/restclient
 */
class Restclient
{
    /**
     * CI Instance
     * @var object
     */
    private $CI;

    /**
     * Destination URL
     * @var string
     */
    private $url;

    /**
     * CURL ressource
     * @var object
     */
    private $curl;

    /**
     * Informations about request
     * @var array
     */
    private $info = array();

    /**
     * Return code
     * @var integer
     */
    private $errno;

    /**
     * Errors
     * @var string
     */
    private $error;

    /**
     * Datas to send
     * @var array
     */
    private $output_value = array();

    /**
     * Headers to send
     * @var array
     */
    private $output_header = array();

    /**
     * Return value
     * @var string
     */
    private $result_value = null;

    /**
     * Return headers
     * @var string
     */
    private $result_header = null;

    /**
     * HTTP Method
     * @var string
     */
    private $http_method = 'post';

    /**
     * Return code
     * @var integer|null
     */
    public $http_code = null;

    /**
     * Content type of return
     * @var string|null
     */
    private $content_type = null;

    /**
     * CURL Options
     * @var array
     */
    private $curl_options = array();

    /**
     * Configuration
     * @var array
     */
    private $config = array(
        'port'          => null,
        'auth'          => false,
        'auth_type'     => 'basic',
        'auth_username' => '',
        'auth_password' => '',
        'header'        => false,
        'cookie'        => false,
        'timeout'       => 30,
        'result_assoc'  => true,
        'cache'         => false,
        'tts'           => 3600,
        'methods'       => array('get', 'put', 'post', 'patch', 'delete')
    );

    /**
     * Class Constructor
     * @method __construct
     * @param  array       $config
     */
    public function __construct(array $config = array())
    {
        // Load the CI Instance
        $this->CI = &get_instance();

        // Initialize configuration
        $this->initialize($config);

        // Initialize default CURL options
        $this->curl_options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_FAILONERROR    => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST  => strtoupper($this->http_method),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_HEADERFUNCTION => array($this, '_headers'),
            CURLOPT_COOKIESESSION  => true,
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_POST           => false
        );
    }

    /**
     * Initialize function
     * @method initialize
     * @param  array      $config Config to assert
     * @return void
     */
    public function initialize(array $config = array())
    {
        // Verification about config file
        if (empty($config)) {
            show_error('Unable to find Restclient config file', null, 'Restclient Error');
        }

        // Append config to library
        $this->config = array_merge($this->config, (isset($config['restclient'])) ? $config['restclient'] : $config);
    }

    /**
     * Magic method who call the right one
     * @method __call
     * @author Romain GALLIEN <r.gallien@santiane.fr>
     * @return string
     */
    public function __call($http_method = 'post', array $arguments = array())
    {
        // Clear all previous datas to avoid errors or mismatch
        $this->clear();

        // Security to check if method is valid
        if (!in_array(strtolower($http_method), $this->config['methods'])) {
            show_error('The '.$http_method.' method is not allowed', null, 'Restclient Error');
        }

        // Set HTTP_METHOD
        $this->http_method = $http_method;
        $this->curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($this->http_method);

        // Security to check if we got argments
        if (empty($arguments[0])) {
            show_error('URL destination is missing', null, 'Restclient Error');
        }

        // Define URL destination
        $this->url = $arguments[0];

        // Define DATAS sent
        $this->output_value = $arguments[1];

        // If we got additionnal options we initialize the lib with
        if (!empty($arguments[2]) && is_array($arguments[2])) {
            $this->initialize($arguments[2]);
        }

        // Execute request and run the output
        return $this->_query();
    }

    /**
     * Prepare the request
     * @return mixed         string / bool
     */
    private function _query()
    {
        // If we got GET request, we have to construct the right URL
        if ($this->http_method === 'get') {
            $this->url = $this->url.'?'.http_build_query($this->output_value);
        }

        // Build and send the CURL request
        $this->result_value = $this->_send_request();

        // If response contain some JSON we decode it
        if (strstr($this->content_type, 'json')) {
            $this->result_value = json_decode($this->result_value, $this->config['result_assoc']);
        }

        // Return
        return $this->result_value;
    }

    /**
     * Send the request
     * @method _send_request
     * @return array
     */
    private function _send_request()
    {
        // Creation of CURL ressource
        $curl = curl_init();

        // Set the destination URL
        $this->curl_options[CURLOPT_URL] = $this->url;

        // If we got some a PORT in config file
        if (!empty($this->config['port'])) {
            $this->curl_options[CURLOPT_PORT] = $this->config['port'];
        }

        // Is auth is necessary
        if ($this->config['auth']) {
            switch ($this->config['auth_type']) {
                case 'basic':
                    $this->curl_options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                    $this->curl_options[CURLOPT_USERPWD] = "{$this->config['auth_username']}:{$this->config['auth_password']}";
            }
        }

        // If we got some HEADERS to send
        if (!empty($this->config['header']) && is_array($this->config['header'])) {
            foreach ($this->config['header'] as $key => $value) {
                $this->output_header[] = $key.': '.$value;
            }
        }

        // If we got post request we set the CURL option
        if ($this->http_method === 'post') {
            $this->curl_options[CURLOPT_POST] = true;
        }

        // Store all datas in CURL option
        if (!empty($this->output_value)) {
            $this->curl_options[CURLOPT_POSTFIELDS] = (is_array($this->output_value)) ? http_build_query($this->output_value) : $this->output_value;
        }

        // Defines HEADERS of request
        $this->curl_options[CURLOPT_HTTPHEADER] = $this->output_header;

        // If we got some COOKIES to send
        if (!empty($this->config['cookie']) && is_array($this->config['cookie'])) {
            $cookies = array();

            foreach ($this->config['cookie'] as $key => $value) {
                $cookies[] = $key.'='.$value;
            }

            $this->curl_options[CURLOPT_COOKIE] = implode(';', $cookies);
        }

        // CURL set options as batch
        curl_setopt_array($curl, $this->curl_options);

        // Request execution
        $response = curl_exec($curl);

        // Get the HTTP response code
        $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Get the HTTP response content type
        $this->content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        // Get additionnal informations about request
        $this->info = curl_getinfo($curl);

        // Errors management
        if ($response === false) {
            $this->errno = curl_errno($curl);
            $this->error = curl_error($curl);
            return false;
        }

        // Close the CURL session
        curl_close($curl);

        // Return
        return $response;
    }

    /**
     * Fetch the cookies
     * @method get_cookie
     * @return array
     */
    public function get_cookie()
    {
        $cookies = array();

        // Search cookies in headers
        preg_match_all('/Set-Cookie: (.*?);/is', $this->result_header, $data, PREG_PATTERN_ORDER);

        // If we got some
        if (isset($data[1])) {
            foreach ($data[1] as $cookie) {
                if (!empty($cookie)) {
                    list($key, $value) = explode('=', $cookie);
                    $cookies[$key] = $value;
                }
            }
        }

        // Return
        return $cookies;
    }

    /**
     * Last informations about request
     * @method info
     * @return array
     */
    public function info()
    {
        return $this->info;
    }

    /**
     * Last HTTP return code
     * @method http_code
     * @return mixed    interger / null
     */
    public function http_code()
    {
        return $this->http_code;
    }

    /**
     * CURL callback about response Headers
     * @method _headers
     * @param  object   $curl CURL ressource
     * @param  array    $data
     * @return integer  Headers size
     */
    public function _headers($curl, $data)
    {
        if (is_resource($curl) && !empty($data)) {
            $this->result_header .= $data;
        }

        return strlen($data);
    }

    /**
     * Pretty debug function
     * @method debug
     * @param  boolean $return True will store the debug, False will print it
     * @return string
     */
    public function debug($return = true)
    {
        // Load text helper to format code and add css to ouput
        $output = '<style type="text/css">body{background-color:#fff;margin:40px;font:13px/20px normal Helvetica,Arial,sans-serif;color:#4F5155}a{color:#039;background-color:transparent;font-weight:400}h1{color:#444;background-color:transparent;border-bottom:1px solid #D0D0D0;font-size:19px;font-weight:400;margin:0 0 14px;padding:14px 15px 10px}code{font-family:Consolas,Monaco,Courier New,Courier,monospace;font-size:12px;background-color:#f9f9f9;border:1px solid #D0D0D0;color:#002166;display:block;margin:14px 0;padding:12px 10px}#body{margin:0 15px}p.footer{text-align:right;font-size:11px;border-top:1px solid #D0D0D0;line-height:32px;padding:0 10px;margin:20px 0 0}#container{margin:10px;border:1px solid #D0D0D0;box-shadow:0 0 8px #D0D0D0}</style>';
        $output .= '<div id="container">'.PHP_EOL;
        $output .= '<h1>Restclient Debug</h1>'.PHP_EOL;
        $output .= '<div id="body">'.PHP_EOL;
        $output .= '<h2>Query</h2>'.PHP_EOL;
        $output .= '<h3>Headers</h3>'.PHP_EOL;
        $output .= (!empty($this->output_header)) ? '<code>'.print_r($this->output_header, true).'</code>' : 'No headers sent';
        $output .= '<h3>Datas</h3>'.PHP_EOL;
        $output .= (!empty($this->output_value)) ? '<code><pre>'.print_r($this->output_value, true).'</pre></code>' : 'No datas sent';
        $output .= '<h3>Call</h3>'.PHP_EOL;
        $output .= (!empty($this->info)) ? '<code><pre>'.print_r($this->info, true).'</pre></code>' : 'Empty call';
        $output .= '<h2>Response (<font color="'.(($this->http_code > 308) ? 'red' : 'green').'"><b>'.$this->http_code.'</b></font>)</h2>'.PHP_EOL;
        $output .= '<h3>Headers</h3>'.PHP_EOL;
        $output .= (!empty($this->result_header)) ? '<code><pre>'.print_r($this->result_header, true).'</pre></code>' : 'No response headers';
        $output .= '<h3>Return</h3>'.PHP_EOL;
        $output .= (!empty($this->result_value)) ? '<code><pre>'.print_r($this->result_value, true).'</pre></code>' : 'No response datas';
        $output .= '</div>'.PHP_EOL;
        $output .= '</div>'.PHP_EOL;

        // If we got some errors
        if (!empty($this->error)) {
            $output .= '<h3>Errors</h3>'.PHP_EOL;
            $output .= '<code>'.PHP_EOL;
            $output .= '<strong>Code:</strong> '.$this->errno.'<br/>'.PHP_EOL;
            $output .= '<strong>Message:</strong> '.$this->error.'<br/>'.PHP_EOL;
            $output .= '</code>'.PHP_EOL;
        }

        // Kind of output
        if (!$return) {
            return $output;
        }

        echo $output;
    }

    /**
     * Clear all global vars
     * @method clear
     * @return void
     */
    private function clear()
    {
        $this->url           = null;
        $this->curl          = null;
        $this->info          = array();
        $this->errno         = null;
        $this->error         = null;
        $this->output_value  = array();
        $this->output_header = array();
        $this->result_value  = null;
        $this->result_header = null;
        $this->http_code     = null;
        $this->content_type  = null;
    }
}

/* End of file Restclient.php */
/* Location: ./libraries/Restclient/Restclient.php */
