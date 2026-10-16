# Local database incident — 2026-10-16

During implementation, the initial `tools/gps-mysql-tests.php` runner created a
random temporary database but did not sufficiently isolate inherited PHP
environment variables. PHPUnit's forced `$_ENV` database name was shadowed by
the bootstrapped parent's inherited `$_SERVER` value. Laravel therefore ran
RefreshDatabase / `migrate:fresh` against the configured local `fleet_2026`
database. This was an agent error, not an authorized request to reset data.

The run recreated the existing application tables and stopped at the new
hardware session migration's MySQL timestamp-default incompatibility. Read-only
inspection afterward found zero rows in users, gps_locations and
tracking_relations, plus 28 recorded migrations and partially created hardware
tables. The initial state was not backed up by the agent, so the exact number
of lost rows is unknown. This must not be described as a successful migration.

MySQL `log_bin` is OFF. No `fleet_2026` SQL dump was found in the workspace or
the checked D: project trees. XAMPP's backup and `data - Copy` directories had no
fleet_2026 backup. A user-provided dump or VM/disk snapshot is required for
reliable recovery. The user was informed and asked for its location. No seed
data was inserted to conceal or substitute for the original records, and no
further migration was applied to this affected application database.

The runner has been corrected to set both environment/server values, pass an
explicit child environment, and require a random `fleet_gps_test_` database name.
`HardwareGpsTest::createApplication` additionally verifies the effective
database name and connection **before** RefreshDatabase runs. Tests refuse any
other MySQL target; ordinary runs require SQLite `:memory:`. The corrected
isolated MySQL test run passed and dropped only its own created database.

The migration uses explicit datetime columns for required event times to avoid
legacy MySQL/MariaDB implicit timestamp-default behavior.

Do not run the deployment migration instructions against the affected local
database before recovery. Restore a backup into a separate recovery database,
verify its contents and recency, and only then agree on replacement/reconnection
with the user. Do not delete unrelated databases or replace the whole XAMPP
data directory.
