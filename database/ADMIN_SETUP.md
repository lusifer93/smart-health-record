# CareNest first administrator setup

1. In Supabase Dashboard, open **Authentication → Users → Add user** and create the administrator with a separate email and a strong password.
2. Copy that user's UUID.
3. In the SQL Editor, run this once, replacing the UUID:

```sql
update public.profiles
set role = 'admin', account_status = 'active'
where id = 'PASTE-THE-AUTH-USER-UUID-HERE';
```

4. Sign in at `http://localhost/smart-health-record/index.php?page=login&role=admin`.

Do not make administrator accounts through public patient registration. Do not use a service-role key for the PHP application.
