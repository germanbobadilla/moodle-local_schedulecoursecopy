# Schedule Course Copy

**Schedule Course Copy** is a Moodle local plugin for site administrators. It
schedules, runs and tracks bulk copies of template courses into live courses.
Every user from the template is enrolled into the copy with their same role;
their grades, submissions and logs are not carried over.

## Description

Schedule Course Copy automates the part of a course-copy cycle that happens
inside Moodle: scheduling copies in bulk, running them through Moodle's own
course copy engine, revealing them to students at the right time, and
reporting on all of it. Everything before that (informing program directors,
pulling enrolment reports, deciding the cycle's timeline) stays a manual,
external process.

You organise work into your own **category** tree, nested as deep as you need
(for example *Main Campus / Nursing / Vocational Nurse*). Each leaf category
is mapped to a real Moodle course category, and you schedule **jobs** into
it. A job is one new course: a template course, the new course's names and
dates, when to copy it, and when to reveal it.

A job moves through:

1. **Scheduled**: waiting for its copy date.
2. **Copying**: the scheduled task submits it to Moodle's own course copy
   engine (the same code behind the "Copy course" button). The new course is
   created hidden.
3. **Ready to reveal**: the copy finished and the course is still hidden.
4. **Revealed**: the course is visible to students, and the program director
   is enrolled if one was recorded against the job.

When a copy reaches *Ready to reveal*, the job's **visibility** setting
decides what happens next:

| Visibility | Behaviour |
| --- | --- |
| Reveal when copied (site default) | Revealed as soon as the copy finishes. |
| Reveal on start date | Revealed by the scheduled task on the course start date. |
| Keep hidden (manual) | Stays hidden until an admin clicks **Reveal now**. |

A copy that fails is marked **Failed**, with the reason, an attempt count and
a timestamped log. It can be retried from the job drawer or in bulk from the
status report. A job that has not started copying can be **cancelled**; this
hides it from every list and frees its short name.

## Screens

- **Overview**: status cards with each status's share of all jobs, a *Needs
  attention* list (failed copies and copies waiting to be revealed), upcoming
  copies, and a per-program table with a stacked progress bar.
- **Categories**: the category tree (filter, collapse) next to the selected
  category. Categories are added and edited in a modal. A category with
  subcategories shows them as cards. A leaf category lists its courses and
  hosts the **Schedule copies** panel:
  - a defaults row (template, copy date, start, end, visibility) that blank
    cells inherit;
  - one row per course; paste cells straight from Excel or Google Sheets
    (DD/MM/YYYY dates are converted), or import a CSV (see `example.csv`);
  - live validation, repeated on the server: full name, short name, short
    name already used by a course or another job, duplicates within the
    list, template, copy date.
- **Status report**: status chips with counts, a category filter
  (subcategories included), search, and row selection with bulk *Retry
  failed*, *Reveal now* and *Cancel scheduled*.
- **Job drawer**: opens from any job on any page. It shows the failure panel
  and log, a progress stepper, the job details, and the actions available at
  its current stage.
- **Settings**: default visibility, program director role, notify roles,
  failure emails, and the three email templates, each with a *Reset to
  default* link.

Every screen also works without JavaScript; the JavaScript adds the modal,
paste, CSV import, live validation and bulk selection on top.

## Requirements

- Moodle 4.2 to 5.3 (`$plugin->requires = 2023042400`,
  `$plugin->supported = [402, 503]`).

## Installation

Install like any other Moodle local plugin, in `local/schedulecoursecopy`
(`public/local/schedulecoursecopy` on Moodle 5.1 and later):

```sh
git clone https://github.com/germanbobadilla/moodle-local_schedulecoursecopy.git local/schedulecoursecopy
```

Then log in as an admin and visit *Site administration > Notifications* to
complete the installation, or run:

```sh
php admin/cli/upgrade.php
```

The scheduled task **Process course copy jobs** runs every five minutes by
default (*Site administration > Server > Scheduled tasks*). It starts due
copies, checks on copies in progress, and reveals copies whose start date has
arrived. **This requires Moodle's cron to be running** (`admin/cli/cron.php`
triggered every minute, normally by an OS crontab entry). Docker images based
on moodlehq/moodle-php-apache do not run cron on their own; add a sidecar
container (or a host cron entry) that loops `php admin/cli/cron.php`.

## Usage

