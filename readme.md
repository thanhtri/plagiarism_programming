Copyright: 2019 Benedikt Schneider (@Nullmann), 2014 Tri (@thantri)

License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

# Revived and reworked version
This plugin was forked from @thantri and last updated 5 years ago. 
In the meantime, the jplag-service was discontinued.
As such, there will be legacy code left behind.

The second big change is that there is no course selection in the settings anymore. It is replaced by plugin-specific capabilities.

## 1. Registering a MOSS account
The plugin relies on MOSS engine in the background to carry out the scanning for similarity.

Create MOSS account: email to moss@moss.stanford.edu a message containing 2 lines (without a header!)
  ```
  registeruser
  mail username@domain
  ```
in which username@domain is your email address. You will receive back a reply containing a perl script to use MOSS. 

Visit http://theory.stanford.edu/~aiken/moss/ for more information.

## 2. Installation
Extract the zip file to `MOODLEROOT/plagiarism/programming`.

Or use git from your MOODLEROOT:
```
git clone https://github.com/Nullmann/moodle-plagiarism_programming plagiarism/programming
```

## 3. Server configuration:
* Make sure that the webserver-process has sufficient right to read all the files in the directory.
* Make sure the cron service is configured properly. For more information see: https://docs.moodle.org/36/en/Cron_with_Unix_or_Linux

## 4. Plugin installation
Login to Moodle as an administrator and visit the notification page to trigger the plugin installation page.

Click the upgrade button to install the plugin and update the database. An installation successful page should appear.

## 5.1 Moodle configuration
Go to Site Administrator → Advanced Features (or url http://<server_url>/admin/settings.php?section=optionalsubsystems). 

Tick the “Enable Plagiarism plugins” checkbox at the end of the page. Then, click “Save Changes”

## 5.2 Plugin configuration
Go to Site Administrator → Plugin → Plagiarism Prevention → Programming Assignment to configure the plugin. The following options are provided:
* Enable this plugin globally or for specific courses.
* MOSS Userid: the userid emailed to you when registered for MOSS. If you find it difficult to locate the userid in that email. Just copy the entire email to the textbox below (you need either find the user id or copy the email. You don't need to do both)

## 6. Assignment settings
Once the plugin is enabled, users will see the 'Source code plagiarism detection' block when creating or editing an assignment.

This block offers the following parameters:

* Programming assignment: Enable the plugin for the current assignment if checked.
* Programming language: Select the programming language for this assignment. Currently, just one programming language is supported for each assignment.
* Submit date: the date when all the submission is scanned for similarity. This date should be after the assignment due date, and also should take into account late submissions.
* Publish scanning result to students: If checked, students can see the similarity report comparing their work with others.
* Display notification: If checked, students are notified that this assignment will be checked for plagiarism in the assignment page.
* Notification text: This box is enabled when display notification is checked, and enables specification of the message to students.

## 7. Viewing the report
In the assignment page, teachers can click on “Rescan” button to trigger the scanning at anytime after the assignment is created (even before its due date).

After the scanning process finishes, a link to the report page appears. Click on the link to see the report:
The report consists of a graph of similarity rate distribution of every pairs, and a table listing similar pairs in descending order. Several options are offered to view the report, including:
* Similarity filter: Display only the pairs having similarities above the specified value
* Similarity type: Choosing between average similarity and maximum similarity. Since the length of each student’s code may vary a lot, the percentage of the similar portions between the two codes differs. Average similarity takes the average rate between the two as the rate of the pair and maximum similarity takes the maximum rate between the two as the rate of the pair. 
  * For example, if student A has 50% of code similar to student B and student B has 30% of code similar to student A (because B’s code is longer than A’s code), average similarity rate is 40% and maximum similarity rate is 50%.
* Display
  * Grouping students: A table with each row containing similarities between students having with one student in the first cell.
  * Ordered table: A simple list of pairs in descending similarity rate.

* Clicking on the bars of the chart will also show the pairs of which similarity rate belong to that range.
* Clicking on the percentage value will bring the details line-per-line comparison view.
  * Comparison view enables to compare the similarity of a pair of students as well as similar portions of one students with all the others. 
  * Lecturers can mark a pair in comarison view as suspicious or normal. 

## 8. Usage tip: Dummy user
It can be helpful to have a dummy user upload the template/framework of the assignment. This way similarities can be seen much easier.

This is especially useful when there are little submissions as moss is pretty restrictive on it's own: http://moss.stanford.edu/general/tips.html
