        /*
         * Queries Minecraft server
         * Returns array on success, false on failure.
         *
         * WARNING: This is using an old "ping" feature, only use this to ping servers prior to 1.7 version.
         *
         * Written by xPaw
         * Added 1.6 support by chillecharlie
         *
         * Website: http://xpaw.ru
         * GitHub: https://github.com/xPaw/PHP-Minecraft-Query
         */
         
function QueryMinecraft_Read_VarInt( $Socket )
{
	$i = 0;
	$j = 0;
	while( true )
	{
		$k = Ord( Socket_Read( $Socket, 1 ) );
		$i |= ( $k & 0x7F ) << $j++ * 7;
		if( $j > 5 )
		{
			throw new RuntimeException( 'VarInt too big' );
		}
		if( ( $k & 0x80 ) != 128 )
		{
			break;
		}
	}
	return $i;
}

function NewQuery( $IP, $Port = 25565, $Timeout = 2 )
{
	socket_clear_error();
	$Socket = Socket_Create( AF_INET, SOCK_STREAM, SOL_TCP );
	Socket_Set_Option( $Socket, SOL_SOCKET, SO_SNDTIMEO, array( 'sec' => (int)$Timeout, 'usec' => 0 ) );
	Socket_Set_Option( $Socket, SOL_SOCKET, SO_RCVTIMEO, array( 'sec' => (int)$Timeout, 'usec' => 0 ) );
	if( $Socket === FALSE || @Socket_Connect( $Socket, $IP, (int)$Port ) === FALSE )
	{
		return FALSE;
	}
	Socket_Send( $Socket, "\xFE\x01", 2, 0 );
	$Len = Socket_Recv( $Socket, $Data, 512, 0 );
	Socket_Close( $Socket );
	if( $Len < 4 || $Data[ 0 ] !== "\xFF" )
	{	
		$Socket = Socket_Create( AF_INET, SOCK_STREAM, SOL_TCP );
		Socket_Set_Option( $Socket, SOL_SOCKET, SO_SNDTIMEO, array( 'sec' => (int)$Timeout, 'usec' => 0 ) );
		Socket_Set_Option( $Socket, SOL_SOCKET, SO_RCVTIMEO, array( 'sec' => (int)$Timeout, 'usec' => 0 ) );
		if( $Socket === FALSE || @Socket_Connect( $Socket, $IP, (int)$Port ) === FALSE )
		{
			return FALSE;
		}
		$Length = StrLen( $IP );
		$Data = Pack( 'cccca*', HexDec( $Length ), 0, 0x04, $Length, $IP ) . Pack( 'nc', $Port, 0x01 );
		Socket_Send( $Socket, $Data, StrLen( $Data ), 0 ); // handshake
		Socket_Send( $Socket, "\x01\x00", 2, 0 ); // status ping
		$Length = QueryMinecraft_Read_VarInt( $Socket ); // full packet length
		if( $Length < 10 )
		{
			Socket_Close( $Socket );
			return FALSE;
		}
		Socket_Read( $Socket, 1 ); // packet type, in server ping it's 0
		$Length = QueryMinecraft_Read_VarInt( $Socket ); // string length
		$Data = Socket_Read( $Socket, $Length, PHP_NORMAL_READ ); // and finally the json string
		Socket_Close( $Socket );
		$Data = JSON_Decode( $Data, true );
		return JSON_Last_Error( ) === JSON_ERROR_NONE ? $Data : FALSE;
	}
	
	$Data = SubStr( $Data, 3 ); // Strip packet header (kick message packet and short length)
	$Data = iconv( 'UTF-16BE', 'UTF-8', $Data );
	// Are we dealing with Minecraft 1.4+ server?
	if( $Data[ 1 ] === "\xA7" && $Data[ 2 ] === "\x31" )
	{
		$Data = Explode( "\x00", $Data );
		$return['description'] = $Data[ 3 ];
		$return['players']['online'] = IntVal( $Data[ 4 ] );
		$return['players']['max'] = IntVal( $Data[ 5 ] );
		$return['version']['protocol'] = IntVal( $Data[ 1 ] );
		$return['version']['name'] = $Data[ 2 ];
		return $return;
	}
	$Data = Explode( "\xA7", $Data );
	$return['description'] = SubStr( $Data[ 0 ], 0, -1 );
	$return['players']['online'] = isset( $Data[ 1 ] ) ? IntVal( $Data[ 1 ] ) : 0;
	$return['players']['max'] = isset( $Data[ 2 ] ) ? IntVal( $Data[ 2 ] ) : 0;
	$return['version']['protocol'] = 0;
	$return['version']['name'] = '1.3';
	return $return;
}

function QueryMinecraft($Server_IP, $Server_Port = '25565')
{
	if($Info = NewQuery( $Server_IP, $Server_Port )){
		$return['MaxPlayers'] = $Info['players']['max'];
		$return['Players'] = $Info['players']['online'];
		$return['HostName'] = $Info['description'];
		$return['Version'] = $Info['version']['name'];
		return $return;
	} else {
		return FALSE;
	}
}
