<?php
//class supports UTF-8 encodings and requires MultiByte String support
if (!defined('ENCODING')) define('ENCODING','UTF-8');

//because of lack of support for UTF-8 in str_word_count function additional one is defined here
define("WORD_COUNT_MASK", "/\p{L}[\p{L}\p{Mn}\p{Pd}'\x{2019}]*/u");
function str_word_count_utf8($string, $format = 0) {
		switch ($format) {
		case 1:
				preg_match_all(WORD_COUNT_MASK, $string, $matches);
				return $matches[0];
		case 2:
				preg_match_all(WORD_COUNT_MASK, $string, $matches, PREG_OFFSET_CAPTURE);
				$result = array();
				foreach ($matches[0] as $match) {
						$result[$match[1]] = $match[0];
				}
				return $result;
		}
		return preg_match_all(WORD_COUNT_MASK, $string, $matches);
}

/**
 * AddressParser class
 * provides params and methods for parsing textual address representation into structure of
 * STREET, CITY and POSTAL_CODE
 */
class AddressParser {
	public static $postal_code_masks = array( //order is important - higher priority - first
		'/(\d\d-\d\d\d)/', //according to Polish standard only
		'/(\d{5,11})/',//typical postal codes (5 to 11 digits)
		//'/([A-Z]{1,3}\d{5,11})/',//alphanumerical postal codes (1-3 Capital Letters directly followed by 5-9 digits)
	);
	private static $important_separator_chars = "|\n\r\t\0\x0B,:;";
	private static $trim_additional_chars = " '\"";
	private static $trim_chars = null;
	private static $important_separator_array = null;
	
	//function which have to be called after class definition
	public static function init() {
		self::$trim_chars = self::$important_separator_chars.self::$trim_additional_chars;
		self::$important_separator_array = str_split(self::$important_separator_chars);
	}
	
	static function setImportantSeparatorChars($value) {
		self::$important_separator_chars = $value;
		self::$trim_chars = self::$important_separator_chars.self::$trim_additional_chars;
		self::$important_separator_array = str_split(self::$important_separator_chars);
	}
	static function getImportantSeparatorChars() {
		return self::$important_separator_chars;
	}

	static function setTrimAdditionalChars($value) {
		self::$trim_additional_chars = $value;
		self::$trim_chars = self::$important_separator_chars.self::$trim_additional_chars;
	}
	static function getTrimAdditionalChars() {
		return self::$trim_additional_chars;
	}

	public static $street_length = 35;
	public static $city_length = 28;
	
