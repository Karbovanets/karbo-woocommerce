<?php
/*
Karbo for WooCommerce
https://github.com/Karbovanets/karbo-woocommerce/
*/


//===========================================================================
function KRBWC__generate_new_Karbo_payment_id ($krbwc_settings=false, $order_info)
{
  global $wpdb;

  $krb_payments_table_name = $wpdb->prefix . 'krbwc_krb_payments';

  if (!$krbwc_settings)
    $krbwc_settings = KRBWC__get_settings ();

  $wallet_api = New ForkNoteWalletd("http://127.0.0.1:18888");
  $new_krb_payment_id = $wallet_api->makePaymentId();

  try {
    $status = $wallet_api->getStatus();
    $next_key_index = $status["blockCount"];
  } catch(Exception $e) {
    $next_key_index = 0;
  }

  $krb_address = $krbwc_settings['address'];

  $address_request_array = array();

        // Retrieve current balance at address considering required confirmations number and api_timemout value.
  $address_request_array['krb_address'] = $krb_address;
  $address_request_array['krb_payment_id'] = $new_krb_payment_id;
  $address_request_array['block_index'] = 100000; //@TODO variable for starting block to check for payment id
  $address_request_array['required_confirmations'] = 0;
  $address_request_array['api_timeout'] = $krbwc_settings['blockchain_api_timeout_secs'];
  $ret_info_array = KRBWC__getreceivedbyaddress_info ($address_request_array, $krbwc_settings);
  // $total_new_keys_generated ++;

  if ($ret_info_array['balance'] === false)
    $status = 'unknown';
  else if ($ret_info_array['balance'] == 0)
    $status = 'assigned'; // Newly generated address with freshly checked zero balance is unused and will be assigned.
  else
    $status = 'used';   // Generated address that was already used to receive money.

  $funds_received                  = ($ret_info_array['balance'] === false)?0:$ret_info_array['balance'];
  $received_funds_checked_at_time  = ($ret_info_array['balance'] === false)?0:time();
  $assigned_at_time  = ($ret_info_array['balance'] === false)?0:time();

  // Prepare `address_meta` field for this clean address and payment_id.
  $address_meta['orders'] = array();
  array_unshift ($address_meta['orders'], $order_info);    // Prepend new order to array of orders
  $address_meta_serialized = KRBWC_serialize_address_meta ($address_meta);
  //$remote_addr  = $order_info['requested_by_ip'];

  // Insert newly generated address into DB
  $query = "INSERT INTO `$krb_payments_table_name` (`krb_address`, `krb_payment_id`, `origin_id`, `index_in_wallet`, `status`, `total_received_funds`, `received_funds_checked_at`, `assigned_at`, `address_meta`) VALUES ('$krb_address', '$new_krb_payment_id', 'none', '$next_key_index', '$status', '$funds_received', '$received_funds_checked_at_time', '$assigned_at_time', '$address_meta_serialized');";
  $ret_code = $wpdb->query($query);

  return $new_krb_payment_id;
}
//===========================================================================

//===========================================================================
// Function makes sure that returned value is valid array
function KRBWC_unserialize_address_meta ($flat_address_meta)
{
   $unserialized = @unserialize($flat_address_meta);
   if (is_array($unserialized))
      return $unserialized;
   return array();
}
//===========================================================================

//===========================================================================
// Function makes sure that value is ready to be stored in DB
function KRBWC_serialize_address_meta ($address_meta_arr)
{
   return KRBWC__safe_string_escape(serialize($address_meta_arr));
}
//===========================================================================

//===========================================================================
/*
$address_request_array = array (
  'krb_payment_id'            => '1xxxxxxx',
  'required_confirmations' => '6',
  'api_timeout'						 => 10,
  );

$ret_info_array = array (
  'result'                      => 'success',
  'message'                     => "",
  'host_reply_raw'              => "",
  'balance'                     => false == error, else - balance
  );
*/

