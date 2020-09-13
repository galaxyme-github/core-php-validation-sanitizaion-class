<?php
/**
 * Pure php Class for sanitization and validation
 * php version 7.0.x ~
 * 
 * @author Ibragim Yandiyev
 * @copyright (c) 2020, Ibragim Yandiyev
 * @link
 */
class DataGlobal {

	/**
	 * Validation data for the current form submission
	 * 
	 * @var array
	 */
	protected $_field_data		= array();

	/**
	 * Validation rules for the current form
	 *
	 * @var array
	 */
	protected $_config_rules	= array();

	/**
	 * Array of validation errors
	 *
	 * @var array
	 */
	protected $_error_array		= array();

	/**
	 * Array of custom error messages
	 *
	 * @var array
	 */
	protected $_error_messages	= array();

	/**
	 * Start tag for error wrapping
	 *
	 * @var string
	 */
	protected $_error_prefix	= '<p>';

	/**
	 * End tag for error wrapping
	 *
	 * @var string
	 */
	protected $_error_suffix	= '</p>';

	/**
	 * Custom error message
	 *
	 * @var string
	 */
	protected $error_string		= '';

	/**
	 * Whether the form data has been validated as safe
	 *
	 * @var bool
	 */
	protected $_safe_form_data	= FALSE;

	/**
	 * Custom data to validate
	 *
	 * @var array
	 */
	public $data	= array();

	/**
	 * Initialize DataGlobal class
	 *
	 * @param	array	$rules
	 * @return	void
	 */
	public function __construct($rules = array())
	{
		$this->data = $_REQUEST;
		$this->_config_rules = $rules;
	}

	// --------------------------------------------------------------------

