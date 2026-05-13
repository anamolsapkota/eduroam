# FreeRADIUS Post-Auth SQL Logging Fix

## Problem

The Authentication Activity chart (Accept/Reject/Other) on the Monitoring page shows empty data across all time ranges. This is because FreeRADIUS is not logging authentication results to the `radpostauth` database table.

The `post-auth` section in the eduroam site config is missing the `sql` module, so successful and failed authentications are never recorded in the database.

## Affected File

```
/etc/freeradius/3.0/sites-enabled/eduroam
```

## What to Change

Add `sql` in two places inside the `post-auth` block:

```diff
     post-auth {
         ...
         linelog_send_accept
+        sql
         Post-Auth-Type REJECT {
             attr_filter.access_reject
             linelog_send_reject
+            sql
         }
     }
```

- First `sql` (after `linelog_send_accept`) — logs successful authentications (Access-Accept)
- Second `sql` (after `linelog_send_reject`, inside `Post-Auth-Type REJECT`) — logs failed authentications (Access-Reject)

## Steps Per Server

```bash
# 1. Backup current config
cp /etc/freeradius/3.0/sites-enabled/eduroam /etc/freeradius/3.0/eduroam.bak.$(date +%Y%m%d)

# 2. Add sql after linelog_send_accept (logs Access-Accept)
sed -i '/linelog_send_accept/a\        sql' /etc/freeradius/3.0/sites-enabled/eduroam

# 3. Add sql after linelog_send_reject (logs Access-Reject)
sed -i '/linelog_send_reject/a\            sql' /etc/freeradius/3.0/sites-enabled/eduroam

# 4. Test config for errors
freeradius -XC 2>&1 | tail -3
# Expected: "Configuration appears to be OK"

# 5. Restart FreeRADIUS
systemctl restart freeradius

# 6. Verify it's running
systemctl status freeradius | head -5

# 7. Verify data is being logged (wait a few seconds for auth attempts)
mysql -u root radius -e 'SELECT COUNT(*) FROM radpostauth;'
```

## One-Liner for Mass Deployment

```bash
cp /etc/freeradius/3.0/sites-enabled/eduroam /etc/freeradius/3.0/eduroam.bak.$(date +%Y%m%d) && \
sed -i '/linelog_send_accept/a\        sql' /etc/freeradius/3.0/sites-enabled/eduroam && \
sed -i '/linelog_send_reject/a\            sql' /etc/freeradius/3.0/sites-enabled/eduroam && \
freeradius -XC 2>&1 | tail -1 && \
systemctl restart freeradius && \
echo "Done: $(hostname)"
```

## Important Notes

- Charts will start populating immediately as users authenticate after the restart.
- Historical data will **not** appear retroactively. Only new authentications from the restart point forward are recorded.
- Do **not** place backup files inside `/etc/freeradius/3.0/sites-enabled/` — FreeRADIUS loads all files in that directory, causing a "Duplicate virtual server" error. Backups are stored one level up in `/etc/freeradius/3.0/`.
- The `radpostauth` table must already exist in the radius database (it is part of the standard FreeRADIUS SQL schema).

## Verification

After applying the fix, you can verify data is flowing:

```bash
# Check recent entries
mysql -u root radius -e 'SELECT * FROM radpostauth ORDER BY id DESC LIMIT 5;'

# Expected output shows username, reply (Access-Accept or Access-Reject), and authdate
```

On the eduroam admin dashboard, navigate to **Monitoring** and the **Authentication Activity** chart should begin showing Accept/Reject/Other bars across all time ranges.
