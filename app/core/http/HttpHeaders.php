<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 29-10-18
 * Time: 21:58
 */

namespace layer\core\http;


class HttpHeaders implements IHttpCodes
{

    static function responseHeader($code, $newLocation = null){
        switch ($code){
            case(self::OK): self::oK(); break;
            case(self::MovedPermanently): self::movedPermanently($newLocation); break;
            case(self::MovedTemporarily): self::movedTemporarily($newLocation); break;
            case(self::TemporaryRedirect): self::temporaryRedirect($newLocation); break;
            case(self::BadRequest): self::badRequest(); break;
            case(self::Unauthorized): self::unauthorized(); break;
            case(self::Forbidden): self::forbidden(); break;
            case(self::NotFound): self::notFound(); break;
            case(self::MethodNotAllowed): self::methodNotAllowed(); break;
            case(self::TooManyRequests): self::tooManyRequests(); break;
            case(self::InternalServerError): self::internalServerError(); break;
            case(self::NotImplemented): self::notImplemented(); break;
            case(self::ServiceUnavailable): self::serviceUnavailable(); break;
        }
    }

    //200
    static function oK(){
        http_response_code(200);
    }

    //301
    static function movedPermanently($location){
        header("Location: $location",TRUE,301);
    }

    //302
    static function movedTemporarily($location){
        header("Location: $location",TRUE,302);
    }

    //307
    static function temporaryRedirect($location){
        header("Location: $location",TRUE,307);
    }

    //400
    static function badRequest(){
        http_response_code(400);
    }

    //401
    static function unauthorized(){
        http_response_code(401);
    }

    //403
    static function forbidden(){
        http_response_code(403);
    }

    //404
    static function notFound(){
        http_response_code(404);
    }

    //405
    static function methodNotAllowed(){
        http_response_code(405);
    }

    //429
    static function tooManyRequests(){
        http_response_code(429);
    }

    //500
    static function internalServerError(){
        http_response_code(500);
    }

    //501
    static function notImplemented(){
        http_response_code(501);
    }

    //503
    static function serviceUnavailable(){
        http_response_code(503);
    }
}