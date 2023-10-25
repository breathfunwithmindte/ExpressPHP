# ExpressPHP

ExpressPHP is a lightweight and scalable PHP web framework inspired by Express.js. It provides developers with a powerful toolset for building modern web applications while embracing the principles of Object-Oriented Programming (OOP).

# Features

  1. Flexible request and response interfaces that can be extended and customized

  2. Modular architecture and separation of concerns for better code organization and reusability

  3. Optimized for performance and scalability for large-scale web applications

  4. Easy to use, with no external dependencies or libraries required

# Installation
To use ExpressPHP in your project, simply download the ExpressPHP.standard.php file and include it in your PHP script.


# Getting Started
To create a new ExpressPHP application, create a new PHP file and require the ExpressPHP.standard.php file. You can then create a new instance of the Express class to start defining your application.

```php
  include ("ExpressPHP.standard.php");
  use ExpressPHP\Standard as Express;

  $app = new Express\Application();

  // your code ...

  $app->run(); // this actually rendering content.

```

# Boilerplates

ExpressPHP is a flexible and modular framework that can be easily integrated into different project structures. To help you get started quickly, we've created a few boilerplate projects that demonstrate how to use ExpressPHP in various contexts:

  > [expressphp_boilerplate_min](expressphp_boilerplate_min): 
  A minimum boilerplate to get started.

  > [expressphp_boilerplate_primary](expressphp_boilerplate_primary): 
  A primary start for most projects that covers almost all the essentials, like custom configuration, request and response types, and routers for different types of responses (JSON, HTML).

  > [expressphp_boilerplate_reactjs](expressphp_boilerplate_reactjs): 
  A boilerplate project that demonstrates how to use ExpressPHP with React on the frontend.
  
  > [expressphp_boilerplate_reactjs_hooks](expressphp_boilerplate_reactjs_hooks): 
  A boilerplate project that demonstrates how to use ExpressPHP with React on the frontend and includes methods for creating hooks, contexts, and other React-related features.

  > [expressphp_boilerplate_authentication_session](expressphp_boilerplate_authentication_session) A boilerplate project that demonstrates how to use ExpressPHP with session-based authentication.

  > [expressphp_boilerplate_authentication_jwt](expressphp_boilerplate_authentication_jwt):
    (requires Composer for JWT library): A boilerplate project that demonstrates how to use ExpressPHP with JWT-based authentication.

  > [expressphp_boilerplate_mongodb](expressphp_boilerplate_mongodb):
    (requires Composer for MongoDB driver): A boilerplate project that demonstrates how to use ExpressPHP with MongoDB.

  > [expressphp_boilerplate_mysql](expressphp_boilerplate_mysql):
    A boilerplate project that demonstrates how to use ExpressPHP with MySQL.


# Routing

ExpressPHP provides a simple and intuitive way to define routes using the Express::METHOD() methods. You can define routes for any HTTP method and specify a callback function to handle the request.

```php  
  $app->getApplicationOptions()->setBegin("/expressphp_boilerplate_min"); // this will ingore part of url, usefull when working with multiple projects using vanilla php and apache server. Look example on expressphp_boilerplate_min

  $app->get("/", function (&$req, &$res) {
    $res->send("build something awesome");
  });
  $app->post("/user", function (&$req, &$res) {
    $res->status(200)->json(array("Hello", "World"));
  });
  $app->put("/user/:id", function (&$req, &$res) {
    $res->status(200)->json(array("Hello", "World"));
  });
  $app->delete("/user/:id", function (&$req, &$res) {
    $res->status(200)->json(array("Hello", "World"));
  });
  $app->get("/*", "notfound");
  function notfound (&$req, &$res) { echo "Page not found || 404"; }
```

# Middleware

ExpressPHP also supports middleware, which are functions that can be executed before or after the route handler. Middleware can be used for authentication, logging, and more.

```php

  $app->use(function(&$req, &$res, &$next){ echo "<script>console.log('middleware is running')</script>"; $next = true; });

  $app->get("/", function (&$req, &$res) {
    $res->send("build something awesome");
  });

  $app->get("/*", "notfound");

```


# Debug

Debugging build in methods are available in ExpressPHP to help you troubleshoot your application.

```php

  $app->logInstance();
  $app->htmlLogApis();

```

# Router
ExpressPHP includes a router for grouping routes and handling requests in a modular way. This allows for greater flexibility in managing different types of APIs, such as public APIs, JSON APIs, and XML APIs.

```php

  $router = new Express\Router($app->getApplicationOptions()); // so router will have same configuration with primary app. But ofc this can be changed.

  $router->get("/index.php", function (&$req, &$res) {
    $res->send("build something awesome");
  });

  $router->run() // router also support render stuff, but it is not recomended.

  $app->useRouter($router);

```


# Links
  > Official website: [https://docs.perfect-evolution.com](https://docs.perfect-evolution.com)\
  > Documentation: [https://docs.perfect-evolution.com/express-php](https://docs.perfect-evolution.com/express-php)

# Author
  >ExpressPHP was developed by Mike Karypidis, a full-stack developer with a passion for building scalable and maintainable web applications. If you have any questions or feedback, feel free to reach out to me at mike@example.com.

# Conclusion
ExpressPHP is a powerful and flexible PHP web framework that provides developers with the tools they need to build modern, scalable, and maintainable web applications. With its modular architecture, flexible routing, and support for middleware, ExpressPHP is a great choice for any PHP project, big or small.
