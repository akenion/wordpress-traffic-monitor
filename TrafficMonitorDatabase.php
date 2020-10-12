<?php

if(!defined('WPINC'))
	exit;

require_once 'TrafficMonitorRecord.php';

/**
 * A wrapper for managing the necessary database elements for traffic monitor
 * TODO: Limit storage capacity and/or number of records retained to prevent exceeding database storage
 */
class TrafficMonitorDatabase{

	//The date format needed for MySQL
	const DATE_FORMAT='Y-m-d H:i:s';

	private wpdb $wpdb;

	/**
	 * @param $wpdb the wpdb instance to use
	 */
	public function __construct(wpdb $wpdb){
		$this->wpdb=$wpdb;
	}

	/**
	 * Create the tables for traffic monitor, if necessary
	 * @return true on success, false on failure
	 */
	public function createSchema(): bool{
		return $this->wpdb->query(
			<<<SQL
			CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}traffic_clients(
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				ip VARCHAR(45) NOT NULL,
				agent TEXT NOT NULL
			);
SQL
		)
		&& $this->wpdb->query(
			<<<SQL
			CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}traffic_records(
				client_id BIGINT UNSIGNED NOT NULL,
				time TIMESTAMP NOT NULL,
				user_id BIGINT(20) UNSIGNED,
				method VARCHAR(10) NOT NULL,
				url TEXT NOT NULL,
				FOREIGN KEY (client_id) REFERENCES {$this->wpdb->prefix}traffic_clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
				FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users (ID) ON DELETE SET NULL ON UPDATE CASCADE
			);
SQL
		);
	}

	/**
	 * Delete all traffic monitor tables
	 * @return true on success, false on failure
	 */
	public function deleteSchema(): bool{
		$queries=[
			"DELETE FROM {$this->wpdb->prefix}traffic_records;",
			"DELETE FROM {$this->wpdb->prefix}traffic_clients;",
			"DROP TABLE {$this->wpdb->prefix}traffic_records;",
			"DROP TABLE {$this->wpdb->prefix}traffic_clients;"
		];
		foreach($queries as $query){
			if(!$this->wpdb->query($query)){
				error_log("Query failed: $query");
				return false;
			}
		}
		return true;
	}

	/**
	 * Insert a client record(if not already recorded) and return the corresponding ID
	 * @return the client ID or null if the insert fails
	 */
	private function insertClient(TrafficMonitorClient $client): ?int{
		$id=$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}traffic_clients WHERE ip=%s AND agent=%s", $client->getIp(), $client->getAgent()));
		if($id===null){
			if($this->wpdb->insert("{$this->wpdb->prefix}traffic_clients", ['ip'=>$client->getIp(), 'agent'=>$client->getAgent()]))
				$id=$this->wpdb->insert_id;
		}
		return $id;
	}

	/**
	 * Store a new record in the database
	 * @param $record the record to store
	 */
	public function record(TrafficMonitorRecord $record): bool{
		$clientId=$this->insertClient($record->getClient());
		if($clientId===null)
			return false;
		return $this->wpdb->insert(
			"{$this->wpdb->prefix}traffic_records",
			[
				'client_id'=>$clientId,
				'time'=>$record->getTime()->format(self::DATE_FORMAT),
				'user_id'=>$record->getUserId(),
				'method'=>$record->getMethod(),
				'url'=>$record->getUrl()
			]
		) === 1;
	}

	/**
	 * Load recent records from the database
	 * @param $count the number of records to display
	 * @return TrafficMonitorRecord[]
	 */
	public function loadRecords(int $count): array{
		//Count must be an int so it can be safely added to the query
		$results=$this->wpdb->get_results(<<<SQL
			SELECT
				ip,
				agent,
				time,
				user_id,
				method,
				url
			FROM
				{$this->wpdb->prefix}traffic_clients c
				JOIN {$this->wpdb->prefix}traffic_records r ON r.client_id=c.id
			ORDER BY
				time DESC
			LIMIT $count;
SQL
			, ARRAY_A);
		if(empty($results))
			error_log('No traffic records found');
		$records=[];
		foreach($results as $result){
			$records[]=new TrafficMonitorRecord(
				new TrafficMonitorClient($result['ip'], $result['agent']),
				$result['method'],
				$result['url'],
				DateTime::createFromFormat(self::DATE_FORMAT, $result['time']),
				$result['user_id']
			);
		}
		return $records;
	}

	/**
	 * Get the top records(most frequent values) for a given record column
	 * @param $column the column to evaluate
	 * @param $count the desired number of records
	 * @return an associative array with the record as the key and the count as the value
	 */
	private function getTop(string $column, int $count): array{
		//This is a private method and hence can only be called internally, so $column will always be a safe value and this is not vulnerable to SQL injection
		$results=$this->wpdb->get_results(<<<SQL
			SELECT
				$column,
				COUNT(*) AS request_count
			FROM
				{$this->wpdb->prefix}traffic_clients c
				JOIN {$this->wpdb->prefix}traffic_records r ON r.client_id=c.id
			GROUP BY
				$column
			ORDER BY
				2 DESC
			LIMIT $count;
SQL
			, ARRAY_A);
		$top=[];
		foreach($results as $result)
			$top[$result[$column]]=intval($result['request_count']);
		return $top;
	}

	/**
	 * Get the top $count IP addresses
	 * @param $count the desired number of records
	 * @return an associative array with the record as the key and the count as the value
	 */
	public function getTopIps(int $count): array{
		return $this->getTop('ip', $count);
	}

	/**
	 * Get the top $count user agents
	 * @param $count the desired number of records
	 * @return an associative array with the record as the key and the count as the value
	 */
	public function getTopAgents(int $count): array{
		return $this->getTop('agent', $count);
	}

	/**
	 * Get the top $count request URLs
	 * @param $count the desired number of records
	 * @return an associative array with the record as the key and the count as the value
	 */
	public function getTopUrls(int $count): array{
		return $this->getTop('url', $count);
	}

	/**
	 * Purge all records prior to the specified date
	 * @param $date the earliest date for which records should be retained
	 * @return true on success, false on failure
	 */
	public function purgeRecords(DateTimeInterface $date): bool{
		return $this->wpdb->query(
			$this->wpdb->prepare(
				<<<SQL
				DELETE FROM {$this->wpdb->prefix}traffic_records WHERE time<%s;
SQL
			, $date->format(self::DATE_FORMAT))
		);
	}

}
