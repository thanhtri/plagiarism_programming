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

M.plagiarism_programming.view_report = {

    history_overlay: null,
    chart_width: 350,
    chart_height: 200,

    init: function(Y, cmid) {
        this.init_report();
    },

    init_report: function() {
        var cells = document.getElementsByClassName('similar_pair');
        for (var i=0; i<cells.length; i++) {
            var img = document.createElement('img');
            img.setAttribute('class', 'show_history');
            img.src = 'pix/show_history.png';
            img.alt = 'see history';
            img.setAttribute('align', 'right');
            cells[i].appendChild(img);
            YAHOO.util.Event.addListener(img, 'click', function(e) {
                YAHOO.util.Event.stopPropagation(e);
                M.plagiarism_programming.view_report.show_chart(this);
            });
        }
        YAHOO.util.Event.addListener(document, 'click', function(e) {
            var node = e.target;
            var overlay = document.getElementById('history_chart');
            while (node!=overlay && node!=null) {
                node = node.parentNode;
            }
            if (node==null) {
                M.plagiarism_programming.view_report.history_overlay.hide();
            }
        });
        this.create_chart();
    },

    create_chart: function() {
        this.history_overlay = new YAHOO.widget.Overlay('history_chart', {
            visible: false,
            effect:{effect:YAHOO.widget.ContainerEffect.FADE,duration:0.25}
        });
    },

    show_chart: function(img) {
        var callback = {
            success: M.plagiarism_programming.view_report.load_overlay,
            argument: img
        };
        var pair_id = img.parentNode.getAttribute('pair');
        YAHOO.util.Connect.asyncRequest('GET', 'mark_result.php?task=get_history&id='+pair_id,
            callback);
    },

    load_overlay: function(o) {
        var history = YAHOO.lang.JSON.parse(o.responseText);
        var width = M.plagiarism_programming.view_report.chart_width-25;
        var height = M.plagiarism_programming.view_report.chart_height-50;

        var overlay = document.createElement('div');
        overlay.setAttribute('class', 'programming_result_chart_overlay');
        overlay.style.width = M.plagiarism_programming.view_report.chart_width+'px';
        overlay.style.height= M.plagiarism_programming.view_report.chart_height+'px';

        var canvas = document.createElement('div');
        canvas.setAttribute('class', 'programming_result_popup_chart');
        canvas.style.width = width+'px';
        canvas.style.height= height+'px';
        var left = 20;
        for (var i in history) {
            var bar = document.createElement('a');
            bar.setAttribute('class', 'bar');
            bar.href = 'view_compare.php?id='+i;
            bar.style.height = (history[i].similarity/100*height)+'px';
            bar.style.left = left+'px';
            bar.style.bottom = '0px';
            canvas.appendChild(bar);

            var label = document.createElement('label');
            label.innerHTML = history[i].similarity+'%';
            label.style.left = left+'px';
            label.style.bottom = (history[i].similarity/100*height+5)+'px';
            canvas.appendChild(label);

            label = document.createElement('label');
            label.innerHTML = history[i].time_text;
            label.style.left = left+'px';
            label.style.bottom = '-35px';
            canvas.appendChild(label);
            left += 50;
        }
        overlay.appendChild(canvas);
        M.plagiarism_programming.view_report.history_overlay.cfg.setProperty('context', [o.argument, 'tl', 'bl'])
        M.plagiarism_programming.view_report.history_overlay.setBody(overlay);
        M.plagiarism_programming.view_report.history_overlay.render(document.body)
        M.plagiarism_programming.view_report.history_overlay.show();
    }
}