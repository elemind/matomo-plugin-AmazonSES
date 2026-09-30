# Matomo AmazonSES Plugin

[![Plugin AmazonSES Tests](https://github.com/elemind/matomo-plugin-AmazonSES/actions/workflows/matomo-tests.yml/badge.svg)](https://github.com/elemind/matomo-plugin-AmazonSES/actions/workflows/matomo-tests.yml)

## Description

Send every Matomo email through the **Amazon SES API v2** over HTTPS. You don't need SMTP credentials or an open port 25/587.

The plugin replaces the Matomo mail transport. Scheduled reports (including PDF attachments), password resets, invitations, alerts and all other emails go through Amazon SES without any other change to Matomo.

**Features**

* Amazon SES API v2 (`SendEmail` with raw MIME), signed with AWS Signature V4. No AWS SDK is bundled, so the plugin stays small.
* Credentials are resolved in this order: the plugin settings, the `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` environment variables, the **ECS task role**, then the **EC2 instance profile** (IMDSv2).
* Optional sender override, for an address or domain verified in SES.
* Optional **configuration set**, so you can publish bounces, complaints and deliveries to SNS, CloudWatch or EventBridge.
* An admin page (*Administration → System → Amazon SES*) with the effective configuration (region, credentials source, sender) and a **Send test email** button. It makes no AWS call besides sending, so an IAM policy that only allows sending is enough.
* Every setting can also be set in `config.ini.php` for container and infrastructure-as-code deployments.
* Errors are never hidden. If SES rejects a message, Matomo logs the exact AWS error; there is no silent fallback to SMTP.

**Requirements**: Matomo 5, PHP 7.2.5+ with the cURL extension, and an Amazon SES identity (email address or domain) verified in the region you use.

## Installation

Install the plugin from the Matomo Marketplace (*Administration → Platform → Marketplace*), or copy it to `plugins/AmazonSES` and activate it:

```
./console plugin:activate AmazonSES
```

As soon as the plugin is active, **all** Matomo emails go through Amazon SES. Deactivate the plugin to go back to the core SMTP / `mail()` transport.

## Configuration

Go to *Administration → System → General settings → Amazon SES*:

| Setting | Description |
|---|---|
| AWS region | Region of your SES account (e.g. `eu-west-1`). If empty, `AWS_REGION` is used, else `us-east-1`. |
| Access key ID / Secret access key | Leave empty to use environment variables or an IAM role (recommended). |
| Configuration set | Optional SES configuration set name. |
| Sender email / name | Optional override of the Matomo "from" address. It must be verified in SES. |

### config.ini.php

A value set in `config/config.ini.php` wins over the UI. The field then becomes read-only in the UI.

```ini
[AmazonSES]
region = "eu-west-1"
senderEmail = "analytics@example.com"
senderName = "Matomo Analytics"
configurationSet = "matomo"
; advanced, only available here: custom endpoint (VPC endpoint, local mock) and HTTP timeout in seconds
;endpoint = "https://vpce-xxxx.email.eu-west-1.vpce.amazonaws.com"
;timeout = 15
```

The standard AWS environment variables are also supported: `AWS_REGION`, `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_SESSION_TOKEN`, `AWS_ENDPOINT_URL_SESV2`, `AWS_ENDPOINT_URL`, `AWS_CONTAINER_CREDENTIALS_RELATIVE_URI`/`FULL_URI` and `AWS_EC2_METADATA_DISABLED`.

### IAM policy

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["ses:SendEmail", "ses:SendRawEmail"],
      "Resource": "*"
    }
  ]
}
```

These are the only permissions the plugin needs. You can restrict `Resource` to your identity and configuration set ARNs. While the account is in the SES sandbox, SES also checks the permission against the **recipient** identities, so they must be verified and covered by `Resource`.

### Security notes

* The secret access key entered in the UI is stored in the Matomo database, in the same way as the core SMTP password. It is never sent back to the browser. For production we recommend an IAM role (EC2/ECS) or environment variables.
* Only super users can see the settings, the status page and the test email API.

## Development

The repository includes a Docker environment with Matomo, MariaDB and a local SES mock ([aws-ses-v2-local](https://github.com/domdomegg/aws-ses-v2-local)):

```
make up          # start Matomo (http://localhost:8080) and the SES mock (http://localhost:8005)
make install     # headless Matomo install, user admin / admin123, plugin activated
make test-email  # send an email with the core console command, then open http://localhost:8005
make test        # unit + integration tests with the Matomo test framework
make unit-test   # fast unit tests, no Matomo needed
make vue-build   # rebuild vue/dist after changing vue/src
make reset       # delete everything
```

To send real emails, copy `.env.example` to `.env`, set your AWS credentials and an empty `AWS_ENDPOINT_URL_SESV2`, then run `docker compose up -d matomo`.

## License

GPL v3 or later
