<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 12:55
 */

namespace layer\core\http;

interface IHttpCodes
{
    const OK = 200;
    const MovedPermanently = 301;
    const MovedTemporarily = 302;
    const TemporaryRedirect = 307;
    const BadRequest = 400;
    const Unauthorized = 401;
    const Forbidden = 403;
    const NotFound = 404;
    const MethodNotAllowed = 405;
    const TooManyRequests = 429;
    const InternalServerError = 500;
    const NotImplemented = 501;
    const ServiceUnavailable = 503;
}