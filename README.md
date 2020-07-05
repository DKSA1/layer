# Layer

Layer is an Object Oriented MVC PHP Framework

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

### Configuration

First we need to setup our configuration.json file

### Directory structure

The structure is organized like this, you have folders with the name of the controller and inside you can find the controller php file, if it's a website controller, you can also find the views folder that contains the views used by this controller.
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
require_once "./app/Autoloader.php";

$app = \layer\core\App::getInstance("./configuration.json");

if($app->execute()) 
{
    \layer\core\utils\Logger::write("Serving content successfully");
} 
else 
{
    \layer\core\utils\Logger::write("Error occurred");
}
```

### Set up .htaccess

# Controllers

Layer works with annotations on classes and methods, it automatically builds the routes map. You should display anything in your controllers, put pass this content to the view.

### For an API

    @ApiController
    
<hr>

    @ApiAction
    
### For a website

    @DefaultController

<hr>

    @Controller

<hr>
    
    @Action

### For an ErrorController

### For an ErrorController

# Filters (Middleware)

Filters are actions that will be executed before and/or after the main action, filters are classes, they are useful to test if a user is connected before accessing to a specific resource or simply log something

You can apply filters on controller or apiController (this means it will be applied to all actions inside this controller) or on some actions only

You can use a filter on multiple actions or controllers

    @Filter
    
Then use it like this:  

You can define Global Filters, they will be applied not matter what 

You can add and remove filter at runtime thanks to the filterManager:
  
# Views 

Views are php files that contains mainly HTML with a few php code, but you should not put your business logic there, to pass content to the view, it's the same way you send data for an api, just add your content to ```self::data[yourkey] = content;``` in your controller or filter and then access it like this ```<?= $this->yourkey ?>```, if the content you are accessing in your view does not exists, it won't display anything and will not throw an error.
You can add and remove views at runtime thanks to the viewManager:

# Utils

### Request

### Response

### File

### Session

### CORS

### Logger

### Mailer

### String Validation and sanitizer

### Websocket server



