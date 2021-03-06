<?php

require('calendar.php');

class Gantti
{

    var $cal       = null;
    var $data      = array();
    var $first     = false;
    var $last      = false;
    var $options   = array();
    var $cellstyle = false;
    var $blocks    = array();
    var $months    = array();
    var $days      = array();
    var $seconds   = 0;

    function __construct($data, $params=array())
    {

        $defaults = array(
            'title'      => false,
            'cellwidth'  => 40,
            'cellheight' => 40,
            'today'      => true,
            );


        $this->options = array_merge($defaults, $params);
        $this->cal     = new Calendar();
        $this->data    =  $data;
        $this->seconds = 60*60*24;

        $this->cellstyle = 'style="width: ' . $this->options['cellwidth'] . 'px; height: ' . $this->options['cellheight'] . 'px"';

        // parse data and find first and last date
        $this->parse();

    }

    function parse()
    {
        foreach($this->data as $d)
        {
            $is_completed = false;
            $completed_label = 'Completed';
            $d['label'] = (isset($d['label']) ? $d['label'] : '');
            $d['done'] = isset($d['done']) ? $d['done'] : 0;
            $d['color'] = isset($d['color']) ? $d['color'] : '';


            if ( is_bool($d['done']) )
            {
                $d['done'] = $d['done'] ? 100 : 0;
            }


            $is_completed = (strpos($d['color'], 'completed') !== false);


            // 'done' key is a percentage
            if (is_numeric($d['done']))
            {
                if ($d['done'] == 100)
                    $d['done'] = $completed_label;
            }


            // 'done' key is a label
            if (!is_numeric($d['done']))
            {
                if ($d['done'] == 'none')
                {
                    $d['color'] = $d['color'] . ' no-percent';
                }
                else
                {
                    $is_completed = true;

                    // grab label from 'done' and set done = 100
                    $completed_label = $d['done'];
                    $d['done'] = 100;

                    // write 'completed' into 'color' without repeating
                    $d['color'] = str_replace('completed', '', $d['color']);
                    $d['color'] = $d['color'] . ' completed';
                }
            }



            // parse values from data into $blocks
            $this->blocks[] = array(
                'label' => ( (!$is_completed) ? $d['label'] : $completed_label ),
                'start' => $start = strtotime(
                    ((!$is_completed) ? $d['start'] : $d['end'])
                ),
                'end'   => $end   = strtotime(
                    (!isset($d['end'])) ? '2014-12-31' : $d['end']
                ),
                'class' => @$d['color'],
                'info'  => ((!$is_completed) ? @$d['info'] : @$d['label']),
                'done'  => @$d['done']
                );


            // iter: set range of blocks
            if(!$this->first || $this->first > $start) $this->first = $start;
            if(!$this->last  || $this->last  < $end)   $this->last  = $end;

        }

        // set range of blocks, adding extra buffer ranges
        $this->first  = $this->cal->date(strtotime("-1 months", date($this->first)));
        $this->last  = $this->cal->date(strtotime("+2 months", date($this->last)));

        $current = $this->first->month();
        $lastDay = $this->last->month()->lastDay()->timestamp;

        // build the months
        while($current->lastDay()->timestamp <= $lastDay)
        {
            $month = $current->month();
            $this->months[] = $month;
            foreach($month->days() as $day)
            {
                $this->days[] = $day;
            }
            $current = $current->next();
        }

    }

