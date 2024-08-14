<?php

namespace AlanBlair\TrendsIntegration\App;

class ResponseStatusMap
{
    const CODE_NOT_FOUND = 404;
    const CODE_ALREADY_EXISTS = 409;
    const CODE_BAD_REQUEST = 400;
    const CODE_OK = 200;

    const STATUS_OK = 'ok';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_DONE = 'done';
    const STATUS_ALREADY_EXISTS = 'exists';
    const STATUS_DOES_NOT_EXIST = 'not-exist';
    const STATUS_UNPUBLISHED = 'unpublished';

    public static function get_map(){
        return [
            'CODE_NOT_FOUND' => self::CODE_NOT_FOUND,
            'CODE_ALREADY_EXISTS' => self::CODE_ALREADY_EXISTS,
            'CODE_BAD_REQUEST' => self::CODE_BAD_REQUEST,
            'CODE_OK' => self::CODE_OK,

            'STATUS_OK' => self::STATUS_OK,
            'STATUS_WARNING' => self::STATUS_WARNING,
            'STATUS_CRITICAL' => self::STATUS_CRITICAL,
            'STATUS_ALREADY_EXISTS' => self::STATUS_ALREADY_EXISTS,
            'STATUS_DONE' => self::STATUS_DONE,
            'STATUS_DOES_NOT_EXIST' => self::STATUS_DOES_NOT_EXIST,
            'STATUS_UNPUBLISHED' => self::STATUS_UNPUBLISHED
        ];
    }
}