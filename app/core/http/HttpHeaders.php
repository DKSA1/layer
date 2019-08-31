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

    static function ResponseHeader($code,$newLocation = null){
        switch ($code){
            case(self::OK): self::OK(); break;
            case(self::MovedPermanently): self::MovedPermanently($newLocation); break;
            case(self::MovedTemporarily): self::MovedTemporarily($newLocation); break;
            case(self::TemporaryRedirect): self::TemporaryRedirect($newLocation); break;
            case(self::BadRequest): self::BadRequest(); break;
            case(self::Unauthorized): self::Unauthorized(); break;
            case(self::Forbidden): self::Forbidden(); break;
            case(self::NotFound): self::NotFound(); break;
            case(self::MethodNotAllowed): self::MethodNotAllowed(); break;
            case(self::TooManyRequests): self::TooManyRequests(); break;
            case(self::InternalServerError): self::InternalServerError(); break;
            case(self::NotImplemented): self::NotImplemented(); break;
            case(self::ServiceUnavailable): self::ServiceUnavailable(); break;
        }
    }

    //200
    static function OK(){
        http_response_code(200);
    }

    //301
    static function MovedPermanently($location){
        header("Location: $location",TRUE,301);
    }

    //302
    static function MovedTemporarily($location){
        header("Location: $location",TRUE,302);
    }

    //307
    static function TemporaryRedirect($location){
        header("Location: $location",TRUE,307);
    }

    //400
    static function BadRequest(){
        http_response_code(400);
    }

    //401
    static function Unauthorized(){
        http_response_code(401);
    }

    //403
    static function Forbidden(){
        http_response_code(403);
    }

    //404
    static function NotFound(){
        http_response_code(404);
    }

    //405
    static function MethodNotAllowed(){
        http_response_code(405);
    }

    //429
    static function TooManyRequests(){
        http_response_code(429);
    }

    //500
    static function InternalServerError(){
        http_response_code(500);
    }

    //501
    static function NotImplemented(){
        http_response_code(501);
    }

    //503
    static function ServiceUnavailable(){
        http_response_code(503);
    }
}