1. **Schedule Course Copy > Settings**: choose the **Default visibility** for
   new copies, the **Program director role**, and, if you want emails, the
   **Notify roles** and email templates. See [Notifications](#notifications).
2. **Categories**: build your campus/program tree and map each leaf category
   to a Moodle course category.
3. **Schedule copies**: open a leaf category, fill in the defaults and one row
   per course (type, paste or import a CSV), then schedule them.
4. **Overview** and **Status report**: follow progress, reveal courses kept
   hidden, and retry failed copies.

The plugin lives under *Site administration > Plugins > Local plugins >
Schedule Course Copy*.

## Notifications

All emails go through Moodle's own messaging system, so each recipient's
usual popup/email/mobile preferences apply.

- **Course scheduled**: sent when a job is created, to whoever holds a
  **notify role** in the *template* course.
- **Course live**: sent when the course is revealed, to the same notify-role
  holders plus everyone enrolled in the template.
- **Copy failed**: sent when a copy fails, to the notify-role holders plus
  site admins. Turn this off with **Failure emails**.

No notify roles are configured by default, so the scheduled and live emails
are off until you choose some. The program director role is separate from
this: it only controls who is *enrolled* in the new course, never who is
emailed.

Each email has an admin-editable subject and body, with these placeholders:

| Placeholder | Resolves to |
| --- | --- |
| `{coursefullname}` | The new course's full name. |
| `{courseshortname}` | The new course's short name. |
| `{templatefullname}` | The template course's full name. |
| `{courselink}` | A link to the new course. Blank until the course exists. |
| `{startdate}` | The new course's start date. |
| `{recipientname}` | The recipient's own full name. |
| `{errormessage}` | Why the copy failed (failure email only). |

A typo in a placeholder is left as literal text, so it is visible rather than
silently blanked. If sending an email fails, the job itself is not affected;
the failure is only logged via `debugging()`.

## Reporting API

### Web service (for other systems)

`local_schedulecoursecopy_get_jobs` returns jobs with their status, category
path, course link, and start and end dates, plus a count per status. It is
part of the predefined **Schedule Course Copy reports** service.

To use it:

1. *Site administration > Advanced features*: turn on **Enable web services**.
2. *Site administration > Server > Web services > Manage protocols*: turn on
   **REST**.
3. Give the account that will call it a role with
   `local/schedulecoursecopy:viewreports`. This is read-only; managers have
   it by default.
4. *External services > Schedule Course Copy reports > Authorised users*: add
   that account.
5. *Manage tokens*: create a token for that account on that service.

```
https://your.moodle/webservice/rest/server.php?wstoken=TOKEN&moodlewsrestformat=json
    &wsfunction=local_schedulecoursecopy_get_jobs&categoryid=5&status=revealed
```

| Parameter | Filter |
| --- | --- |
| `categoryid` | A plugin category and all its subcategories (0 = all). |
| `status` | `scheduled`, `copying`, `ready_to_reveal`, `revealed`, `failed` or `cancelled`. Empty returns every status except cancelled. |
| `courseid` | The copied (live) course id. |
| `templatecourseid` | The template course id. |
| `search` | Part of the course full or short name. |
| `startfrom`, `startto` | Course start date range (Unix time). |
| `copyfrom`, `copyto` | Copy date range (Unix time). |

Each job returns `id`, `fullname`, `shortname`, `status`, `statuslabel`,
`categoryid`, `categoryname`, `categorypath`, `topcategoryid`,
`moodlecategoryid`, `templatecourseid`, `templateshortname`, `courseid`,
`courseurl`, `scheduledate`, `startdate`, `enddate`, `visibility`,
`attempts`, `failurereason`, `timecreated` and `timemodified`.

### Browser endpoint (for logged-in users)

`local/schedulecoursecopy/status.php` takes the same filters and returns the
same JSON for a logged-in user with either capability below. Dates can also
be given as `YYYY-MM-DD`, for example
`status.php?categoryid=5&startfrom=2026-11-01`.

## Capabilities

| Capability | Description |
| --- | --- |
| `local/schedulecoursecopy:manage` | Manage categories and jobs, and use every screen. No role is granted this by default, which keeps the plugin admin-only: site administrators bypass all capability checks. |
| `local/schedulecoursecopy:viewreports` | Read-only access to the reporting API, for example for a reporting service account. Granted to managers by default. |

## Logs

Schedule Course Copy triggers three Moodle events (job scheduled, job status
changed, and report viewed), so every state change and report view shows up
in *Site administration > Reports > Logs*. Each job also keeps its own short
log, shown in the job drawer.

## Privacy

A job optionally records a program director and a course manager. These are
staff work assignments at system context. Deleting a user's data clears
their name from those roles on every job but keeps the job itself, since it
is an operational record of what was copied, when, and to where. This
provider does not track the personal data inside a copied course itself;
that is the responsibility of the course's own activities and Moodle core,
as for any other course copy.

## Known limitations

- Retrying a failed copy is manual. The attempt count is shown ("attempt 2
  of 3"), but copies are not retried automatically.
- A job can only be edited or cancelled while it is still `scheduled`.
- The program director picker on the single job form loads every active
  user in the browser; a very large user base would be better served by a
  searched picker.
- The JavaScript is a plain AMD module with no build step:
  `amd/build/app.min.js` is a copy of `amd/src/app.js`. Regenerate it if you
  add Moodle's grunt build.

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
