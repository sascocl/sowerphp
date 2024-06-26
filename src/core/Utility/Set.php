<?php

/**
 * SowerPHP: Framework PHP hecho en Chile.
 * Copyright (C) SowerPHP <https://www.sowerphp.org>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero
 * de GNU publicada por la Fundación para el Software Libre, ya sea la
 * versión 3 de la Licencia, o (a su elección) cualquier versión
 * posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU
 * para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General
 * Affero de GNU junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace sowerphp\core;

/**
 * Class used for manipulation of arrays.
 */
class Utility_Set {

	/**
	 * This function can be thought of as a hybrid between PHP's array_merge and array_merge_recursive. The difference
	 * to the two is that if an array key contains another array then the function behaves recursive (unlike array_merge)
	 * but does not do if for keys containing strings (unlike array_merge_recursive).
	 * See the unit test for more information.
	 *
	 * Note: This function will work with an unlimited amount of arguments and typecasts non-array parameters into arrays.
	 *
	 * @param array $arr1 Array to be merged
	 * @param array $arr2 Array to merge with
	 * @return array Merged array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::merge
	 */
    public static function merge($arr1, $arr2 = null) {
        $args = func_get_args();

        $r = (array)current($args);
        while (($arg = next($args)) !== false) {
            foreach ((array)$arg as $key => $val)     {
                if (!empty($r[$key]) && is_array($r[$key]) && is_array($val)) {
                    $r[$key] = self::merge($r[$key], $val);
                } elseif (is_int($key)) {
                    $r[] = $val;
                } else {
                    $r[$key] = $val;
                }
            }
        }
        return $r;
    }

	/**
	 * Filters empty elements out of a route array, excluding '0'.
	 *
	 * @param array $var Either an array to filter, or value when in callback
	 * @return mixed Either filtered array, or true/false when in callback
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::filter
	 */
    public static function filter(array $var) {
        foreach ($var as $k => $v) {
            if (is_array($v)) {
                $var[$k] = self::filter($v);
            }
        }
        return array_filter($var, array('Set', '_filter'));
    }

	/**
	 * Utility_Set::filter callback function
	 *
	 * @param array $var Array to filter.
	 * @return bool
	 */
    protected static function _filter($var)
    {
        if ($var === 0 || $var === '0' || !empty($var)) {
            return true;
        }
        return false;
    }

	/**
	 * Pushes the differences in $array2 onto the end of $array
	 *
	 * @param mixed $array Original array
	 * @param mixed $array2 Differences to push
	 * @return array Combined array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::pushDiff
	 */
    public static function pushDiff($array, $array2) {
        if (empty($array) && !empty($array2)) {
            return $array2;
        }
        if (!empty($array) && !empty($array2)) {
            foreach ($array2 as $key => $value) {
                if (!array_key_exists($key, $array)) {
                    $array[$key] = $value;
                } else {
                    if (is_array($value)) {
                        $array[$key] = self::pushDiff($array[$key], $array2[$key]);
                    }
                }
            }
        }
        return $array;
    }

	/**
	 * Maps the contents of the Set object to an object hierarchy.
	 * Maintains numeric keys as arrays of objects
	 *
	 * @param string $class A class name of the type of object to map to
	 * @param string $tmp A temporary class name used as $class if $class is an array
	 * @return object Hierarchical object
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::map
	 */
    public static function map($class = 'stdClass', $tmp = 'stdClass') {
        if (is_array($class)) {
            $val = $class;
            $class = $tmp;
        }

        if (empty($val)) {
            return null;
        }
        return self::_map($val, $class);
    }

