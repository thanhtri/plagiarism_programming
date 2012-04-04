M.plagiarism_programming = {}

/*
 * Entry function!
 * Check the current status of the assignment.
 *      Status = pending  => display button "Start scanning"
 *      Status = finished => display button "Rescan"
 *      Status = uploading, scanning, downloading: display progress
 */
M.plagiarism_programming.initialise = function(Y,cmid,lasttime,checkprogress) {
    var button = YAHOO.util.Dom.get('plagiarism_programming_scan');
    YAHOO.util.Event.addListener(button, 'click', function() {
        M.plagiarism_programming.initiate_scanning(cmid,lasttime);
    });

    if (checkprogress.jplag || checkprogress.moss) {
        M.plagiarism_programming.enable_scanning(false);
        M.plagiarism_programming.monitor_scanning(cmid,lasttime);
    }
}

M.plagiarism_programming.display_progress = function(progress,tool) {
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
}

/*
 * Called when button clicked
 * If the scanning is in progress, set up the timer and the progress bar
 * to monitor upload, scanning, download, finish
 */
M.plagiarism_programming.initiate_scanning = function(cmid,time) {
    var callback = {
        success : response,
        failure : response
    };
    M.plagiarism_programming.enable_scanning(false);
    // show the progress bar
    M.plagiarism_programming.display_progress(0,'jplag');
    M.plagiarism_programming.display_progress(0,'moss');
    // make the request for scanning
    YAHOO.util.Connect.asyncRequest('POST', '../../plagiarism/programming/start_scanning.php',
        callback, 'cmid='+cmid+'&task=scan');
    M.plagiarism_programming.monitor_scanning(cmid,time);
}

M.plagiarism_programming.monitor_scanning = function(cmid,time) {
    var callback = {
        success: M.plagiarism_programming.display_monitor_info,
        argument:[cmid]
    };
    time = (time)?time:0;
    // M.plagiarism_programming.scan_finished = false;
    YAHOO.util.Connect.asyncRequest('POST', '../../plagiarism/programming/start_scanning.php',
        callback, 'cmid='+cmid+'&task=check&time='+time);
}

M.plagiarism_programming.display_monitor_info = function(o) {
    var status = YAHOO.lang.JSON.parse(o.responseText);
    var finished = true;
    for (var tool in status) {
        var stage = YAHOO.util.Dom.get(tool+'_status');
        stage.innerHTML = status[tool].stage;
        M.plagiarism_programming.display_progress(1*status[tool].progress,tool);
        if (status[tool].stage=='done')
            M.plagiarism_programming.download_result(o.argument[0]);

        if (status[tool].stage!='finished' && status[tool].stage!='error') {
            finished = false;
        } else if (status[tool].link) {
            stage.innerHTML = status[tool].link;
        }
    }
    if (!finished) {
        setTimeout('M.plagiarism_programming.monitor_scanning('+o.argument[0]+')',2000);
    } else {
        M.plagiarism_programming.enable_scanning(true);
    }
}

M.plagiarism_programming.download_result = function(cmid) {
    var callback = {
        success: response,
        argument: cmid
    }
    YAHOO.util.Connect.asyncRequest('POST', '../../plagiarism/programming/start_scanning.php', callback,
        'task=download&cmid='+cmid);
}

M.plagiarism_programming.enable_scanning = function(is_on) {
    var button = YAHOO.util.Dom.get('plagiarism_programming_scan');
    
    if (is_on) {   // turn scanning on
        button.disabled = false;
        button.value = 'Rescan';
    } else {
        button.disabled = true;
    }
}

function response(o) {
}

M.plagiarism_programming.show_hide_item = function() {
    var checkbox = document.forms[0].elements['programmingYN'];
    YAHOO.util.Event.addListener(checkbox, 'click', function() {
        M.plagiarism_programming.toogle_checkbox(this);
    }, true);
    M.plagiarism_programming.toogle_checkbox(checkbox);
}

M.plagiarism_programming.toogle_checkbox = function(checkbox) {
    var header = new YAHOO.util.Element('programming_header');
    var divs = header.getElementsByClassName('fitem','div');
    var display_val = (checkbox.checked)?'block':'none';
    for (var i=1; i<divs.length; i++) {
        divs[i].style.display = display_val;
    }
}