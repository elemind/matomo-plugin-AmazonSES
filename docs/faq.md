## FAQ

__Which emails are sent through Amazon SES?__

All of them. The plugin replaces the Matomo mail transport, so scheduled reports, alerts, password resets, invitations and emails sent by other plugins all go through SES while the plugin is activated.

__What happens if an SMTP server is also configured?__

Amazon SES always wins while the plugin is active: Matomo uses a single mail transport, and the plugin replaces the core SMTP / `mail()` transport. The host, port, username, password and encryption set under *General settings → Email server settings* are ignored, and no email is sent twice. The *Amazon SES* admin page shows a warning when an SMTP server is configured.

The **sender address and name** from that same form are still used: they are Matomo's noreply address and name, and the plugin uses them unless you set *Sender email / Sender name* in the plugin settings.

If Amazon SES fails, the plugin does **not** fall back to SMTP. Deactivate the plugin to go back to SMTP; no other change is needed.

__I get "Email address is not verified".__

Amazon SES only sends from verified identities. Verify the sender address, or better its whole domain, in the SES console of the **same region** configured in the plugin. Then either use that address as Matomo's noreply address or set it as *Sender email* in the plugin settings.

In sandbox mode the **recipients** must be verified too, and if your IAM policy restricts `Resource` to specific identities, the recipients must be listed there as well. Request production access in the SES console to email any user.

__Where do the credentials come from?__

The plugin tries, in this order:

1. The access key set in the plugin settings (or in `config.ini.php`).
2. The environment variables `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` (plus `AWS_SESSION_TOKEN`).
3. The ECS / Fargate task role.
4. The EC2 instance profile, via IMDSv2.

The status page shows which source is in use.

__What happens if Amazon SES is down or rejects a message?__

The send fails and Matomo logs the AWS error message (for example `MessageRejected` or `Throttling`). The plugin never falls back to SMTP silently, so a configuration problem doesn't go unnoticed.

__How do I handle bounces and complaints?__

Create an SES *configuration set* with an event destination (SNS, CloudWatch, EventBridge…) and enter its name in the plugin settings. By default SES also adds bouncing addresses to your account-level suppression list.

__Can I use a VPC endpoint or a local SES mock?__

Yes. Set `endpoint` in the `[AmazonSES]` section of `config.ini.php`, or set the `AWS_ENDPOINT_URL_SESV2` environment variable.

__Does it work behind an HTTP proxy?__

Yes. Calls to the SES API use the proxy configured in Matomo's `[proxy]` section. Calls to the ECS/EC2 metadata endpoints never go through the proxy.

__Is there a size limit?__

Amazon SES v2 accepts messages up to 40 MB, including attachments after encoding.
