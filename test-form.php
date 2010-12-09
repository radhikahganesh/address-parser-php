<?php
//if (!defined('ENCODING')) define('ENCODING','UTF-8');
require_once('address-parser.php');
header('Content-Type: text/html; charset='.ENCODING);

//supporting form functions
function safeReadNum($post_name,$default,$min,$max) {
	$res = $default;
	if (isset($_POST[$post_name]) && is_numeric($_POST[$post_name])) $res = $_POST[$post_name];
	if (empty($res) || ($res<$min)) $res = $min;
	if ($res>$max) $res=$max;
	return $res;
}
function safetd($txt) {
	$txt = htmlspecialchars($txt);
	if (empty($txt)) $txt = '&nbsp;';
	return $txt;
}

//reading parameters
$addresses = '';
if (isset($_POST['addr'])) $addresses = $_POST['addr'];
AddressParser::$street_length = safeReadNum('street_length',AddressParser::$street_length,1,100);
AddressParser::$city_length = safeReadNum('city_length',AddressParser::$city_length,1,100);
?><html>
<head>
	<title>Address parser - test form</title>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo ENCODING; ?>" />
	<meta name="robots" content="noindex,nofollow" />
</head>
<body>

	<h1>Address parser</h1>

	<form action="?" method="post">
		Input adresses - each one in one line:<br/>
		<textarea name="addr" cols="150" rows="15"><?php
if (!empty($addresses)) {
	echo htmlspecialchars($addresses);
} else {
?>ul. Pelplińska 3a/43, 51-128 Grudziąc
Chłopska 15 80000 Gdańsk
83-300 Gdynia, Kielecka 2
nie pamiętam adresu
ul. Morska 23, Gdynia
GDAŃSK, Al. Niepodległości
Gdynia, Władysława IV
12345 Władysława IV Gdynia
Gdynia Władysława IV
12345 Gdynia Władysława 45
ul. Spokojna Świnoujście
Świnoujście ul. Spokojna
Prądzyńskiego 100 Łomża
Kanarkowa 10 Augustów
Plac Niebiańskiego Spokoju 121 Pekin
Rondo Solidarności 11 Warszawa
<?php
}
?></textarea><br />
		Street length: <input type="input" name="street_length" value="<?php echo AddressParser::$street_length;?>"><br />
		City length: <input type="input" name="city_length" value="<?php echo AddressParser::$city_length;?>"><br />
		<input type="hidden" name="action" value="PARSE" />
		<input type="submit" value="Parse" />
	</form>
	<br />
<?php
	if (isset($_POST['action']) && ($_POST['action']=='PARSE') //if form was submitted
			&& !empty($addresses)) { //and there is something to parse ;-)
		echo '<table border="1" cellspacing="0" cellpadding="3" width="100%">'.
					'<tr><th>ID</th><th>STREET</th><th>POSTAL CODE</th><th>CITY</th><th width="200">Original text</th></tr>'."\n";
		$lines = explode("\n",$addresses);
		$i=0;
		foreach ($lines as $line) {
			$i++;
			if (empty($line)||(trim($line)=='')) continue;
			$parsed = AddressParser::parseAddress(rtrim($line,"\r\n"));
			echo '<tr><td>'.$i.'&nbsp;</td><td>'.
				safetd($parsed['CROPPED_STREET']).'</td><td>'.
				safetd($parsed['POSTAL_CODE']).'</td><td>'.
				safetd($parsed['CROPPED_CITY']).'</td><td>'.
				safetd(rtrim($line,"\r\n")).
				"</td></tr>\n";
		}
		echo "</table>\n";
	}
?>
	<br />
	<hr />
	2009(c) by Seba ;-)
</body>
</html>