<?php
	$url = $_REQUEST["REQUEST_URL"];
	unset( $_POST["REQUEST_URL"] );
	_relay_request( $url );
	exit;

	////////////////////////////////////////////////////////////////////////////
	function _relay_request( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		//curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 1 );
		curl_setopt( $ch, CURLOPT_URL, $url );
		if ( !strcasecmp( $_SERVER["REQUEST_METHOD"], "POST" ) ) {
			curl_setopt( $ch, CURLOPT_POST, 1 );
			$param = array_to_str( $_POST ) ;
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $param );
		} else {
			curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
		}

		$headers = array();
		foreach ( apache_request_headers() as $name => $value ) {
			if ( preg_match( "/^Content-Length/i", $name ) ) continue;
			if ( preg_match( "/^Content-Type/i", $name ) ) continue;
			if ( preg_match( "/^Host/i", $name ) ) continue;
			$headers[] = $name . ": " .  $value;
		}
		$headers[] = "REMOTE_ADDR_X: {$_SERVER["REMOTE_ADDR"]}";
		$headers[] = "REQUEST_URL_X: {$_REQUEST["REQUEST_URL"]}";
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		if ( isset( $_SERVER["HTTP_USER_AGENT"] ) ) {
			curl_setopt( $ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"] );
		}
		if ( isset( $_SERVER["HTTP_COOKIE"] ) ) {
			curl_setopt( $ch, CURLOPT_COOKIE, $_SERVER["HTTP_COOKIE"] );
		}

		$call_back = function( $ch, $buffer ) {
			$ln = strlen( $buffer );
			static $start_head = false;
			static $start_body = false;
			static $save = "";
			if ( $start_body ) {
				if ( !empty( $GLOBALS["CHUNKED"] ) ) {
					printf( "%x\r\n", strlen( $buffer ) );
				}
				echo $buffer;
				if ( !empty( $GLOBALS["CHUNKED"] ) ) {
					echo "\r\n";
				}
				flush();
				return $ln;
			}
			$buffer = $save . $buffer;
			while( true ) {
				if ( ( $pos = strpos( $buffer, "\r\n" ) ) === false ) {
					break;
				}
				$head = substr( $buffer, 0, $pos );
				$buffer = substr( $buffer, $pos + 2 );
				if ( $pos != 0 ) {
					if ( preg_match( "/^HTTP\//", $head ) &&
							strpos( $head, "Continue" ) === false ) {
						$start_head = true;
					}
					if ( $start_head ) {
						if ( preg_match( "/^Transfer\-Encoding\:\s+chunked/i", $head ) ) {
							$GLOBALS["CHUNKED"] = true;
						}
						header( $head );
					}
				} else if ( $start_head ) {
					$start_body = true;
					if ( $buffer != "" ) {
						if ( !empty( $GLOBALS["CHUNKED"] ) ) {
							printf( "%x\r\n", strlen( $buffer ) );
						}
						echo $buffer;
						if ( !empty( $GLOBALS["CHUNKED"] ) ) {
							echo "\r\n";
						}
						flush();
					}
				}
				if ( $start_body ) break;
			}
			$save = $buffer;
			return $ln;
		};

		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, $call_back );
		if ( curl_exec( $ch ) !== true ) {
			curl_close( $ch );
			header( 'HTTP', true, 403 );
			echo "Could not connect to server\n'{$_REQUEST["REQUEST_URL"]}'\r\n";
			exit;
		}
		if ( !empty( $GLOBALS["CHUNKED"] ) ) {
			echo "0\r\n";
			echo "\r\n";
		}
		curl_close( $ch );
	}

	////////////////////////////////////////////////////////////////////////////
	function array_to_str( $value, $key = "" ) {
		$rt = array();
		if ( !is_array( $value ) ) {
			return array( $key => $value );
		}
		foreach( $value as $k => $v ) {
			if ( $key != "" ) {
				$k = "[{$k}]";
			}
			$str = array_to_str( $v, $key . $k );
			$rt = $rt + $str;
		}
		return $rt;
	}
?>
