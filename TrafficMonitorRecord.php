<?php

if(!defined('WPINC'))
	exit;

require_once "TrafficMonitorClient.php";

class TrafficMonitorRecord{

	private TrafficMonitorClient $client;
	private string $method, $url;
	private DateTime $time;
	private ?string $userId;

	public function __construct(TrafficMonitorClient $client, string $method, string $url, DateTimeInterface $time, string $userId=null){
		$this->client=$client;
		$this->method=$method;
		$this->url=$url;
		$this->time=$time;
		$this->userId=$userId;
	}

	public function getClient(): TrafficMonitorClient{
		return $this->client;
	}

	public function getMethod(): string{
		return $this->method;
	}

	public function getUrl(): string{
		return $this->url;
	}

	public function getTime(): DateTimeInterface{
		return $this->time;
	}

	public function hasUserId(): bool{
		return $this->userId!==null;
	}

	public function getUserId(): ?string{
		return $this->userId;
	}

	public static function fromGlobals(): self{
		$userId=get_current_user_id();
		if($userId===0)
			$userId=null;
		return new self(
			TrafficMonitorClient::fromGlobals(),
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			new DateTime(),
			$userId
		);
	}

}
