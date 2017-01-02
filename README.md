# Motivation
The [CircleOfNice DoctrineRestDriver](https://github.com/CircleOfNice/DoctrineRestDriver) was a great starting point for a solution I required, however I found a couple of bugs so natually went to try and fix them but instead I found it difficult to follow and slightly confusing so ended up ripping it apart to A) fix the bugs, and B) learn more about the Doctrine DBAL

# Prerequisites

- This repo, ideally via [composer](https://getcomposer.org/)
- PHP 5.5 or later

# Installation

Add the driver to your project using composer:

```php
composer require lexiphanic/doctrine-rest-driver
```

Change the following doctrine dbal configuration entries:

```yml
doctrine:
    dbal:
        connections:
            foo:
                driver_class: "Lexiphanic\\DoctrineRestDriver\\Driver"
                options:
                    client: http.rest.location # Service for sending request, EG Guzzle
                    transformer: http.rest.mysql.transformer # Service for transforming a query to Request
```

The CircleOfNice has lots of options that allow you to set various CURL options however they are not the concern of this project. This is the reason there are no futher options. So if you want to alter the requests, responses or transformers, then you can use middlewares in Guzzle for example or create your own transformer.

# Usage

If your API routes follow these few conventions, using the driver is very easy:

- Each route must be structured the same: ```{guzzle base_uri}/{tableName}```
- If there is an `id` column, this will be appended ```{guzzle base_uri}/{tableName}/{id}```
- If the response is an array which only has an array eg ```['children'=>[children...], 'parents'=>[parents...]]``` then it is treated as a result with many rows, row 1 is ```children...```, row 2 is ```parents...``` and so on, otherwise it is treated as a single resultset eg ```['children'=>[...],'parents'=>[...]],'name'=>'...'``` (this is because name is not an array) will return one row that has ```name```, ```children``` and ```parents``` as properties
- Select requests will look like this;
  - ```GET {guzzle base_uri}/{tableName}?foo=bar&bar(a,b)=foo``` where the SQL was ```SELECT ... FROM {tableName} WHERE foo = 'bar' AND bar(a,b) = 'foo'```
  - ```GET {guzzle base_uri}/{tableName}/{id}?foo=bar``` where the SQL was ```SELECT ... FROM {tableName} WHERE foo = 'bar' AND id = {id}```; 
- Delete requests follow the same convention as Select requests, but with the ```Delete``` verb ofcourse
- Insert requests will look like this;
  - ```POST {guzzle base_uri}/{tableName}``` the body of the request will be json encoded ```key => value``` pairs (see the Select request for examples on how they're created)
- Update requests are like Select and and Insert requests, like this;
  - ```PUT {guzzle base_uri}/{tableName}/{id}?foo=bar``` uri and querystrings follow the same convention as Select requests and the body will be the same as Insert requests
- All requests will use have the following appended if they exist on the SQL
  - ```LIMIT foo, bar``` will append ```?_offset=foo&limit=bar``` (```foo``` and ```bar``` will be typecasted to ```int```)
  - ```ORDER BY foo, bar``` will append ```?_order=foo,bar```

If this is not the case, you can ofcourse use an alternative transformer, extend this transformer or add Guzzle middleware.