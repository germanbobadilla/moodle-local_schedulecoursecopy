<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for the Schedule Course Copy plugin.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allcategories'] = 'All categories';
$string['schedulecoursecopy:manage'] = 'Manage Schedule Course Copy';
$string['bulkaddheading'] = 'Add courses';
$string['bulkaddrow'] = '+ Add row';
$string['bulkcolenddate'] = 'End date';
$string['bulkcolfullname'] = 'Full name';
$string['bulkcolscheduledate'] = 'Copy on';
$string['bulkcolshortname'] = 'Short name';
$string['bulkcolstartdate'] = 'Start date';
$string['bulkcoltemplate'] = 'Template shortname';
$string['bulkexistingcourses'] = 'Courses in this category';
$string['bulkintro'] = 'Each row creates one course, copied from a template, into this category. Click a date field\'s calendar icon to pick a date. Add as many rows as you need, then save once.';
$string['bulknothingtodo'] = 'No rows were filled in, nothing was scheduled.';
$string['bulkremoverow'] = 'Remove';
$string['bulkrowerror'] = 'Row {$a->row}: {$a->message}';
$string['bulksavecourses'] = 'Save courses';
$string['bulksummary'] = '{$a} course(s) scheduled.';
$string['categoryadd'] = 'Add category';
$string['categorydeleteconfirm'] = 'Delete the category "{$a}"? This is only possible while it has no sub categories and no jobs.';
$string['categorydeleted'] = 'Category deleted.';
$string['categorydeletefailed'] = 'This category still has sub categories or jobs and cannot be deleted.';
$string['categoryedit'] = 'Edit category';
$string['categorymappingneeded'] = 'Map a Moodle course category to this category before adding courses to it.';
$string['categorymissingmapping'] = 'This category has no Moodle course category mapped, so it cannot hold jobs.';
$string['categorymoodlecategory'] = 'Moodle course category';
$string['categorymoodlecategory_help'] = 'The real Moodle course category new live courses are created in. Required for any category you want to create jobs in directly.';
$string['categoryname'] = 'Name';
$string['categorynomapping'] = "Don't create courses directly in this category";
$string['categorynoneyet'] = 'No categories yet. Add one to start organising your campuses and programs.';
$string['categorynoparent'] = 'None (top level, e.g. a campus)';
$string['categoryparent'] = 'Parent category';
$string['categoryparent_help'] = 'Leave as None for a top level grouping such as a campus. Choose an existing category to nest this one inside it, for example a program inside a campus.';
$string['categorysaved'] = 'Category saved.';
$string['errorcopyfailed'] = 'Moodle reported that the course copy failed.';
$string['errorenddatebeforestart'] = 'The end date cannot be before the start date.';
$string['errorinvaliddate'] = 'Enter a valid date as YYYY-MM-DD, e.g. 2026-09-22.';
$string['errorjobnotdeletable'] = 'Only scheduled or failed jobs can be deleted.';
$string['errorjobnoteditable'] = 'This job can no longer be edited because it has already started copying.';
$string['errorjobwrongstatus'] = 'This job is not at the right stage for that action.';
$string['errornocourseyet'] = 'This job does not have a copied course yet.';
$string['errornomanualenrol'] = 'The manual enrolment plugin is not available on this site.';
$string['errorshortnametaken'] = 'A course with this short name already exists.';
$string['errortemplatenotfound'] = 'No course was found with this short name.';
$string['event_job_created'] = 'Schedule Course Copy job scheduled';
$string['event_job_status_changed'] = 'Schedule Course Copy job status changed';
$string['event_report_viewed'] = 'Schedule Course Copy status report viewed';
$string['indexintro'] = 'Schedule bulk copies of template courses into live courses, and track their progress.';
$string['jobcategory'] = 'Program category';
$string['jobcategory_help'] = 'The Schedule Course Copy category (campus and program) this job belongs to. Only categories with a Moodle course category mapped are offered.';
$string['jobdeleted'] = 'Job deleted.';
$string['jobedit'] = 'Edit job';
$string['jobenddate'] = 'Course end date';
$string['jobformintro'] = 'The course is created hidden, with every user from the template enrolled with their same role (no grades, submissions or logs are copied), and only revealed once you confirm it from the report. No emails are sent automatically at any stage.';
$string['jobfullname'] = 'Full name';
$string['jobidnumber'] = 'ID number (optional)';
$string['jobprogramdirector'] = 'Program director';
$string['jobreveal'] = 'Reveal';
$string['jobrevealconfirm'] = 'Make "{$a}" visible (and enrol the program director, if one is recorded)?';
$string['jobrevealed'] = 'Course revealed.';
$string['jobsaved'] = 'Job saved.';
$string['jobscheduled'] = 'Job scheduled.';
$string['jobscheduledate'] = 'Start copy on';
$string['jobscheduledate_help'] = 'The "Process course copy jobs" scheduled task checks for due jobs every five minutes and will start the copy shortly after this date and time.';
$string['jobshortname'] = 'Short name';
$string['jobstartdate'] = 'Course start date';
$string['jobtemplateshortname'] = 'Template course short name';
$string['jobtemplateshortname_help'] = 'The short name of the existing template course to copy. Every user enrolled in it is enrolled in the copy with their same role; grades, submissions and logs are not copied.';
$string['navcategories'] = 'Categories';
$string['navcategories_desc'] = 'Organise campuses and programs, and map each program to a Moodle course category.';
$string['navnewjob'] = 'Add courses';
$string['navnewjob_desc'] = 'Open a program category and add one or more courses to it, row by row.';
$string['navopen'] = 'Open';
$string['navoverview'] = 'Overview';
$string['navreport'] = 'Status report';
$string['navreport_desc'] = 'See every scheduled, in progress, and completed copy, and act on what is next.';
$string['nojobs'] = 'No jobs match this filter yet.';
$string['pluginname'] = 'Schedule Course Copy';
$string['privacy:metadata:local_schedulecoursecopy_job'] = 'Details of a template to live course copy, including who to contact about it.';
$string['privacy:metadata:local_schedulecoursecopy_job:coursemanagerid'] = 'The user recorded as the course manager for this job.';
$string['privacy:metadata:local_schedulecoursecopy_job:fullname'] = 'The full name of the live course being created.';
$string['privacy:metadata:local_schedulecoursecopy_job:programdirectorid'] = 'The user recorded as the program director for this job.';
$string['privacy:metadata:local_schedulecoursecopy_job:scheduledate'] = 'When the copy was scheduled to start.';
$string['privacy:metadata:local_schedulecoursecopy_job:shortname'] = 'The short name of the live course being created.';
$string['privacy:metadata:local_schedulecoursecopy_job:status'] = 'The current status of the job.';
$string['privacy:metadata:local_schedulecoursecopy_job:timecreated'] = 'When the job was created.';
$string['privacy:role:coursemanager'] = 'Course manager';
$string['privacy:role:programdirector'] = 'Program director';
$string['settings_programdirectorrole'] = 'Program director role (main role added to every copy)';
$string['settings_programdirectorrole_desc'] = 'The role a program director is enrolled with, if one is recorded against a job. No notification is sent to them; this only controls enrolment.';
$string['settingspagename'] = 'Schedule Course Copy settings';
$string['status_copying'] = 'Copying';
$string['status_failed'] = 'Failed';
$string['status_ready_to_reveal'] = 'Ready to reveal';
$string['status_revealed'] = 'Revealed';
$string['status_scheduled'] = 'Scheduled';
$string['statuscolumn'] = 'Status';
$string['task_process_jobs'] = 'Process course copy jobs';
