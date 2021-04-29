<?php
	if ( empty( $_SERVER["REQUEST_METHOD"] ) || $_SERVER["REQUEST_METHOD"] != "POST" ) {
		header('HTTP/1.1 400 Bad Request');
		echo "Bad Request";
		exit;
	}
	if ( empty( $_REQUEST["TYPE"] ) ) {
		header('HTTP/1.1 400 Bad Request');
		echo "Bad Request";
		exit;
	}
	$dir = '/var/log/bme280log/';
	mkdir( $dir, 0755 );
	$result = array();
	if ( $_REQUEST["TYPE"] == "FILELIST" ) {
		$files = array();
		$dp = opendir( $dir );
		if ( $dp !== false ) {
			while( ( $file = readdir( $dp ) ) !== false ) {
				if( !is_file( "$dir/$file" ) ) continue;
				if( preg_match( "/\.log$/", $file ) ) {
					$files[] = $file;
				}
			}
			closedir( $dp );
		}
		rsort( $files );
		foreach( $files as $no => $file ) {
			$date = substr( $file, 0, 4 ) . "/" .
					substr( $file, 4, 2 ) . "/" .
					substr( $file, 6, 2 );
			$result[] = $date;
		}

	} else if ( $_REQUEST["TYPE"] == "LOGDATA" ) {
		if( isset( $_REQUEST["DATE"] ) ) {
			$file = $dir . $_REQUEST["DATE"] . ".log";
			$logdata = file( $file, FILE_IGNORE_NEW_LINES );
			if ( !empty( $logdata ) ) {
				array_shift( $logdata );
				while ( !empty( $logdata ) ) {
					$val = explode( "\t", array_shift( $logdata  ) );
					if ( empty( $val ) ) continue;
					//	$val[0]: date
					//	$val[1]: time
					//	$val[2]: temp.
					//	$val[3]: humid.
					//	$val[4]: press.
					$result[$val[1]] = array(
						floatval( $val[2] ), floatval( $val[3] ), floatval( $val[4] )
					);
				};
			}
		}
	} else if ( $_REQUEST["TYPE"] == "CURRENT" ) {
		$file = $dir . "current";
		$data = trim( file_get_contents( $file ) );
		$val = explode( "\t", $data );
		//	$val[0]: date
		//	$val[1]: time
		//	$val[2]: temp.
		//	$val[3]: humid.
		//	$val[4]: press.
		$result = array(
			"datetime"	=> $val[0] . " " . $val[1],
			"temp"	=> $val[2],
			"humid"	=> $val[3],
			"press"	=> $val[4]
		);
	}
	$text = json_encode( $result, JSON_UNESCAPED_UNICODE );
	header( "Content-Type: application/json; charset=utf-8" );
	header( "Content-Length: " . strlen( $text ) );
	echo $text;
?>
