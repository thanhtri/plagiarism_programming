M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.assignment_setting = {

    submit_date_num : 0,
    Y : null,

    init : function(Y) {
        this.Y = Y;
        this.init_mandatory_field(Y);
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
            var is_valid = M.plagiarism_programming.assignment_setting.check_mandatory_form_field(Y);
            if (!is_valid) {
                e.preventDefault();
            }
        });

        var new_date_button = config_block.one('input[name=add_new_date]');
        new_date_button.on('click', function(e) {
            skipClientValidation = true;
        });

        var add_date = Y.one('input[name=is_add_date]');
        if (add_date && add_date.getAttribute('value')==1) {
            window.scrollTo(0, new_date_button.getY()-150);
        }
    },

    check_mandatory_form_field: function(Y) {
        var config_block = Y.one('#programming_header');
        var checked = config_block.one('input[name=programmingYN]:checked').get('value');
        if (!skipClientValidation && checked=="1") {
            var selected_tool = config_block.one('input[name*=detection_tools]:checked');
            if (selected_tool==null) {
                // whether exist an error message or not?
                var tool_checkbox = config_block.one('input[name*=detection_tools]');
                var parent = tool_checkbox.get('parentNode').get('parentNode');
                var error_msgs = parent.all('.error');
                var error_msg = null;
                if (error_msgs.isEmpty()) { // insert the error message
                    error_msg = Y.Node.create('<span class="error">'+M.str.plagiarism_programming.no_tool_selected_error+
                        '<br></span>');
                    tool_checkbox.get('parentNode').insert(error_msg, 'before');
                } else {
                    error_msg = error_msgs.item(0);
                }
                window.scrollTo(0, error_msg.getY()-40);
                return false;
            }
        }
        return true;
    }
}
