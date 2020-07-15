<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 12:55
 */

namespace rloris\layer\core\http;

interface IHttpCodes
{
    const OK = 200;
    const Created = 201;
    const Accepted = 202;
    const NoContent = 204;
    const MovedPermanently = 301;
    const MovedTemporarily = 302;
    const SeeOther = 303;
    const NotModified = 304;
    const TemporaryRedirect = 307;
    const PermanentRedirect = 308;
    const BadRequest = 400;
    const Unauthorized = 401;
    const Forbidden = 403;
    const NotFound = 404;
    const MethodNotAllowed = 405;
    const NotAcceptable = 406;
    const Conflict = 409;
    const LengthRequired = 411;
    const PreconditionFailed = 412;
    const UnsupportedMediaType = 415;
    const TooManyRequests = 429;
    const InternalServerError = 500;
    const NotImplemented = 501;
    const ServiceUnavailable = 503;
}