    /**
     * Maps the given value as an object. If $value is an object,
     * it returns $value. Otherwise it maps $value as an object of
     * type $class, and if primary assign _name_ $key on first array.
     * If $value is not empty, it will be used to set properties of
     * returned object (recursively). If $key is numeric will maintain array
     * structure
     *
     * @param array $array Array to map
     * @param string $class Class name
     * @param bool $primary whether to assign first array key as the _name_
     * @return mixed Mapped object
     */
    protected static function _map(&$array, $class, $primary = false) {
        if ($class === true) {
            $out = new stdClass;
        } else {
            $out = new $class;
        }
        if (is_array($array)) {
            $keys = array_keys($array);
            foreach ($array as $key => $value) {
                if ($keys[0] === $key && $class !== true) {
                    $primary = true;
                }
                if (is_numeric($key)) {
                    if (is_object($out)) {
                        $out = get_object_vars($out);
                    }
                    $out[$key] = self::_map($value, $class);
                    if (is_object($out[$key])) {
                        if ($primary !== true && is_array($value) && self::countDim($value, true) === 2) {
                            if (!isset($out[$key]->_name_)) {
                                $out[$key]->_name_ = $primary;
                            }
                        }
                    }
                } elseif (is_array($value)) {
                    if ($primary === true) {
                        if (!isset($out->_name_)) {
                            $out->_name_ = $key;
                        }
                        $primary = false;
                        foreach ($value as $key2 => $value2) {
                            $out->{$key2} = self::_map($value2, true);
                        }
                    } else {
                        if (!is_numeric($key)) {
                            $out->{$key} = self::_map($value, true, $key);
                            if (is_object($out->{$key}) && !is_numeric($key)) {
                                if (!isset($out->{$key}->_name_)) {
                                    $out->{$key}->_name_ = $key;
                                }
                            }
                        } else {
                            $out->{$key} = self::_map($value, true);
                        }
                    }
                } else {
                    $out->{$key} = $value;
                }
            }
        } else {
            $out = $array;
        }
        return $out;
    }

	/**
	 * Checks to see if all the values in the array are numeric
	 *
	 * @param array $array The array to check.  If null, the value of the current Set object
	 * @return bool true if values are numeric, false otherwise
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::numeric
	 */
    public static function numeric($array = null) {
        if (empty($array)) {
            return null;
        }

        if ($array === range(0, count($array) - 1)) {
            return true;
        }

        $numeric = true;
        $keys = array_keys($array);
        $count = count($keys);

        for ($i = 0; $i < $count; $i++) {
            if (!is_numeric($array[$keys[$i]])) {
                $numeric = false;
                break;
            }
        }
        return $numeric;
    }

	/**
	 * Return a value from an array list if the key exists.
	 *
	 * If a comma separated $list is passed arrays are numeric with the key of the first being 0
	 * $list = 'no, yes' would translate to  $list = array(0 => 'no', 1 => 'yes');
	 *
	 * If an array is used, keys can be strings example: array('no' => 0, 'yes' => 1);
	 *
	 * $list defaults to 0 = no 1 = yes if param is not passed
	 *
	 * @param mixed $select Key in $list to return
	 * @param mixed $list can be an array or a comma-separated list.
	 * @return string the value of the array key or null if no match
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::enum
	 */
    public static function enum($select, $list = null) {
        if (empty($list)) {
            $list = array('no', 'yes');
        }

        $return = null;
        $list = self::normalize($list, false);

        if (array_key_exists($select, $list)) {
            $return = $list[$select];
        }
        return $return;
    }

	/**
	 * Returns a series of values extracted from an array, formatted in a format string.
	 *
	 * @param array $data Source array from which to extract the data
	 * @param string $format Format string into which values will be inserted, see sprintf()
	 * @param array $keys An array containing one or more Utility_Set::extract()-style key paths
	 * @return array An array of strings extracted from $keys and formatted with $format
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::format
	 */
    public static function format($data, $format, $keys) {
        $extracted = [];
        $count = count($keys);

        if (!$count) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $extracted[] = self::extract($data, $keys[$i]);
        }
        $out = [];
        $data = $extracted;
        $count = count($data[0]);

