<?php

namespace rloris\layer\core\http;

interface IHttpContentType
{
    const TEXT = "text/plain";
    const JSON = 'application/json';
    const XML = 'text/xml';
    const HTML = 'text/html';
    const MULTIPART_FORMDATA = 'multipart/form-data';
}