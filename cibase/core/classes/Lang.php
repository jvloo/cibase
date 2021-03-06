<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Language Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Language
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/libraries/language.html
 */
class CI_Lang {

	/**
	 * List of translations
	 *
	 * @var	array
	 */
	public $language =	array();

	/**
	 * List of loaded language files
	 *
	 * @var	array
	 */
	public $is_loaded =	array();

	/**
	 * Holds Config class object
	 * @var object
	 */
	protected $config;

	/**
	 * Holds Arr class object
	 * @var object
	 */
	protected $arr;

	/**
	 * Holds fallback language
	 * @var string
	 */
	protected $fallback = 'en';

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->config =& load_class('Config', 'core');
		$this->arr =& load_class('Arr', 'core');
		$this->fallback = $this->config->item('language_fallback') ?: 'en';

		// Multilingual site?
		$this->_set_multilingual();

		log_message('info', 'Language Class Initialized');
	}

	// ------------------------------------------------------------------------

	/**
	 * This method sets multilingual support for the application
	 * @access 	protected
	 * @param 	none
	 * @return 	void
	 */
	protected function _set_multilingual()
	{
		// If the website is not multilingual, we return void
		if ( ! $this->config->item('multilingual') OR ! $this->multilingual())
		{
			return;
		}

		// Get what driver to use to store language
		$driver = $this->config->item('language_driver');

		// We secure type by limiting it to sessions or cookies
		if (in_array($driver, array('cookie', 'session'))) {
			$keyname = $this->config->item('language_keyname') ?? 'lang';
		}
		else {
			$driver = $keyname = null;
		}

		// In case we use cookies
		if ($driver == 'cookie')
		{
			global $IN;
			$code = null;

			// Check if the cookie is set
			if ($cookie = $IN->cookie($keyname, true))
			{
				$code = $cookie;
			}
			else
			{
				$code = $this->_set_client_language();
			}
		}
		// In case we use sessions
		elseif ($driver == 'session')
		{
			require_once BASEPATH.'classes/libraries/Session/Session.php';
			$session = new CI_Session;
			if ( ! ($code = $session->userdata($keyname)))
			{
				$code = $this->_set_client_language();
			}
		}
		// If neither cookies nor sessions are used we use client's language
		else
		{
			$code = $this->_set_client_language();
		}

		// Make sure clients language is available. If not, we use default
		// language. If the default language is not available as well, we use
		// language fallback.

		($this->valid_language($code)) OR $code = $this->config->item('language');
		($this->valid_language($code)) OR $code = $this->config->item('language_fallback');

		// Now we store our language code
		if ($driver == 'cookie' && ! isset($cookie)) {
			$IN->set_cookie($keyname, $code, 2678400);
		}
		elseif ($driver == 'session' && isset($session)) {
			$session->set_userdata($keyname, $code);
		}
		else {
			// Nothing :)
		}

		// Change configuration file language file
		$this->config->set_item('language', $code);
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns client's supported language code
	 * @access 	protected
	 * @param 	none
	 * @return 	string
	 */
	protected function _set_client_language()
	{
		return substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	}

	// ------------------------------------------------------------------------

	/**
	 * Load a language file
	 *
	 * @param	mixed	$langfile	Language file name
	 * @param	string	$idiom		Language name (en, etc.)
	 * @param	bool	$return		Whether to return the loaded array of translations
	 * @param 	string	$alt_path	Alternative path to look for the language file
	 *
	 * @return	void|string[]	Array containing translations, if $return is set to true
	 */
	public function load($langfile, $idiom = '', $return = false, $alt_path = '')
	{
		// In case of multiple files
		if (is_array($langfile))
		{
			foreach ($langfile as $value)
			{
				$this->load($value, $idiom, $return, $alt_path);
			}

			return;
		}

		// Remove .php extension if set
		$langfile = str_replace('.php', '', $langfile);

		$langfile .= '.php';

		if (empty($idiom) OR ! preg_match('/^[a-z_-]+$/i', $idiom))
		{
			$idiom = $this->config->item('language');
		}

		if ($return === false && isset($this->is_loaded[$langfile]) && $this->is_loaded[$langfile] === $idiom)
		{
			return;
		}

		// Load the english version first, then we load the the requested one
		$full_lang = array();

		// Load the base file, so any others found can override it
		$basepath = BASEPATH.'lang/'.$this->fallback.'/'.$langfile;
		if (($found = file_exists($basepath)) === true)
		{
			include($basepath);
		}

		// Do we have an alternative path to look in?
		if ($alt_path !== '')
		{
			$alt_path .= 'lang/'.$this->fallback.'/'.$langfile;
			if (file_exists($alt_path))
			{
				include($alt_path);
				$found = true;
			}
		}
		else
		{
			foreach (get_instance()->load->get_package_paths(true) as $package_path)
			{
				$package_path .= 'lang/'.$this->fallback.'/'.$langfile;
				if ($basepath !== $package_path && file_exists($package_path))
				{
					include($package_path);
					$found = true;
					break;
				}
			}
		}

		if ($found !== true)
		{
			show_error('Unable to load the requested language file: lang/'.$this->fallback.'/'.$langfile);
		}

		$full_lang = isset($lang) ? $lang : array();
		$lang = array();

		// Load the base file, so any others found can override it
		$basepath = BASEPATH.'lang/'.$idiom.'/'.$langfile;
		if (file_exists($basepath))
		{
			include($basepath);
		}

		// Do we have an alternative path to look in?
		if ($alt_path !== '')
		{
			$alt_path .= 'lang/'.$idiom.'/'.$langfile;
			if (file_exists($alt_path))
			{
				include($alt_path);
			}
		}
		else
		{
			foreach (get_instance()->load->get_package_paths(true) as $package_path)
			{
				$package_path .= 'lang/'.$idiom.'/'.$langfile;
				if ($basepath !== $package_path && file_exists($package_path))
				{
					include($package_path);
					break;
				}
			}
		}

		if ($found !== true)
		{
			show_error('Unable to load the requested language file: lang/'.$idiom.'/'.$langfile);
		}

		isset($lang) OR $lang = array();

		$full_lang = array_replace_recursive($full_lang, $lang);

		if ( ! isset($full_lang) OR ! is_array($full_lang))
		{
			log_message('error', 'Language file contains no data: lang/'.$idiom.'/'.$langfile);

			if ($return === true)
			{
				return array();
			}
			return;
		}

		if ($return === true)
		{
			return $full_lang;
		}

		$this->is_loaded[$langfile] = $idiom;
		$this->language = array_merge($this->language, $full_lang);

		log_message('info', 'Language file loaded: lang/'.$idiom.'/'.$langfile);
		return true;
	}

	// ------------------------------------------------------------------------
	// !SETTER & GETTER
	// ------------------------------------------------------------------------

	// Added by Kader Bouyakoub

	/**
	 * Fetches a single line of text from the language array
	 *
	 * @param   string  $line       Language line key
	 * @param   mixed   $args       string, integer or array
	 * @param   mixed   $default    to be used in case of fail
	 * @return  string  Translation
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function get($line, $args = null, $default = false)
	{
		$value = $this->arr->get($this->language, $line, $default);

		// Log message error if the line is not found
		if ($value === false)
		{
			log_message('error', 'Cound not find the language line "'.$line.'".');
		}
		// If the line is found, we parse arguments
		elseif ($args)
		{
			$args = (array) $args;

			// Is the user trying to translate arguments?
			foreach ($args as &$arg)
			{
				if (strpos($arg, 'lang:') !== false)
				{
					$arg = str_replace('lang:', '', $arg);
					$arg = $this->get($arg);
				}
			}

			$value = vsprintf($value, $args);
		}

		return $value;
	}

	/**
	 * This method allows you to change line value
	 * @access  public
	 * @param   string  $line   the language line to change
	 * @param   string  $value  language line's new value
	 * @return  void
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function set($line, $value = null)
	{
		$this->arr->set($this->language, $line, $value);
	}

	// ------------------------------------------------------------------------

	// Edited by Kader Bouyakoub: 15/02/2017 @ 10:05

	/**
	 * This method is replaced by Lang::get() and kept for backward compatibility
	 * @param	string	$line		Language line key
	 * @param	mixed	$args		string, integer or array
	 * @param	mixed	$default 	to be used in case of fail
	 * @return	string	Translation
	 */
	public function line($line, $args = null, $default = false)
	{
		return $this->get($line, $args, $default);
	}

	/**
	 * Singular & plural form of language line
	 *
	 * @access  public
	 * @param   string  $singular   singular form of the line
	 * @param   string  $plural     plural form of the line
	 * @param   integer $number     number used for comparison
	 * @return  string
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function nline($singular, $plural, $number = 0)
	{
		$line = ($number == 1) ? $singular : $plural;
		$value = $this->line($line, $number);
		return sprintf($value, $number);
	}

	/**
	 * This method is purely optional because you can use any method you want
	 * to fetch a language line in a particular context.
	 * By default, we use the ':' separator, so the line to get fetched would
	 * be like so: $lang['post:verb'] or $lang['post:noun']
	 *
	 * @access  public
	 * @param   string      $context    the context to use
	 * @param   string      $line       the language line to fetch
	 * @param   mixed       $args       arguments to pass parse
	 * @param   boolean     $default    value to use if no line is found
	 * @return  string      the fetched language line
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function xline($context, $line, $args = null, $default = false)
	{
		return $this->line($line.':'.$context, $args, $default);
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns an array of available languages codes if no parameter is passed,
	 * or an array of selected languages details.
	 *
	 * @access  public
	 * @param   mixed   string, strings, or array
	 * @return  array
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function languages()
	{
		return call_user_func_array(array($this->config, 'languages'), func_get_args());
	}

	/**
	 * Returns current language code if no parameter is passed.
	 * If a single parameter is passed, the array key value is returned.
	 * If multiple parameters or an array are passed, this method returns
	 * the requestes keys values only.
	 *
	 * @access  public
	 * @param   mixed   string, strings or array
	 * @return  mixed
	 *
	 * @author  Kader Bouyakoub <bkader@mail.com>
	 * @link    https://github.com/bkader
	 * @link    https://twitter.com/KaderBouyakoub
	 */
	public function language()
	{
		return call_user_func_array(array($this->config, 'language'), func_get_args());
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns true if the website is multilingual
	 * @access  public
	 * @param   none
	 * @return  boolean
	 */
	public function multilingual()
	{
		return $this->config->multilingual();
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns true if the language is available
	 * @access  public
	 * @param   string  $code   language code
	 * @return  boolean
	 */
	public function valid_language($code)
	{
		return $this->config->valid_language($code);
	}
}