        if (preg_match_all('/\{([0-9]+)\}/msi', $format, $keys2) && isset($keys2[1])) {
            $keys = $keys2[1];
            $format = preg_split('/\{([0-9]+)\}/msi', $format);
            $count2 = count($format);

            for ($j = 0; $j < $count; $j++) {
                $formatted = '';
                for ($i = 0; $i <= $count2; $i++) {
                    if (isset($format[$i])) {
                        $formatted .= $format[$i];
                    }
                    if (isset($keys[$i]) && isset($data[$keys[$i]][$j])) {
                        $formatted .= $data[$keys[$i]][$j];
                    }
                }
                $out[] = $formatted;
            }
        } else {
            $count2 = count($data);
            for ($j = 0; $j < $count; $j++) {
                $args = [];
                for ($i = 0; $i < $count2; $i++) {
                    if (array_key_exists($j, $data[$i])) {
                        $args[] = $data[$i][$j];
                    }
                }
                $out[] = vsprintf($format, $args);
            }
        }
        return $out;
    }

	/**
	 * Implements partial support for XPath 2.0. If $path is an array or $data is empty it the call
	 * is delegated to Utility_Set::classicExtract.
	 *
	 * #### Currently implemented selectors:
	 *
	 * - /User/id (similar to the classic {n}.User.id)
	 * - /User[2]/name (selects the name of the second User)
	 * - /User[id>2] (selects all Users with an id > 2)
	 * - /User[id>2][<5] (selects all Users with an id > 2 but < 5)
	 * - /Post/Comment[author_name=john]/../name (Selects the name of all Posts that have at least one Comment written by john)
	 * - /Posts[name] (Selects all Posts that have a 'name' key)
	 * - /Comment/.[1] (Selects the contents of the first comment)
	 * - /Comment/.[:last] (Selects the last comment)
	 * - /Comment/.[:first] (Selects the first comment)
	 * - /Comment[text=/cakephp/i] (Selects the all comments that have a text matching the regex /cakephp/i)
	 * - /Comment/@* (Selects the all key names of all comments)
	 *
	 * #### Other limitations:
	 *
	 * - Only absolute paths starting with a single '/' are supported right now
	 *
	 * **Warning**: Even so it has plenty of unit tests the XPath support has not gone through a lot of
	 * real-world testing. Please report Bugs as you find them. Suggestions for additional features to
	 * implement are also very welcome!
	 *
	 * @param string $path An absolute XPath 2.0 path
	 * @param array $data An array of data to extract from
	 * @param array $options Currently only supports 'flatten' which can be disabled for higher XPath-ness
	 * @return array An array of matched items
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::extract
	 */
    public static function extract($path, $data = null, $options = []) {
        if (is_string($data)) {
            $tmp = $data;
            $data = $path;
            $path = $tmp;
        }
        if (strpos($path, '/') === false) {
            return self::classicExtract($data, $path);
        }
        if (empty($data)) {
            return [];
        }
        if ($path === '/') {
            return $data;
        }
        $contexts = $data;
        $options = array_merge(array('flatten' => true), $options);
        if (!isset($contexts[0])) {
            $current = current($data);
            if ((is_array($current) && count($data) < 1) || !is_array($current) || !self::numeric(array_keys($data))) {
                $contexts = array($data);
            }
        }
        $tokens = array_slice(preg_split('/(?<!=|\\\\)\/(?![a-z-\s]*\])/', $path), 1);

        do {
            $token = array_shift($tokens);
            $conditions = false;
            if (preg_match_all('/\[([^=]+=\/[^\/]+\/|[^\]]+)\]/', $token, $m)) {
                $conditions = $m[1];
                $token = substr($token, 0, strpos($token, '['));
            }
            $matches = [];
            foreach ($contexts as $key => $context) {
                if (!isset($context['trace'])) {
                    $context = array('trace' => array(null), 'item' => $context, 'key' => $key);
                }
                if ($token === '..') {
                    if (count($context['trace']) == 1) {
                        $context['trace'][] = $context['key'];
                    }
                    $parent = implode('/', $context['trace']) . '/.';
                    $context['item'] = self::extract($parent, $data);
                    $context['key'] = array_pop($context['trace']);
                    if (isset($context['trace'][1]) && $context['trace'][1] > 0) {
                        $context['item'] = $context['item'][0];
                    } elseif (!empty($context['item'][$key])) {
                        $context['item'] = $context['item'][$key];
                    } else {
                        $context['item'] = array_shift($context['item']);
                    }
                    $matches[] = $context;
                    continue;
                }
                if ($token === '@*' && is_array($context['item'])) {
                    $matches[] = array(
                        'trace' => array_merge($context['trace'], (array)$key),
                        'key' => $key,
                        'item' => array_keys($context['item']),
                    );
                } elseif (is_array($context['item'])
                    && array_key_exists($token, $context['item'])
                    && !(strval($key) === strval($token) && count($tokens) == 1 && $tokens[0] === '.')) {
                    $items = $context['item'][$token];
                    if (!is_array($items)) {
                        $items = array($items);
                    } elseif (!isset($items[0])) {
                        $current = current($items);
                        $currentKey = key($items);
                        if (!is_array($current) || (is_array($current) && count($items) <= 1 && !is_numeric($currentKey))) {
                            $items = array($items);
                        }
                    }

                    foreach ($items as $key => $item) {
                        $ctext = array($context['key']);
                        if (!is_numeric($key)) {
                            $ctext[] = $token;
                            $tok = array_shift($tokens);
                            if (isset($items[$tok])) {
                                $ctext[] = $tok;
                                $item = $items[$tok];
                                $matches[] = array(
                                    'trace' => array_merge($context['trace'], $ctext),
                                    'key' => $tok,
                                    'item' => $item,
                                );
                                break;
                            } elseif ($tok !== null) {
                                array_unshift($tokens, $tok);
                            }
                        } else {
                            $key = $token;
                        }

                        $matches[] = array(
                            'trace' => array_merge($context['trace'], $ctext),
                            'key' => $key,
                            'item' => $item,
                        );
                    }
                } elseif ($key === $token || (ctype_digit($token) && $key == $token) || $token === '.') {
                    $context['trace'][] = $key;
                    $matches[] = array(
                        'trace' => $context['trace'],
                        'key' => $key,
                        'item' => $context['item'],
                    );
                }
            }
            if ($conditions) {
                foreach ($conditions as $condition) {
                    $filtered = [];
                    $length = count($matches);
                    foreach ($matches as $i => $match) {
                        if (self::matches(array($condition), $match['item'], $i + 1, $length)) {
                            $filtered[$i] = $match;
                        }
                    }
                    $matches = $filtered;
                }
            }
            $contexts = $matches;

            if (empty($tokens)) {
                break;
            }
        } while(1);

        $r = [];

        foreach ($matches as $match) {
            if ((!$options['flatten'] || is_array($match['item'])) && !is_int($match['key'])) {
                $r[] = array($match['key'] => $match['item']);
            } else {
                $r[] = $match['item'];
            }
        }
        return $r;
    }

	/**
	 * This function can be used to see if a single item or a given xpath match certain conditions.
	 *
	 * @param mixed $conditions An array of condition strings or an XPath expression
	 * @param array $data  An array of data to execute the match on
	 * @param int $i Optional: The 'nth'-number of the item being matched.
	 * @param int $length
	 * @return bool
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::matches
	 */
    public static function matches($conditions, $data = [], $i = null, $length = null) {
        if (empty($conditions)) {
            return true;
        }
        if (is_string($conditions)) {
            return !!self::extract($conditions, $data);
        }
        foreach ($conditions as $condition) {
            if ($condition === ':last') {
                if ($i != $length) {
                    return false;
                }
                continue;
            } elseif ($condition === ':first') {
                if ($i != 1) {
                    return false;
                }
                continue;
            }
            if (!preg_match('/(.+?)([><!]?[=]|[><])(.*)/', $condition, $match)) {
                if (ctype_digit($condition)) {
                    if ($i != $condition) {
                        return false;
                    }
                } elseif (preg_match_all('/(?:^[0-9]+|(?<=,)[0-9]+)/', $condition, $matches)) {
                    return in_array($i, $matches[0]);
                } elseif (!array_key_exists($condition, $data)) {
                    return false;
                }
                continue;
            }
            list(, $key, $op, $expected) = $match;
            if (!isset($data[$key])) {
                return false;
            }

            $val = $data[$key];

            if ($op === '=' && $expected && $expected[0] === '/') {
                return preg_match($expected, $val);
            }
            if ($op === '=' && $val != $expected) {
                return false;
            }
            if ($op === '!=' && $val == $expected) {
                return false;
            }
            if ($op === '>' && $val <= $expected) {
                return false;
            }
            if ($op === '<' && $val >= $expected) {
                return false;
            }
            if ($op === '<=' && $val > $expected) {
                return false;
            }
            if ($op === '>=' && $val < $expected) {
                return false;
            }
        }
        return true;
    }

	/**
	 * Gets a value from an array or object that is contained in a given path using an array path syntax, i.e.:
	 * "{n}.Person.{[a-z]+}" - Where "{n}" represents a numeric key, "Person" represents a string literal,
	 * and "{[a-z]+}" (i.e. any string literal enclosed in brackets besides {n} and {s}) is interpreted as
	 * a regular expression.
	 *
	 * @param array $data Array from where to extract
	 * @param mixed $path As an array, or as a dot-separated string.
	 * @return array Extracted data
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::classicExtract
	 */
    public static function classicExtract($data, $path = null) {
        if (empty($path)) {
            return $data;
        }
        if (is_object($data)) {
            if (!($data instanceof ArrayAccess || $data instanceof Traversable)) {
                $data = get_object_vars($data);
            }
        }
        if (empty($data)) {
            return null;
        }
        if (is_string($path) && strpos($path, '{') !== false) {
            $path = String::tokenize($path, '.', '{', '}');
        } elseif (is_string($path)) {
            $path = explode('.', $path);
        }
        $tmp = [];

        if (empty($path)) {
            return null;
        }

        foreach ($path as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                if (isset($data[intval($key)])) {
                    $data = $data[intval($key)];
                } else {
                    return null;
                }
            } elseif ($key === '{n}') {
                foreach ($data as $j => $val) {
                    if (is_int($j)) {
                        $tmpPath = array_slice($path, $i + 1);
                        if (empty($tmpPath)) {
                            $tmp[] = $val;
                        } else {
                            $tmp[] = self::classicExtract($val, $tmpPath);
                        }
                    }
                }
                return $tmp;
            } elseif ($key === '{s}') {
                foreach ($data as $j => $val) {
                    if (is_string($j)) {
                        $tmpPath = array_slice($path, $i + 1);
                        if (empty($tmpPath)) {
                            $tmp[] = $val;
                        } else {
                            $tmp[] = self::classicExtract($val, $tmpPath);
                        }
                    }
                }
                return $tmp;
            } elseif (false !== strpos($key, '{') && false !== strpos($key, '}')) {
                $pattern = substr($key, 1, -1);

                foreach ($data as $j => $val) {
                    if (preg_match('/^' . $pattern . '/s', $j) !== 0) {
                        $tmpPath = array_slice($path, $i + 1);
                        if (empty($tmpPath)) {
                            $tmp[$j] = $val;
                        } else {
                            $tmp[$j] = self::classicExtract($val, $tmpPath);
                        }
                    }
                }
                return $tmp;
            } else {
                if (isset($data[$key])) {
                    $data = $data[$key];
                } else {
                    return null;
                }
            }
        }
        return $data;
    }

	/**
	 * Inserts $data into an array as defined by $path.
	 *
	 * @param mixed $list Where to insert into
	 * @param mixed $path A dot-separated string.
	 * @param array $data Data to insert
	 * @return array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::insert
	 */
    public static function insert($list, $path, $data = null) {
        if (!is_array($path)) {
            $path = explode('.', $path);
        }
        $_list =& $list;

        $count = count($path);
        foreach ($path as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                $key = intval($key);
            }
            if ($i === $count - 1 && is_array($_list)) {
                $_list[$key] = $data;
            } else {
                if (!isset($_list[$key])) {
                    $_list[$key] = [];
                }
                $_list =& $_list[$key];
            }
            if (!is_array($_list)) {
                return [];
            }
        }
        return $list;
    }

	/**
	 * Removes an element from a Set or array as defined by $path.
	 *
	 * @param mixed $list From where to remove
	 * @param mixed $path A dot-separated string.
	 * @return array Array with $path removed from its value
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::remove
	 */
    public static function remove($list, $path = null) {
        if (empty($path)) {
            return $list;
        }
        if (!is_array($path)) {
            $path = explode('.', $path);
        }
        $_list =& $list;

        foreach ($path as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                $key = intval($key);
            }
            if ($i === count($path) - 1) {
                unset($_list[$key]);
            } else {
                if (!isset($_list[$key])) {
                    return $list;
                }
                $_list =& $_list[$key];
            }
        }
        return $list;
    }

	/**
	 * Checks if a particular path is set in an array
	 *
	 * @param mixed $data Data to check on
	 * @param mixed $path A dot-separated string.
	 * @return bool true if path is found, false otherwise
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::check
	 */
    public static function check($data, $path = null) {
        if (empty($path)) {
            return $data;
        }
        if (!is_array($path)) {
            $path = explode('.', $path);
        }

        foreach ($path as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                $key = intval($key);
            }
            if ($i === count($path) - 1) {
                return (is_array($data) && array_key_exists($key, $data));
            }

            if (!is_array($data) || !array_key_exists($key, $data)) {
                return false;
            }
            $data =& $data[$key];
        }
        return true;
    }

	/**
	 * Computes the difference between a Set and an array, two Sets, or two arrays
	 *
	 * @param mixed $val1 First value
	 * @param mixed $val2 Second value
	 * @return array Returns the key => value pairs that are not common in $val1 and $val2
	 * The expression for this function is ($val1 - $val2) + ($val2 - ($val1 - $val2))
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::diff
	 */
    public static function diff($val1, $val2 = null) {
        if (empty($val1)) {
            return (array)$val2;
        }
        if (empty($val2)) {
            return (array)$val1;
        }
        $intersection = array_intersect_key($val1, $val2);
        while (($key = key($intersection)) !== null) {
            if ($val1[$key] == $val2[$key]) {
                unset($val1[$key]);
                unset($val2[$key]);
            }
            next($intersection);
        }

        return $val1 + $val2;
    }

	/**
	 * Determines if one Set or array contains the exact keys and values of another.
	 *
	 * @param array $val1 First value
	 * @param array $val2 Second value
	 * @return bool true if $val1 contains $val2, false otherwise
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::contains
	 */
    public static function contains($val1, $val2 = null) {
        if (empty($val1) || empty($val2)) {
            return false;
        }

        foreach ($val2 as $key => $val) {
            if (is_numeric($key)) {
                self::contains($val, $val1);
            } else {
                if (!isset($val1[$key]) || $val1[$key] != $val) {
                    return false;
                }
            }
        }
        return true;
    }

	/**
	 * Counts the dimensions of an array. If $all is set to false (which is the default) it will
	 * only consider the dimension of the first element in the array.
	 *
	 * @param array $array Array to count dimensions on
	 * @param bool $all Set to true to count the dimension considering all elements in array
	 * @param int $count Start the dimension count at this number
	 * @return int The number of dimensions in $array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::countDim
	 */
    public static function countDim($array = null, $all = false, $count = 0) {
        if ($all) {
            $depth = array($count);
            if (is_array($array) && reset($array) !== false) {
                foreach ($array as $value) {
                    $depth[] = self::countDim($value, true, $count + 1);
                }
            }
            $return = max($depth);
        } else {
            if (is_array(reset($array))) {
                $return = self::countDim(reset($array)) + 1;
            } else {
                $return = 1;
            }
        }
        return $return;
    }

	/**
	 * Normalizes a string or array list.
	 *
	 * @param mixed $list List to normalize
	 * @param bool $assoc If true, $list will be converted to an associative array
	 * @param string $sep If $list is a string, it will be split into an array with $sep
	 * @param bool $trim If true, separated strings will be trimmed
	 * @return array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::normalize
	 */
    public static function normalize($list, $assoc = true, $sep = ',', $trim = true) {
        if (is_string($list)) {
            $list = explode($sep, $list);
            if ($trim) {
                foreach ($list as $key => $value) {
                    $list[$key] = trim($value);
                }
            }
            if ($assoc) {
                return self::normalize($list);
            }
        } elseif (is_array($list)) {
            $keys = array_keys($list);
            $count = count($keys);
            $numeric = true;

            if (!$assoc) {
                for ($i = 0; $i < $count; $i++) {
                    if (!is_int($keys[$i])) {
                        $numeric = false;
                        break;
                    }
                }
            }
            if (!$numeric || $assoc) {
                $newList = [];
                for ($i = 0; $i < $count; $i++) {
                    if (is_int($keys[$i])) {
                        $newList[$list[$keys[$i]]] = null;
                    } else {
                        $newList[$keys[$i]] = $list[$keys[$i]];
                    }
                }
                $list = $newList;
            }
        }
        return $list;
    }

	/**
	 * Creates an associative array using a $path1 as the path to build its keys, and optionally
	 * $path2 as path to get the values. If $path2 is not specified, all values will be initialized
	 * to null (useful for Utility_Set::merge). You can optionally group the values by what is obtained when
	 * following the path specified in $groupPath.
	 *
	 * @param mixed $data Array or object from where to extract keys and values
	 * @param mixed $path1 As an array, or as a dot-separated string.
	 * @param mixed $path2 As an array, or as a dot-separated string.
	 * @param string $groupPath As an array, or as a dot-separated string.
	 * @return array Combined array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::combine
	 */
    public static function combine($data, $path1 = null, $path2 = null, $groupPath = null) {
        if (empty($data)) {
            return [];
        }

        if (is_object($data)) {
            if (!($data instanceof ArrayAccess || $data instanceof Traversable)) {
                $data = get_object_vars($data);
            }
        }

        if (is_array($path1)) {
            $format = array_shift($path1);
            $keys = self::format($data, $format, $path1);
        } else {
            $keys = self::extract($data, $path1);
        }
        if (empty($keys)) {
            return [];
        }

        if (!empty($path2) && is_array($path2)) {
            $format = array_shift($path2);
            $vals = self::format($data, $format, $path2);
        } elseif (!empty($path2)) {
            $vals = self::extract($data, $path2);
        } else {
            $count = count($keys);
            for ($i = 0; $i < $count; $i++) {
                $vals[$i] = null;
            }
        }

        if ($groupPath != null) {
            $group = self::extract($data, $groupPath);
            if (!empty($group)) {
                $c = count($keys);
                for ($i = 0; $i < $c; $i++) {
                    if (!isset($group[$i])) {
                        $group[$i] = 0;
                    }
                    if (!isset($out[$group[$i]])) {
                        $out[$group[$i]] = [];
                    }
                    $out[$group[$i]][$keys[$i]] = $vals[$i];
                }
                return $out;
            }
        }
        if (empty($vals)) {
            return [];
        }
        return array_combine($keys, $vals);
    }

	/**
	 * Converts an object into an array.
	 * @param object $object Object to reverse
	 * @return array Array representation of given object
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::reverse
	 */
    public static function reverse($object) {
        $out = [];
        if ($object instanceof SimpleXMLElement) {
            return Xml::toArray($object);
        } else if (is_object($object)) {
            $keys = get_object_vars($object);
            if (isset($keys['_name_'])) {
                $identity = $keys['_name_'];
                unset($keys['_name_']);
            }
            $new = [];
            foreach ($keys as $key => $value) {
                if (is_array($value)) {
                    $new[$key] = (array)self::reverse($value);
                } else {
                    if (isset($value->_name_)) {
                        $new = array_merge($new, self::reverse($value));
                    } else {
                        $new[$key] = self::reverse($value);
                    }
                }
            }
            if (isset($identity)) {
                $out[$identity] = $new;
            } else {
                $out = $new;
            }
        } elseif (is_array($object)) {
            foreach ($object as $key => $value) {
                $out[$key] = self::reverse($value);
            }
        } else {
            $out = $object;
        }
        return $out;
    }

	/**
	 * Collapses a multi-dimensional array into a single dimension, using a delimited array path for
	 * each array element's key, i.e. array(array('Foo' => array('Bar' => 'Far'))) becomes
	 * array('0.Foo.Bar' => 'Far').
	 *
	 * @param array $data Array to flatten
	 * @param string $separator String used to separate array key elements in a path, defaults to '.'
	 * @return array
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::flatten
	 */
    public static function flatten($data, $separator = '.') {
        $result = [];
        $path = null;

        if (is_array($separator)) {
            extract($separator, EXTR_OVERWRITE);
        }

        if (!is_null($path)) {
            $path .= $separator;
        }

        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $result += (array)self::flatten($val, array(
                    'separator' => $separator,
                    'path' => $path . $key
                ));
            } else {
                $result[$path . $key] = $val;
            }
        }
        return $result;
    }

	/**
	 * Flattens an array for sorting
	 *
	 * @param array $results
	 * @param string $key
	 * @return array
	 */
    protected static function _flatten($results, $key = null) {
        $stack = [];
        foreach ($results as $k => $r) {
            $id = $k;
            if (!is_null($key)) {
                $id = $key;
            }
            if (is_array($r) && !empty($r)) {
                $stack = array_merge($stack, self::_flatten($r, $id));
            } else {
                $stack[] = array('id' => $id, 'value' => $r);
            }
        }
        return $stack;
    }

	/**
	 * Sorts an array by any value, determined by a Set-compatible path
	 *
	 * @param array $data An array of data to sort
	 * @param string $path A Set-compatible path to the array value
	 * @param string $dir Direction of sorting - either ascending (ASC), or descending (DESC)
	 * @return array Sorted array of data
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::sort
	 */
    public static function sort($data, $path, $dir) {
        $originalKeys = array_keys($data);
        if (is_numeric(implode('', $originalKeys))) {
            $data = array_values($data);
        }
        $result = self::_flatten(self::extract($data, $path));
        list($keys, $values) = array(self::extract($result, '{n}.id'), self::extract($result, '{n}.value'));

        $dir = strtolower($dir);
        if ($dir === 'asc') {
            $dir = SORT_ASC;
        } elseif ($dir === 'desc') {
            $dir = SORT_DESC;
        }
        array_multisort($values, $dir, $keys, $dir);
        $sorted = [];
        $keys = array_unique($keys);

        foreach ($keys as $k) {
            $sorted[] = $data[$k];
        }
        return $sorted;
    }

	/**
	 * Allows the application of a callback method to elements of an
	 * array extracted by a Utility_Set::extract() compatible path.
	 *
	 * @param mixed $path Set-compatible path to the array value
	 * @param array $data An array of data to extract from & then process with the $callback.
	 * @param mixed $callback Callback method to be applied to extracted data.
	 * See http://ca2.php.net/manual/en/language.pseudo-types.php#language.types.callback for examples
	 * of callback formats.
	 * @param array $options Options are:
	 *                       - type : can be pass, map, or reduce. Map will handoff the given callback
	 *                                to array_map, reduce will handoff to array_reduce, and pass will
	 *                                use call_user_func_array().
	 * @return mixed Result of the callback when applied to extracted data
	 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::apply
	 */
    public static function apply($path, $data, $callback, $options = []) {
        $defaults = array('type' => 'pass');
        $options = array_merge($defaults, $options);
        $extracted = self::extract($path, $data);

        if ($options['type'] === 'map') {
            return array_map($callback, $extracted);
        } elseif ($options['type'] === 'reduce') {
            return array_reduce($extracted, $callback);
        } elseif ($options['type'] === 'pass') {
            return call_user_func_array($callback, array($extracted));
        }
        return null;
    }
}
