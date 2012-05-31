M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.assignment_setting = {

    submit_date_num : 0,

    init : function(Y) {
        this.init_mandatory_field();
    },

    init_mandatory_field: function() {
        var required_img = document.getElementsByClassName('req')[0];

        // put the required class for the select and checkboxes
        var select = document.getElementById('id_programming_language');
        var div = select.parentNode.parentNode;
        div.setAttribute('class', div.getAttribute('class')+' required');
        var label = div.firstChild.firstChild;
        label.insertBefore(required_img.cloneNode(true),label.childNodes.item(1));

        var check = document.getElementsByName('detection_tools[jplag]')[0];
        div = check.parentNode.parentNode.previousSibling;
        div.setAttribute('class', div.getAttribute('class')+' required');
        div.childNodes.item(0).appendChild(required_img.cloneNode(true));

        var form = document.forms[0];
        YAHOO.util.Event.addListener(form, 'submit', M.plagiarism_programming.assignment_setting.check_mandatory_form_field);
    },

    check_mandatory_form_field: function(evt) {
        var check = document.getElementsByName('programmingYN')[0];
        if ((check.value==1 && check.checked) || (check.value==0 && !check.checked)) {
            var jplag_select = document.getElementsByName('detection_tools[jplag]')[0];
            var moss_select = document.getElementsByName('detection_tools[moss]')[0];
            if (!jplag_select.checked && !moss_select.checked) {
                var error_msg = document.createElement('span');
                error_msg.setAttribute('class', 'error');
                error_msg.textContent = M.str.plagiarism_programming.no_tool_selected_error;
                jplag_select.parentNode.parentNode.insertBefore(error_msg, jplag_select.parentNode);
                jplag_select.parentNode.parentNode.insertBefore(document.createElement('br'), jplag_select.parentNode);
                YAHOO.util.Event.preventDefault(evt);
            }
        }
    }
}
