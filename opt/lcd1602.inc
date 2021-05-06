<?php
	class lcd1602 {
		const RS	=	0;
		const RW	=	1;
		const STRB	=	2;
		const LED	=	3;
		const D4	=	4;
		const D5	=	5;
		const D6	=	6;
		const D7	=	7;

		const LCD_BLINK_CTRL	= 0x01;
		const LCD_CURSOR_CTRL	= 0x02;
		const LCD_DISPLAY_CTRL	= 0x04;

		const LCD_CLEAR			= 0x01;
		const LCD_HOME			= 0x02;
		const LCD_ENTRY			= 0x04;
		const LCD_CTRL			= 0x08;
		const LCD_CDSHIFT		= 0x10;
		const LCD_FUNC			= 0x20;
		const LCD_CGRAM			= 0x40;
		const LCD_DGRAM			= 0x80;

		const LCD_FUNC_F		= 0x04;
		const LCD_FUNC_N		= 0x08;
		const LCD_FUNC_DL		= 0x10;

		const LCD_CDSHIFT_RL	= 0x04;
		const LCD_ENTRY_ID		= 0x02;

		protected $rows;
		protected $cols;
		protected $bits = 0;
		protected $cx = 0;
		protected $cy = 0;
		protected $rowOff = array( 0x00, 0x40, 0x14, 0x54 );
		protected $lcdControl = 0;

		function __construct( $addr, $rows = 2, $cols  =16, $bits = 4 ) {
			$this->lcd1602( $addr, $rows, $cols, $bits );
		}

		function lcd1602( $addr, $rows = 2, $cols  =16, $bits = 4 ) {
			$this->fd = WiringPi::wiringPiI2CSetup( $addr );

			$this->rows = $rows;
			$this->cols = $cols;
			$this->bits = $bits;

			for ( $i = 0; $i < 8; $i ++ ) {
				//WiringPi::pinMode( $i, WiringPi::OUTPUT );
				$this->digitalWrite( $i, WiringPi::LOW );
			}
			$this->digitalWrite( self::LED, WiringPi::HIGH );	// turn on LCD backlight
			$this->digitalWrite( self::RW, WiringPi::LOW );		// allow writing to LCD

			$this->digitalWrite( self::RS, WiringPi::LOW );
			//WiringPi::pinMode ( self::RS, WiringPi::OUTPUT );
			$this->digitalWrite( self::RS, WiringPi::LOW );
			$this->digitalWrite( self::STRB, WiringPi::LOW );
			//WiringPi::pinMode( self::STRB, WiringPi::OUTPUT );
			$this->digitalWrite( self::STRB, WiringPi::LOW );
			for ( $i = 0; $i < $this->bits; $i ++ ) {
				$dataPin = self::D4 + $i;
				$this->digitalWrite ( $dataPin + $i, WiringPi::LOW );
				//WiringPi::pinMode( $dataPin + $i, WiringPi::OUTPUT );
				$this->digitalWrite( $dataPin + $i, WiringPi::LOW );
			}

			$func = self::LCD_FUNC | self::LCD_FUNC_DL ;		// Set 8-bit mode 3 times
			$this->put4Command( $func >> 4);
			usleep (35000) ;
			$this->put4Command( $func >> 4);
			usleep (35000) ;
			$this->put4Command( $func >> 4);
			usleep (35000) ;
			$func = self::LCD_FUNC ;							// 4th set: 4-bit mode
			$this->put4Command( $func >> 4);
			usleep (35000) ;
			$func |= self::LCD_FUNC_N ;
			$this->putCommand ( $func );
			usleep (35000) ;

			$this->lcdDisplay( true );
			$this->lcdCursor( false );
			$this->lcdCursorBlink( false );
			$this->lcdClear();

			$this->putCommand( self::LCD_ENTRY	| self::LCD_ENTRY_ID );
			$this->putCommand( self::LCD_CDSHIFT | self::LCD_CDSHIFT_RL );
		}

		function lcdPosition( $x, $y ) {
			if ( ( $x > $this->cols ) || ( $x < 0 ) ) {
				return;
			}
			if ( ( $y > $this->rows ) || ( $y < 0 ) ) {
				return;
			}
			$this->putCommand( $x + ( self::LCD_DGRAM | $this->rowOff[$y] ) );
			$this->cx = $x ;
			$this->cy = $y ;
		}

		function lcdPutchar( $data ) {
			$this->digitalWrite( self::RS, 1 );
			$this->sendDataCmd( $data );
			if ( ++ $this->cx >= $this->cols ) {
				$this->cx = 0 ;
				if ( ++ $this->cy >= $this->rows ) {
					$this->cy = 0 ;
				}
				$this->putCommand( $this->cx + (self::LCD_DGRAM | $this->rowOff[$this->cy] ) );
			}
		}

		function lcdPuts( $string ) {
			for ( $n = 0; $n < strlen( $string ); $n ++ ) {
				$this->lcdPutchar( Ord( $string[$n] ) );
			}
		}

		function digitalWrite( $pin, $value) {
			static $old = 0;
			$bit  = 1 << ( $pin & 7 );
			if ( $value == WiringPi::LOW )
				$old &= ( ~ $bit );
			else
				$old |= $bit;
			WiringPi::wiringPiI2CWrite( $this->fd, $old );
		}

		function lcdDisplay( $state ) {
			if ( $state ) {
				$this->lcdControl |=  self::LCD_DISPLAY_CTRL;
			} else {
				$this->lcdControl &= ~self::LCD_DISPLAY_CTRL;
				$this->digitalWrite( self::LED, WiringPi::LOW );		// turn off LCD backlight
			}
			$this->putCommand( self::LCD_CTRL | $this->lcdControl );
		}

		function lcdCursor( $state ) {
			if ( $state )
				$this->lcdControl |=  self::LCD_CURSOR_CTRL;
			else
				$this->lcdControl &= ~self::LCD_CURSOR_CTRL;
			$this->putCommand( self::LCD_CTRL | $this->lcdControl );
		}

		function lcdCursorBlink( $state ) {
			if ( $state )
				$this->lcdControl |=  self::LCD_BLINK_CTRL;
			else
				$this->lcdControl &= ~self::LCD_BLINK_CTRL;
			$this->putCommand( self::LCD_CTRL | $this->lcdControl );
		}

		function lcdClear() {
			$this->putCommand( self::LCD_CLEAR );
			$this->putCommand( self::LCD_HOME );
			$this->cx = $this->cy = 0 ;
			usleep(5000);
		}

		function strobe() {
			$this->digitalWrite( self::STRB, WiringPi::HIGH );
			usleep (50);
			$this->digitalWrite( self::STRB, WiringPi::LOW );
			usleep (50);
		}

		function sendDataCmd( $data ) {
			for ( $i = 0; $i < 4; $i++ ) {
				$d = ( $data & ( 0x10 << $i ) ) ? WiringPi::HIGH : WiringPi::LOW;
				$this->digitalWrite( self::D4 + $i, $d, $i == 3 );
			}
			$this->strobe() ;
			for ( $i = 0; $i < 4; $i++ ) {
				$d =( $data & ( 0x01 << $i ) ) ? WiringPi::HIGH : WiringPi::LOW;
				$this->digitalWrite( self::D4 + $i, $d, $i == 3 );
			}
			$this->strobe() ;
		}

		function putCommand( $command ) {
			$this->digitalWrite( self::RS, WiringPi::LOW ) ;
			$this->sendDataCmd( $command );
			usleep( 2000 ) ;
		}

		function put4Command( $command ) {
			$this->digitalWrite( self::RS, WiringPi::LOW );
			for ( $i = 0; $i < 4; $i ++ ) {
				$this->digitalWrite( self::D4 + $i, ( $command & ( 1 << $i ) ) ? WiringPi::HIGH : WiringPi::LOW, $i == 3 );
			}
			$this->strobe();
		}
	};
?>
