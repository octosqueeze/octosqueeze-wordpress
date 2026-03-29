<?php

namespace OctoSqueeze\WordPress\Tests;

class WpJsonResponse extends \Exception
{
    public bool $success;
    public $data;

    public function __construct(bool $success, $data = null)
    {
        $this->success = $success;
        $this->data = $data;
        parent::__construct($success ? 'wp_send_json_success' : 'wp_send_json_error');
    }
}
