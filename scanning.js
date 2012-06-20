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
    initialise : function(Y, cmid, checkprogress) {
        Y.one('#plagiarism_programming_scan').on('click', function(e) {
            e.preventDefault();
            var time = new Date().getTime() % 100000000;
            M.plagiarism_programming.initiate_scanning(cmid, time);
            M.plagiarism_programming.monitor_scanning(cmid, time);
        });

        if (checkprogress.jplag || checkprogress.moss) {
            M.plagiarism_programming.enable_scanning(false); //disable the scanning button
            M.plagiarism_programming.monitor_scanning(cmid,null); //show the process of unfinished scanning
        }
    },

    /**
     * Display the progressbar at a progress for the tool
     * @param progress a number between 0 and 100, indicating the percentage of the progress
     * @param tool either 'jplag' or 'moss' 
     **/
    display_progress : function(progress, tool) {
        if (!M.plagiarism_programming['progressbar_'+tool]) {
            // if not yet created, create the progress bar
            M.plagiarism_programming['progressbar_'+tool] = new YAHOO.widget.ProgressBar({
                    width:'200px',
                    height:'10px',
                    minValue:0,
                    maxValue:100,
                    value:progress
            }).render(tool+'_tool');
        } else {
            M.plagiarism_programming['progressbar_'+tool].set('value',progress);
        }
    },

    /**
     * Removing the progress bar
     **/
    remove_progress : function(tool) {
        if (M.plagiarism_programming['progressbar_'+tool]) {
            M.plagiarism_programming['progressbar_'+tool].destroy()
            M.plagiarism_programming['progressbar_'+tool] = null;
        }
    },

    /*
     * Called when button clicked
     * If the scanning is in progress, set up the timer and the progress bar
     * to monitor upload, scanning, download, finish
     */
    initiate_scanning : function(cmid, time) {
        var callback = {
            success : function(o) {},
            failure : function(o) {}
        };
        M.plagiarism_programming.enable_scanning(false);
        // show the progress bar
        M.plagiarism_programming.display_progress(0,'jplag');
        M.plagiarism_programming.display_progress(0,'moss');
        // make the request for scanning
        YAHOO.util.Connect.asyncRequest('POST', '../../plagiarism/programming/start_scanning.php',
            callback, 'cmid='+cmid+'&task=scan&time='+time);
    },

    /**
     * Check the status of the scanning on server and display the progress in the progress bar
     * @param cmid: the course module id
     * @param time: the time initiating the scanning (optional) - this parameter is just to synchronise the time
     **/
    monitor_scanning : function(cmid, time) {
        var callback = {
            success: M.plagiarism_programming.display_monitor_info,
            argument:[cmid,time]
        };
        time = (time)?time:0;
        var timestamp = new Date().getTime();
        // M.plagiarism_programming.scan_finished = false;
        // the timestamp is just a dummy variable to prevent browser catching!
        YAHOO.util.Connect.asyncRequest('GET', '../../plagiarism/programming/start_scanning.php?'+
            'cmid='+cmid+'&task=check&time='+time+'&timestamp='+timestamp, callback);
    },

    /**
     * Display the progress information. This is the callback function of monitor_progress
     * @param o: the response
     **/
    display_monitor_info : function(o) {
        var status = YAHOO.lang.JSON.parse(o.responseText);
        var finished = true;
        for (var tool in status) {
            var stage = YAHOO.util.Dom.get(tool+'_status');
            stage.innerHTML = M.plagiarism_programming.convert_status_message(status[tool].stage);
            M.plagiarism_programming.display_progress(1*status[tool].progress,tool);

            if (status[tool].stage!='finished' && status[tool].stage!='error') {
                finished = false;
            } else if (status[tool].link) {
                stage.innerHTML = status[tool].link;
            } else if (status[tool].stage=='error') {
                stage.innerHTML = 'Error: '+status[tool].message;
            }
            if (status[tool].stage=='finished' || status[tool].stage=='error') {
                M.plagiarism_programming.remove_progress(tool);
            }
        }
        if (!finished) {
            setTimeout('M.plagiarism_programming.monitor_scanning('+o.argument[0]+','+o.argument[1]+')',2000);
        } else {
            M.plagiarism_programming.enable_scanning(true);
        }
    },

    /**
     * Convert status to an informative message. The message are passed from the code
     **/
    convert_status_message : function(status) {
        if (status=='pending') {
            return M.str.plagiarism_programming.pending_start;
        } else if (status=='uploading') {
            return M.str.plagiarism_programming.uploading;
        } else if (status=='scanning') {
            return M.str.plagiarism_programming.scanning;
        } else if (status=='downloading') {
            return M.str.plagiarism_programming.downloading;
        } else {
            return status;
        }
    },

    /**
     * Enable/disable the scanning button. The button should be disabled when scanning is in progress
     * or cannot be performed
     **/
    enable_scanning : function(is_on) {
        var button = document.getElementById('plagiarism_programming_scan');
        var message = document.getElementById('scan_message');
        if (is_on) {   // turn scanning on
            button.disabled = false;
            button.value = 'Rescan';
            message.style.display = 'none';
            
        } else {
            button.disabled = true;
            message.style.display = 'inline';
        }
    }
}
