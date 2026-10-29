# Local email worker (Windows)

Run `php scripts/mail-worker.php` from the API directory to process queued customer notifications and retry pending store emails. A file lock prevents duplicate supervisors. Output is written to `storage/logs/mail-worker.log`.

The current Windows user's Startup folder contains `Fashion Store Mail.lnk`, pointing to this script using XAMPP PHP. It starts at sign-in; MySQL and an internet connection must be available. The supervisor retries when a child command exits, including after a temporary database outage. Failed queue jobs still need diagnosis and an explicit retry after their attempt limit.

The computer must stay on for local mail processing. Production should use an always-on backend with a supervised Laravel queue worker and scheduler. A fixed outbound IP authorized in Brevo avoids repeated authorization after a home IP changes.

Brevo IP restrictions apply separately to API and SMTP. Settings > Security > Authorized IPs allows blocking to be deactivated for each, but doing so removes the IP protection. Keep credentials private and never commit `.env`.

Newsletter welcome workflows run in Brevo, independently of this worker. A subscriber already in the newsletter list does not generate another list-addition event. Test an active workflow using a new address, or manually enroll an existing contact through Brevo's supported automation controls.
