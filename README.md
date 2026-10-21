# Schedule Course Copy

**Schedule Course Copy** is a Moodle local plugin, visible only to site administrators, that
schedules, runs, and tracks bulk copies of template courses into live courses,
enrolling every user from the template into the copy with their same role —
without carrying over their grades, submissions, or logs.

## Description

Schedule Course Copy automates the part of a course-copy cycle that happens inside Moodle:
scheduling the copy, running it through Moodle's own course copy engine, and
holding each copy hidden until an admin reveals it. Everything before that
(informing program directors, pulling enrolment reports, deciding the cycle's
timeline) remains a manual, external process — this plugin does not touch it.

You organise work into your own two-level **category** tree (for example a
campus like "Modesto" containing a program like "Vocational Nurse"), map each
program to a real Moodle course category, and schedule **jobs** inside it: a
template course short name, the new course's name and dates, and when the
copy should start.

A job moves through:

1. **Scheduled** — waiting for its start time.
2. **Copying** — the scheduled task submits it to Moodle's own course copy
   engine (the same code behind the "Copy course" button), enrolling every
   user from the template with their same role, but none of their grades,
   submissions, or logs. The new course is created hidden.
3. **Ready to reveal** — the copy finished; the course is still hidden
   pending review.
4. **Revealed** — an admin has revealed it: the course becomes visible, and
   the program director is enrolled if one was recorded against the job.

Any step can end in **Failed**, with the reason recorded and shown on the
report.

This version sends no emails at all — not to the program director, not to
students. The program director role only controls who gets _enrolled_ and
when; every "who needs telling what" step currently happens outside the
plugin, by whatever channel you already use. That may change in a later
version.

## Requirements

- Moodle 4.0 to 5.3 (`$plugin->requires = 2022041900`,
  `$plugin->supported = [400, 503]`).

## Installation

Install like any other Moodle local plugin, in `local/schedulecoursecopy`:

```sh
git clone https://github.com/germanbobadilla/moodle-local_schedulecoursecopy.git local/schedulecoursecopy
```

Then log in as an admin and visit _Site administration > Notifications_ to
complete the installation, or run:

```sh
php admin/cli/upgrade.php
```

The scheduled task **Process course copy jobs** runs every five minutes by
default (Site administration > Server > Scheduled tasks) — it starts due jobs
and checks on ones already copying. **This requires Moodle's cron to actually
be running** (`admin/cli/cron.php` triggered every minute, normally by an OS
crontab entry) — like every scheduled task, jobs will simply sit there
untouched on an install where nothing is calling cron. Docker images based on
moodlehq/moodle-php-apache do not run cron on their own; add a sidecar
container (or a host cron entry) that loops `php admin/cli/cron.php`.

## Usage

1. **Site administration > Plugins > Local plugins > Schedule Course Copy settings** —
   set the **Program director role**, the role a program director is
   enrolled with if one is ever recorded against a job. Default: Manager.
2. **Site administration > Local plugins > Schedule Course Copy > Categories** — build
   your campus/program tree. A category needs a Moodle course category mapped
   before jobs can be created in it.
3. **Add courses** — open a category and add one or more rows: each is a
   template course's short name, the new course's name/short name, and when
   to copy it.
4. **Status report** — filter by category, see every job's status, and reveal
   a course once its copy is ready.

## Status endpoint

`local/schedulecoursecopy/status.php?categoryid=<id>` returns a JSON list of jobs (id,
name, status, scheduled date, live course link once copied) for that category
and its descendants, or every job if no category is given. Like every page in
this plugin it is session authenticated and requires `local/schedulecoursecopy:manage` —
it is not a public or token based API.

## Capabilities

| Capability                        | Description                                                                                                                                                                              |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `local/schedulecoursecopy:manage` | Manage categories and jobs, and view the status report. No role is granted this by default, which is what keeps the plugin admin-only: site administrators bypass all capability checks. |

## Logs

Schedule Course Copy triggers three Moodle events — job scheduled, job status changed,
and report viewed — so every state change and every report view shows up in
**Site administration > Reports > Logs** alongside the rest of the site's
activity, rather than in a separate bespoke log table.

## Privacy

A job optionally records a program director and a course manager. These are
staff work assignments at system context. Deleting a user's data clears
their name from those roles on every job but keeps the job itself, since it
is an operational record of what was copied, when, and to where. This
provider does not, and cannot, track the personal data carried inside a
copied course itself — that is the responsibility of the affected course's
own activities and Moodle core, exactly as it is for any other course copy.

## Known limitations of this first version

- The program director and course manager pickers (single job form only)
  load every active user client-side; a very large user base would benefit
  from an AJAX-searched picker instead.
- A job can only be edited or deleted while it is still `scheduled` (or
  deleted while `failed`); there is no way to edit a job once copying starts.
- Reveal is a deliberately manual, one-job-at-a-time action — there is no
  bulk "reveal everything in this program" button yet, even though the
  report can be filtered down to one program at a time.

## Support

Please use the [GitHub issue tracker](https://github.com/germanbobadilla/moodle-local_schedulecoursecopy/issues)
to report bugs or request features.

## License

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the
[GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html) for more
details.
