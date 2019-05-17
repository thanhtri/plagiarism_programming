M.plagiarism_programming = M.plagiarism_programming || {}
M.plagiarism_programming = {
    /*
     * Entry function!
     * Check the current status of the assignment.
     *      Status = pending  => display button "Start scanning"
     *      Status = finished => display button "Rescan"
     *      Status = uploading, scanning, downloading: display the current progress of scanning
     *               Note that user can always navigate away while the scanning in progress.
     *               However, the scanning will continue. If users return to the page again,
     *               they will see the bar displayed if the scanning still proceed.
     */
    initialise : function (Y, cmid, checkprogress) {
        this.Y = Y;
        Y.one('#plagiarism_programming_scan').on('click', function (e) {
            e.preventDefault();
            var time = new Date().getTime() % 100000000;
            M.plagiarism_programming.initiate_scanning(cmid, time);
            M.plagiarism_programming.monitor_scanning(cmid, time);
        });
        if (checkprogress.jplag || checkprogress.moss) {
            M.plagiarism_programming.enable_scanning(false); // Disable the scanning button.
            M.plagiarism_programming.monitor_scanning(cmid, null); // Show the process of unfinished scanning.
        }
    },
    /**
     * Display the progressbar at a progress for the tool
     * @param progress a number between 0 and 100, indicating the percentage of
     *            the progress
     * @param tool either 'jplag' or 'moss'
     */
    display_progress : function (progress, tool) {
        var Y = M.plagiarism_programming.Y;
        if (!Y.one('#' + tool + '_tool')) {
            return;
        }
        if (!M.plagiarism_programming['progressbar_' + tool]) {
            // If not yet created, create the progress bar.
            M.plagiarism_programming['progressbar_' + tool] = new Y.ProgressBar({ contentBox : '#' + tool + '_tool',
                width : '200px', height : '10px', minValue : 0, maxValue : 100, value : progress, barColor : 'blue',
                border : '1px solid black' });
            M.plagiarism_programming['progressbar_' + tool].render();
        } else {
            M.plagiarism_programming['progressbar_' + tool].set('value', progress);
        }
    },
    /**
     * Removing the progress bar
     */
    remove_progress : function (tool) {
        var Y = M.plagiarism_programming.Y;
        if (M.plagiarism_programming['progressbar_' + tool]) {
            M.plagiarism_programming['progressbar_' + tool].destroy();
            M.plagiarism_programming['progressbar_' + tool] = null;
        }
        if (!Y.one('#' + tool + '_tool')) {
            var node = Y.Node.create('<div id="' + tool + '_tool"></div>')
            Y.one('#' + tool + '_status').get('parentNode').insert(node, 'after');
        }
    },
    /*
     * Called when button clicked
     * If the scanning is in progress, set up the timer and the progress bar
     * to monitor upload, scanning, download, finish
     */
    initiate_scanning : function (cmid, time) {
        var Y = this.Y;
        M.plagiarism_programming.enable_scanning(false);
        // Show the progress bar.
        M.plagiarism_programming.display_progress(0, 'jplag');
        M.plagiarism_programming.display_progress(0, 'moss');
        // Make the request for scanning.
        Y.io(M.cfg.wwwroot + '/plagiarism/programming/start_scanning.php', { method : 'POST',
            data : { cmid : cmid, task : 'scan', time : time } });
    },
    /**
     * Check the status of the scanning on server and display the progress in
     * the progress bar
     * @param cmid: the course module id
     * @param time: the time initiating the scanning (optional) - this parameter
     *            is just to synchronise the time
     */
    monitor_scanning : function (cmid, time) {
        var Y = M.plagiarism_programming.Y;
        time = (time) ? time : 0;
        Y.io(M.cfg.wwwroot + '/plagiarism/programming/start_scanning.php', { method : 'GET',
            data : { cmid : cmid, task : 'check', time : time }, arguments : [ cmid, time ],
            on : { success : M.plagiarism_programming.display_monitor_info } });
    },
    /**
     * Display the progress information. This is the callback function of
     * monitor_progress
     * @param id: ajax request id (not used)
     * @param o: the response
     */
    display_monitor_info : function (id, o, arguments) {
        var Y = M.plagiarism_programming.Y;
        var status = Y.JSON.parse(o.responseText);
        var finished = true;
        for (var tool in status) {
            var stage = Y.one('#' + tool + '_status');
            stage.setContent(M.plagiarism_programming.convert_status_message(status[tool].stage));
            M.plagiarism_programming.display_progress(1 * status[tool].progress, tool);
            if (status[tool].stage != 'finished' && status[tool].stage != 'error') {
                finished = false;
            } else if (status[tool].link) {
                stage.setContent(status[tool].link);
            } else if (status[tool].stage == 'error') {
                stage.setContent('Error: ' + status[tool].message);
            }
            if (status[tool].stage == 'finished' || status[tool].stage == 'error') {
                M.plagiarism_programming.remove_progress(tool);
            }
        }
        if (!finished) {
            setTimeout('M.plagiarism_programming.monitor_scanning(' + arguments[0] + ',' + arguments[1] + ')', 2000);
        } else {
            M.plagiarism_programming.enable_scanning(true);
        }
    },
    /**
     * Convert status to an informative message. The message are passed from the
     * code
     */
    convert_status_message : function (status) {
        if (status == 'pending' || status == 'uploading' || status == 'scanning' || status == 'downloading') {
            return M.str.plagiarism_programming[status];
        } else {
            return status;
        }
    },
    /**
     * Enable/disable the scanning button. The button should be disabled when
     * scanning is in progress or cannot be performed
     */
    enable_scanning : function (is_on) {
        var Y = M.plagiarism_programming.Y;
        var button = Y.one('#plagiarism_programming_scan');
        var message = Y.one('#scan_message');
        if (is_on) { // Turn scanning on.
            button.set('disabled', false);
            button.set('value', 'Rescan');
            message.hide();
        } else {
            button.set('disabled', true);
            message.show();
        }
    } }