function KRBWC__getreceivedbyaddress_info ($address_request_array, $krbwc_settings=false)
{
	if (!$krbwc_settings)
  	$krbwc_settings = KRBWC__get_settings ();

  $krb_address            = $address_request_array['krb_address'];
	$krb_payment_id         = $address_request_array['krb_payment_id'];
  $first_block_index      = $address_request_array['block_index'];
	$required_confirmations = $address_request_array['required_confirmations'];
	$api_timeout            = $address_request_array['api_timeout'];

  $funds_received = false;
  $fnw = New ForkNoteWalletd("http://127.0.0.1:18888");
  $status = $fnw->getStatus();

  $t = $fnw->getTransactions( $status["blockCount"] - 50000, false, 50000, $krb_payment_id, [$krb_address]);
  // print_r( $t );

  $total = 0;

  foreach($t['items'] as $transaction) {
      //echo "Block: ".$transaction['blockHash'] ."<br>\n";
      $tnum = 0;
      foreach($transaction['transactions'] as $bt) {
        //echo "#: $tnum amt". $bt['amount'] . " <br>\n";
        $tnum++;
        $funds_received = $bt['amount'];
        $blockIndex = $bt['blockIndex'];
        if (is_numeric($funds_received)) {
          $funds_received = sprintf("%.12f", $funds_received / 1000000000000.0);
          $confirmations = ($status["blockCount"] - $blockIndex);
          //echo "Recieved: $funds_received in block: $blockIndex ($confirmations confirmations) " .$transaction['blockHash'] ."<br>\n";
          if ($confirmations >= $required_confirmations)
            $total += $funds_received;
        }

    }
  }
  $funds_received = $total;

  if (is_numeric($funds_received))
  {
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'balance'                     => $funds_received,
      );
  }
  else
  {
    $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Failed to get balance.\n",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
  }

  return $ret_info_array;
}
//===========================================================================

//===========================================================================
// Returns:
//    success: number of currency units (dollars, etc...) would take to convert to 1 Karbo, ex: "15.32476".
//    failure: false
//
// $currency_code, one of: USD, AUD, CAD, CHF, CNY, DKK, EUR, GBP, HKD, JPY, NZD, PLN, RUB, SEK, SGD, THB
// $rate_retrieval_method
//		'getfirst' -- pick first successfully retireved rate
//		'getall'   -- retrieve from all possible exchange rate services and then pick the best rate.
//
// $get_ticker_string - true - HTML formatted text message instead of pure number returned.

function KRBWC__get_exchange_rate_per_Karbo ($currency_code, $rate_retrieval_method = 'getfirst', $get_ticker_string=false)
{
   if ($currency_code == 'KRB')
      return "1.00";   // 1:1

	$krbwc_settings = KRBWC__get_settings ();
  $exchange_rate_type = $krbwc_settings['exchange_rate_type'];
  $exchange_multiplier = $krbwc_settings['exchange_multiplier'];
  if (!$exchange_multiplier)
    $exchange_multiplier = 1;

	$current_time  = time();
	$cache_hit     = false;
	$requested_cache_method_type = $rate_retrieval_method . '|' . $exchange_rate_type;
	$ticker_string = "<span style='color:#222;'>According to your settings (including multiplier), current calculated rate for 1 Karbo (in {$currency_code})={{{EXCHANGE_RATE}}}</span>";
	$ticker_string_error = "<span style='color:red;background-color:#FFA'>WARNING: Cannot determine exchange rates (for '$currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.</wspan>";


	$this_currency_info = @$krbwc_settings['exchange_rates'][$currency_code][$requested_cache_method_type];
	if ($this_currency_info && isset($this_currency_info['time-last-checked']))
	{
	  $delta = $current_time - $this_currency_info['time-last-checked'];
	  if ($delta < (@$krbwc_settings['cache_exchange_rates_for_minutes'] * 60))
	  {

	     // Exchange rates cache hit
	     // Use cached value as it is still fresh.
      $final_rate = $this_currency_info['exchange_rate'] / $exchange_multiplier;
			if ($get_ticker_string)
	  		return str_replace('{{{EXCHANGE_RATE}}}', $final_rate, $ticker_string);
	  	else
	  		return $final_rate;
	  }
	}

  $exchange_rate = KRBWC__get_exchange_rate_from_cryptocompare($currency_code, $exchange_rate_type, $krbwc_settings); 

  // Save new currency exchange rate info in cache
  KRBWC__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate);

	if ($get_ticker_string)
	{
		if ($exchange_rate)
    {
			return str_replace('{{{EXCHANGE_RATE}}}', $exchange_rate / $exchange_multiplier, $ticker_string);
    }
		else
		{
			$extra_error_message = "";
			$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
			$fns = array_filter ($fns, 'KRBWC__function_not_exists');

			if (count($fns))
				$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

			return str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $ticker_string_error);
		}
	}
	else
		return $exchange_rate / $exchange_multiplier;
}
//===========================================================================

