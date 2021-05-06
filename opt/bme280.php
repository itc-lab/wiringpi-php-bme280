#!/usr/bin/env php
<?php
	require( "bme280.inc" );
	require( "lcd1602.inc" );

	define( "PID_FILE",	"/var/run/bme280.pid" );
	define( "LOG_DIR",	"/var/log/bme280log/" );
	define( "CURRENT_INTERVAL",	1 );		//	1 sec
	define( "LOG_INTERVAL",		300 );		//	5 min

	if ( $argc > 1 && !strcasecmp( $argv[1], "stop" ) ) {
		$pid = intval( file_get_contents( PID_FILE ) );
		posix_kill( $pid, SIGTERM );
		echo "stoped\n";
		exit;
	}
	if ( is_file( PID_FILE ) ) {
		echo "already running\n";
		exit;
	}
	$pid = pcntl_fork();
	if ($pid == -1) {
		die('fork できません');
	} else if ( $pid ) {
		// 親プロセスの場合
		//pcntl_wait($status);
		echo "pid: ${pid}\n";
		file_put_contents( PID_FILE, $pid );
		exit;
	}

	declare( ticks = 1 );
	@mkdir( LOG_DIR, 0755 );

	$lcd = new lcd1602( 0x27 );

	pcntl_signal( SIGTERM, function( $signo, $siginfo ) {
		$GLOBALS["lcd"]->lcdClear();
		$GLOBALS["lcd"]->lcdDisplay( false );
		unlink( PID_FILE );
		exit;
	} );

	$bme280 = new bme280( 0x76 );
	$prev = -1;
	while( true ) {
		$tm = time();
		if ( $tm == $prev ) {
			usleep( 100000 );
			continue;
		}
		$prev = $tm;

		$bme280->readData();

		$lcd->lcdPosition( 0, 0 );
		$lcd->lcdPuts( sprintf( "%5.2f\xdfC   %5.2f%%", $bme280->temperature + 0.005, $bme280->humidity + 0.005 ) );
		$lcd->lcdPosition( 0, 1 );
		$lcd->lcdPuts( sprintf( "%.2fhPa", $bme280->pressure + 0.005 ) );

		$text = "";
		$text .= strftime( "%Y/%m/%d\t", $tm );
		$text .= strftime( "%H:%M:%S\t", $tm );
		$text .= sprintf( "%.2f\t", $bme280->temperature + 0.005 );
		$text .= sprintf( "%.2f\t", $bme280->humidity + 0.005 );
		$text .= sprintf( "%.2f", $bme280->pressure + 0.005 );
		if ( ( $tm % CURRENT_INTERVAL ) == 0 ) {
			$log_file = LOG_DIR . "current";
			file_put_contents( $log_file, $text, LOCK_EX  );
		}
		if ( ( $tm % LOG_INTERVAL ) == 0 ) {
			$log_file = LOG_DIR . strftime( "%Y%m%d", $tm ) . ".log";
			$fp = fopen( $log_file, "a" );
			if ( $fp !== false ) {
				fseek( $fp, 0, SEEK_END );
				$pos = ftell( $fp );
				if ( $pos == 0 ) {
					fputs( $fp, "date\ttime\ttemp\thumid\tpress\n" );
				}
				fputs( $fp, $text . "\n" );
				fclose( $fp );
			}
		}
	}
?>
