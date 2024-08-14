<?php

namespace AlanBlair\TrendsIntegration\App;

class ResponseDataVO
{
    public $message;
    public $status;
    public $data;

    public function __construct($data, $status = ResponseStatusMap::STATUS_OK, $message = '')
    {
        $this->data = $data;
        $this->status = $status;
        $this->message = $message;
    }
}