	/**
	 * Set Rules
	 *
	 * This function takes an array of field names and validation
	 * rules, any custom error messages, validates the info,
	 * and stores it
	 *
	 * @param	mixed	$field
	 * @param	string	$label
	 * @param	mixed	$rules
	 * @param	array	$errors
	 * @return	this
	 */
	public function set_rules($field, $label = '', $rules = array(), $errors = array())
	{
		// No reason to set rules if a data array has not been specified
		if (empty($this->data))
		{
			return $this;
		}

		// If an array was passed via the first parameter instead of individual string
		// values we cycle through it and recursively call this function.
		if (is_array($field))
		{
			foreach ($field as $row)
			{
				// If not set both field and rules...
				if ( ! isset($row['field'], $row['rules']))
				{
					continue;
				}

				// If the field label wasn't passed we use the field name
				$label = isset($row['label']) ? $row['label'] : $row['field'];

				// Add the custom error message array
				$errors = (isset($row['errors']) && is_array($row['errors'])) ? $row['errors'] : array();

				// Here we go!
				$this->set_rules($row['field'], $label, $row['rules'], $errors);
			}

			return $this;
		}

		// No fields or no rules? Nothing to do...
		if ( ! is_string($field) OR $field === '' OR empty($rules))
		{
			return $this;
		}
		elseif ( ! is_array($rules))
		{
			// Convert pipe-separated rules string to an array
			if ( ! is_string($rules))
			{
				return $this;
			}

			$rules = preg_split('/\|(?![^\[]*\])/', $rules);
		}

		// If the field label wasn't passed we use the field name
		$label = ($label === '') ? $field : $label;

		$indexes = array();

		// Is the field name an array? If it is an array, we break it apart
		// into its components so that we can fetch the corresponding POST or GET data later
		if (($is_array = (bool) preg_match_all('/\[(.*?)\]/', $field, $matches)) === TRUE)
		{
			sscanf($field, '%[^[][', $indexes[0]);

			for ($i = 0, $c = count($matches[0]); $i < $c; $i++)
			{
				if ($matches[1][$i] !== '')
				{
					$indexes[] = $matches[1][$i];
				}
			}
		}

		// Build our master array
		$this->_field_data[$field] = array(
			'field'		=> $field,
			'label'		=> $label,
			'rules'		=> $rules,
			'errors'	=> $errors,
			'is_array'	=> $is_array,
			'keys'		=> $indexes,
			'requestdata'	=> NULL,
			'error'		=> ''
		);
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Run Validation
	 * 
	 * This function does all the work.
	 * 
	 * @param string $group
	 * @return bool
	 */

	public function validate($group = '')
	{
		$validation_array = empty($this->data) ? $_REQUEST : $this->data;

		if (count($this->_field_data) === 0)
		{
			// No validation rules?
			if (count($this->_config_rules) === 0)
			{
				return FALSE;
			}

			$this->set_rules(isset($this->_config_rules[$group]) ? $this->_config_rules[$group] : $this->_config_rules);

			// Were we able to set the rules correctly?
			if (count($this->_field_data) === 0)
			{
				//die('Unable to find validation rules');
				return FALSE;
			}
		}

		// Cycle through the rules for each field and match the corresponding $data item
		foreach ($this->_field_data as $field => &$row)
		{
			// Fetch the data from the data array item and cache it in the _field_data array.
			// Depending on whether the field name is an array or a string will determine where we get it from.
			if ($row['is_array'] === TRUE)
			{
				$this->_field_data[$field]['requestdata'] = $this->_reduce_array($validation_array, $row['keys']);
			}
			elseif (isset($validation_array[$field]))
			{
				$this->_field_data[$field]['requestdata'] = $validation_array[$field];
			}
		}

		// Execute validation rules
		// Note: A second foreach (for now) is required in order to avoid false-positives
		//	 for rules like 'matches', which correlate to other validation fields.
		foreach ($this->_field_data as $field => &$row)
		{
			// Don't try to validate if we have no rules set
			if (empty($row['rules']))
			{
				continue;
			}

			$this->_execute($row, $row['rules'], $row['requestdata']);
		}

		// Did we end up with any errors?
		$total_errors = count($this->_error_array);
		if ($total_errors > 0)
		{
			$this->_safe_form_data = TRUE;
		}

		// Now we need to re-set the POST data with the new, processed data
		empty($this->validation_data) && $this->_reset_post_array();

		return ($total_errors === 0);
	}

		// --------------------------------------------------------------------

	/**
	 * Traverse a multidimensional $_REQUEST or $_GET array index until the data is found
	 *
	 * @param	array
	 * @param	array
	 * @param	int
	 * @return	mixed
	 */
	protected function _reduce_array($array, $keys, $i = 0)
	{
		if (is_array($array) && isset($keys[$i]))
		{
			return isset($array[$keys[$i]]) ? $this->_reduce_array($array[$keys[$i]], $keys, ($i+1)) : NULL;
		}

		// NULL must be returned for empty fields
		return ($array === '') ? NULL : $array;
	}

	// --------------------------------------------------------------------

	/**
	 * Re-populate the _POST or _GET array with our finalized and processed data
	 *
	 * @return	void
	 */
	protected function _reset_post_array()
	{
		foreach ($this->_field_data as $field => $row)
		{
			if ($row['requestdata'] !== NULL)
			{
				if ($row['is_array'] === FALSE)
				{
					isset($_REQUEST[$field]) && $_REQUEST[$field] = is_array($row['requestdata']) ? NULL : $row['requestdata'];
				}
				else
				{
					// start with a reference
					$request_ref =& $_REQUEST;

					// before we assign values, make a reference to the right POST key
					if (count($row['keys']) === 1)
					{
						$request_ref =& $request_ref[current($row['keys'])];
					}
					else
					{
						foreach ($row['keys'] as $val)
						{
							$request_ref =& $request_ref[$val];
						}
					}

					$request_ref = $row['requestdata'];
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Executes the Validation routines
	 *
	 * @param	array
	 * @param	array
	 * @param	mixed
	 * @param	int
	 * @return	mixed
	 */
	protected function _execute($row, $rules, $requestdata = NULL, $cycles = 0)
	{
		// If the $_POST or $_GET data is an array we will run a recursive call
		//
		// Note: We MUST check if the array is empty or not!
		// Otherwise empty arrays will always pass validation.
		if (is_array($requestdata) && ! empty($requestdata))
		{
			foreach ($requestdata as $key => $val)
			{
				$this->_execute($row, $rules, $val, $key);
			}

			return;
		}

		$rules = $this->_prepare_rules($rules);
		foreach ($rules as $rule)
		{
			$_in_array = FALSE;

			// We set the $requestdata variable with the current data in our master array so that
			// each cycle of the loop is dealing with the processed data from the last cycle
			if ($row['is_array'] === TRUE && is_array($this->_field_data[$row['field']]['requestdata']))
			{
				// We shouldn't need this safety, but just in case there isn't an array index
				// associated with this cycle we'll bail out
				if ( ! isset($this->_field_data[$row['field']]['requestdata'][$cycles]))
				{
					continue;
				}

				$requestdata = $this->_field_data[$row['field']]['requestdata'][$cycles];
				$_in_array = TRUE;
			}
			else
			{
				// If we get an array field, but it's not expected - then it is most likely
				// somebody messing with the form on the client side, so we'll just consider
				// it an empty field
				$requestdata = is_array($this->_field_data[$row['field']]['requestdata'])
					? NULL
					: $this->_field_data[$row['field']]['requestdata'];
			}

			// Is the rule a callback?
			$callback = $callable = FALSE;
			if (is_string($rule))
			{
				if (strpos($rule, 'callback_') === 0)
				{
					$rule = substr($rule, 9);
					$callback = TRUE;
				}
			}
			elseif (is_callable($rule))
			{
				$callable = TRUE;
			}
			elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1]))
			{
				// We have a "named" callable, so save the name
				$callable = $rule[0];
				$rule = $rule[1];
			}

			// Strip the parameter (if exists) from the rule
			// Rules can contain a parameter: max_length[5]
			$param = FALSE;
			if ( ! $callable && preg_match('/(.*?)\[(.*)\]/', $rule, $match))
			{
				$rule = $match[1];
				$param = $match[2];
			}

			// Ignore empty, non-required inputs with a few exceptions ...
			if (
				($requestdata === NULL OR $requestdata === '')
				&& $callback === FALSE
				&& $callable === FALSE
				&& ! in_array($rule, array('required', 'isset', 'matches'), TRUE)
			)
			{
				continue;
			}

			// Call the function that corresponds to the rule
			if ($callback OR $callable !== FALSE)
			{
				if ($callback)
				{
					if ( ! method_exists($this, $rule))
					{
						//die('Unable to find callback validation rule: '.$rule);
						$result = FALSE;
					}
					else
					{
						// Run the function and grab the result
						$result = $this->$rule($requestdata, $param);
					}
				}
				else
				{
					$result = is_array($rule)
						? $rule[0]->{$rule[1]}($requestdata)
						: $rule($requestdata);

					// Is $callable set to a rule name?
					if ($callable !== FALSE)
					{
						$rule = $callable;
					}
				}

				// Re-assign the result to the master data array
				if ($_in_array === TRUE)
				{
					$this->_field_data[$row['field']]['requestdata'][$cycles] = is_bool($result) ? $requestdata : $result;
				}
				else
				{
					$this->_field_data[$row['field']]['requestdata'] = is_bool($result) ? $requestdata : $result;
				}
			}
			elseif ( ! method_exists($this, $rule))
			{
				// If our own wrapper function doesn't exist we see if a native PHP function does.
				// Users can use any native PHP function call that has one param.
				if (function_exists($rule))
				{
					// Native PHP functions issue warnings if you pass them more parameters than they use
					$result = ($param !== FALSE) ? $rule($requestdata, $param) : $rule($requestdata);

					if ($_in_array === TRUE)
					{
						$this->_field_data[$row['field']]['requestdata'][$cycles] = is_bool($result) ? $requestdata : $result;
					}
					else
					{
						$this->_field_data[$row['field']]['requestdata'] = is_bool($result) ? $requestdata : $result;
					}
				}
				else
				{
					//die('Unable to find validation rule: '.$rule);
					$result = FALSE;
				}
			}
			else
			{
				$result = $this->$rule($requestdata, $param);

				if ($_in_array === TRUE)
				{
					$this->_field_data[$row['field']]['requestdata'][$cycles] = is_bool($result) ? $requestdata : $result;
				}
				else
				{
					$this->_field_data[$row['field']]['requestdata'] = is_bool($result) ? $requestdata : $result;
				}
			}

			// Did the rule test negatively? If so, grab the error.
			if ($result === FALSE)
			{
				// Callable rules might not have named error messages
				if ( ! is_string($rule))
				{
					$line = 'The {field} field must contain a number greater than or equal to {param}(Anonymous function)';
				}
				else
				{
					$line = $this->_get_error_message($rule, $row['field']);
				}

				// Is the parameter we are inserting into the error message the name
				// of another field? If so we need to grab its "field label"
				if (isset($this->_field_data[$param], $this->_field_data[$param]['label']))
				{
					$param = $this->_translate_fieldname($this->_field_data[$param]['label']);
				}

				// Build the error message
				$message = $this->_build_error_msg($line, $this->_translate_fieldname($row['label']), $param);

				// Save the error message
				$this->_field_data[$row['field']]['error'] = $message;

				if ( ! isset($this->_error_array[$row['field']]))
				{
					$this->_error_array[$row['field']] = $message;
				}

				return;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Prepare rules
	 * 
	 * Re-orders the provided rules in order of importance, so that
	 * they can easily be executed later without weird checks ...
	 * 
	 * "Callbacks" are given the highest priority (always called),
	 * followd by 'required' (called if callbacks didn't fail),
	 * and then every next rule depends on the previous one passing.
	 * 
	 * @param array $rules
	 * @param array
	 */
	protected function _prepare_rules($rules)
	{
		$new_rules = array();
		$callbacks = array();

		foreach ($rules as &$rule)
		{
			// Let 'required' always be the first (non-callback) rule
			if ($rule === 'required')
			{
				array_unshift($new_rules, 'required');
			}
			// 'isset' is a kind of a weird alias for 'required' ...
			elseif ($rule === 'isset' && (empty($new_rules) OR $new_rules[0] !== 'required'))
			{
				array_unshift($new_rules, 'isset');
			}
			// The old/classic 'callback_'-prefixed rules
			elseif (is_string($rule) && strncmp('callback_', $rule, 9) === 0)
			{
				$callbacks[] = $rule;
			}
			// Proper callables
			elseif (is_callable($rule))
			{
				$callbacks[] = $rule;
			}
			// "Named" callables; i.e. array('name' => $callable)
			elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1]))
			{
				$callbacks[] = $rule;
			}
			// Everything else goes at the end of the queue
			else
			{
				$new_rules[] = $rule;
			}
		}

		return array_merge($callbacks, $new_rules);
	}

	// --------------------------------------------------------------------

	/**
	 * Error Language line
	 *
	 *
	 * @param	string	$line		Language line key
	 * @return	string	Translation
	 */
	public function line($line)
	{
		require('./ErrorLang.php');
		$value = isset($lang[$line]) ? $lang[$line] : FALSE;

		return $value;
	}

	// --------------------------------------------------------------------

	/**
	 * Get the error message for the rule
	 *
	 * @param 	string $rule 	The rule name
	 * @param 	string $field	The field name
	 * @return 	string
	 */
	protected function _get_error_message($rule, $field)
	{
		// check if a custom message is defined through validation config row.
		if (isset($this->_field_data[$field]['errors'][$rule]))
		{
			return $this->_field_data[$field]['errors'][$rule];
		}
		// check if a custom message has been set using the set_message() function
		elseif (isset($this->_error_messages[$rule]))
		{
			return $this->_error_messages[$rule];
		}
		elseif (FALSE !== ($line = $this->line('form_validation_'.$rule)))
		{
			return $line;
		}
		// DEPRECATED support for non-prefixed keys, lang file again
		elseif (FALSE !== ($line = $this->line($rule)))
		{
			return $line;
		}

		return $this->line('form_validation_error_message_not_set').'('.$rule.')';
	}

	// --------------------------------------------------------------------

	/**
	 * Translate a field name
	 *
	 * @param	string	the field name
	 * @return	string
	 */
	protected function _translate_fieldname($fieldname)
	{
		// Do we need to translate the field name? We look for the prefix 'lang:' to determine this
		// If we find one, but there's no translation for the string - just return it
		if (sscanf($fieldname, 'lang:%s', $line) === 1 && FALSE === ($fieldname = $this->line($line)))
		{
			return $line;
		}

		return $fieldname;
	}

	// --------------------------------------------------------------------

	/**
	 * Build an error message using the field and param.
	 *
	 * @param	string	The error message line
	 * @param	string	A field's human name
	 * @param	mixed	A rule's optional parameter
	 * @return	string
	 */
	protected function _build_error_msg($line, $field = '', $param = '')
	{
		// Check for %s in the string for legacy support.
		if (strpos($line, '%s') !== FALSE)
		{
			return sprintf($line, $field, $param);
		}

		return str_replace(array('{field}', '{param}'), array($field, $param), $line);
	}

	// --------------------------------------------------------------------

	/**
	 * Get Error Message
	 *
	 * Gets the error message associated with a particular field
	 *
	 * @param	string	$field	Field name
	 * @param	string	$prefix	HTML start tag
	 * @param 	string	$suffix	HTML end tag
	 * @return	string
	 */
	public function error($field, $prefix = '', $suffix = '')
	{
		if (empty($this->_field_data[$field]['error']))
		{
			return '';
		}

		if ($prefix === '')
		{
			$prefix = $this->_error_prefix;
		}

		if ($suffix === '')
		{
			$suffix = $this->_error_suffix;
		}

		return $prefix.$this->_field_data[$field]['error'].$suffix;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Array of Error Messages
	 *
	 * Returns the error messages as an array
	 *
	 * @return	array
	 */
	public function error_array()
	{
		return $this->_error_array;
	}

	// --------------------------------------------------------------------

	/**
	 * Error String
	 *
	 * Returns the error messages as a string, wrapped in the error delimiters
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function error_string($prefix = '', $suffix = '')
	{
		// No errors, validation passes!
		if (count($this->_error_array) === 0)
		{
			return '';
		}

		if ($prefix === '')
		{
			$prefix = $this->_error_prefix;
		}

		if ($suffix === '')
		{
			$suffix = $this->_error_suffix;
		}

		// Generate the error string
		$str = '';
		foreach ($this->_error_array as $val)
		{
			if ($val !== '')
			{
				$str .= $prefix.$val.$suffix."\n";
			}
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Required
	 *
	 * @param	string
	 * @return	bool
	 */
	public function required($str)
	{
		return is_array($str)
			? (empty($str) === FALSE)
			: (trim($str) !== '');
	}

	// --------------------------------------------------------------------

	/**
	 * Performs a Regular Expression match test.
	 *
	 * @param	string
	 * @param	string	regex
	 * @return	bool
	 */
	public function regex_match($str, $regex)
	{
		return (bool) preg_match($regex, $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Match one field to another
	 *
	 * @param	string	$str	string to compare against
	 * @param	string	$field
	 * @return	bool
	 */
	public function matches($str, $field)
	{
		return isset($this->_field_data[$field], $this->_field_data[$field]['requestdata'])
			? ($str === $this->_field_data[$field]['requestdata'])
			: FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Minimum Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function min_length($str, $val)
	{
		if ( ! is_numeric($val))
		{
			return FALSE;
		}

		return ($val <= mb_strlen($str));
	}

	// --------------------------------------------------------------------

	/**
	 * Max Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function max_length($str, $val)
	{
		if ( ! is_numeric($val))
		{
			return FALSE;
		}

		return ($val >= mb_strlen($str));
	}

	// --------------------------------------------------------------------

	/**
	 * Exact Length
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function exact_length($str, $val)
	{
		if ( ! is_numeric($val))
		{
			return FALSE;
		}

		return (mb_strlen($str) === (int) $val);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid URL
	 *
	 * @param	string	$str
	 * @return	bool
	 */
	public function valid_url($str)
	{
		if (empty($str))
		{
			return FALSE;
		}
		elseif (preg_match('/^(?:([^:]*)\:)?\/\/(.+)$/', $str, $matches))
		{
			if (empty($matches[2]))
			{
				return FALSE;
			}
			elseif ( ! in_array(strtolower($matches[1]), array('http', 'https'), TRUE))
			{
				return FALSE;
			}

			$str = $matches[2];
		}

		// Apparently, FILTER_VALIDATE_URL doesn't reject digit-only names for some reason ...
		// See https://github.com/bcit-ci/CodeIgniter/issues/5755
		if (ctype_digit($str))
		{
			return FALSE;
		}

		// PHP 7 accepts IPv6 addresses within square brackets as hostnames,
		// but it appears that the PR that came in with https://bugs.php.net/bug.php?id=68039
		// was never merged into a PHP 5 branch ... https://3v4l.org/8PsSN
		if (preg_match('/^\[([^\]]+)\]/', $str, $matches) && ! is_php('7') && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE)
		{
			$str = 'ipv6.host'.substr($str, strlen($matches[1]) + 2);
		}

		return (filter_var('http://'.$str, FILTER_VALIDATE_URL) !== FALSE);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Email
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_email($str)
	{
		if (function_exists('idn_to_ascii') && preg_match('#\A([^@]+)@(.+)\z#', $str, $matches))
		{
			$domain = defined('INTL_IDNA_VARIANT_UTS46')
				? idn_to_ascii($matches[2], 0, INTL_IDNA_VARIANT_UTS46)
				: idn_to_ascii($matches[2]);

			if ($domain !== FALSE)
			{
				$str = $matches[1].'@'.$domain;
			}
		}

		return (bool) filter_var($str, FILTER_VALIDATE_EMAIL);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Emails
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_emails($str)
	{
		if (strpos($str, ',') === FALSE)
		{
			return $this->valid_email(trim($str));
		}

		foreach (explode(',', $str) as $email)
		{
			if (trim($email) !== '' && $this->valid_email(trim($email)) === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha($str)
	{
		return ctype_alpha($str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_numeric($str)
	{
		return ctype_alnum((string) $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric w/ spaces
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_numeric_spaces($str)
	{
		return (bool) preg_match('/^[A-Z0-9 ]+$/i', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric with underscores and dashes
	 *
	 * @param	string
	 * @return	bool
	 */
	public function alpha_dash($str)
	{
		return (bool) preg_match('/^[a-z0-9_-]+$/i', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Numeric
	 *
	 * @param	string
	 * @return	bool
	 */
	public function numeric($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $str);

	}

	// --------------------------------------------------------------------

	/**
	 * Integer
	 *
	 * @param	string
	 * @return	bool
	 */
	public function integer($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Decimal number
	 *
	 * @param	string
	 * @return	bool
	 */
	public function decimal($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Greater than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function greater_than($str, $min)
	{
		return is_numeric($str) ? ($str > $min) : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Equal to or Greater than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function greater_than_equal_to($str, $min)
	{
		return is_numeric($str) ? ($str >= $min) : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Less than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function less_than($str, $max)
	{
		return is_numeric($str) ? ($str < $max) : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Equal to or Less than
	 *
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	public function less_than_equal_to($str, $max)
	{
		return is_numeric($str) ? ($str <= $max) : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Value should be within an array of values
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function in_list($value, $list)
	{
		return in_array($value, explode(',', $list), TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number  (0,1,2,3, etc.)
	 *
	 * @param	string
	 * @return	bool
	 */
	public function is_natural($str)
	{
		return ctype_digit((string) $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number, but not a zero  (1,2,3, etc.)
	 *
	 * @param	string
	 * @return	bool
	 */
	public function is_natural_no_zero($str)
	{
		return ($str != 0 && ctype_digit((string) $str));
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Base64
	 *
	 * Tests a string for characters outside of the Base64 alphabet
	 * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
	 *
	 * @param	string
	 * @return	bool
	 */
	public function valid_base64($str)
	{
		return (base64_encode(base64_decode($str)) === $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid phone number
	 * @return bool
	 */
	public function phone($str)
	{
		return (bool) preg_match('/^[0-9 ]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Check special chars
	 * @param string
	 * @param string
	 * @return bool
	 */
	public function special_chars($str, $val)
	{
		$special_char_array = str_split($val);
		foreach ($special_char_array as $char)
		{
			if (substr_count($str, $char) > 0)
			{
				return false;
			}
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Check allowed chars
	 * @param string
	 * @param string
	 * @return bool
	 */
	public function allowed_chars($str, $val)
	{
		$allowed_chars = $val;
		$char_array = str_split($str);
		foreach ($char_array as $char)
		{
			if (substr($allowed_chars, $char) <= 0)
			{
				return false;
			}
		}

		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Reset validation vars
	 *
	 * Prevents subsequent validation routines from being affected by the
	 * results of any previous validation routine due to the CI singleton.
	 *
	 * @return	this
	 */
	public function reset_validation()
	{
		$this->_field_data = array();
		$this->_error_array = array();
		$this->_error_messages = array();
		$this->error_string = '';
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Init sanitization function
	 * @param variable $value
	 * @param string $type
	 * @return sanitized variable
	 */

	public function init_sanitize($value, $type)
	{
		switch ($type)
		{
			case 'url': //Removes all illegal character from URL
			$output = filter_var($value, FILTER_SANITIZE_URL);
			break;
			case 'int': //Removes all characters except digits and + - signs
			$output = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
			break;
			case 'float': //Remove all characters, except digits, +- signs, and optionally
			$output = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
			break;
			case 'email': //Removes all illegal characters from an e-mail address
			$value = substr($value, 0, 254);
			$output = filter_var($value, FILTER_SANITIZE_EMAIL);
			break;
			case 'string': //Removes tags/special characters from a string
			$output = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
			break;
			case 'to_html_entities': //trim string, Convert some characters to HTML entities,  Encodes double and single quotes
			$output = htmlentities( trim($value), ENT_QUOTES );
			break;
			case 'to_html_entities_plain': //trim string, Convert some characters to HTML entities, plain text, Does not encode any quotes
			$output = htmlentities( trim($value), ENT_NOQUOTES );
			break;
			case 'upper': // trim string, upper case first word
			$output = ucwords(strtolower(trim($value)));
			break;
			case 'ucfirst': //trim string, upper case first word
			$output = ucfirst(strtolower(trim($value)));
			break;
			case 'lower': //trim string, lower case words
			$output = strtolower(trim($value));
			break;
			case 'urle': //trim string, url encoded
			$output = urlencode(trim($value));
			break;
			case 'trim_urle': //trim string, url decoded
			$output = urldecode(trim($value));
			break;
			case 'filter_special_chars': // Filter HTML-escapes special characters
			$output = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
			break;
			case 'magic_quotes': // This is safe in a database query as adding slash front of predefined characters.
			$output = filter_var($value, FILTER_SANITIZE_MAGIC_QUOTES);
			break;
			case 'remove_backslashes': //Remove the backslash.
			$output = stripslashes($value);
			break;
			case 'remove_html_tags': //Strip the string from HTML tags
				$output = strip_tags($value);
			break;
			case 'trim': //Remove white space from both sides of a string
				$output = trim($value);
			break;
			case 'remove_space': //Remove all white space from string.
				$output = str_replace(' ',  '', $value);
			break;
			default:
			$output = false;
		}

		return $output;
	}

	// --------------------------------------------------------------------

	/**
	 * Sanitize value
	 * 
	 * This function clean values or remove special chars, and prevent sql injection ...
	 * @param string $field
	 * @param string $type
	 * @return sanitized variable
	 */

	public function sanitize($field, $type = '', $data_for = '')
	{
		$sanitization_array = empty($this->data) ? $_REQUEST : $this->data;

		if (empty($field) || empty($type) || empty($this->data))
		{
			return;
		}
		$value = $sanitization_array[$field];
		$sanitization_type_array = explode('|', $type);

		foreach ($sanitization_type_array as $type)
		{
			if (!method_exists($this, $type))
			{
				if (!$value = $this->init_sanitize($value, $type))
				{
					die('Method and Function for TYPE:'. $type . ' does not exist');
					exit;
				}	
			}
			else
			{
				$value = $this->$type($value, $data_for);
			}
		}

		return $value;
	}

	// --------------------------------------------------------------------

	/**
	 * Convert PHP tags to entities
	 *
	 * @param	string
	 * @return	string
	*/
	public function encode_php_tags($value)
	{
		return str_replace(array('<?', '?>'), array('&lt;?', '?&gt;'), $value);
	}

	// --------------------------------------------------------------------

	/**
	 * Prep data for form
	 *
	 * This function allows HTML to be safely shown in a form.
	 * Special characters are converted.
	 *
	 * @param	mixed	$data	Input data
	 * @return	mixed
	*/
	public function prep_for_form($data)
	{
		if ($this->_safe_form_data === FALSE OR empty($data))
		{
			return $data;
		}

		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				$data[$key] = $this->prep_for_form($val);
			}

			return $data;
		}

		return str_replace(array("'", '"', '<', '>'), array('&#39;', '&quot;', '&lt;', '&gt;'), stripslashes($data));
	}

	// --------------------------------------------------------------------

	/**
	 * Prep URL
	 *
	 * @param	string
	 * @return	string
	*/
	public function prep_url($str = '')
	{
		if ($str === 'http://' OR $str === '')
		{
			return '';
		}

		if (strpos($str, 'http://') !== 0 && strpos($str, 'https://') !== 0)
		{
			return 'http://'.$str;
		}

		return $str;
	}

	/**
	 * Hash encode a string
	 *
	 * @param	string	$str
	 * @param	string	$type = 'sha1'
	 * @return	string
	 */
	public function do_hash($str, $type = 'sha1')
	{
		if ( ! in_array(strtolower($type), hash_algos()))
		{
			$type = 'md5';
		}

		return hash($type, $str);
	}
	
	/**
	 * Prevent SQL injection
	 * @param variable $value
	 * @param string $hostname
	 * @param string @username
	 * @param string @password
	 */
	public function prevent_sql_injection($value, $db = array())
	{
		if (!is_array($db) || empty($db))
		{
			return htmlspecialchars(trim($value));
		}
		else{
			// Create connection
			$conn = new mysqli_connect($db['hostname'], $db['username'], $db['password']);
			// Check connection
			if ($conn->connect_error) {
				die("Connection failed: " . $conn->connect_error);
			}
			return htmlspecialchars(mysqli_real_escape_string($con, trim($value)));
			$conn->close();
		}
		
	}

}
?>