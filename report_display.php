<?php
define ('CHART_WITH',800);
define ('CHART_HEIGHT',10);
define('BAR_WIDTH', 20);
function create_table_grouping_mode(&$similarity_table,&$student_names,$cmid) {
    global $CFG;
    
    $table = new html_table();
    foreach ($similarity_table as $s_id=>$similarity_array) {
        $row = new html_table_row();
        // first cell
        $cell = new html_table_cell();
        $cell->text = $student_names[$s_id];
        $row->cells[] = $cell;
        
        // arrow cell
        $cell = new html_table_cell();
        $cell->text = '&rarr;';
        $row->cells[] = $cell;
        
        foreach ($similarity_array as $s2_id=>$similarity) {
            $cell = new html_table_cell();
            $compare_link = html_writer::tag('a', $similarity['rate'].'%',
                array('href'=>$CFG->wwwroot.'/plagiarism/programming/reportviewing.php/'.$cmid.'/report'.$cmid.'/'.$similarity['file']));
            $cell->text = $student_names[$s2_id].'<br/>'.$compare_link;
            $row->cells[] = $cell;
        }
        $table->data[] = $row;
    }
    return $table;
}

function create_table_list_mode(&$list,&$student_names,$cmid) {
    global $CFG;
    
    $table = new html_table();
    foreach ($list as $pair) {
        $row = new html_table_row();
        
        $cell = new html_table_cell();
        $cell->text = $student_names[$pair->student1_id];
        $row->cells[] = $cell;
        
        $cell = new html_table_cell();
        $cell->text = $student_names[$pair->student2_id];
        $row->cells[] = $cell;
        
        $cell = new html_table_cell();
        $cell->text = html_writer::tag('a', $pair->similarity1.'%',
                array('href'=>$CFG->wwwroot.'/plagiarism/programming/reportviewing.php/'.$cmid.'/report'.$cmid.'/'.$pair->comparison));
        $row->cells[] = $cell;
        
        $table->data[] = $row;
    }
    return $table;
}

function create_chart($cmid,$tool,$similarity_type) {
    global $DB;
    
    $select = "cmid=$cmid AND detector='$tool'";
    $similarities = $DB->get_fieldset_select('programming_result','similarity1',$select);
    $thickness = 100;

    $histogram = array();
    for ($i=9;$i>=0;$i--) {
        $histogram[$i] = 0;
    }

    foreach ($similarities as $rate) {
        $histogram[intval(floor($rate/10))]++;
    }
    
    $max_student_num = max($histogram);
    if ($max_student_num>0) {
        $length_ratio = intval(floor(CHART_WITH/$max_student_num));
    } else {
        return '';
    }
    
    $div = '';
    $report_url = new moodle_url(qualified_me());
    foreach ($histogram as $key=>$val) {
        $upper = $key*10+10;
        $lower = $key*10;
        $range = ''.$upper.'-'.$lower;
        $pos_y = (9-$key)*(BAR_WIDTH+5).'px'; // 2 is the space between bars
        $width = max($val*$length_ratio,1).'px';
        // legend of the bar
        $div .= html_writer::tag('div',$range,array('class'=>'legend','style'=>"top:$pos_y;width:40px"));
        // the bar itself
        $report_url->remove_params(array('upper_threshold','lower_threshold'));
        $report_url->params(array('upper_threshold'=>$upper,'lower_threshold'=>$lower));
        $div .= html_writer::tag('a','',array('class'=>'bar','style'=>"top:$pos_y;width:$width",
            'href'=>$report_url->out(false)));
        // number of pairs
        if ($val>0) {
            $left = ($width+50).'px';
            $div .= html_writer::tag('div', $val,
                    array('class'=>'legend','style'=>"top:$pos_y;width:40px;left:$left"));
        }
    }
    return $div;
}