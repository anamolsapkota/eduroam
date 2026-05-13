# Google Drive Log Backup Setup for Eduroam

This guide explains how to set up automatic daily backup of FreeRADIUS logs to Google Drive using rclone.

## Prerequisites

- A running eduroam server with FreeRADIUS logging to `/var/log/freeradius/radius.log`
- A Google account with Google Drive access
- SSH/root access to the server

---

## Step 1: Install rclone

```bash
curl https://rclone.org/install.sh | sudo bash
```

Verify the installation:

```bash
rclone version
```

---

## Step 2: Configure a Google Drive remote

Run the rclone configuration wizard:

```bash
rclone config
```

Follow these prompts:

1. Type `n` for a new remote
2. Enter a name (e.g., `gdrive`)
3. Choose the storage type — select **Google Drive** (type `drive`)
4. For `client_id` and `client_secret`, press Enter to use defaults (or provide your own OAuth app credentials for higher rate limits)
5. For scope, choose **1** (full access to all files)
6. Leave `root_folder_id` and `service_account_file` blank
7. For advanced config, choose `n`
8. For auto config:
   - If the server has a browser: choose `y` and complete the OAuth flow
   - If the server is headless: choose `n`, then copy the provided URL to a machine with a browser, authorize, and paste back the verification code
9. Choose `n` for shared drive (unless you want to use a shared drive)
10. Confirm with `y`

Verify the remote works:

```bash
rclone listremotes
rclone lsd gdrive:
```

---

## Step 3: Configure backup settings in the admin dashboard

1. Log in to the eduroam admin panel
2. Go to **Settings** (`/eduroam/admin/settings.php`)
3. Scroll down to the **Google Drive Log Backup** section
4. Fill in the fields:

| Field | Description | Example |
|-------|-------------|---------|
| **Rclone Remote Name** | The name you gave the remote in Step 2 | `gdrive` |
| **Backup Status** | Enable or disable the backup | `Enable` |
| **Drive Base Folder** | Top-level folder in Google Drive for all logs | `eduroam-logs` |
| **Server Identifier** | Unique name for this server (used as subfolder) | `idp-nec.nren.net.np` |

5. Click **Save Settings**

---

## Step 4: Set up the cron job

Open the root crontab:

```bash
sudo crontab -e
```

Add the following line:

```
55 23 * * * /usr/bin/php /var/www/yoursite/eduroam/scripts/backup_radius_log.php >/dev/null 2>&1
```

> **Note:** Replace `/var/www/yoursite/eduroam/` with the actual installation path on your server.

This runs the backup script at 23:55 every night, uploading that day's radius log entries to Google Drive.

---

## Step 5: Test the setup manually

Run the script manually to verify everything works:

```bash
/usr/bin/php /var/www/yoursite/eduroam/scripts/backup_radius_log.php
```

Expected output on success:

```
Successfully uploaded radius-log-2026-05-13.log to gdrive:eduroam-logs/idp-nec.nren.net.np/
```

If backup is disabled in settings:

```
Backup disabled in settings.
```

If rclone remote is not configured:

```
Error: rclone remote name not configured.
```

---

## Resulting folder structure in Google Drive

```
eduroam-logs/
└── idp-nec.nren.net.np/
    ├── radius-log-2026-05-11.log
    ├── radius-log-2026-05-12.log
    └── radius-log-2026-05-13.log
```

If you have multiple servers, each gets its own subfolder:

```
eduroam-logs/
├── idp-nec.nren.net.np/
│   ├── radius-log-2026-05-13.log
│   └── ...
├── idp-tu.nren.net.np/
│   ├── radius-log-2026-05-13.log
│   └── ...
└── idp-ku.nren.net.np/
    ├── radius-log-2026-05-13.log
    └── ...
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `Error: rclone not found in PATH` | Ensure rclone is installed and accessible. Add `PATH=/usr/local/bin:/usr/bin:/bin` to the top of your crontab if needed. |
| `Error: Radius log not readable` | Check that the web server user has read access to `/var/log/freeradius/radius.log` |
| `Backup disabled in settings` | Go to Settings page and set Backup Status to **Enable** |
| `rclone error (exit 1): ...` | Check that the remote name matches exactly what `rclone listremotes` shows (without the trailing colon) |
| No output / nothing happens | Verify the cron job is running (`grep CRON /var/log/syslog`) and that the database is accessible from the script |

---

## Security notes

- The rclone config file is stored at `~/.config/rclone/rclone.conf` (for the user running the cron). Ensure this file has restricted permissions (`chmod 600`).
- The backup script uses `escapeshellarg()` on all values passed to shell commands to prevent injection.
- Temporary log files are stored in the `stats/` directory during upload and deleted after successful transfer.
