<?php

namespace parserbot\megaparser;


class MiddleParser extends ProxyMiddle {
    protected $messages = [];
    protected $job_id = 0;
    protected $message_level;

    public function __construct($options = [])
    {
        if (isset($options['job_id'])) {
            $this->job_id = $options['job_id'];
        }

        if (isset($options['message_level'])) {
            $this->message_level = $options['message_level'];
        } else {
            $this->message_level = 10;
        }

        parent::__construct($options);
    }

    public function checkStopFlag()
    {
        if ($this->job_id) {
            if ($job = Job::findOne($this->job_id)) {
                if ($job->status == Job::STATUS_FINISHED) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getMessages()
    {
        if ($this->job_id) {
            $messages = Message::find()->where(['job_id'=>$this->job_id])->all();
            return $messages;
        } else {
            return $this->messages;
        }
    }

    public function addMessage($text, $type ='info')
    {

        if ($this->message_level < 5 && $type == 'info') {
            return true;
        }

        if ($this->message_level < 10 && $type == 'debug') {
            return true;
        }

        if ($this->message_level < 6 && $type == 'level6') {
            return true;
        }

        if ($this->job_id) {
            $message = new Message();
            $message->type = $type;
            $message->text = $text;
            $message->created_at = date('Y-m-d H:i:s');
            $message->job_id = (int)$this->job_id;
            $message->save();

        } else {
            $this->messages[] = ['timestamp' => date('Y-m-d H:i:s'), 'message' => $text, 'type' => $type];
        }

    }
}