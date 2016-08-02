<?php

namespace parserbot\megaparser;

class Interrupter implements InterrupterInterface {

    public function __construct($options)
    {

        $this->job_class = isset($options['job_class'])?$options['job_class']:'Job';
        $this->job_id = isset($options['job_id'])?$options['job_id']:'';
    }

    public function checkStopFlag()
    {
        $class = $this->job_class;
        if ($this->job_id) {
            if ($job = $class::findOne($this->job_id)) {
                if ($job->status == $class::STATUS_FINISHED) {
                    return true;
                }
            }
        }

        return false;
    }
}