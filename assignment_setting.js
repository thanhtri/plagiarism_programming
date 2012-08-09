M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.assignment_setting = {

    submit_date_num : 0,
    Y : null,

    init : function(Y, jplag_support, moss_support) {
        this.Y = Y;
        this.init_mandatory_field(Y);
        this.init_disable_unsupported_tool(Y, jplag_support, moss_support);
        this.enable_disable_elements(Y, jplag_support, moss_support);
    },

    init_mandatory_field: function(Y) {
        var required_img = Y.one('.req');

        // put the required class for the select and checkboxes
        var config_block = Y.one('#programming_header');
        var items = config_block.all('.fitem');
        var div = items.item(1);
        div.addClass('required');
        var label = div.one('span.helplink');
        label.insert(required_img.cloneNode(true), 'before');

        div = items.item(2);
        div.addClass('required');
        label = div.one('span.helplink');
        label.insert(required_img.cloneNode(true), 'before');

        Y.one(document.forms[0]).on('submit', function(e) {
            if (skipClientValidation) {
                return;
            }
            var is_tool_selected = this.check_mandatory_form_field(Y);
            var is_date_valid = this.check_submit_date(Y);
            if (!is_tool_selected || !is_date_valid) {
                e.preventDefault();
            }
        }, this);var config_block = this.Y.one('#programming_header');

        var new_date_button = config_block.one('input[name=add_new_date]');
        new_date_button.on('click', function(e) {
            skipClientValidation = true;
        });

        var add_date = Y.one('input[name=is_add_date]');
        if (add_date && add_date.getAttribute('value')==1) {
            window.scrollTo(0, new_date_button.getY()-150);
        }
    },

    /**
     * Check whether at least one tool is selected.
     * @return true or false
     **/
    check_mandatory_form_field: function(Y) {
        if (this.is_plugin_enabled()) {
            var config_block = Y.one('#programming_header');
            var selected_tool = config_block.one('input[name*=detection_tools]:checked');
            if (selected_tool==null) {
                // whether exist an error message or not?
                var tool_checkbox = config_block.one('input[name*=detection_tools]');
                M.plagiarism_programming.assignment_setting.display_error_message(Y, tool_checkbox,
                    M.str.plagiarism_programming.no_tool_selected_error)
                return false;
            }
        }
        return true;
    },

    /**
     * Check to make sure all submit date is not before the current date
     * @return true or false
     **/
    check_submit_date : function(Y) {
        if (!this.is_plugin_enabled()) { // do not check if plugin not enabled
            return true;
        }
        var config_block = Y.one('#programming_header');
        var all_valid = true;
        var enabled_chk = config_block.all('input[type=checkbox][name*=scan_date]');
        var size = enabled_chk.size();
        var current_date = new Date();
        for (var i=0; i<size; i++) {
            if (enabled_chk.item(i).get('checked')) {
                var day = config_block.one('select[name=scan_date\\['+i+'\\]\\[day\\]]').get('value');
                var month=config_block.one('select[name=scan_date\\['+i+'\\]\\[month\\]]').get('value');
                var year=config_block.one('select[name=scan_date\\['+i+'\\]\\[year\\]]').get('value');
                var date = new Date(year, month-1, day);
                var current = new Date(current_date.getFullYear(), current_date.getMonth(), current_date.getDate());
                if (date.getTime()<current.getTime()) {
                    M.plagiarism_programming.assignment_setting.display_error_message(Y, 
                        enabled_chk.item(i), M.str.plagiarism_programming.invalid_submit_date_error);
                    all_valid = false;
                }
            }
        }
        return all_valid;
    },
    
    display_error_message : function(Y, node, error_msg) {
        while (node!=null && node.get('tagName')!='FIELDSET') {
            node = node.get('parentNode');
        }
        if (node!=null && node.get('tagName')=='FIELDSET' && node.all('.error').isEmpty()) { // insert the message
            var msg_node = Y.Node.create('<span class="error">'+error_msg+'<br></span>');
            node.get('children').item(0).insert(msg_node,'before');
            window.scrollTo(0, msg_node.getY()-40);
        }
    },

    init_disable_unsupported_tool: function(Y, jplag_lang, moss_lang) {
        Y.all('input[name=programmingYN]').on('click', function() {
            this.enable_disable_elements(Y, jplag_lang, moss_lang);
        }, this);
        Y.one('select[name=programming_language]').on('change', function() {
                this.enable_disable_elements(Y, jplag_lang, moss_lang);
        }, this);
    },

    enable_disable_elements: function(Y, jplag_lang, moss_lang) {
        var config_block = this.Y.one('#programming_header');
        if (!this.is_plugin_enabled()) {
            // disable both MOSS and JPlag
            config_block.all('input[name*=detection_tools]').set('disabled', true);
        } else { // disable each one of them
            var value = Y.one('select[name=programming_language]').get('value');
            var jplag_disabled = true;
            var moss_disabled = true;
            if (jplag_lang && jplag_lang[value]) {
                jplag_disabled = false;
            }
            if (moss_lang && moss_lang[value]) {
                moss_disabled = false;
            }
            Y.one('input[type=checkbox][name=detection_tools\\[jplag\\]]').set('disabled', jplag_disabled);
            Y.one('input[type=checkbox][name=detection_tools\\[moss\\]]').set('disabled', moss_disabled);
        }
    },

    is_plugin_enabled: function() {
        var config_block = this.Y.one('#programming_header');
        return config_block.one('input[name=programmingYN]:checked').get('value')=='1';
    }
}