//===========================================================================
function KRBWC__function_not_exists ($fname) { return !function_exists($fname); }
//===========================================================================

//===========================================================================
function KRBWC__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate)
{
  // Save new currency exchange rate info in cache
  $krbwc_settings = KRBWC__get_settings ();   // Re-get settings in case other piece updated something while we were pulling exchange rate API's...
  $krbwc_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['time-last-checked'] = time();
  $krbwc_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['exchange_rate'] = $exchange_rate;
  KRBWC__update_settings ($krbwc_settings);

}
//===========================================================================

//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function KRBWC__get_exchange_rate_from_cryptocompare ($currency_code, $rate_type, $krbwc_settings)
{
 $source_url = "https://min-api.cryptocompare.com/data/price?fsym=KRB&tsyms=" . $currency_code;
 $result = @KRBWC__file_get_contents ($source_url, false, $krbwc_settings['exchange_rate_api_timeout_secs']);

 $rate_obj = @json_decode(trim($result), true);

 // Only realtime rate is available
 return @$rate_obj[$currency_code];
}
//===========================================================================

//===========================================================================
/*
  Get web page contents with the help of PHP cURL library
   Success => content
   Error   => if ($return_content_on_error == true) $content; else FALSE;
*/
function KRBWC__file_get_contents ($url, $return_content_on_error=false, $timeout=60, $user_agent=FALSE, $is_post=false, $post_data="")
{

   if (!function_exists('curl_init'))
      {

      	if (!$is_post)
      	{
					$ret_val = @file_get_contents ($url);
					return $ret_val;
				}
				else
				{
					return false;
				}
      }

  $p       = substr(md5(microtime()), 24) . 'bw'; // curl post padding
  $ch      = curl_init   ();

	if ($is_post)
	{
		$new_post_data = $post_data;
		if (is_array($post_data))
		{
		foreach ($post_data as $k => $v)
			{
				$safetied = $v;
				if (is_object($safetied))
					$safetied = KRBWC__object_to_array($safetied);
				if (is_array($safetied))
				{
					$safetied = serialize($safetied);
					$safetied = $p . str_replace('=', '_', KRBWC__base64_encode($safetied));
					$new_post_data[$k] = $safetied;
				}
			}
		}
	}


      {
      // To accomodate older PHP 5.0.x systems
      curl_setopt ($ch, CURLOPT_URL            , $url);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER , true);     // return web page
      curl_setopt ($ch, CURLOPT_HEADER         , false);    // don't return headers
      curl_setopt ($ch, CURLOPT_ENCODING       , "");       // handle compressed
      curl_setopt ($ch, CURLOPT_USERAGENT      , $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12")); // who am i
      curl_setopt ($ch, CURLOPT_AUTOREFERER    , true);     // set referer on redirect
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , $timeout);       // timeout on connect
      curl_setopt ($ch, CURLOPT_TIMEOUT        , $timeout);       // timeout on response in seconds.
      curl_setopt ($ch, CURLOPT_FOLLOWLOCATION , true);     // follow redirects
      curl_setopt ($ch, CURLOPT_MAXREDIRS      , 10);       // stop after 10 redirects
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER  , false);    // Disable SSL verifications
      if ($is_post) { curl_setopt ($ch, CURLOPT_POST, true); }
      if ($is_post) { curl_setopt ($ch, CURLOPT_POSTFIELDS, $new_post_data); }
      }

   $content = curl_exec   ($ch);
   $err     = curl_errno  ($ch);
   $header  = curl_getinfo($ch);
   // $errmsg  = curl_error  ($ch);


   curl_close             ($ch);

   if (!$err && $header['http_code']==200)
      return trim($content);
   else
   {
      if ($return_content_on_error)
         return trim($content);
      else
         return FALSE;
   }
}
//===========================================================================

