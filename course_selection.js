M.plagiarism_programming = M.plagiarism_programming || {};
M.plagiarism_programming.select_course = {
    
    init: function(Y) {
        var panel = new YAHOO.widget.Panel('course_selection_panel', {
            width: '500px',
            height: '400px',
            visible: false,
            fixedcenter: true,
            constraintoviewport: true
        });
        panel.setHeader('Select courses to enable the plugin');
        panel.setBody('<div></div>');
        panel.render('page');
        M.plagiarism_programming.select_course.panel = panel;
        
        // initialize the radio button
        var select_level = YAHOO.util.Dom.getElementsByClassName('plagiarism_programming_enable_level');
        for (var i=0; i<select_level.length; i++) {
            YAHOO.util.Event.addListener(select_level[i],'click',this.update_level,true);
            if (select_level[i].getAttribute('value')=='course') {
                YAHOO.util.Event.addListener(select_level[i],'click',this.show_course_panel);
            }
        }
    },
    
    show_course_panel: function() {
        M.plagiarism_programming.select_course.panel.show();
        var callback = {
            success: M.plagiarism_programming.select_course.display_courses
        }
        YAHOO.util.Connect.asyncRequest('GET','coursesetting/ajax.php',callback,'task=getcourse');
    },
    
    display_courses: function(o) {
        console.log('display courses');
        var courses = YAHOO.lang.JSON.parse(o.responseText);
        var wrapperDiv = document.createElement('div');
        var wrapperElement = new YAHOO.util.Element(wrapperDiv);
        wrapperElement.addClass('plagiarsm_programming_course_selection_wrapper');
        //var body_content = '';
        for (var i =0;i<courses.length;i++) {
            var cls = (i%2==0)?'even':'odd';
            var course = courses[i];
            var row = new YAHOO.util.Element(document.createElement('div'));
            row.addClass(cls);
            row.addClass('row');
            row.addClass('clearfix');
            
            var courseName = new YAHOO.util.Element(document.createElement('div'));
            courseName.addClass('coursename');
            courseName.appendChild(document.createTextNode(course.name));
            row.appendChild(courseName);

            var button = document.createElement('input');
            button.setAttribute('class', 'selectbutton');
            button.setAttribute('type','button');
            button.setAttribute('value','Enable');
            button.courseid = course.id;
            
            row.appendChild(button);
            YAHOO.util.Event.addListener(button,'click',function() {
                M.plagiarism_programming.select_course.enable_disable_course(this.courseid,!this.isEnabled,this);
                this.disabled = true;
            },true);
            M.plagiarism_programming.select_course.change_status(course.enabled, button);
            
            wrapperElement.appendChild(row);
        }
        M.plagiarism_programming.select_course.panel.setBody(wrapperDiv);
    },
    
    enable_disable_course: function(id,enable,button) {
        var callback = {
            success: function(o) {
                M.plagiarism_programming.select_course.change_status(enable,button);
            }
        };
        var task = (enable)?'enablecourse':'disablecourse';
        YAHOO.util.Connect.asyncRequest('POST','coursesetting/ajax.php',callback,'task='+task+'&id='+id);
    },
    
    change_status: function(isEnabled,button) {
        var row = new YAHOO.util.Element(button.parentNode);
        button.isEnabled = isEnabled;
        if (isEnabled) {
            button.disabled = false;
            button.value = 'Disable';
            row.addClass('enabled');
        } else {
            button.disabled = false;
            button.value = 'Enable';
            row.removeClass('enabled');
        }
    },
    
    update_level: function() {
        YAHOO.util.Connect.asyncRequest('POST','coursesetting/ajax.php',{},'task=setenabledlevel&level='+this.value);
    }
    
}