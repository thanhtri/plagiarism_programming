M.plagiarism_programming = M.plagiarism_programming || {};
M.plagiarism_programming.select_course = {

    init: function(Y) {
        Yu = Y;
        this.Y = Y;
        this.init_course_panel(Y);
        this.init_radio_button(Y);
    },

    init_course_panel: function(Y) {
        var coursepanel = Y.Node.create('<div></div>');
        // delegate events to course panel
        coursepanel.delegate('click', function(ev) {
            ev.preventDefault();
            var page = ev.target.getAttribute('page');
            M.plagiarism_programming.select_course.show_course_panel(page);
        }, 'a.changepagelink');
        coursepanel.delegate('click', function(ev) {
            var course_id = this.getAttribute('name').substr(7);
            M.plagiarism_programming.select_course.enable_disable_course(course_id, this.get('checked'), this);
        }, 'input[type=checkbox][name*=course]');

        Y.one('body').append(coursepanel);

        var panel = new Y.Panel({
            srcNode: coursepanel,
            width: '500px',
            height: '450px',
            visible: false,
            centered: true,
            zIndex: 2,
            hideOn: [
                {
                    eventName: 'clickoutside'
                }
            ]
        }).render();
        panel.set('headerContent', M.str.plagiarism_programming.course_select);
        panel.set('bodyContent', '<div></div>');
        panel.set('footerContent', this.create_footer_panel(Y));
        M.plagiarism_programming.select_course.panel = panel;
    },

    init_radio_button: function(Y) {
        // initialize the radio button
        var select_level = Y.all('.plagiarism_programming_enable_level');
        select_level.on('click', function(ev) {
            this.update_level(ev.target.get('value'));
            if (ev.target.get('value')=='course') {
                this.show_course_panel(1);
            }
        }, this);
    },

    init_category_cbo: function(Y) {
        Y.io('coursesetting/ajax.php?task=getcategory', {
            method: 'GET',
            data: {
                task: 'getcategory'
            },
            on: {
                success: function(id, o) {
                    var footer = M.plagiarism_programming.select_course.panel.footerNode;
                    var category_holder = footer.one('.course_category_holder');
                    category_holder.append(o.responseText);
                    var select = category_holder.one('select');
                    select.set('id', 'course_search_category');
                }
            }
        });
    },

    show_course_panel: function(page) {
        var Y = this.Y;
        M.plagiarism_programming.select_course.panel.show();

        Y.io('coursesetting/ajax.php', {
            method: 'GET',
            data: {
                task: 'getcourse',
                page: page
            },
            on: {
                success: function(id, o) {
                    M.plagiarism_programming.select_course.display_courses(o)
                }
            }
        });
    },

    display_courses: function(o) {
        var Y = this.Y;
        var form_html = o.responseText;
        var wrapperDiv = Y.Node.create('<div></div>');
        wrapperDiv.addClass('plagiarsm_programming_course_selection_wrapper');
        wrapperDiv.append(form_html);
        M.plagiarism_programming.select_course.panel.set('bodyContent', wrapperDiv);
    },

    /**
     * Create panel footer, which is an ajax search box to search all courses
     **/
    create_footer_panel : function(Y) {
        var footer = Y.Node.create('<div></div>');
        footer.addClass('enable_course_footer_panel');

        var label_category = Y.Node.create('<label></label>');
        label_category.setContent(M.str.plagiarism_programming.search_by_category);
        label_category.set('for', 'course_search_category');
        var category_holder = Y.Node.create('<span></span>');
        category_holder.addClass('course_category_holder');

        var label_name = Y.Node.create('<label></label>');
        label_name.setContent(M.str.plagiarism_programming.by_name);
        label_name.set('for', 'course_search_name');
        var text = Y.Node.create('<input id="course_search_name" type="text"/>');
        var button = Y.Node.create('<input type="button" value="'+M.str.plagiarism_programming.search+'"/>');
        button.on('click', M.plagiarism_programming.select_course.search_course, this);

        footer.append(label_category);
        footer.append(category_holder);
        footer.append('<br/>');
        footer.append(label_name);
        footer.append(text);
        footer.append(button);

        this.init_category_cbo(Y);
        return footer;
    },

    enable_disable_course: function(id,enable,checkbox) {
        var Y = this.Y;
        var task = (enable) ? 'enablecourse' : 'disablecourse';
        Y.io('coursesetting/ajax.php', {
            method: 'POST',
            data: {
                task: task,
                id: id
            }
        });
    },

    update_level: function(level) {
        var Y = this.Y;
        Y.io('coursesetting/ajax.php', {
            method: 'POST',
            data: {
                task: 'setenabledlevel',
                level: level
            }
        });
    },

    search_course: function(ev) {
        var Y = this.Y;
        var category_select = Y.one('#course_search_category');
        var category = category_select.get('value');
        var name = Y.one('#course_search_name').get('value');

        Y.io('coursesetting/ajax.php', {
            method: 'GET',
            data: {
                task: 'getcourse',
                name: name,
                category: category
            },
            on: {
                success: this.display_courses
            }
        });
    }
}
