<?php

namespace IPS\teamspeak;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

require_once( \IPS\ROOT_PATH . '/applications/teamspeak/sources/3rd_party/TeamSpeakAdmin.php' );

abstract class _Api
{
	/**
	 * @var \TeamSpeakAdmin
	 */
	protected $instance = null;

	protected $settings;

	/**
	 * Builds up the connection to the TS server.
	 * @param \TeamSpeakAdmin $tsInstance
	 * @param bool $login
	 */
	public function __construct( \TeamSpeakAdmin $tsInstance = null, $login = true )
	{
		$this->settings = \IPS\Settings::i();

		if ( !is_null( $tsInstance ) )
		{
			$this->instance = $tsInstance;
		}

		$this->instance = $this->createInstance( $login );
	}

	/**
	 * Unset the instance after we are done with it.
	 */
	public function __destruct()
	{
		$this->logout();
		$this->instance = null;
	}

	/**
	 * @param bool $login
	 * @return \TeamSpeakAdmin
	 */
	protected function createInstance( $login = true )
	{
		if ( !is_null( $this->instance ) )
		{
			return $this->instance;
		}

		$config = [
			'host' => $this->settings->teamspeak_server_ip,
			'username' => $this->settings->teamspeak_query_admin,
			'password' => $this->settings->teamspeak_query_password,
			'query_port' => $this->settings->teamspeak_query_port,
		];

		try
		{
			return $this->connect( $config['host'], $config['query_port'], $config['username'], $config['password'], $login );
		}
		catch ( \IPS\teamspeak\Exception\ConnectionException $e )
		{
			\IPS\Log::log( $e, 'teamspeak_connect' );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'teamspeak_connect_2' );
		}
	}

	/**
	 * Get the TS server instance.
	 *
	 * @return \TeamSpeakAdmin
	 */
	public function getInstance()
	{
		return $this->instance;
	}

	/**
	 * Return called class.
	 *
	 * @param \TeamSpeakAdmin $tsInstance
	 * @param bool $login
	 * @return mixed
	 */
	public static function i( \TeamSpeakAdmin $tsInstance = null, $login = true )
	{
		$classname = get_called_class();
		return new $classname( $tsInstance, $login );
	}

	/**
	 * Logout from the TS server.
	 */
	public function logout()
	{
		if ( $this->instance instanceof \TeamSpeakAdmin )
		{
			$this->instance->logout();
		}
	}

	/**
	 * Connect to the TS server.
	 *
	 * @param $host Hostname/IP
	 * @param $qPort Query Port
	 * @param $username Server admin name
	 * @param $password Server admin password
	 * @param bool $login
	 * @param int $timeout Connection timeout
	 * @return \TeamSpeakAdmin
	 * @throws \IPS\teamspeak\Exception\ConnectionException
	 */
	protected function connect( $host, $qPort, $username, $password, $login = true, $timeout = 2 )
	{
		$ts = new \TeamSpeakAdmin( $host, $qPort, $timeout );

		if ( $ts->succeeded( $e = $ts->connect() ) )
		{
			if ( $login )
			{
				if ( !$ts->succeeded( $e = $ts->login( $username, $password ) ) )
				{
					throw new \IPS\teamspeak\Exception\ConnectionException( $this->arrayToString( $e['errors'] ) );
				}
			}

			if ( $ts->succeeded( $e = $ts->selectServer( $this->settings->teamspeak_virtual_port ) ) )
			{
				if ( !$login )
				{
					return $ts;
				}

				if ( $ts->succeeded( $e = $ts->setName( $this->settings->teamspeak_query_nickname ?: mt_rand( 10, 1000 ) ) ) )
				{
					return $ts;
				}
			}
		}

		throw new \IPS\teamspeak\Exception\ConnectionException( $this->arrayToString( $e['errors'] ) );
	}

	/**
	 * Extract the required data from the array that we get from \TeamSpeakAdmin.
	 *
	 * @param \TeamSpeakAdmin $ts
	 * @param array $data
	 * @param bool $bool Only check if it succeeded (no data required)?
	 * @return bool|mixed
	 * @throws \Exception
	 */
	protected static function getReturnValue( \TeamSpeakAdmin $ts, array $data, $bool = false )
	{
		if ( $ts->succeeded( $data ) )
		{
			if ( !$bool )
			{
				return $ts->getElement( 'data', $data );
			}

			return true;
		}

		throw new \Exception( static::arrayToString( $ts->getElement( 'errors', $data ) ) );
	}

	/**
	 * Convert the errors array (from \TeamSpeakAdmin) into a string for logging.
	 *
	 * @param array $errors
	 * @return string
	 */
	protected static function arrayToString( array $errors )
	{
		$string = '';

		foreach ( $errors as $error )
		{
			$string .= $error . ' ';
		}

		return trim( $string );
	}
}