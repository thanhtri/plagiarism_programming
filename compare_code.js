/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.compare_code = {
    
    pattern: /match[0-9]+-[01]\.html#([0-9]+)/,
    id: 0, //set in init function
    
    init : function(Y,id) {
        this.id = id;
        this.init_links();
        this.init_action();
    },
    
    init_links: function() {
        var links = YAHOO.util.Selector.query('div.programming_result_comparison_top_right a, '
            +'div.programming_result_comparison_bottom_left a, '
            +'div.programming_result_comparison_bottom_right a')
        for (var i=0;i<links.length;i++) {
            if (links[i].getAttribute('href')!=null) {
                YAHOO.util.Event.addListener(links[i],'click',function(e) {
                    YAHOO.util.Event.preventDefault(e);
                    var href = this.getAttribute('href');
                    var matches = M.plagiarism_programming.compare_code.pattern.exec(href);
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_left',matches[1]);
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_right',matches[1]);
                },true);
            }
        }
    },  
    
    init_action: function() {
        var action_select = document.getElementById('action_menu');
        this.change_image(action_select.options[action_select.selectedIndex].value);
        YAHOO.util.Event.addListener(action_select,'change',this.action_menu_onchange,true);
    },
    
    action_menu_onchange: function(e) {
        var action = this.options[this.selectedIndex].value;
        var callback = {
            success: function(o) {
                if (o.responseText=='OK') {
                    M.plagiarism_programming.compare_code.change_image(action);
                }
            }
        }
        YAHOO.util.Connect.asyncRequest('POST','mark_result.php',callback,'id='+M.plagiarism_programming.compare_code.id+'&action='+action);
    },
    
    change_image: function(action) {
        var src = '';
        switch (action) {
            case 'Y':src = 'suspicious.png';break;
            case 'N':src = 'normal.png';break;
            default :src = '';
        }
        var img = document.getElementById('mark_image');
        img.setAttribute('src', src);
    },
    
    move_frame: function(div_class,anchorName) {
        var div = document.getElementsByClassName(div_class)[0];
        var anchors = div.getElementsByTagName('a');
        for (var i=0;i<anchors.length;i++) {
            if (anchors[i].getAttribute('name')==anchorName) {
                div.scrollTop = anchors[i].offsetTop;
            }
        }
    }
}