# Layer

Layer is an Object Oriented MVC PHP Framework

<b>This project is currently on ALPHA phase, you can still try it and give me your feedback</b>

MVC stands for Model-View-Controller

The model represents the data to manipulate, the view represents how the data should be displayed, the controller handles the business logic of your app.

-   Models are defined by simple classes

-   Views are defined by .php file with HTML content

-   Controllers are defined by classes

-   Actions are defined by methods inside the controllers

You can easily create API or website with Layer

-   A website uses views to display content and let the user interact with it (MVC)

-   An API does not uses views to display the data, it return the data in a specific format like JSON, XML, Text...

-   You can define custom errors controller for your website or api

# Getting Started

### Composer

Get the latest version from master by running this command after your project initialisation

    composer require rloris/layer:dev-master

### Configuration

First we need to setup our configuration.json file, it should look like this, it's the minimum setup

```
{
    "locations": {
        "controllers": "{path_to_the_folder_containing_your_controllers}",
        "shared": "{path_to_the_folder_containing_your_shared_components}",
        "build": "{path_to_the_folder_for_layer_output_build_files}",
        "log": "{path_to_the_folder_for_layer_output_log_files}"
    },
    "environment": {
        "current": "dev",
        "dev": {
            "routeTemplate" : "",
            "apiRouteTemplate" : "api",
            "log": false,
            "logTemplate" : "[{request_datetime}][{environment}][{request_method} {request_resource}]:{message}",
            "build": true
        },
        "prod": {
            "routeTemplate" : "",
            "apiRouteTemplate" : "api",
            "log": true,
            "logTemplate" : "[{request_datetime}][{environment}][{client_ip} {client_browser} {client_os}][{request_method} {request_resource}]:{message}",
            "build": false
        },
        "{your_other_env_name}": {
            "routeTemplate" : "",
            "apiRouteTemplate" : "api",
            "log": false,
            "logTemplate" : "[{request_datetime}][{environment}][{client_ip} {client_browser} {client_os}][{request_method} {request_resource}]:{message}",
            "build": false
        }
    },
    "layouts" : {
        "{your_layout_name}": {
          "pre": ["header","navbar","breadcrumbs"],
          "post": ["footer"]
        },
        "{your_other_layout_name}": {
          "pre": ["header", "navbar"],
          "post": ["footer"]
        }
    },
    "globals": {
        "{name_of_the_constant}": "{value_of_this_constant}",
        "{name_of_another_constant}": "{value_of_this_constant}",
    }
}
```
> locations
- `controllers` points to the directory containing all the controllers of your application
- `shared` points to the directory containing all the shared elements (views, filters) of your application
- `build` specify the directory where layer will be generating files used by the framework
- `log` points to the directory where log files will be stored
> environment
- `routeTemplate` is the prefix that will be added for all Controller routes
- `ApiRouteTemplate` is the prefix that will be added for all ApiController routes (For example: useful for api errors to show them as json instead of html view)
- `log` enable/disable Logger     
- `logTemplate` Tells to the Logger the structure to use for each log entry
- `build` enable/disable scan for changes in folder (controllers, shared) to build the new routes map (recommended: set true in development env and false in production)
> layouts
- Layouts are shared views attached together, they are used only for a website, not for an api, leave empty if you are building an api
- keys are the names of your layouts, you can use layouts in Controllers for your actions by specifying the layout to use
- Layouts are made out of `pre` views (will be rendered before your main content) and `post` views (will be rendered after your main content)
- You change the layout configuration at runtime thanks to the viewManager available in Controllers
> globals
- You can store whatever you want in the `globals` section
- All keys will be converted in uppercase constant, for example the value of `my_key` will be available in `MY_KEY` in the whole app

### Directory structure

The structure is organized like this, you have folders with the name of the controller and inside you can find the controller php file, if it's a website controller, you can also find the views folder that contains the views used by this controller.

