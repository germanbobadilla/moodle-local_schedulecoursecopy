# Changelog

All notable changes to this plugin are documented here.

## 0.6.0 - 2026-10-21

* Rebuilt the admin UI to the design handoff:
  * **Overview**: a single status strip, "Needs attention" with Review / Reveal
    now, upcoming copies with date blocks, and a programs table (leaf categories
    with jobs).
  * **Categories**: one page with the category tree on the left (filter,
    collapse) and the selected category on the right. Add and edit open in a
    modal (`core_form/modalform`); `category_edit.php` remains as the no-JS
    fallback, and `category_view.php` redirects here. Categories have an
    optional **ID number**.
  * **Schedule copies** panel: a defaults row (template, copy on, start, end,
    visibility). Blank cells inherit the defaults. Rows can be pasted in from
    Excel or Sheets (DD/MM/YYYY is converted) or imported from a CSV (see
    `example.csv`). Validation runs live and on the server, in the design's
    order, with the message under the short name. Short names are now also
    checked against other jobs, not just existing courses.
  * **Status report**: status chips with counts, a category filter, search, row
    selection, and a bulk bar (Retry failed / Reveal now / Cancel scheduled).
  * **Job drawer**, opened from any job on any page: failure panel with the job
    log, a progress stepper, details, and actions that depend on the status.
  * **Settings** tab (`settings_page.php`): grouped sections, a role checkbox
    grid, placeholder chips, and "Reset to default". The admin settings page
    still works and saves the same values.
* New job lifecycle features:
  * **Visibility** per job: reveal on start date (the scheduled task now does
    this), reveal as soon as copied, or keep hidden for a manual reveal.
    Existing jobs are set to manual, which matches how they worked before.
  * A **Cancelled** status. Cancelled jobs disappear from every list and free
    their short name.
  * **Retry** for failed jobs, plus a failure **attempt** count.
  * A timestamped **log** on each job.
  * An optional **Copy failed** email (on by default), sent to the notify roles
    and site admins, with its own subject and body and an `{errormessage}`
    placeholder.
