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

-   An API does not uses views to display the data, it return the data in a specific format like JSON, XML, ... (MC)

Layer works with a few annotations:

# For an API

    @ApiController
    Example: 
    
<hr>

    @ApiAction
    Example: 
    
# For a website

    @Controller
    Example: 

<hr>
    
    @Action
    Example: 

# Filters (Middlewares)

Filters are actions that will be executed before and/or after the main action, filters are classes, they are useful to test if a user is connected before accessing to a specific ressource

You can apply filters on controller or apiController (this means it will be applied to all actions inside this controller) or on some actions only

You can use a filter on multiple actions or controllers

    @Filter
    Example:
    
    
    
    Then use it like this:  
  
# Views 

Views are php files that contains mainly HTML with a few php code