NOTE: A specific controller can only use views from it's own views folder or shared views, it cannot uses views from another controller views folder. If you want to reuse a view for different controllers, create a shared view !
```
/mycontrollers
    /home
        HomeController.php
        /views
            index.php
            contact.php
            about.php
    /blog
        BlogController.php
        /views
            list-post.php
            edit-post.php
    /auth  
        AuthController.php
        /views
            login.php
            signin.php
    /api
        /user
            UserApiController.php
        /blog
            BlogApiController.php
/shared
    /filters
        AuthFilter.php
        LogFilter.php
        GlobalFilter.php
    /views
        header.php
        modal.php
        footer.php
        /alerts
            success.php
            warning.php
            failure.php
```

### Create an index.php

This will be the main entry point of your application, every request will trigger this script, you should not display anything in it but you can add code before and after for other reasons.

```{php}
// require autoloader
require_once "./vendor/autoload.php";

// init app with configuration file path
$app = rloris\layer\App::getInstance("./configuration.json");

// execute app
if($code = $app->execute()) 
{
    // request or error handled successfully
    rloris\layer\utils\Logger::write("Serving content successfully with status code: $code");
} 
else 
{
    // error could not be handled
    rloris\layer\utils\Logger::write("Error occurred");
}
```

### Set up .htaccess for Apache server

All requests should be forwarded to your index.php entry file

```
<IfModule mod_rewrite.c>    
    Options +FollowSymLinks
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !-d
    RewriteCond %{REQUEST_URI} !-f
    RewriteCond %{REQUEST_URI} !-l
    RewriteRule ^(public)($|/) - [L]
    RewriteRule ^(.*)$ index.php?url=$1 [L,QSA]
</IfModule>
```
This redirects all request to the index.php file or public folder where your css, images, js will be located

### Set up for nginx server

```
location  -d {}
location  -f {}
location  -l {}
location ~ ^/(public)($|/) { }
location / {
  rewrite ^(.*)$ /index.php?url=$1 break;
}
```

All requests should be forwarded to your index.php entry file or public folder where your css, js, images... are located

### Setup Done

Once your setup is done you can begin to create controller classes, view files and filter classes by following the instructions in the next chapter. Layer works with annotations on classes and methods, it automatically builds the routes map, no need to tell the router to add a route. Layer detects if a file was updated or not by using hashes to only rebuild the updated part.

# Controllers

You should NOT display anything in your controllers, but pass this content to the view if you want to display it. WHY ? because headers are handled and sent by Layer, by displaying something in the controllers or filters you break this system and your custom layer headers won't be sent.

Layer detects a controller if it's in the controllers folder specified in the configuration.json file, and that the controller's class name contains ```{Your_Name}Controller.php```, once your class is created just extends it with your specific needs:

-   BaseController for a website controller
-   ApiBaseController for an api controller
-   ErrorBaseController for a website error controller
-   ApiErrorBaseController for an api error controller

Then add an annotation to tell layer how it should handle this controller's route : 

## Annotations

### Controller for an API

Put these on top of an ApiBaseController class ```@ApiController```

```
/**
 * @ApiController(routeTemplate='users', defaultAction='getUsers')
 */
class UserApiController extends ApiBaseController { ... }
```

This means when you visit `/api/users/` the default action is `getUsers()`

You do not need to add `/api` because it is already done by reading the configuration.json "apiRouteTemplate" key in `environment/{current_env}/apiRouteTemplate`

If you change this key in your configuration file to `myApi` then all api routes will be available by visiting `/myApi/{route}`
    
Then for each public method in this class you want to reach add this annotation `@ApiAction`
    
```
    // inside UserApiController class
    /**
     * @ApiAction(routeTemplate='/',methods={'get'})
     */
    public function getUsers() { ... }

    /**
     * @ApiAction(routeTemplate='/',methods={'options'})
     */
    public function getOptions() { /* Handle CORS */ }
```

The getUsers() action will be triggered when you visit `/api/users/` with GET method
 
### Controller for a website

Put these on top of a BaseController class `@DefaultController` or `@Controller`
Put `@DefaultController` on top if it's the controller that will be triggered when there is not route specified (default route)

```
/**
 * @DefaultController(routeTemplate='home', layoutName='basic')
 */
class HomepageController extends BaseController { ... }
```

