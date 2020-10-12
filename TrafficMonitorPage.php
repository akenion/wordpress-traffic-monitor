<?php

if(!defined('WPINC'))
	exit;

/**
 * A page for viewing traffic monitor logs
 */
class TrafficMonitorPage{

	public const TITLE='Monitor Traffic';
	public const MAX_RECORDS=25;

	private string $capability;
	private TrafficMonitorDatabase $db;
	private int $topCount;

	/**
	 * @param $capability the capability required to view the page
	 * @param $db the database instance to use
	 * @param $topCount the maximum number of "top" records to display per category
	 */
	public function __construct(string $capability, TrafficMonitorDatabase $db, int $topCount=10){
		$this->capability=$capability;
		$this->db=$db;
		$this->topCount=$topCount;
	}

	/**
	 * Check if the current user has the appropriate permissions to view the page
	 * @return true if authorized, false otherwise
	 */
	private function checkPermissions(): bool{
		return current_user_can($this->capability);
	}

	/**
	 * Render the page
	 */
	private function render(): void{
		wp_enqueue_style('traffic_monitor_styles');
		$top=[
			'User Agents'=>$this->db->getTopAgents($this->topCount),
			'IPs'=>$this->db->getTopIps($this->topCount),
			'URLs'=>$this->db->getTopUrls($this->topCount)
		];
?>
		<div class="wrap">
			<h1>Traffic Monitor</h1>
			<?php foreach($top as $label=>$data): ?>
			<h2>Top <?= htmlentities($label) ?></h2>
			<ol>
				<?php foreach($data as $value=>$count): ?>
					<li><?= htmlentities($value) ?> (<?= $count ?>)</li>
				<?php endforeach ?>
			</ol>
			<?php endforeach ?>
			<h2>Recent Requests</h2>
			<table class="recent-requests">
				<tr>
					<th>Time</th>
					<th>IP</th>
					<th>User ID</th>
					<th>Method</th>
					<th>URL</th>
					<th>User Agent</th>
				</tr>
				<?php foreach($this->db->loadRecords(self::MAX_RECORDS) as $record): ?>
				<tr>
					<td><?= $record->getTime()->format('d/m/Y H:i:s') ?></td>
					<td><?= htmlentities($record->getClient()->getIp()) ?></td>
					<td><?php if($record->hasUserId()): ?><?= htmlentities($record->getUserId()) ?><?php else: ?>None<?php endif ?></td>
					<td><?= htmlentities($record->getMethod()) ?></td>
					<td class="overflow"><?= htmlentities($record->getUrl()) ?></td>
					<td class="overflow"><?= htmlentities($record->getClient()->getAgent()) ?></td>
				</tr>
				<?php endforeach ?>
			</table>
		</div>
<?php
	}

	/**
	 * Check permissions and render the page
	 */
	public function display(): void{
		if($this->checkPermissions())
			$this->render();
	}

}