	public static $street_prefixes = array( //street prefixes with priorities
		//Polish
		'ul'=>5,
		'ulica'=>6,
		'al'=>3,
		'aleja'=>6,
		'aleje'=>6,
		'oś'=>5,
		'os'=>5,
		'osiedle'=>5,
		'pl'=>3,
		'plac'=>6,
		'rnd'=>3,
		'rondo'=>3,
		'rynek'=>5,
		'bulwar'=>4,
		'skwer'=>2,
		//International
		'rd'=>1,
		'road'=>1,
		'st'=>1,
		'street'=>1,
		'ave'=>1,
		'avenue'=>1,
		'blvd'=>1,
		'boulevard'=>1,
		'ar'=>1,
		'park'=>1,
	);

	
	/*
	*/
	private static function streetcity_split($txt) {
		//echo 'streetcity_split('.$txt.") started\n";
		$result = array();
		$notnice = false;
		$maxlength = (self::$street_length>self::$city_length) ? self::$street_length : self::$city_length;
		//echo 'DEBUG: maxlength ='.$maxlength."\n";
		$maxtoken = substr($txt, 0, $maxlength);
		//echo 'DEBUG: maxtoken =['.$maxtoken."]\n";
		$last_separator = 0;
		//important separators
		foreach (self::$important_separator_array as $separator) {
			$pos = strrpos($maxtoken, $separator, $last_separator);
			if ($pos>$last_separator) $last_separator = $pos;
			//echo 'DEBUG: separator = '.$separator."\n pos=".$pos."\nlast_separator =".$last_separator."\n";
		}
		//numbers as separators
		if ($last_separator==0) {
			for ($i=0; $i<10; $i++) {
				$pos = strrpos($maxtoken, (string)$i, $last_separator);
				if ($pos>$last_separator) $last_separator = $pos;
				//echo 'DEBUG: separator = '.$i."\n pos=".$pos."\nlast_separator =".$last_separator."\n";
			}
			if ($last_separator>0) $last_separator++;
		}
		//roma numbers as separators
		if ($last_separator==0) {
			$words = str_word_count_utf8(mb_strtolower($maxtoken,ENCODING), 2);
			$value = 0;
			foreach($words as $key=>$word) {
				if (self::is_roma_number($word)) {
					$last_separator = $key + strlen($word);
				}
			}
		}
		//space as separator
		if ($last_separator==0) $last_separator = strrpos($maxtoken, ' ', 0);
		//echo 'DEBUG: last_separator (after space) ='.$last_separator."\n";
		if ($last_separator>0) {
		//echo 'DEBUG: last_separator>0';
			$tokens[]=substr($txt, 0, $last_separator);
			$tokens[]=substr($txt, $last_separator);
		} else {//it is impossible to split in nice way so we need to remember this during crop
			$notnice = true;
			$tokens[]=$maxtoken;
			$tokens[]=substr($txt, -$maxlength);
		}
		//echo 'DEBUG: notnice = '.($notnice?'true':'false')."\n";
		//echo "DEBUG: tokens:\n";
		//print_r($tokens);
		//echo "\n";

		$first_street = self::is_street($tokens[0]);
		$second_street = self::is_street($tokens[1]);
		if ($first_street<0) {
			$tokens[0] = substr($txt,0,-$first_street);
			$tokens[1] = substr($txt,-$first_street);
			$second_street = 0;
			//echo "DEBUG: tokens:\n";
			//print_r($tokens);
			//echo "\n";
		}
		if ($first_street >= $second_street) {
			$result['STREET'] = $tokens[0];
			$result['CITY'] = $tokens[1];
			$result['CROPPED_STREET'] = self::crop_street($result['STREET']);
			$result['CROPPED_CITY'] = self::crop_city($result['CITY'], $notnice);
		} else {
			$result['CITY'] = $tokens[0];
			$result['STREET'] = $tokens[1];
			$result['CROPPED_CITY'] = self::crop_city($result['CITY']);
			$result['CROPPED_STREET'] = self::crop_street($result['STREET'],$notnice);
		}

		return $result;
	}

	private static $roma_chars = array('M','D','C','L','X','V','I','m','d','c','l','x','v','i');
	/**
	 * Utility function to verify if string is valid roma number
	 * @param txt - string to check
	 * @result boolean
	 */
	public static function is_roma_number($txt) {
		if (strlen($txt)>6) return false;
		for($i = 0; $i<strlen($txt);  $i++) {
			if (!in_array($txt[$i],self::$roma_chars)) return false;
		}
		return true;
	}

	/**
	 * Utility function to check how much string looks like street part of address
	 *
	 * @param txt string to check
	 * @result number higher value if string is closer to street patterns
	 */
	private static function is_street($txt) {
		$is_street_prefix = false;
		if (strcspn($txt,'0123456789')<strlen($txt)) {//if ends with number it is probably street
			//echo 'DEBUG: is_street("'.$txt."\") = 1000\n";
			return 1000;
		}
		$txt = mb_strtolower($txt,ENCODING);
		//look for street prefix
		$words = str_word_count_utf8($txt,2);
		//echo "DEBUG: words:\n";print_r($words); echo "\n";
		$value = 0;
		foreach ($words as $key=>$word) {
			@$v = self::$street_prefixes[$word];
			if ($v != 0) {
				if ($is_street_prefix===false) {
					$is_street_prefix = $key;
					//echo 'DEBUG: is_street('.$txt.') street_prefix - word:'.$word.' +'.$v."\n";
				}
			}
			if (($v==0) && (self::is_roma_number($word))) $v = 2;
			$value += $v;
			//if ($v!=0) echo 'DEBUG: is_street('.$txt.') - word:'.$word.' +'.$v."\n";
		}
		if ($is_street_prefix!==false) {
			$after_prefix = trim(substr($txt, $is_street_prefix+strlen($words[$is_street_prefix])),self::$trim_chars.'.');
			//echo 'DEBUG: after_prefix: ['.$after_prefix."]\n";
			if (empty($after_prefix)) $value = -$is_street_prefix;
		}
		//echo 'DEBUG: is_street("'.$txt.'") = '.$value."\n";
		return $value;
	}