//===========================================================================
function KRBWC__object_to_array ($object)
{
	if (!is_object($object) && !is_array($object))
    return $object;
  return array_map('KRBWC__object_to_array', (array) $object);
}
//===========================================================================

//===========================================================================
// Credits: http://www.php.net/manual/en/function.mysql-real-escape-string.php#100854
function KRBWC__safe_string_escape ($str="")
{
   $len=strlen($str);
   $escapeCount=0;
   $targetString='';
   for ($offset=0; $offset<$len; $offset++)
   {
     switch($c=$str{$offset})
     {
         case "'":
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '"':
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '\\':
                 $escapeCount++;
                 $targetString.=$c;
                 break;
         default:
                 $escapeCount=0;
                 $targetString.=$c;
     }
   }
   return $targetString;
}
//===========================================================================

//===========================================================================
// Syntax:
//    KRBWC__log_event (__FILE__, __LINE__, "Hi!");
//    KRBWC__log_event (__FILE__, __LINE__, "Hi!", "/..");
//    KRBWC__log_event (__FILE__, __LINE__, "Hi!", "", "another_log.php");
function KRBWC__log_event ($filename, $linenum, $message, $prepend_path="", $log_file_name='__log.php')
{
   $log_filename   = dirname(__FILE__) . $prepend_path . '/' . $log_file_name;
   $logfile_header = "<?php exit(':-)'); ?>\n" . '/* =============== KarboWC LOG file =============== */' . "\r\n";
   $logfile_tail   = "\r\nEND";

   // Delete too long logfiles.
   //if (@file_exists ($log_filename) && filesize($log_filename)>1000000)
   //   unlink ($log_filename);

   $filename = basename ($filename);

   if (@file_exists ($log_filename))
      {
      // 'r+' non destructive R/W mode.
      $fhandle = @fopen ($log_filename, 'r+');
      if ($fhandle)
         @fseek ($fhandle, -strlen($logfile_tail), SEEK_END);
      }
   else
      {
      $fhandle = @fopen ($log_filename, 'w');
      if ($fhandle)
         @fwrite ($fhandle, $logfile_header);
      }

   if ($fhandle)
      {
      @fwrite ($fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date("Y-m-d, G:i:s T") . "|" . KRBWC_VERSION . "/" . KRBWC_EDITION . "|$filename($linenum)|: " . $message . $logfile_tail);
      @fclose ($fhandle);
      }
}
//===========================================================================

//===========================================================================
function KRBWC__SubIns ()
{
  return;
}
//===========================================================================

//===========================================================================
function KRBWC__send_email ($email_to, $email_from, $subject, $plain_body)
{
   $message = "
   <html>
   <head>
   <title>$subject</title>
   </head>
   <body>" . $plain_body . "
   </body>
   </html>
   ";

   // To send HTML mail, the Content-type header must be set
   $headers  = 'MIME-Version: 1.0' . "\r\n";
   $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

   // Additional headers
   $headers .= "From: " . $email_from . "\r\n";    //"From: Birthday Reminder <birthday@example.com>" . "\r\n";

   // Mail it
   $ret_code = @mail ($email_to, $subject, $message, $headers);

   return $ret_code;
}
//===========================================================================

