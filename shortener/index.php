<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = getPdo();

// Extract namespace and code from query (preferred via rewrites) or from path
$namespace = isset($_GET['namespace']) ? trim((string)$_GET['namespace']) : null;
$code = isset($_GET['code']) ? trim((string)$_GET['code']) : null;

if ($code === null || $code === '') {
	// Fallback: try to parse /{namespace}/{code} or /{code} from REQUEST_URI
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
	$parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
	if (count($parts) >= 2) {
		$namespace = $parts[0];
		$code = $parts[1];
	} elseif (count($parts) === 1) {
		$code = $parts[0];
		$namespace = null;
	}
}

if ($code === null || $code === '') {
	respond(404, 'Short link not found');
}

// Find the shortener by namespace+code
$shortenerSql = 'SELECT id FROM shorteners WHERE code = :code AND (namespace <=> :namespace) AND is_active = 1 LIMIT 1';
$stmt = $pdo->prepare($shortenerSql);
$stmt->execute([':code' => $code, ':namespace' => $namespace]);
$shortener = $stmt->fetch();

if (!$shortener) {
	respond(404, 'Short link not found');
}

$shortenerId = (int)$shortener['id'];

// Load ordered active targets
$targetsSql = 'SELECT id, target_url, daily_quota FROM shortener_targets WHERE shortener_id = :sid AND is_active = 1 ORDER BY position ASC';
$targetsStmt = $pdo->prepare($targetsSql);
$targetsStmt->execute([':sid' => $shortenerId]);
$targets = $targetsStmt->fetchAll();

if (!$targets) {
	respond(404, 'No targets configured');
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');

// Attempt to allocate one click to the first target with remaining quota
$pdo->beginTransaction();

try {
	$allocatedUrl = null;
	$retryOnDuplicate = false;

	foreach ($targets as $target) {
		$targetId = (int)$target['id'];
		$quota = (int)$target['daily_quota'];

		if ($quota <= 0) {
			continue; // Skip zero-quota targets
		}

		// Lock existing counter row if present
		$lockStmt = $pdo->prepare('SELECT clicks FROM daily_clicks WHERE target_id = :tid AND click_date = :d FOR UPDATE');
		$lockStmt->execute([':tid' => $targetId, ':d' => $today]);
		$row = $lockStmt->fetch();

		if ($row) {
			$current = (int)$row['clicks'];
			if ($current < $quota) {
				$upd = $pdo->prepare('UPDATE daily_clicks SET clicks = clicks + 1, updated_at = NOW() WHERE target_id = :tid AND click_date = :d');
				$upd->execute([':tid' => $targetId, ':d' => $today]);
				$allocatedUrl = (string)$target['target_url'];
				break;
			}
			// else exhausted; try next target
		} else {
			// No row yet for today; create with clicks = 1 if under quota
			if ($quota > 0) {
				try {
					$ins = $pdo->prepare('INSERT INTO daily_clicks (target_id, click_date, clicks, updated_at) VALUES (:tid, :d, 1, NOW())');
					$ins->execute([':tid' => $targetId, ':d' => $today]);
					$allocatedUrl = (string)$target['target_url'];
					break;
				} catch (PDOException $e) {
					// Possible duplicate due to race; retry by re-locking the row once
					$retryOnDuplicate = true;
				}
			}
		}

		if ($retryOnDuplicate) {
			$retryOnDuplicate = false;
			$lockStmt->execute([':tid' => $targetId, ':d' => $today]);
			$row = $lockStmt->fetch();
			if ($row && (int)$row['clicks'] < $quota) {
				$upd = $pdo->prepare('UPDATE daily_clicks SET clicks = clicks + 1, updated_at = NOW() WHERE target_id = :tid AND click_date = :d');
				$upd->execute([':tid' => $targetId, ':d' => $today]);
				$allocatedUrl = (string)$target['target_url'];
				break;
			}
		}
	}

	if ($allocatedUrl !== null) {
		$pdo->commit();
		redirect_to($allocatedUrl, 302);
	}

	$pdo->rollBack();
	respond(429, 'Daily limit reached for all targets');
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	respond(500, 'Internal error');
}

