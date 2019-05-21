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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.plagiarism_programming = M.plagiarism_programming || {};
M.plagiarism_programming.view_report = { history_overlay : null, chart_width : 350, chart_height : 200, init : function (Y) {
    this.init_report(Y);
}, init_report : function (Y) {
    Y.all('.similar_pair').each(function (cell) {
        var img = Y.Node.create('<img src="pix/show_history.png" class="show_history" alt="history" align="right" />');
        img.on('click', function (e) {
            e.stopPropagation();
            M.plagiarism_programming.view_report.show_chart(this, Y);
        });
        cell.append(img);
    });
    Y.one(document).on('click', function (e) {
        var node = e.target;
        while (node != null && !node.hasClass('programming_result_chart_overlay')) {
            node = node.get('parentNode');
        }
        if (node == null && M.plagiarism_programming.view_report.history_overlay != null) {
            M.plagiarism_programming.view_report.history_overlay.hide();
        }
    });
    this.create_chart(Y);
}, create_chart : function (Y) {
    this.history_overlay = new Y.Overlay({});
}, show_chart : function (img, Y) {
    var pair_id = Y.one(img).get('parentNode').getAttribute('pair');
    Y.io('mark_result.php?task=get_history&id=' + pair_id, { on : { success : function (id, response) {
        var div = M.plagiarism_programming.view_report.load_overlay(response, Y)
        var history_overlay = M.plagiarism_programming.view_report.history_overlay;
        history_overlay.align(img, [ Y.WidgetPositionAlign.TL, Y.WidgetPositionAlign.TR ])
        history_overlay.set('bodyContent', div);
        history_overlay.render(document.body)
        history_overlay.show();
    } } });
}, load_overlay : function (response, Y) {
    var history = Y.JSON.parse(response.responseText);
    var overlay = Y.Node.create('<div class="programming_result_chart_overlay"></div>');
    var canvas = Y.Node.create('<div class="programming_result_popup_chart"></div>');
    var canvas_height = M.plagiarism_programming.view_report.chart_height - 50;
    var left = 20;
    var h_label = Y.Node.create('<label class="h_label">' + M.str.moodle.date + '</label>');
    var v_label = Y.Node.create('<label class="v_label">%</label>');
    var title = Y.Node.create('<label class="title">' + M.str.plagiarism_programming.similarity_history + '</label>')
    canvas.append(h_label);
    canvas.append(v_label);
    canvas.append(title);
    for (var i in history) {
        var bar = Y.Node.create('<a class="bar" href="view_compare.php?id=' + i + '"/>');
        bar.setStyles({ height : (history[i].similarity / 100 * canvas_height) + 'px', left : left + 'px' });
        canvas.append(bar);
        var label = Y.Node.create('<label>' + history[i].similarity + '%</label>');
        label.setStyles({ left : left + 'px', bottom : (history[i].similarity / 100 * canvas_height + 5) + 'px' });
        canvas.append(label);
        label = Y.Node.create('<label>' + history[i].time_text + '</label>');
        label.setStyles({ left : left + 'px', bottom : '-35px' })
        canvas.append(label);
        left += 50;
    }
    overlay.append(canvas);
    return overlay;
} }