//===========================================================================
function KRBWC__is_gateway_valid_for_use (&$ret_reason_message=NULL)
{
  $valid = true;
  $krbwc_settings = KRBWC__get_settings ();

////   'service_provider'                     =>  'local_wallet',    // 'blockchain_info'

  //----------------------------------
  // Validate settings
  if ($krbwc_settings['service_provider']=='local_wallet')
  {         
          $krbwc_settings = KRBWC__get_settings();
          $address = $krbwc_settings['address'];

          try{
            $wallet_api = New ForkNoteWalletd("http://127.0.0.1:18888");
            $address_balance = $wallet_api->getBalance($address);
          }
          catch(Exception $e) {
          }

          if (!$address)
          {
            $reason_message = __("Please specify Wallet Address in Karbo plugin settings.", 'woocommerce');
            $valid = false;
          }
          // @TODO
          // else if (!preg_match ('/^xpub[a-zA-Z0-9]{98}$/', $address))
          // {
          //   $reason_message = __("Karbo Address ($address) is invalid. Must be 98 characters long, consisting of digits and letters.", 'woocommerce');
          //   $valid = false;
          // }

          else if ($address_balance === false)
          {
            $reason_message = __("Karbo address is not found in wallet.", 'woocommerce');
            $valid = false;
          }
  }

  if (!$valid)
  {
    if ($ret_reason_message !== NULL)
      $ret_reason_message = $reason_message;
    return false;
  }

  //----------------------------------

  //----------------------------------
  // Validate connection to exchange rate services

  $store_currency_code = 'USD';
  if ($store_currency_code != 'KRB')
  {
    $currency_rate = KRBWC__get_exchange_rate_per_Karbo ($store_currency_code, 'getfirst', false);
    if (!$currency_rate)
    {
      $valid = false;

      // Assemble error message.
      $error_msg = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";
      $extra_error_message = "";
      $fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
      $fns = array_filter ($fns, 'KRBWC__function_not_exists');
      $extra_error_message = "";
      if (count($fns))
        $extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

      $reason_message = str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $error_msg);

      if ($ret_reason_message !== NULL)
        $ret_reason_message = $reason_message;
      return false;
    }
  }

  return true;
  //----------------------------------
}
//===========================================================================


//===========================================================================
// Some hosting services disables base64_encode/decode.
// this is equivalent replacement to fix errors.
function KRBWC__base64_decode($input)
{
	  if (function_exists('base64_decode'))
	  	return base64_decode($input);

    $keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    $chr1 = $chr2 = $chr3 = "";
    $enc1 = $enc2 = $enc3 = $enc4 = "";
    $i = 0;
    $output = "";

    // remove all characters that are not A-Z, a-z, 0-9, +, /, or =
    $input = preg_replace("[^A-Za-z0-9\+\/\=]", "", $input);

    do {
        $enc1 = strpos($keyStr, substr($input, $i++, 1));
        $enc2 = strpos($keyStr, substr($input, $i++, 1));
        $enc3 = strpos($keyStr, substr($input, $i++, 1));
        $enc4 = strpos($keyStr, substr($input, $i++, 1));
        $chr1 = ($enc1 << 2) | ($enc2 >> 4);
        $chr2 = (($enc2 & 15) << 4) | ($enc3 >> 2);
        $chr3 = (($enc3 & 3) << 6) | $enc4;
        $output = $output . chr((int) $chr1);
        if ($enc3 != 64) {
            $output = $output . chr((int) $chr2);
        }
        if ($enc4 != 64) {
            $output = $output . chr((int) $chr3);
        }
        $chr1 = $chr2 = $chr3 = "";
        $enc1 = $enc2 = $enc3 = $enc4 = "";
    } while ($i < strlen($input));
    return urldecode($output);
}

function KRBWC__base64_encode($data)
{
	  if (function_exists('base64_encode'))
	  	return base64_encode($data);

    $b64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
    $o1 = $o2 = $o3 = $h1 = $h2 = $h3 = $h4 = $bits = $i = 0;
    $ac = 0;
    $enc = '';
    $tmp_arr = array();
    if (!$data) {
        return data;
    }
    do {
    // pack three octets into four hexets
    $o1 = KRBWC_charCodeAt($data, $i++);
    $o2 = KRBWC_charCodeAt($data, $i++);
    $o3 = KRBWC_charCodeAt($data, $i++);
    $bits = $o1 << 16 | $o2 << 8 | $o3;
    $h1 = $bits >> 18 & 0x3f;
    $h2 = $bits >> 12 & 0x3f;
    $h3 = $bits >> 6 & 0x3f;
    $h4 = $bits & 0x3f;
    // use hexets to index into b64, and append result to encoded string
    $tmp_arr[$ac++] = KRBWC_charAt($b64, $h1).KRBWC_charAt($b64, $h2).KRBWC_charAt($b64, $h3).KRBWC_charAt($b64, $h4);
    } while ($i < strlen($data));
    $enc = implode($tmp_arr, '');
    $r = (strlen($data) % 3);
    return ($r ? substr($enc, 0, ($r - 3)) : $enc) . substr('===', ($r || 3));
}

function KRBWC_charCodeAt($data, $char) {
    return ord(substr($data, $char, 1));
}

function KRBWC_charAt($data, $char) {
    return substr($data, $char, 1);
}
//===========================================================================
