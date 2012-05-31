// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This is the javascript code of the compare code page (to compare the similarity of 2 students' code)
 * It include ajax calls to mark a student as suspicious and cross similarity of one student with another
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.compare_code = {

    id: 0, //set in init function
    menu: null, //the student similarity menu,
    name_table: null,
    result_table: null,
    student1: null,
    student2: null,

    init : function(Y,info,name_table,result_table,anchor) {
        this.id = info.id;
        this.student1 = info.student1;
        this.student2 = info.student2;
        this.name_table = name_table;
        this.result_table = result_table;

        this.init_links();
        this.init_action(info.mark);
        this.init_similarity_menu();
        this.init_compare_code();
        if (anchor>=0) {
            this.move_frame('programming_result_comparison_bottom_left', 'sim_'+anchor);
            this.move_frame('programming_result_comparison_bottom_right','sim_'+anchor);
        }
    },

    init_links: function() {
        var links = YAHOO.util.Selector.query('div.programming_result_comparison_top_left a.similarity_link');
        for (var i=0; i<links.length; i++) {
            if (links[i].getAttribute('href')!=null) {
                YAHOO.util.Event.addListener(links[i],'click',function(e) {
                    YAHOO.util.Event.preventDefault(e);
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_left',this.getAttribute('href'));
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_right',this.getAttribute('href'));
                },true);
            }
        }
    },

    init_compare_code: function() {
        var div = document.getElementsByClassName('programming_result_comparison_top_right')[0];
        div.appendChild(document.createElement('br'));
        var checkbox1 = this.create_checkbox_for_turning_on_cross_similarity('programming_result_comparison_bottom_left');
        div.appendChild(checkbox1);
        div.appendChild(document.createTextNode(' Show similarity of '+this.name_table[this.student1]+' with other students'));

        div.appendChild(document.createElement('br'));
        var checkbox2 = this.create_checkbox_for_turning_on_cross_similarity('programming_result_comparison_bottom_right');
        div.appendChild(checkbox2);
        div.appendChild(document.createTextNode(' Show similarity of '+this.name_table[this.student2]+' with other students'));
    },

    init_action: function(mark) {
        var action_select = document.getElementById('action_menu');
        this.change_image(mark);
        YAHOO.util.Event.addListener(action_select,'change',this.action_menu_onchange,true);
    },

    init_similarity_menu: function() {
        var div = document.createElement('div');
        div.id = 'similarity_menu_div';
        document.body.appendChild(div);

        var menu = new YAHOO.widget.Menu('basicMenu',{});
        M.plagiarism_programming.compare_code.menu = menu;
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
        YAHOO.util.Connect.asyncRequest('POST','mark_result.php',callback,
            'id='+M.plagiarism_programming.compare_code.id+'&action='+action);
    },

    change_image: function(action) {
        var src = '';
        switch (action) {
            case 'Y':src = 'pix/suspicious.png';break;
            case 'N':src = 'pix/normal.png';break;
            default :src = '';
        }
        var img = document.getElementById('mark_image');
        img.setAttribute('src', src);
        if (action==null || action=='') {
            img.style.display = 'none';
        } else {
            img.style.display = 'inline';
        }
    },

    move_frame: function(div_class,anchorName) {
        this.unselect_blocks(div_class);
        var div = document.getElementsByClassName(div_class)[0];
        var portion = YAHOO.util.Selector.query('div.'+div_class+' font.'+anchorName)[0];
        portion.setAttribute('class', portion.getAttribute('class')+' block_selected');
        div.scrollTop = portion.offsetTop;
    },
    
    unselect_blocks: function(div_class) {
        var previous_portion = YAHOO.util.Selector.query('div.'+div_class+' font.block_selected');
        for (var i=0; i<previous_portion.length; i++) {
            var className = previous_portion[i].getAttribute('class');
            previous_portion[i].setAttribute('class',className.replace(/(?:^|\s)block_selected(?!\S)/,''));
        }
    },

    show_similarity_other_students: function(div_class) {
        var div = document.getElementsByClassName(div_class)[0];
        var spans = div.getElementsByTagName('span');
        for (var i=0; i<spans.length; i++) {
            if (spans[i].getAttribute('type')=='end') {
                var img = this.create_image_to_show_similarity_with_others();
                spans[i].appendChild(img,spans[i]);
            }
        }
    },

    hide_similarity_with_other_students: function(div_class) {
        var div = document.getElementsByClassName(div_class)[0];
        var imgs = div.getElementsByClassName('student_similarity_view_img');
        var num = imgs.length;
        for (var i=0; i<num; i++) {
            imgs[0].parentNode.removeChild(imgs[0]);
        }
    },

    create_image_to_show_similarity_with_others: function() {
        var img = document.createElement('img');
        img.src = 'pix/list_student.png';
        img.setAttribute('class', 'student_similarity_view_img');
        YAHOO.util.Event.addListener(img,'mouseover',function(e) {
            var end_span = img.parentNode;
            M.plagiarism_programming.compare_code.color_portion(end_span);
        });
        YAHOO.util.Event.addListener(img,'mouseout',function(e) {
            var end_span = img.parentNode;
            M.plagiarism_programming.compare_code.uncolor_portion(end_span);
        });
        YAHOO.util.Event.addListener(img,'click',function(e) {
            var end_span = img.parentNode;
            M.plagiarism_programming.compare_code.show_menu(end_span);
        });
        return img;
    },

    color_portion: function(end_span) {
        this.unselect_blocks('programming_result_comparison_bottom_left');
        this.unselect_blocks('programming_result_comparison_bottom_right');
        var sid = end_span.getAttribute('sid');
        var colors = end_span.getAttribute('color').split(',');
        var color = colors[colors.length-1];
        var prev_node = this.previous_node(end_span);
        while (!this.is_end_node(prev_node,sid)) {
            if (prev_node.nodeType==3) {
                console.log('Color text');
                var font = document.createElement('font');
                font.setAttribute('class','colored');
                font.setAttribute('color', color);
                prev_node.parentNode.insertBefore(font, prev_node);
                prev_node = font.appendChild(prev_node);
            }
            prev_node = this.previous_node(prev_node);
        }
    },

    uncolor_portion: function(end_span) {
        var sid = end_span.getAttribute('sid');
        var prev_node = this.previous_node(end_span);
        while (!this.is_end_node(prev_node,sid)) {
            if (prev_node.nodeType==3 && prev_node.parentNode.getAttribute('class')=='colored') {
                var font = prev_node.parentNode;
                font.parentNode.insertBefore(prev_node, font);
                font.parentNode.removeChild(font);
            }
            prev_node = this.previous_node(prev_node);
        }
    },

    previous_node: function(node) {
        var prev_node;
        prev_node = node.previousSibling;
        while (prev_node==null) {
            node = node.parentNode;
            prev_node = node.previousSibling;
        }
        while (prev_node.nodeType==1 && prev_node.tagName=='FONT') {
            console.log('Last child: '+prev_node.lastChild.nodeValue);
            prev_node = prev_node.lastChild;
        }
//        if (prev_node.nodeType==1) {
//            console.log(prev_node.tagName);
//        } else {
//            console.log('Text');
//        }
        return prev_node;
    },

    is_end_node: function(node,sid) {
        return node.nodeType==1 && node.tagName=='SPAN' && node.getAttribute('type')=='begin' && node.getAttribute('sid')==sid;
    },

    show_menu: function(span) {
        console.log('Show menu');
        var menu = M.plagiarism_programming.compare_code.menu;
        menu.clearContent();
        var sids = span.getAttribute('sid').split(',');
        var anchors = span.getAttribute('anchor').split(',');
        var this_student = this.student1;
        var buble_up = span.parentNode;
        var className = buble_up.getAttribute('class');
        while (className!='programming_result_comparison_bottom_left' && className!='programming_result_comparison_bottom_right') {
            buble_up = buble_up.parentNode;
            className = buble_up.getAttribute('class');
        }
        if (className=='programming_result_comparison_bottom_right') {
            this_student = this.student2;
        }
        for (var i=0; i<sids.length; i++) {
            var name = this.name_table[sids[i]];
            var std1 = Math.max(this_student, sids[i]);
            var std2 = Math.min(this_student, sids[i]);
            menu.addItem({text:name,url:'view_compare.php?id='+this.result_table[std1][std2]+'&anchor='+anchors[i]});
        }
        menu.cfg.setProperty('context',[span.firstChild,'tl','bl']);
        menu.render('similarity_menu_div');
        menu.show();
    },

    create_checkbox_for_turning_on_cross_similarity: function(className) {
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        YAHOO.util.Event.addListener(checkbox,'click',function() {
            if (this.checked) {
                M.plagiarism_programming.compare_code.show_similarity_other_students(className);
            } else {
                M.plagiarism_programming.compare_code.hide_similarity_with_other_students(className);
            }
        },true);
        return checkbox;
    }
}