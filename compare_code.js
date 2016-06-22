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
 * It include ajax calls to mark a student as suspicious and cross similarity of one student with another,
 * as well as showing the similarity history chart
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


M.plagiarism_programming = M.plagiarism_programming || {};

M.plagiarism_programming.compare_code = {
    Y: null, // YUI instance

    id: 0, //set in init function
    menu: null, //the student similarity menu,
    name_table: null,
    result_table: null,
    student1: null,
    student2: null,
    history_overlay: null,
    chart_width: 325,
    chart_height: 150,

    init : function(Y, info, name_table, summary_table, result_table, anchor) {
        this.Y = Y;
        this.id = info.id;
        this.student1 = info.student1;
        this.student2 = info.student2;
        this.name_table = name_table;
        this.result_table = result_table;
        this.summary_table = summary_table;

        this.init_summary_table();
        this.init_links(Y);
        this.init_action(Y);
        this.init_select_version(Y);
        this.init_compare_code(Y);
        if (anchor>=0) {
            this.move_frame('programming_result_comparison_bottom_left', 'sim_'+anchor);
            this.move_frame('programming_result_comparison_bottom_right','sim_'+anchor);
        }
        this.init_history_chart(Y);

        // hide menu and chart if click outside
        Y.one(document).on('click', function(e) {
            var node = e.target;
            while (node!=null && !node.hasClass('yui3-overlay-content')) {
                node = node.get('parentNode');
            }
            if (node==null) {
                M.plagiarism_programming.compare_code.history_overlay.hide();
                M.plagiarism_programming.compare_code.menu.hide();
            }
        });

        this.change_image(Y.one('#action_menu').get('value'));
    },

    init_links: function(Y) {
        Y.all('div.programming_result_comparison_top_left a.similarity_link').each(function(link) {
            if (link.get('href')!=null) {
                link.on('click', function(e) {
                    e.preventDefault();
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_left',
                        link.getAttribute('href'));
                    M.plagiarism_programming.compare_code.move_frame('programming_result_comparison_bottom_right',
                        link.getAttribute('href'));
                });
            }
        });
    },

    init_compare_code: function(Y) {
        var div = Y.one('.programming_result_comparison_top_right');

        if (this.name_table[this.student1]) {
            div.append('<br/>');
            var checkbox1 = this.create_checkbox_for_turning_on_cross_similarity('programming_result_comparison_bottom_left');
            checkbox1.set('id', 'chk_student_1');
            div.append(checkbox1);
            div.append('<label for="chk_student_1">'+
                M.str.plagiarism_programming.show_similarity_to_others.replace('{student}',this.name_table[this.student1])
                +'</label>');
        }

        if (this.name_table[this.student2]) {
            div.append('<br/>');
            var checkbox2 = this.create_checkbox_for_turning_on_cross_similarity('programming_result_comparison_bottom_right');
            checkbox2.set('id', 'chk_student_2');
            div.append(checkbox2);
            div.append('<label for="chk_student_2">'+
                M.str.plagiarism_programming.show_similarity_to_others.replace('{student}', this.name_table[this.student2])+
                '</label>');
        }

        // the similarity menu
        M.plagiarism_programming.compare_code.menu = new Y.Overlay({});
    },

    init_action: function(Y) {
        var select = Y.one('#action_menu');
        select.on('change', this.action_menu_onchange, select);
    },

    init_select_version: function(Y) {
        var version_select = Y.one('#report_version');
        if (version_select!=null) {
            version_select.on('change', function(ev) {
                var id = this.get('value');
                window.location = 'view_compare.php?id='+id;
            }, version_select);
        }
    },

    init_history_chart: function(Y) {
        var select_history = Y.one('#report_version');
        var history = M.str.plagiarism_programming.history_char;
        var link = Y.Node.create('<a id="show_history_link" href="#">'+history+'</a>');
        select_history.get('parentNode').append(link);
        this.create_chart();
        link.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            M.plagiarism_programming.compare_code.history_overlay.show();
        });
    },

    action_menu_onchange: function(e) {
        var action = this.get('value');
        var Y = M.plagiarism_programming.compare_code.Y;
        Y.io('mark_result.php', {
            method: 'POST',
            data: {
                task: 'mark',
                id: M.plagiarism_programming.compare_code.id,
                action: action,
                sesskey: M.cfg.sesskey
            },
            on: {
                complete: function(id, res) {
                    if (res.responseText=='OK') {
                        M.plagiarism_programming.compare_code.change_image(action);
                    }
                }
            }
        });
    },

    change_image: function(action) {
        var src = '';
        switch (action) {
            case 'Y':src = 'pix/suspicious.png'; break;
            case 'N':src = 'pix/normal.png'; break;
            default :src = '';
        }
        var Y = M.plagiarism_programming.compare_code.Y;
        var img = Y.one('#mark_image');
        img.set('src', src);
        img.setStyle('display', (action==null || action=='')?'none':'inline')
    },

    move_frame: function(div_class, anchorName) {
        var Y = M.plagiarism_programming.compare_code.Y;
        this.unselect_blocks(div_class);
        var div = Y.one('.'+div_class);
        var portion = div.one('font.'+anchorName);
        portion.addClass('block_selected');
        div.set('scrollTop', portion.get('offsetTop'));
    },

    unselect_blocks: function(div_class) {
        M.plagiarism_programming.compare_code.Y.all('div.'+div_class+' font.block_selected').each(function(similar_block) {
            similar_block.removeClass('block_selected');
        });
    },

    show_similarity_other_students: function(div_class) {
        M.plagiarism_programming.compare_code.Y.all('div.'+div_class+' span').each(function(span) {
            if (span.getAttribute('type')=='end') {
                var img = M.plagiarism_programming.compare_code.create_image_to_show_similarity_with_others();
                span.append(img);
            }
        });
    },

    hide_similarity_with_other_students: function(div_class) {
        M.plagiarism_programming.compare_code.Y.all('div.'+div_class+' .student_similarity_view_img').each(function(img) {
            img.remove(true);
        });
    },

    create_image_to_show_similarity_with_others: function() {
        var Y = M.plagiarism_programming.compare_code.Y;
        var img = Y.Node.create('<img src="pix/list_student.png" class="student_similarity_view_img" />');
        img.on('mouseover', function(e) {
            var span = Y.Node.getDOMNode(img).parentNode;
            M.plagiarism_programming.compare_code.color_portion(span);
        });
        img.on('mouseout', function(e) {
            var span = Y.Node.getDOMNode(img).parentNode;
            M.plagiarism_programming.compare_code.uncolor_portion(span);
        });
        img.on('click', function(e) {
            e.stopPropagation();
            var span = Y.Node.getDOMNode(img).parentNode;
            M.plagiarism_programming.compare_code.show_menu(span);
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
            prev_node = prev_node.lastChild;
        }
        return prev_node;
    },

    is_end_node: function(node, sid) {
        return node.nodeType==1 && node.tagName=='SPAN' && node.getAttribute('type')=='begin' && node.getAttribute('sid')==sid;
    },

    show_menu: function(span) {
        var Y = M.plagiarism_programming.compare_code.Y;
        var menu = M.plagiarism_programming.compare_code.menu;
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
        var menu_list = Y.Node.create('<ul class="programming_student_similarity_menu"/>');
        for (var i=0; i<sids.length; i++) {
            var name = this.name_table[sids[i]];
            var std1 = Math.max(this_student, sids[i]);
            var std2 = Math.min(this_student, sids[i]);
            if (std1 && std2) {
                var href = 'view_compare.php?id='+this.result_table[std1][std2]+'&anchor='+anchors[i];
                menu_list.append('<li><a href="'+href+'">'+name+'</a></li>');
            }
        }
        menu.set('bodyContent', menu_list);
        menu.align(span.firstChild, [Y.WidgetPositionAlign.TL, Y.WidgetPositionAlign.TR]);
        menu.render(document.body);
        menu.show();
    },

    create_checkbox_for_turning_on_cross_similarity: function(className) {
        var Y = this.Y;
        var checkbox = Y.Node.create('<input type="checkbox"/>');
        checkbox.on('click', function(e) {
            if (checkbox.get('checked')) {
                M.plagiarism_programming.compare_code.show_similarity_other_students(className);
            } else {
                M.plagiarism_programming.compare_code.hide_similarity_with_other_students(className);
            }
        });
        return checkbox;
    },

    create_chart: function() {
        var Y = this.Y;
        this.history_overlay = new Y.Overlay({
            visible:false
        });
        this.history_overlay.align(Y.one('#show_history_link'), [Y.WidgetPositionAlign.TR, Y.WidgetPositionAlign.BR]);

        Y.io('mark_result.php?task=get_history&id='+M.plagiarism_programming.compare_code.id+'&sesskey='+M.cfg.sesskey, {
            on: {
                sesskey : M.cfg.sesskey,
                success: M.plagiarism_programming.compare_code.load_overlay
            }
        });
    },

    load_overlay: function(id, o) {
        var Y = M.plagiarism_programming.compare_code.Y;
        var history = M.plagiarism_programming.compare_code.Y.JSON.parse(o.responseText);

        var overlay = Y.Node.create('<div class="programming_result_chart_overlay"></div>');
        var canvas = Y.Node.create('<div class="programming_result_popup_chart"></div>');
        var h_label = Y.Node.create('<label class="h_label">'+M.str.moodle.date+'</label>');
        var v_label = Y.Node.create('<label class="v_label">%</label>');

        canvas.append(h_label);
        canvas.append(v_label);
        var canvas_height = M.plagiarism_programming.compare_code.chart_height-50;
        var left = 20;
        for (var i in history) {
            var bar = Y.Node.create('<a class="bar"/>');
            bar.set('href', 'view_compare.php?id='+i);
            bar.setStyles({
                height: (history[i].similarity/100*canvas_height) + 'px',
                left: left + 'px'
            });
            canvas.append(bar);

            var label = Y.Node.create('<label>'+history[i].similarity+'%</label>');
            label.setStyles({
                left: left+'px',
                bottom: (history[i].similarity/100*canvas_height+5)+'px'
            });
            canvas.append(label);

            label = Y.Node.create('<label>'+history[i].time_text+'</label>');
            label.setStyles({
                left: left+'px',
                bottom: '-35px'
            })
            canvas.append(label);
            left += 50;
        }
        overlay.append(canvas);
        M.plagiarism_programming.compare_code.history_overlay.set('bodyContent', overlay);
        M.plagiarism_programming.compare_code.history_overlay.render(document.body)
    },

    init_summary_table: function() {
        var Y = this.Y;

        var table = null;
        if (Y.version=='3.4.1') {
            this.summary_table.columns[0].formatter = function(o) {
                var color = o.value;
                o.value = '';
                var cell = this.createCell(o);
                cell.setAttribute('style', 'background-color: '+color);
            }
            table = new Y.DataTable.Base({
                columnset: this.summary_table.columns,
                recordset: this.summary_table.data
            });
            table.plug(Y.Plugin.DataTableScroll, {
                height: "93px"
            });
            table.render('.simiarity_table_holder');
        } else {
            this.summary_table.columns[0].nodeFormatter = function(o) {
                o.cell.setAttribute('style', 'background-color: '+o.value);
                o.value = '';
            }
            this.summary_table.columns[1].allowHTML = this.summary_table.columns[2].allowHTML = true;
            table = new Y.DataTable({
                columnset: this.summary_table.columns,
                recordset: this.summary_table.data,
                scrollable: 'y',
                height: '120px'
            });
            table.render('.simiarity_table_holder');
            table.set('scrollable', (table.get('contentBox').get('clientHeight') >= table._tableNode.get('scrollHeight')) ? false : 'y');
        }

    }
}