    function render()
    {

        $html = array();

        // common styles
        $cellstyle  = 'style="line-height: ' . $this->options['cellheight'] . 'px; height: ' . $this->options['cellheight'] . 'px"';
        $wrapstyle  = 'style="width: ' . $this->options['cellwidth'] . 'px"';
        $totalstyle = 'style="width: ' . (count($this->days)*$this->options['cellwidth']) . 'px"';
        // start the diagram
        $html[] = '<figure class="gantt">';

        // set a title if available
        if($this->options['title'])
        {
            $html[] = '<figcaption>' . $this->options['title'] . '</figcaption>';
        }

        // sidebar with labels
        $html[] = '<aside>';
        $html[] = '<ul class="gantt-labels" style="margin-top: ' . (($this->options['cellheight']*2)+1) . 'px">';

        $firstTimeEnter=true;
        $rememberLastId;

        foreach($this->blocks as $i => $block)
        {

            if($firstTimeEnter)
            {
                $html[] = '<li class="gantt-label"><strong ' . $cellstyle . '>' . $block['label'] . '</strong></li>';

                $firstTimeEnter=false;
                $rememberLastId=$block['label'];

            }
            else

                if($rememberLastId!=$block['label'])
                {
                    $html[] = '<li class="gantt-label"><strong ' . $cellstyle . '>' . $block['label'] . '</strong></li>';


                    $rememberLastId=$block['label'];
                }

        }
        $html[] = '</ul>';
        $html[] = '</aside>';

        // data section
        $html[] = '<section class="gantt-data">';

        // data header section
        $html[] = '<header>';

        // months headers
        $html[] = '<ul class="gantt-months" ' . $totalstyle . '>';
        foreach($this->months as $month)
        {
            $html[] = '<li class="gantt-month" style="width: ' . ($this->options['cellwidth'] * $month->countDays()) . 'px"><strong ' . $cellstyle . '>' . $month->name() . '</strong></li>';
        }
        $html[] = '</ul>';

        // days headers
        $html[] = '<ul class="gantt-days" ' . $totalstyle . '>';
        foreach($this->days as $day)
        {
            $weekend = ($day->isWeekend()) ? ' weekend' : '';

            // something is very strange with the days
            $today   = ($day->isYesterday())   ? ' today' : '';

            $html[] = '<li class="gantt-day' . $weekend . $today . '" ' . $wrapstyle . '><span ' . $cellstyle . '>' . $day->padded() . '</span></li>';
        }
        $html[] = '</ul>';

        // end header
        $html[] = '</header>';



        // main items
        $html[] = '<ul class="gantt-items" ' . $totalstyle . '>';

        $firstTimeEnter=true;
        $rememberLastId="";

        foreach($this->blocks as $i => $block)
        {

            if($firstTimeEnter==true)
            {

                $html[] = '<li class="gantt-item">';

                // days
                $html[] = '<ul class="gantt-days">';
                foreach($this->days as $day)
                {

                    $weekend = ($day->isWeekend()) ? ' weekend' : '';
                    $today   = ($day->isToday())   ? ' today' : '';

                    $html[] = '<li class="gantt-day' . $weekend . $today . '" ' . $wrapstyle . '><span ' . $cellstyle . '>' . $day . '</span></li>';
                }
                $html[] = '</ul>';


                $firstTimeEnter=false;
                $rememberLastId=$block['label'];
            }
            else if($rememberLastId!=$block['label'])
            {
                $html[] = '</li>';

                $html[] = '<li class="gantt-item">';

                // days
                $html[] = '<ul class="gantt-days">';
                foreach($this->days as $day)
                {

                    $weekend = ($day->isWeekend()) ? ' weekend' : '';
                    $today   = ($day->isToday())   ? ' today' : '';

                    $html[] = '<li class="gantt-day' . $weekend . $today . '" ' . $wrapstyle . '><span ' . $cellstyle . '>' . $day . '</span></li>';
                }
                $html[] = '</ul>';

                $rememberLastId=$block['label'];
            }

            $days       = (($block['end'] - $block['start']) / $this->seconds) + 1;
            $offset     = (($block['start'] - $this->first->month()->timestamp) / $this->seconds);
            $top        = round($i * ($this->options['cellheight'] + 1));
            $left       = round($offset * $this->options['cellwidth']);
            $width      = round($days * $this->options['cellwidth'] - 9);
            $percentage = $block['done'] . '%';
            $height     = round($this->options['cellheight']-8);
            $class      = ($block['class']) ? ' ' . $block['class'] : '';
            $days       = ($days > 100 || $days == 1) ? '' : $days;
            $info       = $block['info'];

            $html[] = '<span class="gantt-block' . $class . '" style="left: ' . $left . 'px; width: ' . $width . 'px; height: ' . $height . 'px" title="' . $info .'">
                <span class="gantt-text-percentage">' . $percentage . '</span>
                <div class="gantt-block-percentage" data-percent="' . $percentage . '%" style="width: '. $percentage . '"> </div>
                <strong class="gantt-block-label">' . $days . '</strong>
            </span>';

        }

        $html[] = '</ul>';

        if($this->options['today'])
        {

            // today
            // something is very strange with the days
            $today  = $this->cal->yesterday();
            $offset = (($today->timestamp - $this->first->month()->timestamp) / $this->seconds);
            $left   = round($offset * $this->options['cellwidth']) + round(($this->options['cellwidth'] / 2) - 1);

            if($today->timestamp > $this->first->month()->firstDay()->timestamp && $today->timestamp < $this->last->month()->lastDay()->timestamp)
            {
                $html[] = '<time style="top: ' . ($this->options['cellheight'] * 2) . 'px; left: ' . $left . 'px" datetime="' . $today->format('Y-m-d') . '">Today</time>';
            }

        }

        // end data section
        $html[] = '</section>';

        // end diagram
        $html[] = '</figure>';

        return implode('', $html);

    }


    function __toString()
    {
        return $this->render();
    }

}
