<?php

namespace Elegant\Http;

use Elegant\Foundation\Http\File\UploadedFile;
use Elegant\Support\Arr;
use Elegant\Support\Str;
use SplFileInfo;
use stdClass;

/**
 * Input Class
 *
 * Pre-processes global input data for security
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Input
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/userguide3/libraries/input.html
 */
class Request extends \CI_Input {

    /**
     * IP address of the current user
     *
     * @var	string
     */
    protected $ip_address = false;

    /**
     * Allow GET array flag
     *
     * If set to FALSE, then $_GET will be set to an empty array.
     *
     * @var	bool
     */
    protected $_allow_get_array = true;

    /**
     * Standardize new lines flag
     *
     * If set to TRUE, then newlines are standardized.
     *
     * @var	bool
     */
    protected $_standardize_newlines;

    /**
     * Enable XSS flag
     *
     * Determines whether the XSS filter is always active when
     * GET, POST or COOKIE data is encountered.
     * Set automatically based on config setting.
     *
     * @var	bool
     */
    protected $_enable_xss = false;

    /**
     * List of all HTTP request headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Raw input stream data
     *
     * Holds a cache of php://input contents
     *
     * @var	string
     */
    protected $_raw_input_stream;

    /**
     * Parsed input stream data
     *
     * Parsed from php://input at runtime
     *
     * @see	CI_Input::input_stream()
     * @var	array
     */
    protected $_input_stream;

    protected $security;
    protected $uni;

