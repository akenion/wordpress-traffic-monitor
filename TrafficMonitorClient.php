<?php

if(!defined('WPINC'))
	exit;

/**
 * A client to the website for traffic monitoring purposes
 */
class TrafficMonitorClient{

	private string $ip, $agent;

	/**
	 * @param $ip the client's IP address(as a string)
	 * @param $age the client's user agent 
	 */
	public function __construct(string $ip, string $agent){
		$this->ip=$ip;
		$this->agent=$agent;
	}

	public function getIp(): string{
		return $this->ip;
	}

	public function getAgent(): string{
		return $this->agent;
	}

	/**
	 * Determine the IP of the client making the current request
	 */
	private static function resolveClientIp(): string{
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Instantiate a client instance using PHP's global state for the current request
	 * @return the newly created client
	 */
	public static function fromGlobals(): self{
		return new self(
			self::resolveClientIp(),
			$_SERVER['HTTP_USER_AGENT']
		);
	}

}
