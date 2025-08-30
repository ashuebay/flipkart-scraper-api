PHP Shortener with MySQL-backed daily per-target limits

Overview
- **Goal**: Map a short link to multiple destination URLs with per-day quotas in order. Requests are routed to URL1 until its quota is consumed, then to URL2, and so on. Quotas reset each day automatically.

Schema
- Import `schema.sql` into your MySQL database.

Configuration
- Copy `config.php` and set environment variables or edit defaults:
  - **DB_HOST**, **DB_PORT**, **DB_NAME**, **DB_USER**, **DB_PASS**
  - **APP_TIMEZONE**: default `UTC`. Determines the day boundary.

Routing
- Apache: place this project as a vhost docroot and keep the included `.htaccess`.
- Nginx (example):
```nginx
location / {
    try_files $uri /index.php?$query_string;
}
```

How it works
- Each shortener has ordered targets with a `daily_quota`.
- For each request, the app starts a transaction and finds the first target with remaining quota for today. It atomically increments the counter row in `daily_clicks` and redirects.
- Counters are stored per `click_date`, so no reset job is required.

Creating a link example
```sql
-- Create shortener fs9.in/ppad/abc (namespace=ppad, code=abc)
INSERT INTO shorteners (`namespace`, `code`) VALUES ('ppad', 'abc');
SET @sid = LAST_INSERT_ID();

-- Add five targets in order; daily quotas
INSERT INTO shortener_targets (shortener_id, position, target_url, daily_quota) VALUES
(@sid, 1, 'https://example.com/url1', 3000),
(@sid, 2, 'https://example.com/url2', 2000),
(@sid, 3, 'https://example.com/url3', 2000),
(@sid, 4, 'https://example.com/url4', 2000),
(@sid, 5, 'https://example.com/url5', 2000);
```

Requesting
- Hit `/ppad/abc` (or `/abc` if you omit the namespace). The first 3000 requests today go to `url1`, the next 2000 to `url2`, and so forth.

Notes
- If all quotas are exhausted for today, the server replies `429 Daily limit reached for all targets`.
- Adjust status codes or behavior in `index.php` as needed.