	/*
	 * Utility function to crop text according to street pattern length specification
	 *
	 * @param txt - string to be cropped
	 * @keep_end - false (default) if keep left part as much as possible, true to keep right part of string as much as possible
	 */
	private static function crop_street($txt,$keep_end=false) {
		if ($keep_end) {
			$txt = rtrim($txt,self::$trim_chars);
			$txt = substr($txt,-self::$street_length);
			$txt = ltrim($txt,self::$trim_chars);
		} else {
			$txt = ltrim($txt,self::$trim_chars);
			$txt = substr($txt,0,self::$street_length);
			$txt = rtrim($txt,self::$trim_chars);
		}
		return $txt;
	}

	/*
	 * Utility function to crop text according to city pattern length specification
	 *
	 * @param txt - string to be cropped
	 * @keep_end - false (default) if keep left part as much as possible, true to keep right part of string as much as possible
	 */
	private static function crop_city($txt,$keep_end=false) {
		if ($keep_end) {
			$txt = rtrim($txt,self::$trim_chars);
			$txt = substr($txt,-self::$city_length);
			$txt = ltrim($txt,self::$trim_chars);
		} else {
			$txt = ltrim($txt,self::$trim_chars);
			$txt = substr($txt,0,self::$city_length);
			$txt = rtrim($txt,self::$trim_chars);
		}
		return $txt;
	}

	/**
	 * Parse Address
	 * 
	 * @param txt - text to parse
	 * @result array containing structured address if parsing was successfull togather with ORIGINAL value or partial structure in case of problems
	 */
	public static function parseAddress($txt) {
		$result = array();
		$result['ORIGINAL'] = $txt;
		//split by postal code
		foreach (self::$postal_code_masks as $postal_code_mask) {
			$tmp = preg_split($postal_code_mask, $txt, 3, PREG_SPLIT_DELIM_CAPTURE);
			if (count($tmp)>=3) break;
		}
		$result['TMP'] = $tmp;
		if (count($tmp)<3) { //postal code not found
			$result['POSTAL_CODE'] = '';
			//trim source text
			$txt = trim($txt,self::$trim_chars);
			//split $txt into street and city part
			$result = array_merge($result,self::streetcity_split($txt));
		} else {//postal code found
			$result['POSTAL_CODE']=$tmp[1];//if found, allways in the middle
			$before = trim($tmp[0],self::$trim_chars);
			$after = trim($tmp[2],self::$trim_chars);
			if (empty($before)) {//postal code at the beginning
				//split $txt into street and city part
				$result = array_merge($result,self::streetcity_split($after));
			} else if (empty($after)) {//postal code at the end
				//split $txt into street and city part
				$result = array_merge($result,self::streetcity_split($before));
			} else {//postal code in the middle
				if (self::is_street($before)>=self::is_street($after)) {//check if there is number before
					$result['STREET'] = $before;
					$result['CITY'] = $after;
				} else {//check if there is number after
					$result['CITY'] = $before;
					$result['STREET'] = $after;
				}
				$result['CROPPED_STREET'] = self::crop_street($result['STREET']);
				$result['CROPPED_CITY'] = self::crop_city($result['CITY']);
			}
		}
		return $result;
	}

}
AddressParser::init();
?>