    /**
     * All of the converted files for the request.
     *
     * @var array
     */
    protected array $convertedFiles;

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     * Determines whether to globally enable the XSS processing
     * and whether to allow the $_GET array.
     *
     * @return	void
     */
    public function __construct()
    {
        $this->_allow_get_array		= (config_item('allow_get_array') !== false);
        $this->_enable_xss		= (config_item('global_xss_filtering') === true);
        $this->_standardize_newlines	= (bool) config_item('standardize_newlines');

        $this->security =& load_class('Security', 'core');

        // Do we need the UTF-8 class?
        if (UTF8_ENABLED === true)
        {
            $this->uni =& load_class('Utf8', 'core');
        }

        // Sanitize global arrays
        $this->_sanitize_globals();

        // CSRF Protection check
        // app/Http/Middleware/VerifyCsrfToken.php

        log_message('info', 'Input Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Fetch from array
     *
     * Internal method used to retrieve values from global arrays.
     *
     * @param	array	&$array		$_GET, $_POST, $_COOKIE, $_SERVER, etc.
     * @param	mixed	$index		Index for item to be fetched from $array
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    protected function _fetch_from_array(&$array, $index = null, $xss_clean = null)
    {
        is_bool($xss_clean) OR $xss_clean = $this->_enable_xss;

        // If $index is NULL, it means that the whole $array is requested
        isset($index) OR $index = array_keys($array);

        // allow fetching multiple keys at once
        if (is_array($index))
        {
            $output = new stdClass();
            foreach ($index as $key)
            {
                $output->$key = $this->_fetch_from_array($array, $key, $xss_clean);
            }

            return $output;
        }

        if (isset($array[$index]))
        {
            $value = $array[$index];
        }
        elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) // Does the index contain array notation
        {
            $value = $array;
            for ($i = 0; $i < $count; $i++)
            {
                $key = trim($matches[0][$i], '[]');
                if ($key === '') // Empty notation will return the value as array
                {
                    break;
                }

                if (isset($value[$key]))
                {
                    $value = $value[$key];
                }
                else
                {
                    return null;
                }
            }
        }
        else
        {
            return null;
        }

        return ($xss_clean === true)
            ? $this->security->xss_clean($value)
            : $value;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the GET array
     *
     * @param	mixed	$index		Index for item to be fetched from $_GET
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function get($index = null, $xss_clean = null)
    {
        return $this->_fetch_from_array($_GET, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the POST array
     *
     * @param	mixed	$index		Index for item to be fetched from $_POST
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function post($index = null, $xss_clean = null)
    {
        return $this->_fetch_from_array($_POST, $index, $xss_clean);
    }

    public function input($index = null, $xss_clean = null)
    {
        if ( ! is_array($this->_input_stream)) {
            parse_str($this->raw_input_stream, $this->_input_stream);
            is_array($this->_input_stream) OR $this->_input_stream = [];
        }

        $inputs = array_merge($_POST, $this->_input_stream);

        return $this->_fetch_from_array($inputs, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from POST data with fallback to GET
     *
     * @param	string	$index		Index for item to be fetched from $_POST or $_GET
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function post_get($index, $xss_clean = null)
    {
        return isset($_POST[$index])
            ? $this->post($index, $xss_clean)
            : $this->get($index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from GET data with fallback to POST
     *
     * @param	string	$index		Index for item to be fetched from $_GET or $_POST
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function get_post($index, $xss_clean = null)
    {
        return isset($_GET[$index])
            ? $this->get($index, $xss_clean)
            : $this->post($index, $xss_clean);
    }

    /**
     * Add extra key at $_GET variable.
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function add_get($key, $value)
    {
        $_GET[$key] = $value;
    }

    /**
     * Add extra key at $_POST variable.
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function add_post($key, $value)
    {
        $_POST[$key] = $value;
    }

    public function merge_get(array $input)
    {
        foreach ($input as $key => $value) {
            $this->add_get($key, $value);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the COOKIE array
     *
     * @param	mixed	$index		Index for item to be fetched from $_COOKIE
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function cookie($index = null, $xss_clean = null)
    {
        return $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the SERVER array
     *
     * @param	mixed	$index		Index for item to be fetched from $_SERVER
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function server($index, $xss_clean = null)
    {
        return $this->_fetch_from_array($_SERVER, $index, $xss_clean);
    }

    // ------------------------------------------------------------------------

    /**
     * Fetch an item from the php://input stream
     *
     * Useful when you need to access PUT, DELETE or PATCH request data.
     *
     * @param	string	$index		Index for item to be fetched
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function input_stream($index = null, $xss_clean = null)
    {
        // Prior to PHP 5.6, the input stream can only be read once,
        // so we'll need to check if we have already done that first.
        if ( ! is_array($this->_input_stream))
        {
            // $this->raw_input_stream will trigger __get().
            parse_str($this->raw_input_stream, $this->_input_stream);
            is_array($this->_input_stream) OR $this->_input_stream = [];
        }

        return $this->_fetch_from_array($this->_input_stream, $index, $xss_clean);
    }

    // ------------------------------------------------------------------------

    /**
     * Set cookie
     *
     * Accepts an arbitrary number of parameters (up to 7) or an associative
     * array in the first parameter containing all the values.
     *
     * @param	string|mixed[]	$name		Cookie name or an array containing parameters
     * @param	string		$value		Cookie value
     * @param	int		$expire		Cookie expiration time in seconds
     * @param	string		$domain		Cookie domain (e.g.: '.yourdomain.com')
     * @param	string		$path		Cookie path (default: '/')
     * @param	string		$prefix		Cookie name prefix
     * @param	bool		$secure		Whether to only transfer cookies via SSL
     * @param	bool		$httponly	Whether to only makes the cookie accessible via HTTP (no javascript)
     * @param	string		$samesite	SameSite attribute
     * @return	void
     */
    public function set_cookie($name, $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = null, $httponly = null, $samesite = null)
    {
        if (is_array($name))
        {
            // always leave 'name' in last place, as the loop will break otherwise, due to $$item
            foreach (['value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name', 'samesite'] as $item)
            {
                if (isset($name[$item]))
                {
                    $$item = $name[$item];
                }
            }
        }

        if ($prefix === '' && config_item('cookie_prefix') !== '')
        {
            $prefix = config_item('cookie_prefix');
        }

        if ($domain == '' && config_item('cookie_domain') != '')
        {
            $domain = config_item('cookie_domain');
        }

        if ($path === '/' && config_item('cookie_path') !== '/')
        {
            $path = config_item('cookie_path');
        }

        $secure = ($secure === null && config_item('cookie_secure') !== null)
            ? (bool) config_item('cookie_secure')
            : (bool) $secure;

        $httponly = ($httponly === null && config_item('cookie_httponly') !== null)
            ? (bool) config_item('cookie_httponly')
            : (bool) $httponly;

        if ( ! is_numeric($expire))
        {
            $expire = time() - 86500;
        }
        else
        {
            $expire = ($expire > 0) ? time() + $expire : 0;
        }

        isset($samesite) OR $samesite = config_item('cookie_samesite');
        if (isset($samesite))
        {
            $samesite = ucfirst(strtolower($samesite));
            in_array($samesite, ['Lax', 'Strict', 'None'], true) OR $samesite = 'Lax';
        }
        else
        {
            $samesite = 'Lax';
        }

        if ($samesite === 'None' && ! $secure)
        {
            log_message('error', $name.' cookie sent with SameSite=None, but without Secure attribute.');
        }

        if ( ! is_php('7.3'))
        {
            $maxage = $expire - time();
            if ($maxage < 1)
            {
                $maxage = 0;
            }

            $cookie_header = 'Set-Cookie: '.$prefix.$name.'='.rawurlencode($value);
            $cookie_header .= ($expire === 0 ? '' : '; Expires='.gmdate('D, d-M-Y H:i:s T', $expire)).'; Max-Age='.$maxage;
            $cookie_header .= '; Path='.$path.($domain !== '' ? '; Domain='.$domain : '');
            $cookie_header .= ($secure ? '; Secure' : '').($httponly ? '; HttpOnly' : '').'; SameSite='.$samesite;
            header($cookie_header);
            return;
        }

        $setcookie_options = [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ];
        setcookie($prefix.$name, $value, $setcookie_options);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the IP Address
     *
     * Determines and validates the visitor's IP address.
     *
     * @return	string	IP address
     */
    public function ip_address()
    {
        if ($this->ip_address !== false)
        {
            return $this->ip_address;
        }

        $proxy_ips = config_item('proxy_ips');
        if ( ! empty($proxy_ips) && ! is_array($proxy_ips))
        {
            $proxy_ips = explode(',', str_replace(' ', '', $proxy_ips));
        }

        $this->ip_address = $this->server('REMOTE_ADDR');

        if ($proxy_ips)
        {
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'] as $header)
            {
                if (($spoof = $this->server($header)) !== null)
                {
                    // Some proxies typically list the whole chain of IP
                    // addresses through which the client has reached us.
                    // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
                    sscanf($spoof, '%[^,]', $spoof);

                    if ( ! $this->valid_ip($spoof))
                    {
                        $spoof = null;
                    }
                    else
                    {
                        break;
                    }
                }
            }

            if ($spoof)
            {
                for ($i = 0, $c = count($proxy_ips); $i < $c; $i++)
                {
                    // Check if we have an IP address or a subnet
                    if (strpos($proxy_ips[$i], '/') === false)
                    {
                        // An IP address (and not a subnet) is specified.
                        // We can compare right away.
                        if ($proxy_ips[$i] === $this->ip_address)
                        {
                            $this->ip_address = $spoof;
                            break;
                        }

                        continue;
                    }

                    // We have a subnet ... now the heavy lifting begins
                    isset($separator) OR $separator = $this->valid_ip($this->ip_address, 'ipv6') ? ':' : '.';

                    // If the proxy entry doesn't match the IP protocol - skip it
                    if (strpos($proxy_ips[$i], $separator) === false)
                    {
                        continue;
                    }

                    // Convert the REMOTE_ADDR IP address to binary, if needed
                    if ( ! isset($ip, $sprintf))
                    {
                        if ($separator === ':')
                        {
                            // Make sure we're have the "full" IPv6 format
                            $ip = explode(':',
                                str_replace('::',
                                    str_repeat(':', 9 - substr_count($this->ip_address, ':')),
                                    $this->ip_address
                                )
                            );

                            for ($j = 0; $j < 8; $j++)
                            {
                                $ip[$j] = intval($ip[$j], 16);
                            }

                            $sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
                        }
                        else
                        {
                            $ip = explode('.', $this->ip_address);
                            $sprintf = '%08b%08b%08b%08b';
                        }

                        $ip = vsprintf($sprintf, $ip);
                    }

                    // Split the netmask length off the network address
                    sscanf($proxy_ips[$i], '%[^/]/%d', $netaddr, $masklen);

                    // Again, an IPv6 address is most likely in a compressed form
                    if ($separator === ':')
                    {
                        $netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
                        for ($j = 0; $j < 8; $j++)
                        {
                            $netaddr[$j] = intval($netaddr[$j], 16);
                        }
                    }
                    else
                    {
                        $netaddr = explode('.', $netaddr);
                    }

                    // Convert to binary and finally compare
                    if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0)
                    {
                        $this->ip_address = $spoof;
                        break;
                    }
                }
            }
        }

        if ( ! $this->valid_ip($this->ip_address))
        {
            return $this->ip_address = '0.0.0.0';
        }

        return $this->ip_address;
    }

    /**
     * Determine if the current request URI matches a pattern.
     *
     * @param  mixed  ...$patterns
     * @return bool
     */
    public function is(...$patterns)
    {
        $path = $this->decodedPath();

        return collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $path));
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @param	string	$ip	IP address
     * @param	string	$which	IP protocol: 'ipv4' or 'ipv6'
     * @return	bool
     */
    public function valid_ip($ip, $which = '')
    {
        switch (strtolower($which))
        {
            case 'ipv4':
                $which = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $which = FILTER_FLAG_IPV6;
                break;
            default:
                $which = 0;
                break;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch User Agent string
     *
     * @return	string|null	User Agent string or NULL if it doesn't exist
     */
    public function user_agent($xss_clean = null)
    {
        return $this->_fetch_from_array($_SERVER, 'HTTP_USER_AGENT', $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Globals
     *
     * Internal method serving for the following purposes:
     *
     *	- Unsets $_GET data, if query strings are not enabled
     *	- Cleans POST, COOKIE and SERVER data
     * 	- Standardizes newline characters to PHP_EOL
     *
     * @return	void
     */
    protected function _sanitize_globals()
    {
        // Is $_GET data allowed? If not we'll set the $_GET to an empty array
        if ($this->_allow_get_array === false)
        {
            $_GET = [];
        }
        elseif (is_array($_GET))
        {
            foreach ($_GET as $key => $val)
            {
                $_GET[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }

        // Clean $_POST Data
        if (is_array($_POST))
        {
            foreach ($_POST as $key => $val)
            {
                $_POST[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }

        // Clean $_COOKIE Data
        if (is_array($_COOKIE))
        {
            // Also get rid of specially treated cookies that might be set by a server
            // or silly application, that are of no use to a CI application anyway
            // but that when present will trip our 'Disallowed Key Characters' alarm
            // http://www.ietf.org/rfc/rfc2109.txt
            // note that the key names below are single quoted strings, and are not PHP variables
            unset(
                $_COOKIE['$Version'],
                $_COOKIE['$Path'],
                $_COOKIE['$Domain']
            );

            foreach ($_COOKIE as $key => $val)
            {
                if (($cookie_key = $this->_clean_input_keys($key)) !== false)
                {
                    $_COOKIE[$cookie_key] = $this->_clean_input_data($val);
                }
                else
                {
                    unset($_COOKIE[$key]);
                }
            }
        }

        // Sanitize PHP_SELF
        $_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);

        log_message('debug', 'Global POST, GET and COOKIE data sanitized');
    }

    // --------------------------------------------------------------------

    /**
     * Clean Input Data
     *
     * Internal method that aids in escaping data and
     * standardizing newline characters to PHP_EOL.
     *
     * @param	string|string[]	$str	Input string(s)
     * @return	string
     */
    protected function _clean_input_data($str)
    {
        if (is_array($str))
        {
            $new_array = [];
            foreach (array_keys($str) as $key)
            {
                $new_array[$this->_clean_input_keys($key)] = $this->_clean_input_data($str[$key]);
            }
            return $new_array;
        }

        /* We strip slashes if magic quotes is on to keep things consistent

           NOTE: In PHP 5.4 get_magic_quotes_gpc() will always return 0 and
                 it will probably not exist in future versions at all.
        */
        if ( ! is_php('5.4') && get_magic_quotes_gpc())
        {
            $str = stripslashes($str);
        }

        // Clean UTF-8 if supported
        if (UTF8_ENABLED === true)
        {
            $str = $this->uni->clean_string($str);
        }

        // Remove control characters
        $str = remove_invisible_characters($str, false);

        // Standardize newlines if needed
        if ($this->_standardize_newlines === true)
        {
            return preg_replace('/(?:\r\n|[\r\n])/', PHP_EOL, $str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Clean Keys
     *
     * Internal method that helps to prevent malicious users
     * from trying to exploit keys we make sure that keys are
     * only named with alpha-numeric text and a few other items.
     *
     * @param	string	$str	Input string
     * @param	bool	$fatal	Whether to terminate script exection
     *				or to return FALSE if an invalid
     *				key is encountered
     * @return	string|bool
     */
    protected function _clean_input_keys($str, $fatal = true)
    {
        if ( ! preg_match('/^[a-z0-9:_\/|-]+$/i', $str))
        {
            if ($fatal === true)
            {
                return false;
            }
            else
            {
                set_status_header(503);
                echo 'Disallowed Key Characters.';
                exit(7); // EXIT_USER_INPUT
            }
        }

        // Clean UTF-8 if supported
        if (UTF8_ENABLED === true)
        {
            return $this->uni->clean_string($str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Request Headers
     *
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	array
     */
    public function request_headers($xss_clean = false)
    {
        // If header is already defined, return it immediately
        if ( ! empty($this->headers))
        {
            return $this->_fetch_from_array($this->headers, null, $xss_clean);
        }

        // In Apache, you can simply call apache_request_headers()
        if (function_exists('apache_request_headers'))
        {
            $this->headers = apache_request_headers();
        }
        else
        {
            isset($_SERVER['CONTENT_TYPE']) && $this->headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];

            foreach ($_SERVER as $key => $val)
            {
                if (sscanf($key, 'HTTP_%s', $header) === 1)
                {
                    // take SOME_HEADER and turn it into Some-Header
                    $header = str_replace('_', ' ', strtolower($header));
                    $header = str_replace(' ', '-', ucwords($header));

                    $this->headers[$header] = $_SERVER[$key];
                }
            }
        }

        return $this->_fetch_from_array($this->headers, null, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Get Request Header
     *
     * Returns the value of a single member of the headers class member
     *
     * @param	string		$index		Header name
     * @param	bool		$xss_clean	Whether to apply XSS filtering
     * @return	string|null	The requested header on success or NULL on failure
     */
    public function get_request_header($index, $xss_clean = false)
    {
        static $headers;

        if ( ! isset($headers))
        {
            empty($this->headers) && $this->request_headers();
            foreach ($this->headers as $key => $value)
            {
                $headers[strtolower($key)] = $value;
            }
        }

        $index = strtolower($index);

        if ( ! isset($headers[$index]))
        {
            return null;
        }

        return ($xss_clean === true)
            ? $this->security->xss_clean($headers[$index])
            : $headers[$index];
    }

    /**
     * Determines whether a request accepts JSON.
     *
     * @return bool
     */
    public function acceptsJson()
    {
        return $this->accepts('application/json');
    }

    /**
     * Determine if the current request probably expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return ($this->ajax() && ! $this->pjax() && $this->accepts('*')) || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->get_request_header('Accept');

        return isset($acceptable[0]) && Str::contains($acceptable[0], ['/json', '+json']);
    }

    /**
     * Determines whether the current requests accepts a given content type.
     *
     * @param  string|array  $contentTypes
     * @return bool
     */
    public function accepts($contentTypes): bool
    {
        $accepts = collect($this->get_request_header('Accept'));

        if (count($accepts) === 0) {
            return true;
        }

        $types = (array) $contentTypes;

        foreach ($accepts as $accept) {
            if ($accept === '*/*' || $accept === '*') {
                return true;
            }

            foreach ($types as $type) {
                if ($this->matchesType($accept, $type) || $accept === strtok($type, '/').'/*') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the given content types match.
     *
     * @param  string  $actual
     * @param  string  $type
     * @return bool
     */
    public static function matchesType($actual, $type)
    {
        if ($actual === $type) {
            return true;
        }

        $split = explode('/', $actual);

        return isset($split[1]) && preg_match('#'.preg_quote($split[0], '#').'/.+\+'.preg_quote($split[1], '#').'#', $type);
    }

    // --------------------------------------------------------------------

    /**
     * Is AJAX request?
     *
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
     *
     * @return 	bool
     */
    public function is_ajax_request()
    {
        return ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Determine if the current request URL and query string match a pattern.
     *
     * @param  mixed  ...$patterns
     * @return bool
     */
    public function fullUrlIs(...$patterns): bool
    {
        $url = $this->fullUrl();

        return collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $url));
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax(): bool
    {
        return $this->is_ajax_request();
    }

    /**
     * Determine if the request is the result of a PJAX call.
     *
     * @return bool
     */
    public function pjax(): bool
    {
        return $this->server('X-PJAX') == true;
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function prefetch(): bool
    {
        return strcasecmp($this->server('HTTP_X_MOZ') ?? '', 'prefetch') === 0 ||
            strcasecmp($this->headers['Purpose'] ?? '', 'prefetch') === 0;
    }

    // --------------------------------------------------------------------

    /**
     * Is CLI request?
     *
     * Test to see if a request was made from the command line.
     *
     * @deprecated	3.0.0	Use is_cli() instead
     * @return	bool
     */
    public function is_cli_request()
    {
        return is_cli();
    }

    // --------------------------------------------------------------------

    /**
     * Get Request Method
     *
     * Return the request method
     *
     * @param	bool	$upper	Whether to return in upper or lower case
     *				(default: FALSE)
     * @return 	string
     */
    public function method($upper = false)
    {
        return ($upper)
            ? strtoupper($this->server('REQUEST_METHOD'))
            : strtolower($this->server('REQUEST_METHOD'));
    }

    /**
     * Get the route handling the request.
     *
     * @param  string|null  $param
     * @param  mixed  $default
     * @return \Elegant\Routing\Route|object|string|null
     */
    public function route(string $param = null, $default = null)
    {
        $route = app('route');

        if (is_null($route) || is_null($param)) {
            return $route;
        }

        return $route->param($param, $default);
    }

    /**
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @return string The raw path (i.e. not urldecoded)
     */
    public function getPathInfo()
    {
        if (null !== $this->server('REQUEST_URI')) {
            $requestUri = $this->server('REQUEST_URI');

            if ('' !== $requestUri && '/' === $requestUri[0]) {
                // To only use path and query remove the fragment.
                if (false !== $pos = strpos($requestUri, '#')) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path,
                // only use URL path.
                $uriComponents = parse_url($requestUri);

                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }

                if (isset($uriComponents['query'])) {
                    $requestUri .= '?'.$uriComponents['query'];
                }
            }

            return $requestUri;
        }

        return '';
    }

    /**
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    public function getBaseUrl(): string
    {
        return $this->getSchemeAndHttpHost() . $this->server('HTTP_HOST');
    }

    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     *
     * @return string
     */
    public function getSchemeAndHttpHost(): string
    {
        return $this->getScheme().'://'.$this->getHttpHost();
    }

    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost(), '/');
    }

    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url(): string
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    public function fullUrl(): string
    {
        $query = $this->getQueryString();

        $question = $this->getBaseUrl().$this->getPathInfo() === '/' ? '/?' : '?';

        return $query ? $this->url().$question.$query : $this->url();
    }

    /**
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     */
    public function getQueryString(): ?string
    {
        $qs = $this->get_request_header('QUERY_STRING', true);

        return '' === $qs ? null : $qs;
    }

    /**
     * Generates a normalized URI (URL) for the Request.
     *
     * @return string
     */
    public function getUri()
    {
        return $this->getSchemeAndHttpHost().$this->getPathInfo();
    }

    /**
     * Gets the request's scheme.
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     *
     * @return string
     */
    public function getHttpHost(): string
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();

        if (('http' == $scheme && 80 == $port) || ('https' == $scheme && 443 == $port)) {
            return $this->getHost();
        }

        return $this->getHost().':'.$port;
    }

    /**
     * Checks whether the request is secure or not.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        $https = $this->server('HTTPS');

        return !empty($https) && 'off' !== strtolower($https);
    }

    /**
     * Returns the host name.
     *
     * @return string
     */
    public function getHost(): string
    {
        if (!$host = $this->server('HOST')) {
            if (!$host = $this->server('SERVER_NAME')) {
                $host = $this->server('SERVER_ADDR', '');
            }
        }

        // trim and remove port number from host
        // host is lowercase as per RFC 952/2181
        return strtolower(preg_replace('/:\d+$/', '', trim($host)));
    }

    /**
     * Returns the port on which the request is made.
     *
     * @return int|string|null Can be a string if fetched from the server bag
     */
    public function getPort()
    {
        if (!$host = $this->server('HOST')) {
            return $this->server('SERVER_PORT');
        }

        if ('[' === $host[0]) {
            $pos = strpos($host, ':', strrpos($host, ']'));
        } else {
            $pos = strrpos($host, ':');
        }

        if (false !== $pos && $port = substr($host, $pos + 1)) {
            return (int) $port;
        }

        return 'https' === $this->getScheme() ? 443 : 80;
    }

    // ------------------------------------------------------------------------

    /**
     * Get a subset containing the provided keys with values from the input data.
     *
     * @param  array|mixed  $keys
     * @return array
     */
    public function only($keys): array
    {
        $results = [];

        $input = $this->all();

        $placeholder = new stdClass;

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = data_get($input, $key, $placeholder);

            if ($value !== $placeholder) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path(): string
    {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern === '' ? '/' : $pattern;
    }

    /**
     * Get the current decoded path info for the request.
     *
     * @return string
     */
    public function decodedPath(): string
    {
        return rawurldecode($this->path());
    }

    /**
     * Get all of the input except for a specified array of items.
     *
     * @param  array|mixed  $keys
     * @return array
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->all();

        Arr::forget($results, $keys);

        return $results;
    }

    /**
     * Magic __get()
     *
     * Allows read access to protected properties
     *
     * @param	string	$name
     * @return	mixed
     */
    public function __get($name)
    {
        if ($name === 'raw_input_stream')
        {
            isset($this->_raw_input_stream) OR $this->_raw_input_stream = file_get_contents('php://input');
            return $this->_raw_input_stream;
        }
        elseif ($name === 'ip_address')
        {
            return $this->ip_address;
        }
    }

    /**
     * Get all of the input and files for the request.
     *
     * @param  array|mixed|null  $keys
     * @return array
     */
    public function all($keys = null): array
    {
        $input = array_merge((array) $this->post(), (array) $this->get(), $this->allFiles());

        if (! $keys) {
            return $input;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($input, $key));
        }

        return $results;
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        if (! is_array($files = $this->file($key))) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($this->isValidFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the given file is a valid file instance.
     *
     * @param  mixed  $file
     * @return bool
     */
    protected function isValidFile($file): bool
    {
        return $file instanceof SplFileInfo && $file->getPath() !== '';
    }

    /**
     * Retrieve a file from the request.
     *
     * @param string|null $key
     * @param  mixed  $default
     * @return \Elegant\Foundation\Http\File\UploadedFile|\Elegant\Foundation\Http\File\UploadedFile[]|array|null
     */
    public function file(string $key = null, $default = null)
    {
        return data_get($this->allFiles(), $key, $default);
    }

    /**
     * Get an array of all of the files on the request.
     *
     * @return array
     */
    public function allFiles(): array
    {
        $files = $this->files();

        return $this->convertedFiles = $this->convertedFiles ?? $this->convertUploadedFiles($files);
    }

    /**
     * Get files from php $_FILES variable.
     *
     * @return array
     */
    public function files(): array
    {
        if (empty($_FILES)) {
            return [];
        }

        return array_map(function ($fileData) {
            if (is_array($fileData['name'])) {
                return array_map(function ($index) use ($fileData) {
                    if($fileData['error'][0] === UPLOAD_ERR_NO_FILE) {
                        return [];
                    }

                    return [
                        'name'     => $fileData['name'][$index],
                        'type'     => $fileData['type'][$index],
                        'tmp_name' => $fileData['tmp_name'][$index],
                        'error'    => $fileData['error'][$index],
                        'size'     => $fileData['size'][$index],
                    ];
                }, array_keys($fileData['name']));
            }

            if($fileData['error'] === UPLOAD_ERR_NO_FILE) {
                return [];
            }

            return [
                'name'     => $fileData['name'],
                'type'     => $fileData['type'],
                'tmp_name' => $fileData['tmp_name'],
                'error'    => $fileData['error'],
                'size'     => $fileData['size'],
            ];
        }, $_FILES);
    }

    /**
     * Convert the given array to custom Laraigniter UploadedFiles.
     *
     * @param  array  $files
     * @return array
     */
    protected function convertUploadedFiles(array $files): array
    {
        return array_map(function ($file) {
            if(empty($file)) {
                return [];
            }

            if (is_array($file[array_key_first($file)])) {
                return !empty($file[array_key_first($file)]) ? $this->convertUploadedFiles($file) : [];
            } else {
                return UploadedFile::createFromBase($file);
            }
        }, $files);
    }

    /**
     * Determine if the request contains a given input item key.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function has($key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $value) {
            if (! Arr::has($input, $value)) {
                return false;
            }
        }

        return true;
    }
}