If I visit `/` my request will be forwarded internally to the `HomepageController`, I can also visit `/home` to reach this controller, there should be only one `@DefaultController` in your project, the others should be `@Controller`

The default action for `@Controller` and `@DefaultController` is index(), you can of course change it by specifying another one in these annotations

Then for each public method in this class you want to reach add this annotation `@Action`

```
    // inside HomepageController class
    /**
     * @Action(methods={"get"})
     */
    public function index() { ... }

    /**
     * @Action(methods={"get"})
     */
    public function about_us() { ... }

    /**
     * @Action(methods={"get"})
     */
     public function contact() { ... }

```

When you do not specify the routeTemplate in the annotation, the method name will be used, thus by visiting `/home/about_us` or `/home/contact` the about_us() or contact() action will be triggered

### Error Controller to handle website errors

Put these on top of an ErrorBaseController class `@ErrorController`

```
/**
 * @ErrorController(layoutName='basic')
 */
class ErrorsController extends ErrorBaseController { ... }
```

This tells layer to use this class to handle all errors thrown by a website

Then inside this controller, put this annotation on top of methods to handle specific errors ``

```
    // inside ErrorsController class
    /**
     * @ErrorAction(errorCodes={"5\d\d"}, viewName='index')
     */
    public function serverError() { ... }

    /**
     * @ErrorAction(errorCodes={"404", "400"}, viewName='index')
     */
    public function notFoundError() { ... }

    /**
     * @ErrorAction(viewName='index')
     */
    public function clientError() { ... }
```

In this case, all errors with an http code of `5xx` will trigger `serverError()`, all errors with an http code of `404` and `400` will trigger `notFoundError()`, the rest will trigger `clientError()`


### API Error Controller to handle API errors

Put these on top of an ApiErrorBaseController class `@ApiErrorController`

```
/**
 * @ApiErrorController
 */
class ApiErrorsController extends ApiErrorBaseController { ... }
```
This tells layer to handle all api errors and forward them to this controller

Then simply tell layer how to handle a specific error by specifying the action to trigger, put this annotation on method inside this controller `@ApiErrorAction`

```
    // inside ApiErrorsController class

    /**
     * @ApiErrorAction(errorCodes={"4\d\d"})
     */
    public function clientError() { ... }

    /**
     * @ApiErrorAction(errorCodes={"5\d\d"})
     */
    public function serverError() { ... }
``` 

In this case all errors with an http code of `5xx` will trigger the `serverError()` action and all errors with http code of `4xx` will trigger the `clientError()` action

### Route parameters

Layer handles route parameters by name, you can define parameters in routeTemplate <b>(controller and action)</b> like this :

```
    /**
     * @Controller(routeTemplate='auth/{#identifier}', layoutName='basic', filters={'time'})
     */
    class AuthController extends BaseController { ... }
```
Here I have declared in my AuthController a mandatory parameter of type number, this means all my actions will need this parameter to be triggered, and of course, they will be able to get it

If your parameter is mandatory and can be anything, the syntax to declare it, is this one, `{param}`

If your parameter is mandatory and a number, the syntax to declare it, is this one `{#param}`

If you parameter is not mandatory, just add `?` at the end like this whether it's a number or other `{param?}` 

Then to get this parameter, just use it's name as method parameter like this :

```
    // inside AuthController class

    /**
     * @ApiAction(methods={"get"})
     */
    public function connect($identifier) { ... }
```

As you notice here, I set up the routeTemplate of the controller thus I can use `$identifier` for all methods inside this controller, if you want the same result only for an action, do it like this :

```
/**
* @Controller(routeTemplate='auth', layoutName='basic', filters={'time'})
*/
class AuthController extends BaseController { 
    /**
     * @ApiAction(routeTemplate='{#identifier}', methods={"get"})
     */
    public function connect($identifier) { ... }
}
```

Here the difference is that only connect method will be able to use `$identifier` since I didn't declare it for the controller

# Filters (Middleware)

Filters are actions that will be executed before and/or after the main action, filters are classes, they are useful to test if a user is connected before accessing to a specific resource or simply log something

