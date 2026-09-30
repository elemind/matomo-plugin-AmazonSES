## Changelog

### 0.1.0 - 2026-09-30

* First release.
* Sends all Matomo emails through the Amazon SES API v2 (raw MIME, AWS Signature V4, no SDK dependency).
* Credential chain: plugin settings, environment variables, ECS task role, EC2 instance profile (IMDSv2).
* Settings for the region, configuration set and sender override; every setting can also be set in config.ini.php.
* Admin page with the effective configuration and a test email button (no AWS call besides sending).
* English and Italian translations.
