## Changelog

### 0.1.1 - 2026-10-05

* Security: a custom SES endpoint must now use `https://`. Plain `http://` sent the AWS-signed requests and the email contents unencrypted; it is now rejected unless `allowInsecureEndpoint = 1` (or `AMAZONSES_ALLOW_INSECURE_ENDPOINT=1`) is set, which is meant for a local SES mock only. Endpoints with credentials, a query string or a fragment are rejected too.
* Security: HTTP requests are restricted to the HTTP and HTTPS protocols.
* The admin page reports an invalid region or endpoint, and warns when a plain HTTP endpoint is allowed.
* Upgrade note: if you use a plain `http://` endpoint (e.g. a local mock), add `allowInsecureEndpoint = 1` to the `[AmazonSES]` section of `config.ini.php`, otherwise sending fails.

### 0.1.0 - 2026-09-30

* First release.
* Sends all Matomo emails through the Amazon SES API v2 (raw MIME, AWS Signature V4, no SDK dependency).
* Credential chain: plugin settings, environment variables, ECS task role, EC2 instance profile (IMDSv2).
* Settings for the region, configuration set and sender override; every setting can also be set in config.ini.php.
* Admin page with the effective configuration and a test email button (no AWS call besides sending).
* Warning on the admin page when a core SMTP server is configured (it is ignored while the plugin is active).
* Only `ses:SendEmail` / `ses:SendRawEmail` IAM permissions are required.
* English and Italian translations.