* New **Default visibility** setting (Settings > Reveal; defaults to "Reveal when
  copied"). It prefills the visibility for new copies in the Schedule copies
  panel and the single job form; each batch can still change it.
* New **reporting endpoint**: the `local_schedulecoursecopy_get_jobs` web service, in a
  predefined "Schedule Course Copy reports" service, for other systems with a token.
  It filters by category (subcategories included), status, course, template, name and
  start/copy date ranges, and returns each job's status, category path, course link,
  and start and end dates, plus counts per status. `status.php` takes the same filters
  (dates also as YYYY-MM-DD) and returns the same fields for logged-in users. New
  read-only capability `local/schedulecoursecopy:viewreports` for reporting accounts.
  Now requires Moodle 4.2 or later (`core_external`).
* Program director is now optional in the single job form, as documented in
  0.1.10. Before this, jobs created from the bulk table couldn't be edited.

## 0.5.0 - 2026-10-21

* Added optional job notifications, off by default. Configure one or more
  **Notify roles** (Site administration > Plugins > Local plugins >
  Schedule Course Copy settings) and the plugin emails, through Moodle's own
  messaging system, whoever holds those roles in the template course when a
  job is scheduled, and again — plus everyone enrolled in the template — once
  it is revealed (live). Both notifications have an admin-editable subject
  and body with `{token}` placeholders (course names, course link, start
  date, recipient name). Fixed the "Program director role" setting's label,
  which read as if it were a required, universal role; it is optional and
  only used when one is recorded against a specific job.
* Also corrected the `jobformintro` string, which still said no emails are
  ever sent automatically.

## 0.4.0 - 2026-10-21

* Renamed the plugin from Auto Copy (`local_autocopy`) to **Schedule Course
  Copy** (`local_schedulecoursecopy`) — clearer about what it actually does
  (schedule a course copy), and avoids confusion with Moodle's own
  unrelated "auto-backup" terminology. Since Moodle ties a plugin's tables
  to its component name, this required a full uninstall/reinstall rather
  than an in-place rename; the existing category and job rows were
  exported before uninstalling and re-inserted unchanged afterward, so no
  data was lost in the move.

## 0.3.1 - 2026-09-22

* Corrected 0.3.0: a copy now enrols every user from the template with their
  same role and nothing else — grades, submissions and logs are not copied.
  0.3.0 set `userdata = 1`, which (combined with listing every role to keep)
  actually triggers Moodle's *full* user data backup, not just enrolments.
  The correct combination for "enrol only" is `userdata = 0` with the roles
  still listed; verified directly against a template with real grades and
  logs that neither carries over, while enrolments and roles still match
  exactly.

## 0.3.0 - 2026-09-22

* **Behaviour change**: a copy now includes the template's own enrolled
  users, their roles, groups and other user data, exactly as they are —
  previously every copy explicitly excluded all of this. Getting Moodle's
  copy engine to actually do this took two changes, not one: `userdata = 1`
  alone is silently ignored unless at least one role is also explicitly
  listed to keep, so every role actually used in the template is now passed
  along too.
* Removed the "Awaiting instructor" step entirely, along with
  `job_manager::set_instructor()`, `setinstructor.php`, `instructor_form.php`,
  the `instructorid` column, and the now unused "Instructor role" setting.
  There is nothing left to nominate: whoever is enrolled in the template,
  including any teacher, is already enrolled in the copy. A job now goes
  straight from `copying` to `ready_to_reveal`.

## 0.2.0 - 2026-09-22

* Rebuilt the "add courses" panel from scratch as a plain HTML table inside
  a card, dropping Moodle's form API (moodleform/repeat_elements) for it
  entirely. That approach kept causing real, hard to fix problems: a group
  never shows a per field validation error (only one combined message per
  row was ever possible), and a growable column computes a different width
  in the header strip than in each row, so columns drifted out of alignment
  every time the row's own content changed. A real `<table>` has neither
  problem, and needs no work-around to use a native date input.
* "Add row" and "Remove" are now instant and client side (a few lines of
  JavaScript cloning a row template) instead of a full page round trip.
* `classes/form/bulk_job_form.php` is removed; `classes/local/bulk_course_table.php`
  now owns parsing, validation and rendering for this page.

## 0.1.10 - 2026-09-22

* Removed Program director from the bulk row form entirely: the row now has
  exactly six fields (Template, Full name, Short name, Copy on, Start date,
  End date). `programdirectorid` on a job is now optional at the database
  level; a program director is only enrolled at reveal time if one happens
  to be set (e.g. via the single job form). The configured program director
  role in Settings is unaffected and still used wherever a program director
  is enrolled.
* Fixed a real layout bug: after removing a column, "Full name" was still
  set to grow and fill the freed-up space, and it grew differently in the
  header strip than in each row (the row also has a Remove button eating
  space that the header doesn't), so columns drifted out of alignment.
  Every column is now a fixed width in both places, so this can't recur.

## 0.1.9 - 2026-09-22

* Removed the "Choose..." placeholder text from the Program director
  dropdown in the bulk row form: it now just starts blank. A blank
  selection is still rejected on save, so a row can't be accidentally
  submitted with the wrong program director.

## 0.1.8 - 2026-09-22

* Replaced the Program director autocomplete (a "Choose..." chip plus a
  separate search box) in the bulk row form with a plain dropdown, matching
  the compact, single-box style of every other field in the row.

## 0.1.7 - 2026-09-22

* Moved "Start copy on" into each course row (previously one shared date for
  the whole batch): courses in the same batch do not necessarily copy on
  the same day.
* Replaced the YYYY-MM-DD text boxes for Copy on/Start date/End date with a
  native browser date input: one compact box with a built-in calendar icon
  you click to pick a date, instead of typing it. The value is mirrored
  into a hidden field behind the scenes, so validation and storage are
  unchanged.

## 0.1.6 - 2026-09-22

* Fixed a real, silent bug: submitting an incomplete course row (bad date,
  no program director, etc.) was rejected, but no error message was ever
  shown — the form just redisplayed with no explanation. The cause was a
  PEAR HTML_QuickForm_group limitation (it never passes error state down to
  its own child elements), so per-field errors inside a repeated row were
  swallowed. Every problem in a row is now combined into one visible red
  message under that row.
* Replaced the per-row Start date/End date pickers (and the shared "Start
  copy on" picker) with plain YYYY-MM-DD text fields. The previous pickers
  used Moodle's date_selector, which showed an "Enable" checkbox per date;
  the two checkboxes could end up in visibly different states between
  fields, which looked broken. The plain fields match the styling of the
  other row fields exactly and cannot end up in an inconsistent state.

## 0.1.4 - 2026-09-21

* Removed the "Course manager" picker from both the single job form and the
  bulk row form: it was never wired into enrolment (only the program director
  and instructor roles, configured under Settings, are actually enrolled), so
  it was just an extra field that did nothing.
* Added Start date and End date pickers to each row of the bulk form. Start
  date defaults to midnight; end date is automatically pushed to 23:59 the
  same day, so it covers the whole day (applies to the single job form too).
* Simplified "Start copy on" from a date-and-time picker to a date-only
  picker: the time-of-day selectors were unused clutter, since a copy always
  starts at midnight on the chosen day.
* Reworked the bulk row form's layout: each course is now a bordered card
  with a column-header strip above it (Template, Full name, Short name,
  Start date, End date, Program director), instead of an unstyled strip of
  fields crammed onto one line.

## 0.1.3 - 2026-09-21

* Added a row-based bulk course creation form (Categories > a category name):
  add one or more courses to a category in one page, using Moodle's own
  `repeat_elements()` to grow the form with an "Add another course" button
  without leaving the page.
* Fixed a validation gap: a row with course details filled in but no program
  director selected could be submitted, creating a job with an invalid
  (zero) program director. Every non-blank row is now validated in full
  before anything is saved.

## 0.1.2 - 2026-09-21

* Added a real tab bar (Overview / Categories / Status report) to every page
  in the plugin, so you can move between sections without going back to the
  dashboard first.
* Restyled the dashboard cards and every listing table to use Moodle's own
  Bootstrap/`generaltable` classes instead of unstyled markup.
* Report actions (Edit, Delete, Set instructor, Reveal) are now proper
  buttons instead of plain pipe-separated text links.
* Fixed a real bug: the Program category, Program director and Course
  manager selectors had no empty placeholder option, so Moodle silently
  pre-selected the first category/user in the list. Submitting the form
  without touching those fields would record the wrong program director.
  All three now require an explicit choice.

## 0.1.1 - 2026-09-21

* Removed all automated email sending: no more program director/course
  manager notifications at each stage, and no student notification feature
  (form fields, settings, "Email students" action, and the underlying
  `email_students()` method are all gone). Enrolment of the program director
  and instructor is unaffected — only the emailing is removed.
* Dropped the now-unused email tracking and student template columns from
  `local_schedulecoursecopy_job` via a proper upgrade step.
* Fixed a fatal error ("Class moodleform not found") on the category, job, and
  set-instructor forms: `formslib.php` must be required explicitly, Moodle
  does not autoload it.
* Documented that this plugin's scheduled task, like every scheduled task,
  does nothing unless Moodle's cron is actually being triggered — the
  moodlehq/moodle-php-apache Docker image does not run cron on its own.

## 0.1.0 - 2026-09-21

* Initial release (alpha).
* Campus/program category tree, mapped to real Moodle course categories.
* Schedule a job: template course, destination, dates, program director,
  course manager, and when to start.
* Scheduled task starts due jobs through Moodle's own course copy engine with
  `userdata = 0` (no enrolments, no self-enrolment keys, no other user data),
  and polls in-progress copies to completion.
* Status report per category with actions: set instructor, reveal, edit/delete
  while still scheduled.
* JSON status endpoint per category.
* Moodle events for job creation, status changes, and report views.
* Privacy provider with full export/delete support.
