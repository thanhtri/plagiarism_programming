M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.assignment_setting = {

    submit_date_num : 0,
    Y : null,

    init : function(Y) {
        this.Y = Y;
        this.init_mandatory_field();
    },

    init_mandatory_field: function() {
        var Y = this.Y;
        var required_img = Y.one('.req');

        // put the required class for the select and checkboxes
        // the problem with the mform require rule is that they fails validation even when not enabled
        var config_block = Y.one('#programming_header');
        if (!config_block) {
            config_block = Y.one('#id_programming_header');
        }
        var items = config_block.all('.fitem');
        var div = items.item(1);
        div.addClass('required');
        var label = div.one('span.helplink');
        if (!label) {
            label = div.one('span.helptooltip');
        }
        label.insert(required_img.cloneNode(true), 'before');

        div = items.item(2);
        div.addClass('required');
        label = div.one('span.helplink');
        if (!label) {
            label = div.one('span.helptooltip');
        }
        label.insert(required_img.cloneNode(true), 'before');

        var skipClientValidation = false;
        Y.one('#mform1').on('submit', function(e) {
console.log('Submit clicked');
            if (skipClientValidation) {
                return;
            }
console.log('Called');
            var is_date_valid = this.check_submit_date(Y);
            if (!is_date_valid) {
                e.preventDefault();
            }
        }, this);

        // do not need to validate when clicking no submit button
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
     * Check to make sure all submit date is not before the current date
     * @return true or false
     **/
    check_submit_date : function(Y) {
        if (!this.is_plugin_enabled()) { // do not check if plugin not enabled
            return true;
        }
        var config_block = Y.one('#programming_header');
        if (!config_block) {
            config_block = Y.one('#id_programming_header');
        }
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

    /**
     * Is at least one tool is enabled
     * @return true if at least one among jplag and moss is checked
     **/
    is_plugin_enabled: function() {
        var config_block = this.Y.one('#programming_header');
        if (!config_block) {
            config_block = this.Y.one('#id_programming_header');
        }
        return config_block.one('input[name=programmingYN]:checked').get('value')=='1';
    }
}