You can apply filters on controller or apiController (this means it will be applied to all actions inside this controller) or on some actions only if you specify it

Layer detects a filter if it's in the shared folder specified in the configuration.json file, and that the filter's class name contains ```{Your_Name}Filter.php```, once your class is created just extends it with the ```BaseFilter``` class.

Then, add this annotation : ```@Filter```
    
    eg: 
        /**
        * @Filter
        */
        class AuthFilter extends BaseFilter
        {
        
            public function in()
            {
                /* input stuff here */
            }
        
            public function out()
            {
                /* output stuff here */
            }
        }
    
You can use a filter on multiple actions or controllers, the order defines which filter will be called first, like this :  

-   To apply a filter on a controller (thus every action in this controller)
    
```
    /**
    * @DefaultController(filters={'time', 'log'})
    */
    class HomepageController extends BaseController { ... }
```

In this case, on input, the filter called time will be called first then log, then on output, log will be called first then time.

-   To apply a filter on an action

```
    /**
    * @Action(methods={"post"}, filters={"auth"})
    */
    public function upload()
    {
        self::$data = ["content" => "File uploaded with success", "title" => 'Upload file'];
    }
```
    
If upload is inside the HomepageController, then filters will be applied in this order, on input, 
    
time => log => auth
    
then the upload() method will be called, and on output, 
    
auth => log => time


You can define Global Filters by changing the annotation to ```@GlobalFilter```, they will be applied every time :

```
    /**
     * @GlobalFilter
     */
    class MyGlobalFilter extends BaseFilter
    {
    
        public function in()
        {
            // CORS allows all origins for api
            if(strtoupper(self::$request->getRequestMethod()) === IHttpMethods::OPTIONS) {
                self::cors()->allowAnyOrigins()->allowAnyMethods()->allowAnyHeaders();
            }
        }
    
        public function out()
        {
            /* output stuff here */
        }
    }
```

You can add and remove filter at runtime thanks to the filterManager available in <b>Controller</b> classes and <b>Filter</b> classes :
  
```
    self::$filterManager->add("auth");
    if(self::$filterManager->isActive("time"))
         self::$filterManager->remove("time");
```
    
# Views 

Views are php files that contains mainly HTML with a few php code, but you should not put your business logic there, to pass content to the view, it's the same way you send data for an api, just add your content to ```self::data[yourkey] = content;``` in your controller or filter and then access it like this ```<?= $this->yourkey ?>```, if the content you are accessing in your view does not exists, it won't display anything and will not throw an error.

You can add and remove views at runtime thanks to the viewManager available with `self::$viewManager` by extending the class `BaseController`.

`PreViews` are shared views that will be displayed before the main content

`PostViews` are shared views that will be displayed after the main content

`ContentView` is the main content view that will be displayed after the previews and before the postviews

```
        // getting GET parameter with name ok
        $ok = self::$request->getGet('ok');

        // adding alert shared view before main content of this action
        if($ok === 'true')
        {
            self::$viewManager->addPreView('alerts/success');
        }
        else if($ok === 'false')
        {
            self::$viewManager->addPreView('alerts/failure');
        }
        else
        {
            self::$viewManager->addPreView('alerts/alert');
        }
```
    
# Utils

### Request

You can access the request in any controller or filter class by typing `self::$request`

    DOC TODO
    
### Response

You can access the response in any controller or filter class by typing `self::$response`

    DOC TODO
    
### File

The file API allows you to handle a file very easily whether it's an uploaded file or not

    DOC TODO
    
### Session

You can access the Session manager in any controller or filter class by typing `self::session()`

    DOC TODO
    
### CORS

You can access the CORS manager in any controller or filter class by typing `self::cors()`

    DOC TODO
    
### Logger

You can use the logger to write directly using a specific template from your configuration file `Logger::write("Hello World")`

    DOC TODO

# What's planned next

-   Add XML output support for API response
-   Add route parameters customization with annotation like ```@RouteParam(name="id", regex="...")``` support 
-   Add websocket server support => (extension ?)
-   Add LORM (Layer Object Relational Mapping) support => (extension ?)

