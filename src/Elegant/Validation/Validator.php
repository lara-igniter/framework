<?php

namespace Elegant\Validation;

use Elegant\Database\Model\Model;
use Elegant\Contracts\Validation\Rule;
use Elegant\Foundation\Http\File\File;
use Elegant\Foundation\Http\File\UploadedFile;
use Elegant\Support\Arr;
use Elegant\Support\Facades\Date;
use Elegant\Support\Str;
use Exception;
use InvalidArgumentException;

if (! class_exists('CI_Form_validation', false) && defined('BASEPATH')) {
    require_once BASEPATH.'libraries/Form_validation.php';
}

class Validator extends \CI_Form_validation
{
    protected ?string $currentFieldName = null;

    /**
     * {@inheritdoc}
     */
    public function set_rules($field, $label = '', $rules = [], $errors = [])
    {
        $method = $this->CI->input->method();
        if (empty($this->validation_data) && in_array($method, ['put', 'patch'], true)) {
            $this->set_data($_POST);
        }

        return parent::set_rules($field, $label, $rules, $errors);
    }

    /**
     * Executes the Validation routines
     *
     * @param array $row
     * @param array $rules
     * @param mixed $postdata
     * @param int $cycles
     *
     * @return    mixed
     * @throws \Elegant\Routing\Exceptions\RouteNotFoundException
     */
    protected function _execute($row, $rules, $postdata = null, $cycles = 0)
    {
        $this->setCurrentFieldName($row['field']);

        // If the $_POST data is an array we will run a recursive call
        //
        // Note: We MUST check if the array is empty or not!
        //       Otherwise empty arrays will always pass validation.
        if (is_array($postdata) && !empty($postdata)) {
            foreach ($postdata as $key => $val) {
                $this->_execute($row, $rules, $val, $key);
            }

            return;
        }

        // Pass attribute name at filled validation rule
        $key = array_search('filled', $rules);
        if ($key) {
            $rules[$key] = 'filled[' . $this->_field_data[$row['field']]['field'] . ']';
        }

        $rules = $this->_prepare_rules($rules);
        foreach ($rules as $rule) {
            $_in_array = false;

            // We set the $postdata variable with the current data in our master array so that
            // each cycle of the loop is dealing with the processed data from the last cycle
            if ($row['is_array'] === true && is_array($this->_field_data[$row['field']]['postdata'])) {
                // We shouldn't need this safety, but just in case there isn't an array index
                // associated with this cycle we'll bail out
                if (!isset($this->_field_data[$row['field']]['postdata'][$cycles])) {
                    continue;
                }

                $postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
                $_in_array = true;
            } else {
                // If we get an array field, but it's not expected - then it is most likely
                // somebody messing with the form on the client side, so we'll just consider
                // it an empty field
                $postdata = is_array($this->_field_data[$row['field']]['postdata'])
                    ? null
                    : $this->_field_data[$row['field']]['postdata'];
            }

            // Is the rule a callback?
            $callback = $callable = false;
            if (is_string($rule)) {
                if (strpos($rule, 'callback_') === 0) {
                    $rule = substr($rule, 9);
                    $callback = true;
                }
            } elseif (is_callable($rule)) {
                $callable = true;
            } elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                // We have a "named" callable, so save the name
                $callable = $rule[0];
                $rule = $rule[1];
            } elseif ($rule instanceof Rule) {
                $callback = true;
            }

            // Strip the parameter (if exists) from the rule
            // Rules can contain a parameter: max_length[5]
            $param = false;
            if ($rule instanceof Rule) {
                //
            } elseif (!$callable && preg_match('/(.*?)\[(.*)\]/', $rule, $match)) {
                $rule = $match[1];
                $param = $match[2];
            }

            // Ignore empty, non-required inputs with a few exceptions ...
            if (
                ($postdata === null or $postdata === '')
                && $callback === false
                && $callable === false
                && !in_array($rule, ['required', 'filled', 'matches'], true)
            ) {
                continue;
            }

            // Call the function that corresponds to the rule
            if ($callback or $callable !== false) {
                if ($callback) {
                    if ($rule instanceof Rule) {
                        $result = $rule->passes();
                    } else if (method_exists($this->CI, $rule)) {
                        // Run the function and grab the result
                        $result = $this->CI->$rule($postdata, $param);
                    } else {
                        log_message('debug', 'Unable to find callback validation rule: ' . $rule);
                        $result = false;
                    }
                } else {
                    $result = is_array($rule)
                        ? $rule[0]->{$rule[1]}($postdata)
                        : $rule($postdata);

                    // Is $callable set to a rule name?
                    if ($callable !== false) {
                        $rule = $callable;
                    }
                }

                // Re-assign the result to the master data array
                if ($_in_array === true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                }
            } elseif (!method_exists($this, $rule)) {
                // If our own wrapper function doesn't exist we see if a native PHP function does.
                // Users can use any native PHP function call that has one param.
                if (function_exists($rule)) {
                    // Native PHP functions issue warnings if you pass them more parameters than they use
                    $result = ($param !== false) ? $rule($postdata, $param) : $rule($postdata);

                    if ($_in_array === true) {
                        $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                    } else {
                        $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                    }
                } else {
                    log_message('debug', 'Unable to find validation rule: ' . $rule);
                    $result = false;
                }
            } else {
//                $method = "validate" . Str::studly($rule);
//
//                $result = $this->$method($postdata, $param);

                $result = $this->$rule($postdata, $param);

                if ($_in_array === true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                }
            }

            // Did the rule test negatively? If so, grab the error.
            if ($result === false) {
                // Bypass image validation rules if nullable or sometimes rule exist
                if (in_array($rule, ['image', 'file', 'dimensions', 'mimes', 'mimetypes', 'between', 'size', 'min', 'max'], true)
                    && count(array_intersect(['sometimes', 'nullable'], $rules)) > 0) {
                    if(is_array($row['postdata'])) {
                        foreach ($row['postdata'] as $value) {
                            if(!$value->isValid()) {
                                continue;
                            }
                        }
                    } else {
                        if(!is_object($row['postdata']) || !$row['postdata']->isValid()) {
                            continue;
                        }
                    }
                }

                // Callable rules might not have named error messages
                if (!is_string($rule)) {
                    if ($rule instanceof Rule) {
                        $line = $rule->message();
                    } else {
                        $line = $this->CI->lang->line('form_validation_error_message_not_set') . '(Anonymous function)';
                    }
                } else {
                    $line = $this->_get_error_message($rule, $row);
                }

                // Is the parameter we are inserting into the error message the name
                // of another field? If so we need to grab its "field label"
                if (isset($this->_field_data[$param], $this->_field_data[$param]['label'])) {
                    $param = $this->_translate_fieldname($this->_field_data[$param]['label']);
                }

                // Convert a collection object that is formatted as json to params only
                if ($this->json($param)) {
                    $param = collect(json_decode($param, true))->implode(',');
                }

                // Build the error message
                $message = $this->_build_error_msg($line, $this->_translate_fieldname($row['label']), $param);

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if (!isset($this->_error_array[$row['field']])) {
                    $this->_error_array[$row['field']] = $message;
                }

                return;
            }
        }
    }

    /**
     * Get the error message for the rule
     *
     * @param string $rule
     * @param $field
     * @return    string
     */
    protected function _get_error_message($rule, $field): string
    {
        $type = 'string';

        if (count(array_intersect($field['rules'], ['in_list'])) > 0) {
            $type = 'array';
        }

        if (count(array_intersect($field['rules'], ['numeric', 'integer', 'is_natural', 'is_natural_no_zero'])) > 0) {
            $type = 'numeric';
        }

        if (count(array_intersect($field['rules'], ['dimensions', 'file', 'mimes', 'mimetypes'])) > 0) {
            $type = 'file';
        }

        // check if a custom message is defined through validation config row.
        if (isset($this->_field_data[$field['field']]['errors'][$rule])) {
            return $this->_field_data[$field['field']]['errors'][$rule];
        } // check if a custom message has been set using the set_message() function
        elseif (isset($this->_error_messages[$rule])) {
            return $this->_error_messages[$rule];
        } elseif (false !== ($line = $this->CI->lang->line('form_validation_' . $rule, false))) {
            return is_array($line) ? $line[$type] : $line;
        } // DEPRECATED support for non-prefixed keys, lang file again
        elseif (false !== ($line = $this->CI->lang->line($rule, false))) {
            return is_array($line) ? $line[$type] : $line;
        }

        return $this->CI->lang->line('error_message_not_set') . '(' . $rule . ')';
    }

    /**
     * Prepare rules
     * @param array $rules
     * @return    array
     */
    protected function _prepare_rules($rules): array
    {
        $new_rules = [];
        $callbacks = [];

        foreach ($rules as &$rule) {
            // Let 'required' always be the first (non-callback) rule
            if ($rule === 'required') {
                array_unshift($new_rules, 'required');
            } // 'filled' is a kind of a weird alias for 'required' ...
            elseif ($rule === 'filled' && (empty($new_rules) or $new_rules[0] !== 'required')) {
                array_unshift($new_rules, 'filled');
            } // The old/classic 'callback_'-prefixed rules
            elseif (is_string($rule) && strncmp('callback_', $rule, 9) === 0) {
                $callbacks[] = $rule;
            } // Proper callables
            elseif (is_callable($rule)) {
                $callbacks[] = $rule;
            } // "Named" callables; i.e. array('name' => $callable)
            elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                $callbacks[] = $rule;
            } // Everything else goes at the end of the queue
            else {
                $new_rules[] = $rule;
            }
        }

        return array_merge($callbacks, $new_rules);
    }

    /**
     * Validate the date is after a given date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function after($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str) && !$str instanceof DateTimeInterface) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(' ')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'after');

        return $field === 'today'
            ? Date::parse($str)->gt(Date::parse(now()))
            : Date::parse($str)->gt(Date::parse($this->getValue($field)));
    }

    /**
     * Validate the date is equal or after a given date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function after_or_equal($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str) && !$str instanceof DateTimeInterface) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(' ')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'after_or_equal');

        return $field === 'today'
            ? Date::parse($str)->gte(Date::parse(now()))
            : Date::parse($str)->gte(Date::parse($this->getValue($field)));
    }

    /**
     * Validate that an attribute contains only alphabetic characters.
     *
     * @param string $str
     * @return    bool
     */
    public function alpha($str): bool
    {
        return is_string($str) && preg_match('/^[\pL\pM]+$/u', $str);
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters, dashes, and underscores.
     *
     * @param string $str
     * @return bool
     */
    public function alpha_dash($str): bool
    {
        if (!is_string($str) && !is_numeric($str)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN_-]+$/u', $str) > 0;
    }

    /**
     * Validate that an attribute contains only alpha-numeric characters.
     *
     * @param string $str
     * @return    bool
     */
    public function alpha_num($str): bool
    {
        if (!is_string($str) && !is_numeric($str)) {
            return false;
        }

        return preg_match('/^[\pL\pM\pN]+$/u', $str) > 0;
    }

    /**
     * Validate that an attribute is an array.
     *
     * @param $str
     * @param $field
     * @return bool
     */
    public function array($str, $field): bool
    {
        if (!is_array($str)) {
            return false;
        }

        if (empty($field)) {
            return true;
        }

        return empty(array_diff_key($str, array_fill_keys($field, '')));
    }

    /**
     * Validate that an attribute is a base64.
     *
     * @param string $str
     * @return bool
     */
    public function base64(string $str): bool
    {
        return (base64_encode(base64_decode($str)) === $str);
    }

    /**
     * Validate the date is before a given date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function before($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str) && !$str instanceof DateTimeInterface) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(' ')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'before');

        return $field === 'today'
            ? Date::parse($str)->isBefore(Date::parse(now()))
            : Date::parse($str)->isBefore(Date::parse($this->getValue($field)));
    }

    /**
     * Validate the date is before or equal a given date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function before_or_equal($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str) && !$str instanceof DateTimeInterface) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(' ')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'before_or_equal');

        return $field === 'today'
            ? Date::parse($str)->isPast()
            : Date::parse($str)->lte(Date::parse($this->getValue($field)));
    }

    /**
     * Validate the size of an attribute is between a set of values.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function between($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(2, $parameters, 'between');

        $size = $this->getSize($attribute, $str);

        return $size >= $parameters[0] && $size <= $parameters[1];
    }

    /**
     * Validate that an attribute is a boolean.
     *
     * @param string $str
     * @return bool
     */
    public function boolean(string $str): bool
    {
        $acceptable = [true, false, 0, 1, '0', '1'];

        return in_array($str, $acceptable, true);
    }

    /**
     * Validate that an attribute has a matching confirmation.
     *
     * @param string $str
     * @return bool
     */
    public function confirmed(string $str): bool
    {
        return $this->same($str, 'password_confirmation');
    }

    /**
     * Validate that an attribute is a valid date.
     *
     * @param mixed $str
     * @return bool
     */
    public function date($str): bool
    {
        if ((!is_string($str) && !is_numeric($str)) || strtotime($str) === false) {
            return false;
        }

        $date = date_parse($str);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validate that an attribute is equal to another date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function date_equals($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str) && !$str instanceof DateTimeInterface) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(' ')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'date_equals');
        return $field === 'today'
            ? Date::parse($str)->equalTo(Date::parse(now()))
            : Date::parse($str)->equalTo(Date::parse($this->getValue($field)));
    }

    /**
     * Validate that an attribute is a valid date.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     */
    public function date_format($str, $field): bool
    {
        if (!is_string($str) && !is_numeric($str)) {
            return false;
        }

        $format = $field;

        $date = DateTime::createFromFormat('!' . $format, $str);

        return $date && $date->format($format) == $str;
    }

    /**
     * Validate that an attribute has a given number of decimal places.
     *
     * @param mixed $str
     * @param $field
     * @return bool
     *
     * TODO: rename to decimal() Load MY_Form_validation without extend CI_Form_validation.
     */
    public function decimal_number($str, $field): bool
    {
        if (!$this->numeric($str)) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'decimal_number');

        $matches = [];

        preg_match('/^[+-]?\d*.(\d*)$/', $str, $matches);

        $decimals = strlen(end($matches));

        if (!isset($parameters[1])) {
            return $decimals == $parameters[0];
        }

        return $decimals >= $parameters[0] &&
            $decimals <= $parameters[1];
    }

    /**
     * Validate that an attribute is different from another attribute.
     *
     * @param string $str
     * @param string $field
     * @return bool
     */
    public function different(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'different');

        return !($str === $this->getValue($parameters[0]));
    }

    /**
     * Validate that an attribute has a given number of digits.
     *
     * @param $str
     * @param $field
     * @return bool
     */
    public function digits($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'digits');

        return !preg_match('/[^0-9]/', $str)
            && strlen((string)$str) == $field;
    }

    /**
     * Validate that an attribute is between a given number of digits.
     *
     * @param $str
     * @param $field
     * @return bool
     */
    public function digits_between($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $this->requireParameterCount(2, $parameters, 'digits_between');

        $length = strlen((string) $str);

        return ! preg_match('/[^0-9]/', $str)
            && $length >= $parameters[0] && $length <= $parameters[1];
    }

    public function dimensions($str, $field): bool
    {
        if ($this->isValidFileInstance($str) && in_array($str->getMimeType(), ['image/svg+xml', 'image/svg'])) {
            return true;
        }

        if (!$this->isValidFileInstance($str) || !$sizeDetails = @getimagesize($str->getRealPath())) {
            return false;
        }

        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'dimensions');

        [$width, $height] = $sizeDetails;

        $parameters = $this->parseNamedParameters($parameters);

        if ($this->failsBasicDimensionChecks($parameters, $width, $height) ||
            $this->failsRatioCheck($parameters, $width, $height)) {
            return false;
        }

        return true;
    }

    /**
     * Validate that an attribute is a valid e-mail address.
     *
     * @param string $str
     * @return bool
     */
    public function email(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that the values of an attribute are in array values.
     *
     * @param $value
     * @param $list
     * @return bool
     */
    public function enum($value, $list): bool
    {
        if (is_null($value) || !class_exists($list)) {
            return false;
        }

        try {
            if($list instanceof \App\Enums\AbstractEnum) {
                $value = $list::tryFrom($value);
            } elseif ($list instanceof \Laraigniter\Enum\BaseEnum) {
                $value = $list::from($value);
            }

            return $value ?? true;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Validate the existence of an attribute value in a database table.
     *
     * @param $str
     * @param $field
     * @return bool
     */
    public function exists($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode('.')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'exists');

        $table = $parameters[0];
        $idColumn = $parameters[1] ?? 'id';

        if (! isset($this->CI->db)) {
            return false;
        }

        // MY_Model shares this singleton; leftover WHERE/JOIN clauses must not
        // leak into existence checks (especially under in-process PHPUnit).
        $this->CI->db->reset_query();

        return $this->CI->db->limit(1)->get_where($table, [$idColumn => $str])->num_rows() === 1;
    }

    /**
     * Test if the given width and height fail any conditions.
     *
     * @param array $parameters
     * @param int $width
     * @param int $height
     * @return bool
     */
    protected function failsBasicDimensionChecks(array $parameters, int $width, int $height): bool
    {
        return (isset($parameters['width']) && $parameters['width'] != $width) ||
            (isset($parameters['min_width']) && $parameters['min_width'] > $width) ||
            (isset($parameters['max_width']) && $parameters['max_width'] < $width) ||
            (isset($parameters['height']) && $parameters['height'] != $height) ||
            (isset($parameters['min_height']) && $parameters['min_height'] > $height) ||
            (isset($parameters['max_height']) && $parameters['max_height'] < $height);
    }

    /**
     * Determine if the given parameters fail a dimension ratio check.
     *
     * @param array $parameters
     * @param int $width
     * @param int $height
     * @return bool
     */
    protected function failsRatioCheck(array $parameters, int $width, int $height): bool
    {
        if (!isset($parameters['ratio'])) {
            return false;
        }

        [$numerator, $denominator] = array_replace(
            [1, 1], array_filter(sscanf($parameters['ratio'], '%f/%d'))
        );

        $precision = 1 / (max($width, $height) + 1);

        return abs($numerator / $denominator - $width / $height) > $precision;
    }

    /**
     * Validate the given value is a valid file.
     *
     * @param mixed $str
     * @return bool
     */
    public function file($str): bool
    {
        return $this->isValidFileInstance($str);
    }

    /**
     * Validate the given attribute is filled if it is present.
     *
     * @param $str
     * @param $field
     * @return bool
     */
    public function filled($str, $field): bool
    {
        if ($field && !empty($str)) {
            return $this->required($str);
        }

        return false;
    }

    /**
     * Get an attribute name for a given value.
     *
     * @param mixed $value
     * @return int|string|null
     */
    protected function getAttributeName($value)
    {
        $output = array_filter($this->_field_data, function ($data) use ($value) {
            if($value instanceof File) {
                return $data['field'] == $this->getCurrentFieldName();
            }

            return $data['field'] == $this->getCurrentFieldName() && $data['postdata'] == $value;
        });

        return !empty($output) ? array_key_first($output) : '';
    }

    /**
     * Get a rule for a given attribute.
     *
     * @param string $attribute
     * @return mixed
     */
    protected function getRule(string $attribute)
    {
        return $this->_field_data[$attribute]['rules'];
    }

    /**
     * Get the size of an attribute.
     *
     * @param string $attribute
     * @param mixed $value
     * @return false|float|int|string
     */
    protected function getSize(string $attribute, $value)
    {
        $hasNumeric = $this->hasRule($attribute, ['numeric', 'integer']);

        // This method will determine if the attribute is a number, string, or file and
        // return the proper size accordingly. If it is a number, then number itself
        // is the size. If it is a file, we take kilobytes, and for a string the
        // entire length of the string will be considered the attribute size.
        if (is_numeric($value) && $hasNumeric) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        } elseif ($value instanceof File) {
            return (int)($value->getSize() / 1024);
        }

        return mb_strlen($value);
    }

    /**
     * Get the value of a given attribute.
     *
     * @param string $attribute
     * @return mixed
     */
    protected function getValue(string $attribute)
    {
        return Arr::get($this->_field_data, $attribute)['postdata'];
    }

    /**
     * Check that the given value is a valid file instance.
     *
     * @param mixed $value
     * @return bool
     */
    public function isValidFileInstance($value): bool
    {
        if ($value instanceof UploadedFile && !$value->isValid()) {
            return false;
        }

        return $value instanceof File;
    }

    /**
     * Validate that the values of an attribute are in array values.
     *
     * @param $value
     * @param $list
     * @return bool
     */
    public function in_array($value, $list): bool
    {
        if ($this->json($list)) {
            $list = collect(json_decode($list, true));

            return in_array($value, $list->values()->toArray(), true);
        }

        return in_array($value, explode(',', $list), true);
    }

    /**
     * Validate that an attribute is an integer.
     *
     * @param string $str
     * @return    bool
     */
    public function integer($str): bool
    {
        return filter_var($str, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate that an attribute is a valid IP.
     *
     * @param string $str
     * @return    bool
     */
    public function ip(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that an attribute is a valid IPv4.
     *
     * @param string $str
     * @return    bool
     */
    public function ipv4(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate that an attribute is a valid IPv6.
     *
     * @param string $str
     * @return    bool
     */
    public function ipv6(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Validate the attribute is a valid JSON string.
     *
     * @param string $str
     * @return bool
     */
    public function json(string $str): bool
    {
        if (is_array($str)) {
            return false;
        }

        if (!is_scalar($str) && !is_null($str) && !method_exists($str, '__toString')) {
            return false;
        }

        json_decode($str);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate that an attribute is less than another attribute.
     *
     * @param string $str
     * @param string $field
     * @return    bool
     */
    public function lt(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'lt');

        $comparedToValue = $this->getValue($parameters[0]);

        if (is_null($comparedToValue) && (is_numeric($str) && is_numeric($parameters[0]))) {
            return $this->getSize($attribute, $str) < $parameters[0];
        }

        if (is_numeric($parameters[0])) {
            return false;
        }

        if (is_numeric($str) && is_numeric($comparedToValue)) {
            return $str < $comparedToValue;
        }

        return $this->getSize($attribute, $str) < $this->getSize($attribute, $comparedToValue);
    }

    /**
     * Validate that an attribute is less than or equal another attribute.
     *
     * @param string $str
     * @param string $field
     * @return bool
     */
    public function lte(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'lte');

        $comparedToValue = $this->getValue($parameters[0]);

        if (is_null($comparedToValue) && (is_numeric($str) && is_numeric($parameters[0]))) {
            return $this->getSize($attribute, $str) <= $parameters[0];
        }

        if (is_numeric($parameters[0])) {
            return false;
        }

        if (is_numeric($str) && is_numeric($comparedToValue)) {
            return $str <= $comparedToValue;
        }

        return $this->getSize($attribute, $str) <= $this->getSize($attribute, $comparedToValue);
    }

    /**
     * Validate that an attribute is greater than another attribute.
     *
     * @param string $str
     * @param string $field
     * @return    bool
     */
    public function gt(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'gt');

        $comparedToValue = $this->getValue($parameters[0]);

        if (is_null($comparedToValue) && (is_numeric($str) && is_numeric($parameters[0]))) {
            return $this->getSize($attribute, $str) > $parameters[0];
        }

        if (is_numeric($parameters[0])) {
            return false;
        }

        if (is_numeric($str) && is_numeric($comparedToValue)) {
            return $str > $comparedToValue;
        }

        return $this->getSize($attribute, $str) > $this->getSize($attribute, $comparedToValue);
    }

    /**
     * Validate that an attribute is greater than or equal another attribute.
     *
     * @param string $str
     * @param string $field
     * @return    bool
     */
    public function gte(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'gt');

        $comparedToValue = $this->getValue($parameters[0]);

        if (is_null($comparedToValue) && (is_numeric($str) && is_numeric($parameters[0]))) {
            return $this->getSize($attribute, $str) >= $parameters[0];
        }

        if (is_numeric($parameters[0])) {
            return false;
        }

        if (is_numeric($str) && is_numeric($comparedToValue)) {
            return $str >= $comparedToValue;
        }

        return $this->getSize($attribute, $str) >= $this->getSize($attribute, $comparedToValue);
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param string $attribute
     * @param $rules
     * @return bool
     */
    public function hasRule(string $attribute, $rules): bool
    {
        return count(array_intersect($this->getRule($attribute), $rules)) > 0;
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function max($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'max');

        if ($str instanceof UploadedFile && !$str->isValid()) {
            return false;
        }

        return $this->getSize($attribute, $str) <= (int)$field;
    }

    /**
     * Validate the guessed extension of a file upload is in a set of file extensions.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function mimes($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        if (!$this->isValidFileInstance($str)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($str, $parameters)) {
            return false;
        }

        if (in_array('jpg', $parameters) || in_array('jpeg', $parameters)) {
            $parameters = array_unique(array_merge($parameters, ['jpg', 'jpeg']));
        }

        return $str->getPath() !== '' && in_array($str->getClientOriginalExtension(), $parameters);
    }

    /**
     * Validate the MIME type of a file upload attribute is in a set of MIME types.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function mimetypes($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        if (!$this->isValidFileInstance($str)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($str, $parameters)) {
            return false;
        }

        return $str->getPath() !== '' &&
            (in_array($str->getMimeType(), $parameters) ||
                in_array(explode('/', $str->getMimeType())[0] . '/*', $parameters));
    }

    /**
     * Validate the MIME type of a file is an image MIME type.
     *
     * @param mixed $str
     * @return bool
     */
    public function image($str): bool
    {
        return $this->mimes($str, 'jpg,jpeg,png,gif,bmp,svg,webp');
    }

    /**
     * Validate the size of an attribute is greater than a minimum value.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function min($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'min');

        if ($str instanceof UploadedFile && !$str->isValid()) {
            return false;
        }

        return $this->getSize($attribute, $str) >= (int)$field;
    }

    /**
     * Validate that an attribute does not pass a regular expression check.
     *
     * @param $str
     * @param string $regex
     * @return bool
     */
    public function not_regex($str, string $regex): bool
    {
        if (!is_string($str) && !is_numeric($str)) {
            return false;
        }

        return preg_match($regex, $str) < 1;
    }

    /**
     * "Indicate" validation should pass if value is null.
     *
     * Always returns true, just lets us put "nullable" in rules.
     *
     * @return bool
     */
    public function nullable(): bool
    {
        return true;
    }

    /**
     * Validate that an attribute is numeric.
     *
     * @param string $str
     * @return    bool
     */
    public function numeric($str): bool
    {
        return is_numeric($str);
    }

    /**
     * Parse named parameters to $key => $value items.
     *
     * @param array $parameters
     * @return array
     */
    public function parseNamedParameters(array $parameters): array
    {
        return array_reduce($parameters, function ($result, $item) {
            [$key, $value] = array_pad(explode('=', $item, 2), 2, null);

            $result[$key] = $value;

            return $result;
        });
    }

    /**
     * Parse the connection / table for the unique / exists rules.
     *
     * @param $table
     * @return array
     */
    public function parseTable($table): array
    {
        $table = Str::contains($table, '.') ? explode('.', $table, 2) : $table;

        $table = Arr::wrap($table);

        $modelClass = 'App\\Models\\' . Str::ucfirst(Str::singular($table[0]));

        if (class_exists($modelClass) && is_a($modelClass, Model::class, true)) {
            $model = new $modelClass;

            $table = $model->getTable();
            $idColumn = $model->getKeyName();
        }

        return [$table, $idColumn ?? null];
    }

    /**
     * Validate that an attribute is a valid phone number.
     *
     * @param string $str
     * @return    bool
     */
    public function phone($str): bool
    {
        return (bool) preg_match('/^(?:\+|00)?[1-9][0-9\s\-\(\).]{3,20}$/', $str);
    }

    /**
     * Validate that an attribute does not exist.
     *
     * @param $str
     * @return bool
     */
    public function prohibited($str): bool
    {
        return false;
    }

    /**
     * Validate that an attribute passes a regular expression check.
     *
     * @param $str
     * @param string $regex
     * @return bool
     */
    public function regex($str, string $regex): bool
    {
        if (!is_string($str) && !is_numeric($str)) {
            return false;
        }

        return preg_match($regex, $str) > 0;
    }

    /**
     * Validate that a required attribute exists.
     *
     * @param $str
     * @return bool
     */
    public function required($str): bool
    {
        if (is_null($str)) {
            return false;
        } elseif (is_string($str) && trim($str) === '') {
            return false;
        } elseif ((is_array($str) || $str instanceof Countable) && count($str) < 1) {
            return false;
        } elseif ($str instanceof File) {
            return (string)$str->getPath() !== '';
        }

        return true;
    }

    /**
     * Require a certain number of parameters to be present.
     *
     * @param int $count
     * @param array $parameters
     * @param string $rule
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function requireParameterCount(int $count, array $parameters, string $rule)
    {
        if (count($parameters) < $count) {
            throw new InvalidArgumentException("Validation rule $rule requires at least $count parameters.");
        }
    }

    /**
     * Validate that two attributes match.
     *
     * @param string $str
     * @param string $field
     * @return bool
     */
    public function same(string $str, string $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode('.')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'same');

        return $str === $this->getValue($parameters[0]);
    }

    /**
     * Check if PHP uploads are explicitly allowed.
     *
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function shouldBlockPhpUpload($value, array $parameters): bool
    {
        if (in_array('php', $parameters)) {
            return false;
        }

        $phpExtensions = [
            'php', 'php3', 'php4', 'php5', 'phtml',
        ];

        return ($value instanceof UploadedFile)
            ? in_array(trim(strtolower($value->getClientOriginalExtension())), $phpExtensions)
            : in_array(trim(strtolower($value->getExtension())), $phpExtensions);
    }

    /**
     * Validate the size of an attribute.
     *
     * @param mixed $str
     * @param mixed $field
     * @return bool
     */
    public function size($str, $field): bool
    {
        $parameters = !empty($field) ? Str::of($field)->explode(',')->toArray() : [];

        $attribute = $this->getAttributeName($str);

        $this->requireParameterCount(1, $parameters, 'size');

        return $this->getSize($attribute, $str) == (int)$field;
    }

    /**
     * "Validate" optional attributes.
     *
     * Always returns true, just lets us put sometimes in rules.
     *
     * @return bool
     */
    public function sometimes(): bool
    {
        return true;
    }

    /**
     * Validate that an attribute has a given number of digits.
     *
     * @param $str
     * @return bool
     */
    public function string($str): bool
    {
        return is_string($str);
    }

    /**
     * Unique
     *
     * Check if the input value doesn't already exist
     * in the specified database field.
     *
     * @param string $str
     * @param string $field
     * @return    bool
     */
    public function unique(string $str, string $field): bool
    {
        [$table, $idColumn] = $this->parseTable($field);

        $parameters = !empty($field) ? Str::of($field)->explode('.')->toArray() : [];

        $this->requireParameterCount(1, $parameters, 'unique');

        if (isset($parameters[2])) {
            return isset($this->CI->db) && $this->CI->db->limit(1)->get_where($table, [$parameters[1] => $str, "{$idColumn} <>" => $parameters[2]])->num_rows() === 0;
        } else {
            return isset($this->CI->db) && $this->CI->db->limit(1)->get_where($table, [$parameters[1] => $str])->num_rows() === 0;
        }
    }

    /**
     * Longitude GPS
     *
     * @param string $str
     * @return    bool
     */
    public function url(string $str): bool
    {
        if (!is_string($str)) {
            return false;
        }

        /*
         * This pattern is derived from Symfony\Component\Validator\Constraints\UrlValidator (5.0.7).
         *
         * (c) Fabien Potencier <fabien@symfony.com> http://symfony.com
         */
        $pattern = '~^
            (aaa|aaas|about|acap|acct|acd|acr|adiumxtra|adt|afp|afs|aim|amss|android|appdata|apt|ark|attachment|aw|barion|beshare|bitcoin|bitcoincash|blob|bolo|browserext|calculator|callto|cap|cast|casts|chrome|chrome-extension|cid|coap|coap\+tcp|coap\+ws|coaps|coaps\+tcp|coaps\+ws|com-eventbrite-attendee|content|conti|crid|cvs|dab|data|dav|diaspora|dict|did|dis|dlna-playcontainer|dlna-playsingle|dns|dntp|dpp|drm|drop|dtn|dvb|ed2k|elsi|example|facetime|fax|feed|feedready|file|filesystem|finger|first-run-pen-experience|fish|fm|ftp|fuchsia-pkg|geo|gg|git|gizmoproject|go|gopher|graph|gtalk|h323|ham|hcap|hcp|http|https|hxxp|hxxps|hydrazone|iax|icap|icon|im|imap|info|iotdisco|ipn|ipp|ipps|irc|irc6|ircs|iris|iris\.beep|iris\.lwz|iris\.xpc|iris\.xpcs|isostore|itms|jabber|jar|jms|keyparc|lastfm|ldap|ldaps|leaptofrogans|lorawan|lvlt|magnet|mailserver|mailto|maps|market|message|mid|mms|modem|mongodb|moz|ms-access|ms-browser-extension|ms-calculator|ms-drive-to|ms-enrollment|ms-excel|ms-eyecontrolspeech|ms-gamebarservices|ms-gamingoverlay|ms-getoffice|ms-help|ms-infopath|ms-inputapp|ms-lockscreencomponent-config|ms-media-stream-id|ms-mixedrealitycapture|ms-mobileplans|ms-officeapp|ms-people|ms-project|ms-powerpoint|ms-publisher|ms-restoretabcompanion|ms-screenclip|ms-screensketch|ms-search|ms-search-repair|ms-secondary-screen-controller|ms-secondary-screen-setup|ms-settings|ms-settings-airplanemode|ms-settings-bluetooth|ms-settings-camera|ms-settings-cellular|ms-settings-cloudstorage|ms-settings-connectabledevices|ms-settings-displays-topology|ms-settings-emailandaccounts|ms-settings-language|ms-settings-location|ms-settings-lock|ms-settings-nfctransactions|ms-settings-notifications|ms-settings-power|ms-settings-privacy|ms-settings-proximity|ms-settings-screenrotation|ms-settings-wifi|ms-settings-workplace|ms-spd|ms-sttoverlay|ms-transit-to|ms-useractivityset|ms-virtualtouchpad|ms-visio|ms-walk-to|ms-whiteboard|ms-whiteboard-cmd|ms-word|msnim|msrp|msrps|mss|mtqp|mumble|mupdate|mvn|news|nfs|ni|nih|nntp|notes|ocf|oid|onenote|onenote-cmd|opaquelocktoken|openpgp4fpr|pack|palm|paparazzi|payto|pkcs11|platform|pop|pres|prospero|proxy|pwid|psyc|pttp|qb|query|redis|rediss|reload|res|resource|rmi|rsync|rtmfp|rtmp|rtsp|rtsps|rtspu|s3|secondlife|service|session|sftp|sgn|shttp|sieve|simpleledger|sip|sips|skype|smb|sms|smtp|snews|snmp|soap\.beep|soap\.beeps|soldat|spiffe|spotify|ssh|steam|stun|stuns|submit|svn|tag|teamspeak|tel|teliaeid|telnet|tftp|tg|things|thismessage|tip|tn3270|tool|ts3server|turn|turns|tv|udp|unreal|urn|ut2004|v-event|vemmi|ventrilo|videotex|vnc|view-source|wais|webcal|wpid|ws|wss|wtai|wyciwyg|xcon|xcon-userid|xfire|xmlrpc\.beep|xmlrpc\.beeps|xmpp|xri|ymsgr|z39\.50|z39\.50r|z39\.50s)://                                 # protocol
            (((?:[\_\.\pL\pN-]|%[0-9A-Fa-f]{2})+:)?((?:[\_\.\pL\pN-]|%[0-9A-Fa-f]{2})+)@)?  # basic auth
            (
                ([\pL\pN\pS\-\_\.])+(\.?([\pL\pN]|xn\-\-[\pL\pN-]+)+\.?) # a domain name
                    |                                                 # or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}                    # an IP address
                    |                                                 # or
                \[
                    (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                \]  # an IPv6 address
            )
            (:[0-9]+)?                              # a port (optional)
            (?:/ (?:[\pL\pN\-._\~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})* )*          # a path
            (?:\? (?:[\pL\pN\-._\~!$&\'\[\]()*+,;=:@/?]|%[0-9A-Fa-f]{2})* )?   # a query (optional)
            (?:\# (?:[\pL\pN\-._\~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})* )?       # a fragment (optional)
        $~ixu';

        return preg_match($pattern, $str) > 0;
    }

    /**
     * Get the current field name at getAttributeName() function
     *
     * @return string|null
     */
    public function getCurrentFieldName(): ?string
    {
        return $this->currentFieldName;
    }

    /**
     * Set the current field name
     *
     * @param string|null $currentFieldName
     * @return void
     */
    public function setCurrentFieldName(?string $currentFieldName): void
    {
        $this->currentFieldName = $currentFieldName;
    }
}
