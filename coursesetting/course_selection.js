M.plagiarism_programming = M.plagiarism_programming || {};
M.plagiarism_programming.select_course = {

    init: function(Y) {
        this.Y = Y;
        this.init_course_panel();
        this.init_radio_button();
    },

    init_course_panel: function() {
        var panel = new YAHOO.widget.Panel('course_selection_panel', {
            width: '500px',
            height: '420px',
            visible: false,
            fixedcenter: true,
            constraintoviewport: true
        });
        panel.setHeader('Select courses to enable the plugin');
        panel.setBody('<div></div>');
        panel.setFooter(this.create_footer_panel());
        panel.render('page');
        M.plagiarism_programming.select_course.panel = panel;
    },

    init_radio_button: function() {
        // initialize the radio button
        var select_level = YAHOO.util.Dom.getElementsByClassName('plagiarism_programming_enable_level');
        for (var i=0; i<select_level.length; i++) {
            YAHOO.util.Event.addListener(select_level[i],'click',this.update_level,true);
            if (select_level[i].getAttribute('value')=='course') {
                YAHOO.util.Event.addListener(select_level[i],'click', function(ev) {
                    M.plagiarism_programming.select_course.show_course_panel(1);
                });
            }
        }
    },

    init_category_cbo: function() {
        var callback = {
            success: M.plagiarism_programming.select_course.get_categories
        }
        YAHOO.util.Connect.asyncRequest('GET', 'coursesetting/ajax.php?task=getcategory', callback);
    },

    show_course_panel: function(page) {
        M.plagiarism_programming.select_course.panel.show();
        var callback = {
            success: M.plagiarism_programming.select_course.display_courses
        }

        YAHOO.util.Connect.asyncRequest('GET','coursesetting/ajax.php?task=getcourse&page='+page, callback);
    },

    display_courses: function(o) {
        var form_html = o.responseText;
        var wrapperDiv = document.createElement('div');
        wrapperDiv.setAttribute('class', 'plagiarsm_programming_course_selection_wrapper');
        wrapperDiv.innerHTML = form_html;
        var checkboxes = wrapperDiv.getElementsByTagName('input');

        // override back and next link
        var links = wrapperDiv.getElementsByTagName('a');
        var i = 0;
        for (i=0; i<links.length; i++) {
            var link = links[i];
            if (link.id=='changepagelink') {
                YAHOO.util.Event.addListener(link, 'click', function(e) {
                    YAHOO.util.Event.preventDefault(e);
                    var page = this.getAttribute('page');
                    M.plagiarism_programming.select_course.show_course_panel(page);
                });
            }
        }

        for (i=0; i<checkboxes.length; i++) {
            var chk = checkboxes[i];
            if (chk.getAttribute('type')=='checkbox' && chk.getAttribute('name').substr(0,7)=='course_') {
                M.plagiarism_programming.select_course.change_status(chk.checked,chk);
                YAHOO.util.Event.addListener(chk, 'click', function() {
                    var course_id = this.getAttribute('name').substr(7);
                    M.plagiarism_programming.select_course.enable_disable_course(course_id,this.checked,this);
                });
            }
        }
        M.plagiarism_programming.select_course.panel.setBody(wrapperDiv);
    },

    create_footer_panel : function() {
        var footer = document.createElement('div');
        footer.setAttribute('class','enable_course_footer_panel');

        var label_category = document.createElement('label');
        label_category.innerHTML = 'Course search by category';
        label_category.setAttribute('for', 'course_search_category');
        var category_holder = document.createElement('span');
        category_holder.setAttribute('class', 'course_category_holder');

        var label_name = document.createElement('label');
        label_name.innerHTML = 'By name ';
        label_name.setAttribute('for', 'course_search_name');
        var text = document.createElement('input');
        text.setAttribute('id', 'course_search_name');
        text.setAttribute('type', 'text');
        var button = document.createElement('input');
        button.setAttribute('type','button');
        button.setAttribute('value','Search');
        YAHOO.util.Event.addListener(button, 'click', M.plagiarism_programming.select_course.search_course);

        footer.appendChild(label_category);
        footer.appendChild(category_holder);
        footer.appendChild(label_name);
        footer.appendChild(text);
        footer.appendChild(button);

        this.init_category_cbo();
        return footer;
    },

    enable_disable_course: function(id,enable,checkbox) {
        var callback = {
            success: function(o) {
                M.plagiarism_programming.select_course.change_status(enable,checkbox);
            }
        };
        var task = (enable)?'enablecourse':'disablecourse';
        YAHOO.util.Connect.asyncRequest('POST','coursesetting/ajax.php',callback,'task='+task+'&id='+id);
    },

    change_status: function(is_enabled,checkbox) {
        var row = new YAHOO.util.Element(checkbox.parentNode.parentNode.parentNode);
        if (is_enabled) {
            row.addClass('enabled');
        } else {
            row.removeClass('enabled');
        }
    },

    update_level: function() {
        YAHOO.util.Connect.asyncRequest('POST','coursesetting/ajax.php',{},'task=setenabledlevel&level='+this.value);
    },

    get_categories: function(o) {
        console.log('Get categories');
        var footer = M.plagiarism_programming.select_course.panel.footer;
        var category_holder = footer.getElementsByClassName('course_category_holder')[0];
        category_holder.innerHTML = o.responseText;
        var select = category_holder.getElementsByTagName('select')[0];
        select.id = 'course_search_category';
    },

    search_course: function(ev) {
        var category_select = document.getElementById('course_search_category');
        var category = category_select.options[category_select.selectedIndex].value;
        var name = document.getElementById('course_search_name').value;
        var callback = {
            success: M.plagiarism_programming.select_course.display_courses
        }
        YAHOO.util.Connect.asyncRequest('POST', 'coursesetting/ajax.php?task=getcourse&name='
            +name+'&category='+category, callback);
    }
}