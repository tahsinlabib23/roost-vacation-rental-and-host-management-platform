# F6 messaging demo data

The messaging demo seeder is an explicit local/testing fixture. It is intentionally not called by `DatabaseSeeder` and refuses to run in other environments.

Run it with:

```powershell
php artisan db:seed --class=MessagingDemoSeeder
```

All demo users use the local-only password `RoostDemo123!`:

- `f6.guest1@example.test` and `f6.host1@example.test` have a conversation with more than 50 messages for inbox, unread-state, and history-pagination checks.
- `f6.guest2@example.test` and `f6.host2@example.test` have no conversation, so they demonstrate the first-contact flow.
- Published and draft properties are included so guest search can verify that drafts never appear.

The seeder only updates records identified by the reserved `f6.*@example.test` addresses and stable demo property titles. Rerunning it does not multiply conversations or messages. These credentials and records must never be used as production data.
