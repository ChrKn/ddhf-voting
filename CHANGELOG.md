# Changelog

Notable changes to the DDHF MV Abstimmungssystem.  
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added
- The eight board posts under § 19 of the statutes are created by a migration, one vote each.
- The admin panel links to the ballot, in a new tab, for people who also represent a member or
  board post.
- The ballot and the attendance list show, next to a member's votes, the member count they were
  calculated from. It is frozen at check-in together with the votes.
- Before check-in, and when no assembly is running, the ballot shows the votes (and, for members,
  the member count) currently on record, so delegates can check them ahead of the assembly.
- The assembly chair can correct a wrong member count (or a board post's votes) during the
  assembly, with a mandatory reason, while no poll is open. The correction updates the master
  data and the frozen votes; closed polls stay as counted. The minutes list old and new values,
  the reason and who made the correction.

### Changed
- Password reset and invitation links are valid for 24 hours instead of one hour; the mail
  states the validity in hours.

### Fixed
- An expired or already used password link no longer shows the form for setting a password.
  It redirects to "Passwort vergessen" with an error message instead.
- Setting a temporary password invalidates any password link that is still open.

## 1.0.0 - 2026-11-08

First release, rebuilt on Laravel; replaces the plain-PHP system that was originally meant 
as a one time throw-away solution during Covid-19.

### Login and accounts
- Login with email and password, rate limited; system admins and managers land in the admin
  panel, representatives on the ballot.
- The password reset mail doubles as the invitation for new people; several people can be
  invited at once, and a filter shows who has not set a password yet.
- Where the invitation does not arrive, admins and managers can set a temporary password for a
  representative, which has to be replaced at the first login.
- Roles: system admin, manager, member representative. Only the system admin edits members,
  member counts and people; managers conduct assemblies.
- `php artisan voting:create-admin` replaces the old first-run setup.

### Voting members
- Members and board posts vote. A member's votes are `floor(members / 50) + 1`, configurable in
  `config/voting.php`; a board post has a fixed number of votes that belongs to the post, not to
  the person holding it.
- Member import from CSV or XLSX: columns are guessed and can be changed, rows are matched by
  name and can be remapped, created or skipped, with a preview of the changed votes and a list of
  members missing from the file. Board posts are left alone.
- `php artisan voting:import-people` creates representatives and maps them to members or board posts, with
  `--dry-run`, `--skip-invalid`, `--force` and `--invite`.

### Polls and ballot
- Motions and elections with several positions and candidates; a poll is locked once opened.
- One ballot per member and poll, weighted with the votes from its check-in. Abstention is
  preselected, and the ballot is reviewed before it is cast.
- People who represent several members choose which one they vote for.
- Secret polls store only that a member voted; the choices go into per-option sums alone.
- Polls run from draft to open, closed and published, and can be reset. No interim results while
  a poll is open; members see a result once it is published.

### Assemblies
- A control page for managers: open and close the assembly, check members in and out, run the
  polls, follow the progress live.
- A projector view at `/screen/{token}`, without login: progress while a poll is open, the result
  once published.
- Minutes as CSV that Excel opens with umlauts intact: attendance, every closed poll with turnout
  and results, and an append-only log of check-ins, checkouts and poll actions with time, votes
  and the acting person.
- Attendance is frozen when a poll is closed, so later checkouts do not change its turnout.
- A member checking in again keeps the votes from its first check-in.

### Operations
- SQLite in WAL mode; no queue worker or cron job needed, mail is sent within the request.
- After a poll is closed or reset, the WAL file is checkpointed and freed pages are zeroed, so
  the order of the votes leaves no trace in the database files.
- Reply-To and Return-Path for outgoing mail are configurable.
