#!/usr/bin/env php
<?php
	define( "PID_FILE",	"/var/run/bme280.pid" );
	define( "LOG_DIR",	"/var/log/bme280log/" );
	define( "CURRENT_INTERVAL",	1 );		//	1 sec
	define( "LOG_INTERVAL",		300 );		//	5 min

	class bme280 {
		protected $fd;
		protected $dig_T1;
		protected $dig_T2;
		protected $dig_T3;
		protected $dig_P1;
		protected $dig_P2;
		protected $dig_P3;
		protected $dig_P4;
		protected $dig_P5;
		protected $dig_P6;
		protected $dig_P7;
		protected $dig_P8;
		protected $dig_P9;
		protected $dig_H1;
		protected $dig_H2;
		protected $dig_H3;
		protected $dig_H4;
		protected $dig_H5;
		protected $dig_H6;
		protected $t_fine = 0;

		function __construct( $addr ) {
			$this->fd = WiringPi::wiringPiI2CSetup( $addr );
			$this->setup();
		}

		function bme280( $addr ) {
			$this->fd = WiringPi::wiringPiI2CSetup( $addr );
			$this->setup();
		}

		function readData() {
			$data = array();
			for ( $reg = 0xf7; $reg <= 0xff; $reg ++ ) {
				array_push( $data, WiringPi::wiringPiI2CReadReg8( $this->fd, $reg ) );
			}
			$pres_raw = ( $data[0] << 12 ) | ( $data[1] << 4 ) | ( $data[2] >> 4 );
			$temp_raw = ( $data[3] << 12 ) | ( $data[4] << 4 ) | ( $data[5] >> 4 );
			$hum_raw  = ( $data[6] << 8 )  |  $data[7];

			$this->temperature	= $this->compensate_T_double( $temp_raw );
			$this->pressure		= $this->compensate_P_double( $pres_raw );
			$this->humidity		= $this->compensate_H_double( $hum_raw );
		}

		function compensate_T_double( $adc_T ) {
			$v1 = ( $adc_T / 16384.0 - $this->dig_T1 / 1024.0 ) * $this->dig_T2;
			$v2 = ( $adc_T / 131072.0 - $this->dig_T1 / 8192.0 ) * ( $adc_T / 131072.0 - $this->dig_T1 / 8192.0) * $this->dig_T3;
			$this->t_fine = $v1 + $v2;
			return $this->t_fine / 5120.0;
		}

		function compensate_P_double( $adc_P ) {
			$v1 = ( $this->t_fine / 2.0 ) - 64000.0;
			$v2 = $v1 * $v1 * $this->dig_P6 / 32768.0;
			$v2 = $v2 + $v1 * $this->dig_P5 * 2.0;
			$v2 = ( $v2 / 4.0 ) + $this->dig_P4 * 65536.0;
			$v1 = ( $this->dig_P3 * $v1 * $v1 / 524288.0 + $this->dig_P2 * $v1 ) / 524288.0;
			$v1 = ( 1 + $v1 / 32768.0 ) * $this->dig_P1;
			if ( $v1 == 0 ) {
				return 0;
			}
			$p = 1048576.0 - $adc_P;
			$p = ( $p - ( $v2 / 4096.0 ) ) * 6250.0 / $v1;
			$v1 = $this->dig_P9 * $p * $p / 2147483648.0;
			$v2 = $p * $this->dig_P8 / 32768.0;
			$p = $p + ( $v1 + $v2 + $this->dig_P7 ) / 16.0;
			return $p / 100.0;		//	Pa -> hPa
		}

		function compensate_H_double( $adc_H ) {
			$var_h = $this->t_fine - 76800.0;
			if ( $var_h == 0 ) {
				return 0;
			}
			$var_h = ( $adc_H - ( $this->dig_H4 * 64.0 + $this->dig_H5 / 16384.0 * $var_h ) ) *
						( $this->dig_H2 / 65536.0 * ( 1.0 + $this->dig_H6 / 67108864.0 * $var_h * ( 1.0 + $this->dig_H3 / 67108864.0 * $var_h ) ) );
			$var_h = $var_h * ( 1.0 - $this->dig_H1 * $var_h / 524288.0 );
			if ( $var_h > 100.0 ) {
				$var_h = 100.0;
			} else if ( $var_h < 0.0 ) {
				$var_h = 0.0;
			}
			return $var_h;
		}

		function setup() {
			$this->calibrate();

			$osrs_t		= 2;		//	Temperature oversampling x 2
			$osrs_p		= 2;		//	Pressure oversampling x 2
			$osrs_h		= 2;		//	Humidity oversampling x 2
			$mode		= 3;		//	Normal mode
			$t_sb		= 5;		//	Tstandby 1000ms
			$filter		= 0;		//	Filter off
			$spi3w_en	= 0;		//	3-wire SPI Disable
			$ctrl_meas_reg	= ( $osrs_t << 5 ) | ( $osrs_p << 2 ) | $mode;
			$config_reg		= ( $t_sb << 5 ) | ( $filter << 2 ) | $spi3w_en;
			$ctrl_hum_reg	= $osrs_h;
			WiringPi::wiringPiI2CWriteReg8( $this->fd, 0xf2, $ctrl_hum_reg );
			WiringPi::wiringPiI2CWriteReg8( $this->fd, 0xf4, $ctrl_meas_reg );
			WiringPi::wiringPiI2CWriteReg8( $this->fd, 0xf5, $config_reg );
		}

		function calibrate() {
			$this->dig_T1 = WiringPi::wiringPiI2CReadReg16( $this->fd, 0x88 );
			$this->dig_T2 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x8a ) );
			$this->dig_T3 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x8c ) );
			$this->dig_P1 = WiringPi::wiringPiI2CReadReg16( $this->fd, 0x8e );
			$this->dig_P2 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x90 ) );
			$this->dig_P3 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x92 ) );
			$this->dig_P4 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x94 ) );
			$this->dig_P5 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x96 ) );
			$this->dig_P6 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x98 ) );
			$this->dig_P7 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x9a ) );
			$this->dig_P8 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x9c ) );
			$this->dig_P9 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0x9e ) );
			$this->dig_H1 = WiringPi::wiringPiI2CReadReg8( $this->fd, 0xa1 );
			$this->dig_H2 = $this->signed16( WiringPi::wiringPiI2CReadReg16( $this->fd, 0xe1 ) );
			$this->dig_H3 = WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe3 );
			$this->dig_H4 = $this->signed16( WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe4 ) << 4 | WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe5 ) & 0x0f );
			$this->dig_H5 = $this->signed16( WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe6 ) << 4 | ( WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe5 ) >> 4 ) & 0x0f );
			$this->dig_H6 = $this->signed8( WiringPi::wiringPiI2CReadReg8( $this->fd, 0xe7 ) );
		}

		function signed16( $val ) {
			if ( $val & 0x8000 ) {
				$val = - ( ( $val ^ 0xffff ) + 1 );
			}
			return $val;
		}

		function signed8( $val ) {
			if ( $val & 0x80 ) {
				$val = - ( ( $val ^ 0xff ) + 1 );
			}
			return $val;
		}
	}

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

	pcntl_signal( SIGTERM, function( $signo, $siginfo ) {
		unlink( PID_FILE );
		exit;
	} );

	declare( ticks = 1 );
	@mkdir( LOG_DIR, 0755 );
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
