<?php
declare(strict_types=1);

// Basic configuration for PDO connection
// Prefer environment variables if available

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'shortener';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbCharset = 'utf8mb4';

// Application timezone (used to compute the current day boundary)
$appTimezone = getenv('APP_TIMEZONE') ?: 'UTC';
date_default_timezone_set($appTimezone);

/**
 * Returns a configured PDO instance.
 */
function getPdo(): PDO {
	global $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbCharset;
	$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	$pdo = new PDO($dsn, $dbUser, $dbPass, $options);
	return $pdo;
}

/**
 * Sends an HTTP redirect and terminates the script.
 */
function redirect_to(string $url, int $statusCode = 302): void {
	if (!headers_sent()) {
		http_response_code($statusCode);
		header('Location: ' . $url);
	}
	exit;
}

/**
 * Send a text response with an HTTP status code and exit.
 */
function respond(int $statusCode, string $message): void {
	http_response_code($statusCode);
	header('Content-Type: text/plain; charset=utf-8');
	echo $message;
	exit;
}

